<?php

namespace Sandstorm\NeosTwoFactorAuthentication\Controller;

use OTPHP\TOTP;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Security\Context;
use Neos\Neos\Controller\Module\AbstractModuleController;
use Neos\Fusion\View\FusionView;
use Sandstorm\NeosTwoFactorAuthentication\Domain\Model\SecondFactor;
use Sandstorm\NeosTwoFactorAuthentication\Domain\Repository\SecondFactorRepository;
use Sandstorm\NeosTwoFactorAuthentication\Service\TOTPService;

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
        $otp = TOTPService::generateNewTotp();

        $secret = $otp->getSecret();
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
        $isValid = TOTPService::checkIfOtpIsValid($secret, $secondFactorFromApp);

        if (!$isValid) {
            $this->addFlashMessage('Submitted OTP was not correct');
            $this->redirect('new');
        }

        $secondFactor = new SecondFactor();
        $secondFactor->setAccount($this->securityContext->getAccount());
        $secondFactor->setSecret($secret);
        $secondFactor->setType(SecondFactor::TYPE_TOTP);
        $this->secondFactorRepository->add($secondFactor);
        $this->persistenceManager->persistAll();
        $this->redirect('index');
    }

    /**
     * @param SecondFactor $secondFactor
     * @return void
     */
    public function deleteAction(SecondFactor $secondFactor)
    {
        $this->secondFactorRepository->remove($secondFactor);
        $this->persistenceManager->persistAll();
        $this->redirect('index');
    }
}
