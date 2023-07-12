<?php

namespace Sandstorm\NeosTwoFactorAuthentication\Service;

use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Neos\Flow\Security\Account;
use Neos\Flow\Annotations as Flow;
use Neos\Neos\Domain\Repository\DomainRepository;
use Neos\Neos\Domain\Repository\SiteRepository;
use OTPHP\TOTP;

class TOTPService
{
    /**
     * @Flow\Inject
     * @var DomainRepository
     */
    protected $domainRepository;

    /**
     * @Flow\Inject
     * @var SiteRepository
     */
    protected $siteRepository;

    /**
     * @Flow\InjectConfiguration(path="issuerName")
     * @var string
     */
    protected $issuerName;

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
        $issuer = $this->issuerName != '' ? urlencode($this->issuerName) : $urlEncodedSiteName;
        $oauthData = "otpauth://totp/$userIdentifier?secret=$secret&period=30&issuer=$issuer";
        $qrCode = (new QRCode(new QROptions([
            'outputType' => QRCode::OUTPUT_MARKUP_SVG
        ])))->render($oauthData);

        return $qrCode;
    }
}
