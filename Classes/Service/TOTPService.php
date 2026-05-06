<?php

namespace Sandstorm\NeosTwoFactorAuthentication\Service;

use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Security\Account;
use Neos\Neos\Domain\Repository\DomainRepository;
use Neos\Neos\Domain\Repository\SiteRepository;
use OTPHP\TOTP;

/**
 * @Flow\Scope("singleton")
 */
class TOTPService
{
    /**
     * @Flow\Inject
     */
    protected DomainRepository $domainRepository;

    /**
     * @Flow\Inject
     */
    protected SiteRepository $siteRepository;

    /**
     * @Flow\InjectConfiguration(path="issuerName")
     */
    protected ?string $issuerName;

    public static function generateNewTotp(): TOTP
    {
        return TOTP::create();
    }

    public static function checkIfOtpIsValid(string $secret, string $submittedOtp): bool
    {
        $otp = TOTP::create($secret);
        return $otp->verify($submittedOtp);
    }

    public function generateQRCodeForTokenAndAccount(TOTP $otp, Account $account): string
    {
        $secret = $otp->getSecret();
        $currentDomain = $this->domainRepository->findOneByActiveRequest();
        $currentSite = $currentDomain !== null ? $currentDomain->getSite() : $this->siteRepository->findDefault();
        $currentSiteName = $currentSite->getName();
        $urlEncodedSiteName = urlencode($currentSiteName);
        $userIdentifier = $account->getAccountIdentifier();
        // If the issuerName is set in the configuration, use that. Else fall back to the default.
        $issuer = !empty($this->issuerName) ? urlencode($this->issuerName) : $urlEncodedSiteName;
        $oauthData = "otpauth://totp/$userIdentifier?secret=$secret&period=30&issuer=$issuer";
        $qrCode = (new QRCode(new QROptions([
            'outputType' => QRCode::OUTPUT_MARKUP_SVG
        ])))->render($oauthData);

        return $qrCode;
    }
}
