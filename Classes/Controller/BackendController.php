<?php

namespace Sandstorm\NeosTwoFactorAuthentication\Controller;

use BaconQrCode\Renderer\Image\ImagickImageBackEnd;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Security\Context;
use Neos\Neos\Controller\Module\AbstractModuleController;
use Neos\Fusion\View\FusionView;
use Sandstorm\NeosTwoFactorAuthentication\Domain\Repository\SecondFactorRepository;
use Sandstorm\NeosTwoFactorAuthentication\Service\TOTPService;
use Sandstorm\NeosTwoFactorAuthentication\Utility\Base32;
use Sandstorm\NeosTwoFactorAuthentication\Utility\SecurityUtility;

/**
 * @Flow\Scope("singleton")
 */
class BackendController extends AbstractModuleController
{
    /**
     * @var SecondFactorRepository
     * @Flow\Inject
     */
    protected $secondFactorRepository;

    /**
     * @var Context
     * @Flow\Inject
     */
    protected $securityContext;

    protected $defaultViewObjectName = FusionView::class;

    /**
     * used to list all second factors of the current user
     */
    public function indexAction()
    {
        $account = $this->securityContext->getAccount();
        $factors = $this->secondFactorRepository->findByAccount($account);

        $this->view->assign('factors', $factors);
    }

    /**
     * show the form to register a new second factor
     */
    public function newAction()
    {
        // according to https://www.php.net/manual/en/function.random-bytes.php this method
        // generates cryptographically secure pseudo-random bytes
        $key = random_bytes(128);
        $secret = Base32::encode($key);

        // TODO: ...&issuer=$issuer
        // TODO: name of the site, currently just "neos"
        $oauthData = "otpauth://totp/neos?secret=$secret";
        $qrCode = (new QRCode(new QROptions([
            'outputType' => QRCode::OUTPUT_MARKUP_SVG
        ])))->render($oauthData);

        $this->view->assign('secret', $secret);
        $this->view->assign('qrCode', $qrCode);
    }

    /**
     * save the registered second factor
     */
    public function createAction(string $secret, string $secondFactorFromApp)
    {
        $isValid = TOTPService::totpIsValid($secret, $secondFactorFromApp);
        \Neos\Flow\var_dump($isValid);
        \Neos\Flow\var_dump($secret);
        \Neos\Flow\var_dump($secondFactorFromApp);
        die();
    }
}
