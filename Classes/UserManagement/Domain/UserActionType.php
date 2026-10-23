<?php

declare(strict_types=1);

namespace Sandstorm\NeosTwoFactorAuthentication\UserManagement\Domain;

/**
 * What a mailed link allows its holder to do. Kept as a single value per token; a Keycloak-like list of
 * "required actions" (set password + set up 2FA in one wizard) can extend this later.
 */
enum UserActionType: string
{
    case RESET_PASSWORD = 'resetPassword';
    case INITIAL_PASSWORD = 'initialPassword';
    case VERIFY_EMAIL = 'verifyEmail';

    /**
     * @return list<self>
     */
    public static function passwordActions(): array
    {
        return [self::RESET_PASSWORD, self::INITIAL_PASSWORD];
    }

    public function isPasswordAction(): bool
    {
        return in_array($this, self::passwordActions(), true);
    }
}
