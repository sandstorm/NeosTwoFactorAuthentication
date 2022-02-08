<?php

namespace Sandstorm\NeosTwoFactorAuthentication\Service;

use OTPHP\TOTP;

class TOTPService
{
    public static function generateNewTotp(): TOTP
    {
        return TOTP::create();
    }

    public static function checkIfOtpIsValid(string $secret, string $submittedOtp): bool
    {
        $otp = TOTP::create($secret);
        return $otp->verify($submittedOtp);
    }
}
