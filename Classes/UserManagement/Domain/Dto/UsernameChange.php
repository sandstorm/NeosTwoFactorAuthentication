<?php

declare(strict_types=1);

namespace Sandstorm\NeosTwoFactorAuthentication\UserManagement\Domain\Dto;

/**
 * An account that follows its user's newly validated email (`usernameIsEmail`). Not $changed if the new username
 * is taken by another account.
 */
final class UsernameChange
{
    public function __construct(
        public readonly string $oldUsername,
        public readonly string $newUsername,
        public readonly bool $changed,
    ) {
    }

    /**
     * @param list<self> $usernameChanges
     */
    public static function firstNewUsername(array $usernameChanges): ?string
    {
        foreach ($usernameChanges as $usernameChange) {
            if ($usernameChange->changed) {
                return $usernameChange->newUsername;
            }
        }
        return null;
    }
}
