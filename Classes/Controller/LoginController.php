<?php

namespace Sandstorm\NeosTwoFactorAuthentication\Controller;

/*
 * This file is part of the Sandstorm.NeosTwoFactorAuthentication package.
 */

use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Neos\Error\Messages\Message;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Configuration\ConfigurationManager;
use Neos\Flow\Mvc\Controller\ActionController;
use Neos\Flow\Mvc\FlashMessage\FlashMessageService;
use Neos\Flow\Security\Account;
use Neos\Flow\Security\Context as SecurityContext;
use Neos\Flow\Session\SessionManagerInterface;
use Neos\Fusion\View\FusionView;
use Neos\Neos\Domain\Repository\DomainRepository;
use Neos\Neos\Domain\Repository\SiteRepository;
use Sandstorm\NeosTwoFactorAuthentication\Domain\Model\SecondFactor;
use Sandstorm\NeosTwoFactorAuthentication\Domain\Repository\SecondFactorRepository;
use Sandstorm\NeosTwoFactorAuthentication\Http\Middleware\SecondFactorMiddleware;
use Sandstorm\NeosTwoFactorAuthentication\Service\TOTPService;

class LoginController extends ActionController
{
    /**
     * @var string
     */
    protected $defaultViewObjectName = FusionView::class;

    /**
     * @var SecurityContext
     * @Flow\Inject
     */
    protected $securityContext;

    /**
     * @var DomainRepository
     * @Flow\Inject
     */
    protected $domainRepository;

    /**
     * @Flow\Inject
     * @var SiteRepository
     */
    protected $siteRepository;

    /**
     * @Flow\Inject
     * @var FlashMessageService
     */
    protected $flashMessageService;

    /**
     * @var SecondFactorRepository
     * @Flow\Inject
     */
    protected $secondFactorRepository;

    /**
     * @Flow\Inject(lazy=false)
     * @var SessionManagerInterface
     */
    protected $sessionManager;

    /**
     * This action decides which tokens are already authenticated
     * and decides which is next to authenticate
     *
     * ATTENTION: this code is copied from the Neos.Neos:LoginController
     */
    public function askForSecondFactorAction(?string $username = null)
    {
        $currentDomain = $this->domainRepository->findOneByActiveRequest();
        $currentSite = $currentDomain !== null ? $currentDomain->getSite() : $this->siteRepository->findDefault();

        $this->view->assignMultiple([
            'styles' => array_filter($this->getNeosSettings()['userInterface']['backendLoginForm']['stylesheets']),
            'username' => $username,
            'site' => $currentSite,
            'flashMessages' => $this->flashMessageService->getFlashMessageContainerForRequest($this->request)->getMessagesAndFlush(),
        ]);

        // TODO: should we safe redirect to original request?
    }

    public function checkOtpAction(string $otp)
    {
        $account = $this->securityContext->getAccount();

        $isValidOtp = $this->enteredTokenMatchesAnySecondFactor($otp, $account);

        // WHY: We need to check the OTP here and set the authentication status on the Session Object of 2FA package
        // see Sandstorm/NeosTwoFactor
        if ($isValidOtp) {
            $this->sessionManager->getCurrentSession()->putData(
                SecondFactorMiddleware::SESSION_OBJECT_ID,
                [SecondFactorMiddleware::SESSION_OBJECT_AUTH_STATUS => SecondFactorMiddleware::SECOND_FACTOR_AUTHENTICATED]
            );
        } else {
            // FIXME: not visible in View!
            $this->addFlashMessage('Invalid otp!', 'Error', Message::SEVERITY_ERROR);
        }

        // TODO: should we safe redirect to original request?
        $this->redirect('index', 'Backend\Backend', 'Neos.Neos');
    }

    /**
     * Check if the given token matches any registered second factor
     *
     * @param string $enteredSecondFactor
     * @param Account $account
     * @return bool
     */
    private function enteredTokenMatchesAnySecondFactor(string $enteredSecondFactor, Account $account): bool
    {
        /** @var SecondFactor[] $secondFactors */
        $secondFactors = $this->secondFactorRepository->findByAccount($account);
        foreach ($secondFactors as $secondFactor) {
            $isValid = TOTPService::checkIfOtpIsValid($secondFactor->getSecret(), $enteredSecondFactor);
            if ($isValid) {
                return true;
            }
        }

        return false;
    }

    /**
     * This action decides which tokens are already authenticated
     * and decides which is next to authenticate
     *
     * ATTENTION: this code is copied from the Neos.Neos:LoginController
     */
    public function setupSecondFactorAction(?string $username = null)
    {
        $otp = TOTPService::generateNewTotp();
        $secret = $otp->getSecret();

        $currentDomain = $this->domainRepository->findOneByActiveRequest();
        $currentSite = $currentDomain !== null ? $currentDomain->getSite() : $this->siteRepository->findDefault();
        $currentSiteName = $currentSite->getName();
        $urlEncodedSiteName = urlencode($currentSiteName);

        $userIdentifier = $this->securityContext->getAccount()->getAccountIdentifier();

        $oauthData = "otpauth://totp/$userIdentifier?secret=$secret&period=30&issuer=$urlEncodedSiteName";
        $qrCode = (new QRCode(new QROptions([
            'outputType' => QRCode::OUTPUT_MARKUP_SVG
        ])))->render($oauthData);

        $this->view->assignMultiple([
            'styles' => array_filter($this->getNeosSettings()['userInterface']['backendLoginForm']['stylesheets']),
            'username' => $username,
            'site' => $currentSite,
            'secret' => $secret,
            'qrCode' => $qrCode,
            'flashMessages' => $this->flashMessageService->getFlashMessageContainerForRequest($this->request)->getMessagesAndFlush(),
        ]);
    }

    /**
     * TODO: extract this to separate function, currently duplicated from BackendController
     *
     * @param string $secret
     * @param string $secondFactorFromApp
     * @return void
     */
    public function createSecondFactorAction(string $secret, string $secondFactorFromApp)
    {
        $isValid = TOTPService::checkIfOtpIsValid($secret, $secondFactorFromApp);

        if (!$isValid) {
            $this->addFlashMessage('Submitted OTP was not correct', '', Message::SEVERITY_WARNING);
            $this->redirect('setupSecondFactor');
        }

        $account = $this->securityContext->getAccount();

        $secondFactor = new SecondFactor();
        $secondFactor->setAccount($account);
        $secondFactor->setSecret($secret);
        $secondFactor->setType(SecondFactor::TYPE_TOTP);
        $this->secondFactorRepository->add($secondFactor);
        $this->persistenceManager->persistAll();

        $this->addFlashMessage('Successfully created otp');

        // TODO: Discuss: we could skip this to force the user to enter a otp again directly after setup
        $this->sessionManager->getCurrentSession()->putData(
            SecondFactorMiddleware::SESSION_OBJECT_ID,
            [SecondFactorMiddleware::SESSION_OBJECT_AUTH_STATUS => SecondFactorMiddleware::SECOND_FACTOR_AUTHENTICATED]
        );

        // TODO: should we safe redirect to original request?
        $this->redirect('index', 'Backend\Backend', 'Neos.Neos');
    }

    /**
     * @return array
     */
    protected function getNeosSettings(): array
    {
        $configurationManager = $this->objectManager->get(ConfigurationManager::class);
        return $configurationManager->getConfiguration(
            ConfigurationManager::CONFIGURATION_TYPE_SETTINGS,
            'Neos.Neos'
        );
    }
}
