<?php

declare(strict_types=1);

namespace Sandstorm\NeosTwoFactorAuthentication\UserManagement\Domain\Dto;

/**
 * Read model of one Flow account of a {@see SimpleUser}.
 */
final class SimpleAccount
{
    /**
     * @param list<string> $roleIdentifiers
     */
    public function __construct(
        public readonly string $accountId,
        public readonly string $accountIdentifier,
        public readonly string $authenticationProviderName,
        public readonly array $roleIdentifiers,
        public readonly bool $active,
        public readonly ?\DateTimeInterface $expirationDate,
        public readonly ?\DateTimeInterface $lastSuccessfulAuthentication,
        public readonly int $secondFactorCount,
    ) {
    }
}
