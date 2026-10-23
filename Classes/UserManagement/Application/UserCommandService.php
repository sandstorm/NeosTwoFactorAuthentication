<?php

declare(strict_types=1);

namespace Sandstorm\NeosTwoFactorAuthentication\UserManagement\Application;

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Security\Account;
use Neos\Flow\Security\AccountRepository;
use Neos\Neos\Domain\Model\User;
use Neos\Neos\Domain\Service\UserService;
use Neos\Party\Domain\Model\ElectronicAddress;
use Neos\Party\Domain\Model\PersonName;
use Sandstorm\NeosTwoFactorAuthentication\Domain\Repository\SecondFactorRepository;
use Sandstorm\NeosTwoFactorAuthentication\UserManagement\Domain\UserActionType;

/**
 * Write side of the user management, wrapping Neos' UserService and the Party model. See {@see SimpleUserFinder}.
 *
 * Domain rule violations throw {@see UserManagementException} with a translation id as message.
 */
#[Flow\Scope('singleton')]
class UserCommandService
{
    #[Flow\Inject]
    protected UserService $userService;

    #[Flow\Inject]
    protected AccountRepository $accountRepository;

    #[Flow\Inject]
    protected SimpleUserFinder $simpleUserFinder;

    #[Flow\Inject]
    protected UserActionTokenService $tokenService;

    #[Flow\Inject]
    protected UserMailer $userMailer;

    #[Flow\Inject]
    protected SecondFactorRepository $secondFactorRepository;

    #[Flow\InjectConfiguration(path: 'userManagement.usernameIsEmail')]
    protected bool $usernameIsEmail = false;

    /**
     * Creates a user with an unapproved primary email. Without $password, the account gets a random password
     * nobody knows (Neos needs credentials) - send the initial password mail afterwards.
     *
     * @param list<string> $roleIdentifiers
     * @throws UserManagementException
     */
    public function createUser(
        string $firstName,
        string $lastName,
        string $email,
        string $username,
        array $roleIdentifiers,
        string $authenticationProviderName,
        ?string $password
    ): User {
        $email = trim($email);
        $username = $this->usernameIsEmail ? $email : trim($username);

        if ($username === '') {
            throw new UserManagementException('error.usernameRequired', 1760000101);
        }
        $this->assertValidEmail($email);
        if (!$this->simpleUserFinder->isManagedProvider($authenticationProviderName)) {
            throw new UserManagementException('error.invalidProvider', 1760000102);
        }
        if ($this->accountRepository->findByAccountIdentifierAndAuthenticationProviderName($username, $authenticationProviderName) !== null) {
            throw new UserManagementException('error.usernameTaken', 1760000103);
        }
        if ($this->simpleUserFinder->isPrimaryEmailTakenByOtherUser($email)) {
            throw new UserManagementException('error.emailTaken', 1760000104);
        }

        $user = new User();
        $user->setName(new PersonName('', $firstName, '', $lastName, '', $username));
        $electronicAddress = $this->createEmailAddress($email);
        $user->addElectronicAddress($electronicAddress);
        $user->setPrimaryElectronicAddress($electronicAddress);

        $this->userService->addUser(
            $username,
            $password ?? bin2hex(random_bytes(64)),
            $user,
            $roleIdentifiers,
            $authenticationProviderName
        );
        return $user;
    }

    /**
     * @return bool whether the email changed (it is then no longer approved)
     * @throws UserManagementException
     */
    public function updateUser(User $user, string $firstName, string $lastName, string $email): bool
    {
        $email = trim($email);
        $this->assertValidEmail($email);

        $name = $user->getName();
        $name->setFirstName($firstName);
        $name->setLastName($lastName);

        $primaryEmail = $this->simpleUserFinder->getPrimaryEmail($user);
        $emailChanged = $primaryEmail === null
            || SimpleUserFinder::normalizeEmail($primaryEmail->getIdentifier()) !== SimpleUserFinder::normalizeEmail($email);

        if ($emailChanged) {
            if ($this->simpleUserFinder->isPrimaryEmailTakenByOtherUser($email, $user)) {
                throw new UserManagementException('error.emailTaken', 1760000104);
            }
            $electronicAddress = $this->createEmailAddress($email);
            $user->addElectronicAddress($electronicAddress);
            $user->setPrimaryElectronicAddress($electronicAddress);
            if ($primaryEmail !== null) {
                $user->removeElectronicAddress($primaryEmail);
            }
        }
        $this->userService->updateUser($user);
        return $emailChanged;
    }

    /**
     * @param list<string> $roleIdentifiers
     */
    public function setRolesForAccount(Account $account, array $roleIdentifiers): void
    {
        $this->userService->setRolesForAccount($account, $roleIdentifiers);
    }

    public function setActive(User $user, bool $active): void
    {
        if ($active) {
            $this->userService->activateUser($user);
        } else {
            $this->userService->deactivateUser($user);
        }
    }

    public function setPassword(User $user, string $password): void
    {
        $this->userService->setUserPassword($user, $password);
        foreach ($this->simpleUserFinder->getManagedAccounts($user) as $account) {
            $this->tokenService->invalidatePasswordTokens($account);
        }
    }

    /**
     * Sets the password via a mailed token: consumes the token, invalidates all other password links of the
     * account, approves the email (the link proves inbox access) and destroys the user's sessions.
     */
    public function setPasswordWithToken(Account $account, string $password): void
    {
        $user = $this->requireUserOfAccount($account);
        $this->setPassword($user, $password);
        $this->markEmailValidated($user);
    }

    public function markEmailValidated(User $user): void
    {
        $primaryEmail = $this->simpleUserFinder->getPrimaryEmail($user);
        if ($primaryEmail !== null && !$primaryEmail->isApproved()) {
            $primaryEmail->setApproved(true);
            // ElectronicAddress has no repository, it is persisted via cascade from the user
            $this->userService->updateUser($user);
        }
    }

    public function deleteUser(User $user): void
    {
        // second factors and user action tokens are removed via ON DELETE CASCADE
        $this->userService->deleteUser($user);
    }

    public function resetSecondFactors(User $user): int
    {
        $count = 0;
        foreach ($this->simpleUserFinder->getManagedAccounts($user) as $account) {
            foreach ($this->secondFactorRepository->findByAccount($account) as $secondFactor) {
                $this->secondFactorRepository->remove($secondFactor);
                $count++;
            }
        }
        return $count;
    }

    /**
     * Sends one mail to the user's primary email with a set-password link per managed account.
     *
     * Ignores `requireValidatedEmail`: for INITIAL_PASSWORD it would be a chicken-and-egg problem, and an admin
     * explicitly triggering a reset knows whom they are mailing.
     *
     * @throws UserManagementException
     */
    public function sendPasswordMail(User $user, UserActionType $action): void
    {
        $primaryEmail = $this->simpleUserFinder->getPrimaryEmail($user);
        if ($primaryEmail === null) {
            throw new UserManagementException('error.noEmail', 1760000105);
        }
        $accounts = array_filter($this->simpleUserFinder->getManagedAccounts($user), fn (Account $account) => $account->isActive());
        if ($accounts === []) {
            throw new UserManagementException('error.noActiveAccount', 1760000106);
        }
        $links = [];
        foreach ($accounts as $account) {
            $links[] = [
                'username' => $account->getAccountIdentifier(),
                'token' => $this->tokenService->issue($account, $action, $primaryEmail->getIdentifier()),
            ];
        }
        $this->userMailer->sendPasswordLinks(
            $primaryEmail->getIdentifier(),
            $user,
            $action,
            $links,
            (new \DateTimeImmutable())->add($this->tokenService->getLifetime($action))
        );
    }

    /**
     * @throws UserManagementException
     */
    public function sendEmailVerificationMail(User $user): void
    {
        $primaryEmail = $this->simpleUserFinder->getPrimaryEmail($user);
        if ($primaryEmail === null) {
            throw new UserManagementException('error.noEmail', 1760000105);
        }
        $accounts = $this->simpleUserFinder->getManagedAccounts($user);
        if ($accounts === []) {
            throw new UserManagementException('error.noActiveAccount', 1760000106);
        }
        // the token needs an account to hang on to; any account of the user proves the same inbox
        $token = $this->tokenService->issue($accounts[0], UserActionType::VERIFY_EMAIL, $primaryEmail->getIdentifier());
        $this->userMailer->sendEmailVerification(
            $primaryEmail->getIdentifier(),
            $user,
            $token,
            (new \DateTimeImmutable())->add($this->tokenService->getLifetime(UserActionType::VERIFY_EMAIL))
        );
    }

    public function requireUserOfAccount(Account $account): User
    {
        $user = $this->simpleUserFinder->findUserOfAccount($account);
        if ($user === null) {
            throw new \RuntimeException('Account ' . $account->getAccountIdentifier() . ' has no Neos user.', 1760000107);
        }
        return $user;
    }

    /**
     * @throws UserManagementException
     */
    private function assertValidEmail(string $email): void
    {
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new UserManagementException('error.invalidEmail', 1760000108);
        }
    }

    private function createEmailAddress(string $email): ElectronicAddress
    {
        $electronicAddress = new ElectronicAddress();
        $electronicAddress->setType(SimpleUserFinder::EMAIL_ADDRESS_TYPE);
        $electronicAddress->setIdentifier($email);
        $electronicAddress->setApproved(false);
        return $electronicAddress;
    }
}
