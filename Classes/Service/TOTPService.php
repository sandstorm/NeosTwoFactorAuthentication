<?php

namespace Sandstorm\NeosTwoFactorAuthentication\Service;

use Sandstorm\NeosTwoFactorAuthentication\Utility\Base32;

class TOTPService
{
    /**
     * Input current OTP and the secret to validate the TOTP is correct
     * @return bool
     */
    public static function totpIsValid(string $secret, string $totp): bool
    {
        $currentTOTP = self::generateOtp($secret);
        \Neos\Flow\var_dump($currentTOTP);
        die();
        return hash_equals($currentTOTP, $totp);
    }

    /**
     * Inspired by https://github.com/Spomky-Labs/otphp/blob/d7490027037dac4259b1589ba79c2ecccf4157ca/src/OTP.php#L43
     *
     * @param string $secret
     * @return string
     */
    protected static function generateOtp(string $secret): string
    {
        $timestamp = time();
        $hash = hash_hmac('sha1', self::getTimecode($timestamp), Base32::decode($secret), true);

        $hmac = array_values(unpack('C*', $hash));

        $offset = ($hmac[\count($hmac) - 1] & 0xF);
        $code = ($hmac[$offset + 0] & 0x7F) << 24 | ($hmac[$offset + 1] & 0xFF) << 16 | ($hmac[$offset + 2] & 0xFF) << 8 | ($hmac[$offset + 3] & 0xFF);
        $otp = $code % (10 ** 6);

        return str_pad((string) $otp, 6, '0', STR_PAD_LEFT);
    }

    protected static function getTimecode(int $timestamp): int
    {
        return (int) floor($timestamp / 30);
    }
}
