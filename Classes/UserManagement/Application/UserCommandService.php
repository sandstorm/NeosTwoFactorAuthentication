<?php

declare(strict_types=1);

namespace Sandstorm\NeosTwoFactorAuthentication\UserManagement\Application;

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Persistence\PersistenceManagerInterface;
use Neos\Flow\Security\Account;
use Neos\Flow\Security\AccountRepository;
use Neos\Flow\Security\Context as SecurityContext;
use Neos\Neos\Domain\Model\User;
use Neos\Neos\Domain\Service\UserService;
use Neos\Party\Domain\Model\ElectronicAddress;
use Neos\Party\Domain\Model\PersonName;
use Psr\Log\LoggerInterface;
use Sandstorm\NeosTwoFactorAuthentication\Domain\Repository\SecondFactorRepository;
use Sandstorm\NeosTwoFactorAuthentication\UserManagement\Domain\Dto\UsernameChange;
use Sandstorm\NeosTwoFactorAuthentication\UserManagement\Domain\UserActionType;

/**
 * Write side of the user management, wrapping Neos' UserService and the Party model. See {@see SimpleUserFinder}.
 *
 * Domain rule violations throw {@see UserManagementException} with a translation id as message.
 */
#[Flow\Scope('singleton')]
class UserCommandService
{
    /**
     * Credentials of an account created without password: no valid bcrypt hash, so password_verify() never matches.
     * Lets the module tell "no password set yet" apart from a real password.
     */
    public const NO_PASSWORD_CREDENTIALS = 'bcrypt=>!no-password-set';

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

    #[Flow\Inject]
    protected SecurityContext $securityContext;

    #[Flow\Inject]
    protected Neos8PersonalWorkspaceMigrator $neos8PersonalWorkspaceMigrator;

    #[Flow\Inject]
    protected PersistenceManagerInterface $persistenceManager;

    #[Flow\Inject(name: 'Neos.Flow:SecurityLogger', lazy: false)]
    protected LoggerInterface $securityLogger;

    #[Flow\InjectConfiguration(path: 'userManagement.usernameIsEmail')]
    protected bool $usernameIsEmail = false;

    /**
     * Creates a user with an unapproved primary email. Without $password, the account gets credentials no password
     * matches ({@see self::NO_PASSWORD_CREDENTIALS}) - send the initial password mail afterwards.
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
        if ($password === null) {
            foreach ($user->getAccounts() as $account) {
                $account->setCredentialsSource(self::NO_PASSWORD_CREDENTIALS);
                $this->accountRepository->update($account);
            }
        }
        return $user;
    }

    /**
     * With `usernameIsEmail`, a previous email that accounts are named after stays on the user (not primary) until
     * the new one is validated - see {@see self::markEmailValidated()}.
     *
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
            if ($primaryEmail !== null && !($this->usernameIsEmail && $this->isUsernameOfAccount($user, $primaryEmail->getIdentifier()))) {
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
     *
     * @return list<UsernameChange>
     */
    public function setPasswordWithToken(Account $account, string $password): array
    {
        $user = $this->requireUserOfAccount($account);
        $this->setPassword($user, $password);
        return $this->markEmailValidated($user);
    }

    /**
     * With `usernameIsEmail`, accounts named after a previous email of the user follow the validated email: they
     * are renamed, logged out and (on Neos 8) their personal workspace is migrated. The user gets a mail.
     *
     * @return list<UsernameChange>
     */
    public function markEmailValidated(User $user): array
    {
        $primaryEmail = $this->simpleUserFinder->getPrimaryEmail($user);
        if ($primaryEmail === null || $primaryEmail->isApproved()) {
            return [];
        }
        $primaryEmail->setApproved(true);

        $email = $primaryEmail->getIdentifier();
        $usernameChanges = [];
        // a copy, addresses are removed in the loop
        foreach ($user->getElectronicAddresses()->toArray() as $previousEmail) {
            if ($previousEmail === $primaryEmail
                || $previousEmail->getType() !== SimpleUserFinder::EMAIL_ADDRESS_TYPE
                || !$this->isUsernameOfAccount($user, $previousEmail->getIdentifier())
            ) {
                continue;
            }
            // kept by updateUser() - removed even if usernameIsEmail was switched off in the meantime
            $user->removeElectronicAddress($previousEmail);
            if (!$this->usernameIsEmail) {
                continue;
            }
            foreach ($this->simpleUserFinder->getManagedAccounts($user) as $account) {
                $username = SimpleUserFinder::normalizeEmail($account->getAccountIdentifier());
                if ($username === SimpleUserFinder::normalizeEmail($previousEmail->getIdentifier()) && $username !== SimpleUserFinder::normalizeEmail($email)) {
                    $usernameChanges[] = $this->renameAccount($user, $account, $email);
                }
            }
        }
        // ElectronicAddress has no repository, it is persisted via cascade from the user
        $this->userService->updateUser($user);

        $this->sendUsernameChangedMail($email, $user, $usernameChanges);
        return $usernameChanges;
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

    private function isUsernameOfAccount(User $user, string $email): bool
    {
        foreach ($this->simpleUserFinder->getManagedAccounts($user) as $account) {
            if (SimpleUserFinder::normalizeEmail($account->getAccountIdentifier()) === SimpleUserFinder::normalizeEmail($email)) {
                return true;
            }
        }
        return false;
    }

    private function renameAccount(User $user, Account $account, string $newUsername): UsernameChange
    {
        $oldUsername = $account->getAccountIdentifier();
        $existingAccount = $this->accountRepository->findByAccountIdentifierAndAuthenticationProviderName($newUsername, $account->getAuthenticationProviderName());
        if (($existingAccount !== null && $existingAccount !== $account) || !$this->neos8PersonalWorkspaceMigrator->canMigrate($oldUsername, $newUsername)) {
            $this->securityLogger->warning(sprintf('Sandstorm/NeosTwoFactorAuthentication: username "%s" not changed to "%s", the name is taken.', $oldUsername, $newUsername));
            return new UsernameChange($oldUsername, $newUsername, false);
        }

        // sessions are tagged with the account identifier - after the rename they could no longer be destroyed
        $this->securityContext->destroySessionsForAccount($account, 'Username changed');
        $this->neos8PersonalWorkspaceMigrator->renameWithWorkspace($oldUsername, $newUsername, function () use ($account, $newUsername) {
            $account->setAccountIdentifier($newUsername);
            $this->accountRepository->update($account);
            // inside the workspace transaction on Neos 8
            $this->persistenceManager->persistAll();
        });
        // createUser() sets the username as alias
        if ($user->getName()->getAlias() === $oldUsername) {
            $user->getName()->setAlias($newUsername);
        }
        return new UsernameChange($oldUsername, $newUsername, true);
    }

    /**
     * @param list<UsernameChange> $usernameChanges
     */
    private function sendUsernameChangedMail(string $recipient, User $user, array $usernameChanges): void
    {
        $changed = array_values(array_filter($usernameChanges, fn (UsernameChange $change) => $change->changed));
        if ($changed === []) {
            return;
        }
        // the username already changed - a failing mail must not undo that
        try {
            $this->userMailer->sendUsernameChanged($recipient, $user, $changed);
        } catch (\Throwable $e) {
            $this->securityLogger->error('Sandstorm/NeosTwoFactorAuthentication: could not send username changed mail: ' . $e->getMessage());
        }
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
