<?php

declare(strict_types=1);

namespace Sandstorm\NeosTwoFactorAuthentication\UserManagement\Domain\Dto;

/**
 * The "simplified user model": hides Neos' User -> Person -> ElectronicAddress / Account-per-provider
 * structure from controllers and Fusion. Built by the SimpleUserFinder, changed via the UserCommandService.
 */
final class SimpleUser
{
    /**
     * @param list<SimpleAccount> $accounts
     */
    public function __construct(
        public readonly string $userId,
        public readonly string $firstName,
        public readonly string $lastName,
        public readonly string $displayName,
        public readonly ?string $primaryEmail,
        public readonly bool $emailApproved,
        public readonly array $accounts,
    ) {
    }

    /**
     * A user is active if at least one of their accounts is active (same as Neos' UserService::isActive()).
     */
    public function isActive(): bool
    {
        foreach ($this->accounts as $account) {
            if ($account->active) {
                return true;
            }
        }
        return false;
    }

    /**
     * @return list<string>
     */
    public function getAccountIdentifiers(): array
    {
        return array_map(fn (SimpleAccount $account) => $account->accountIdentifier, $this->accounts);
    }

    /**
     * @return list<string>
     */
    public function getRoleIdentifiers(): array
    {
        $roles = [];
        foreach ($this->accounts as $account) {
            $roles = [...$roles, ...$account->roleIdentifiers];
        }
        return array_values(array_unique($roles));
    }

    public function getSecondFactorCount(): int
    {
        return array_sum(array_map(fn (SimpleAccount $account) => $account->secondFactorCount, $this->accounts));
    }

    public function getLastSuccessfulAuthentication(): ?\DateTimeInterface
    {
        $last = null;
        foreach ($this->accounts as $account) {
            if ($account->lastSuccessfulAuthentication !== null && ($last === null || $account->lastSuccessfulAuthentication > $last)) {
                $last = $account->lastSuccessfulAuthentication;
            }
        }
        return $last;
    }

    public function matchesSearchTerm(string $searchTerm): bool
    {
        $searchTerm = mb_strtolower(trim($searchTerm));
        if ($searchTerm === '') {
            return true;
        }
        $haystacks = [$this->displayName, $this->primaryEmail ?? '', ...$this->getAccountIdentifiers()];
        foreach ($haystacks as $haystack) {
            if (str_contains(mb_strtolower($haystack), $searchTerm)) {
                return true;
            }
        }
        return false;
    }

    public function hasAccountForProvider(string $authenticationProviderName): bool
    {
        foreach ($this->accounts as $account) {
            if ($account->authenticationProviderName === $authenticationProviderName) {
                return true;
            }
        }
        return false;
    }
}
