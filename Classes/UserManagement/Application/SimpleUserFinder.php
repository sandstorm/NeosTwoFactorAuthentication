<?php

declare(strict_types=1);

namespace Sandstorm\NeosTwoFactorAuthentication\UserManagement\Application;

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Persistence\PersistenceManagerInterface;
use Neos\Flow\Security\Account;
use Neos\Flow\Security\AccountRepository;
use Neos\Neos\Domain\Model\User;
use Neos\Neos\Domain\Repository\UserRepository;
use Neos\Party\Domain\Model\ElectronicAddress;
use Neos\Party\Domain\Service\PartyService;
use Sandstorm\NeosTwoFactorAuthentication\Domain\Repository\SecondFactorRepository;
use Sandstorm\NeosTwoFactorAuthentication\UserManagement\Domain\Dto\SimpleAccount;
use Sandstorm\NeosTwoFactorAuthentication\UserManagement\Domain\Dto\SimpleUser;

/**
 * Read side of the user management. Everything that needs to know how Neos stores users
 * (User -> Person -> ElectronicAddress, one Account per authentication provider) lives here and in the
 * {@see UserCommandService} - so a later switch to a DB view / own table only touches these two classes.
 *
 * Only the PRIMARY electronic address of type Email is considered as "the" email of a user.
 */
#[Flow\Scope('singleton')]
class SimpleUserFinder
{
    /**
     * Neos.Party.availableElectronicAddressTypes key of email addresses
     */
    public const EMAIL_ADDRESS_TYPE = 'Email';

    #[Flow\Inject]
    protected UserRepository $userRepository;

    #[Flow\Inject]
    protected AccountRepository $accountRepository;

    #[Flow\Inject]
    protected PartyService $partyService;

    #[Flow\Inject]
    protected SecondFactorRepository $secondFactorRepository;

    #[Flow\Inject]
    protected PersistenceManagerInterface $persistenceManager;

    /** @var list<string> */
    #[Flow\InjectConfiguration(path: 'userManagement.authenticationProviders')]
    protected array $authenticationProviders = [];

    /**
     * All users having at least one account of a managed authentication provider.
     *
     * Loads all users and filters in PHP - fine for the few hundred users a Neos backend usually has.
     *
     * @return list<SimpleUser>
     */
    public function findAll(string $searchTerm = '', string $authenticationProviderName = ''): array
    {
        $result = [];
        foreach ($this->userRepository->findAll() as $user) {
            if (!$user instanceof User) {
                continue;
            }
            $simpleUser = $this->toSimpleUser($user);
            if ($simpleUser->accounts === []) {
                continue;
            }
            if ($authenticationProviderName !== '' && !$simpleUser->hasAccountForProvider($authenticationProviderName)) {
                continue;
            }
            if (!$simpleUser->matchesSearchTerm($searchTerm)) {
                continue;
            }
            $result[] = $simpleUser;
        }
        usort($result, fn (SimpleUser $a, SimpleUser $b) => strcasecmp($a->displayName, $b->displayName));
        return $result;
    }

    public function findById(string $userId): ?SimpleUser
    {
        $user = $this->findUserEntityById($userId);
        return $user !== null ? $this->toSimpleUser($user) : null;
    }

    public function findUserEntityById(string $userId): ?User
    {
        $user = $this->persistenceManager->getObjectByIdentifier($userId, User::class);
        return $user instanceof User ? $user : null;
    }

    public function findAccountEntityById(string $accountId): ?Account
    {
        $account = $this->persistenceManager->getObjectByIdentifier($accountId, Account::class);
        return $account instanceof Account && $this->isManagedProvider($account->getAuthenticationProviderName()) ? $account : null;
    }

    public function findUserOfAccount(Account $account): ?User
    {
        $party = $this->partyService->getAssignedPartyOfAccount($account);
        return $party instanceof User ? $party : null;
    }

    /**
     * Active accounts of managed providers whose user's primary email matches (case-insensitive, trimmed).
     *
     * @return list<Account>
     */
    public function findActiveAccountsByPrimaryEmail(string $email, bool $onlyApproved): array
    {
        $accounts = [];
        foreach ($this->findUsersByPrimaryEmail($email) as $user) {
            $primaryEmail = $this->getPrimaryEmail($user);
            if ($primaryEmail === null || ($onlyApproved && !$primaryEmail->isApproved())) {
                continue;
            }
            foreach ($this->getManagedAccounts($user) as $account) {
                if ($account->isActive()) {
                    $accounts[] = $account;
                }
            }
        }
        return $accounts;
    }

    /**
     * Active accounts of managed providers with exactly this account identifier (one per provider at most).
     *
     * @return list<Account>
     */
    public function findActiveAccountsByAccountIdentifier(string $accountIdentifier): array
    {
        $accounts = [];
        foreach ($this->authenticationProviders as $authenticationProviderName) {
            $account = $this->accountRepository->findActiveByAccountIdentifierAndAuthenticationProviderName($accountIdentifier, $authenticationProviderName);
            if ($account instanceof Account) {
                $accounts[] = $account;
            }
        }
        return $accounts;
    }

    /**
     * @return list<User>
     */
    public function findUsersByPrimaryEmail(string $email): array
    {
        $email = self::normalizeEmail($email);
        if ($email === '') {
            return [];
        }
        $query = $this->userRepository->createQuery();
        $candidates = $query->matching(
            $query->equals('primaryElectronicAddress.identifier', $email, false)
        )->execute()->toArray();

        return array_values(array_filter(
            $candidates,
            fn ($user) => $user instanceof User && self::normalizeEmail($this->getPrimaryEmail($user)?->getIdentifier() ?? '') === $email
        ));
    }

    public function isPrimaryEmailTakenByOtherUser(string $email, ?User $exceptUser = null): bool
    {
        foreach ($this->findUsersByPrimaryEmail($email) as $user) {
            if ($exceptUser === null || $this->persistenceManager->getIdentifierByObject($user) !== $this->persistenceManager->getIdentifierByObject($exceptUser)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Reports legacy data where several users share the same primary email (our module prevents new duplicates).
     *
     * @return array<string, list<SimpleUser>> normalized email => users
     */
    public function findDuplicatePrimaryEmails(): array
    {
        $byEmail = [];
        foreach ($this->findAll() as $user) {
            if ($user->primaryEmail !== null && self::normalizeEmail($user->primaryEmail) !== '') {
                $byEmail[self::normalizeEmail($user->primaryEmail)][] = $user;
            }
        }
        return array_filter($byEmail, fn (array $users) => count($users) > 1);
    }

    public function getPrimaryEmail(User $user): ?ElectronicAddress
    {
        $primary = $user->getPrimaryElectronicAddress();
        return $primary instanceof ElectronicAddress && $primary->getType() === self::EMAIL_ADDRESS_TYPE ? $primary : null;
    }

    /**
     * @return list<Account>
     */
    public function getManagedAccounts(User $user): array
    {
        $accounts = [];
        foreach ($user->getAccounts() as $account) {
            if ($account instanceof Account && $this->isManagedProvider($account->getAuthenticationProviderName())) {
                $accounts[] = $account;
            }
        }
        return $accounts;
    }

    public function isManagedProvider(string $authenticationProviderName): bool
    {
        return in_array($authenticationProviderName, $this->authenticationProviders, true);
    }

    /**
     * @return list<string>
     */
    public function getManagedProviders(): array
    {
        return $this->authenticationProviders;
    }

    public static function normalizeEmail(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    private function toSimpleUser(User $user): SimpleUser
    {
        $primaryEmail = $this->getPrimaryEmail($user);
        $name = $user->getName();

        return new SimpleUser(
            (string)$this->persistenceManager->getIdentifierByObject($user),
            $name->getFirstName(),
            $name->getLastName(),
            $name->getFullName(),
            $primaryEmail?->getIdentifier(),
            $primaryEmail !== null && $primaryEmail->isApproved(),
            array_map(fn (Account $account) => $this->toSimpleAccount($account), $this->getManagedAccounts($user)),
        );
    }

    private function toSimpleAccount(Account $account): SimpleAccount
    {
        return new SimpleAccount(
            (string)$this->persistenceManager->getIdentifierByObject($account),
            $account->getAccountIdentifier(),
            $account->getAuthenticationProviderName(),
            array_map('strval', array_keys($account->getRoles())),
            $account->isActive(),
            $account->getExpirationDate(),
            $account->getLastSuccessfulAuthenticationDate(),
            $this->secondFactorRepository->countByAccount($account),
        );
    }
}
