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
use Neos\Flow\Security\Authentication\AuthenticationManagerInterface;
use Neos\Flow\Security\Context as SecurityContext;
use Neos\Fusion\View\FusionView;
use Neos\Neos\Domain\Repository\DomainRepository;
use Neos\Neos\Domain\Repository\SiteRepository;
use Sandstorm\NeosTwoFactorAuthentication\Domain\Model\SecondFactor;
use Sandstorm\NeosTwoFactorAuthentication\Domain\Repository\SecondFactorRepository;
use Sandstorm\NeosTwoFactorAuthentication\Security\Authentication\Token\UsernameAndPasswordWithSecondFactor;
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
     * @var AuthenticationManagerInterface
     * @Flow\Inject
     */
    protected $authenticationManager;

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
     * This action decides which tokens are already authenticated
     * and decides which is next to authenticate
     *
     * ATTENTION: this code is copied from the Neos.Neos:LoginController
     */
    public function askForSecondFactorAction(?string $username = null, bool $unauthorized = false)
    {
        $currentDomain = $this->domainRepository->findOneByActiveRequest();
        $currentSite = $currentDomain !== null ? $currentDomain->getSite() : $this->siteRepository->findDefault();

        $this->view->assignMultiple([
            'styles' => array_filter($this->getNeosSettings()['userInterface']['backendLoginForm']['stylesheets']),
            'username' => $username,
            'site' => $currentSite,
            'flashMessages' => $this->flashMessageService->getFlashMessageContainerForRequest($this->request)->getMessagesAndFlush(),
        ]);
    }

    /**
     * This action decides which tokens are already authenticated
     * and decides which is next to authenticate
     *
     * ATTENTION: this code is copied from the Neos.Neos:LoginController
     */
    public function setupSecondFactorAction(?string $username = null, bool $unauthorized = false)
    {
        $otp = TOTPService::generateNewTotp();
        $secret = $otp->getSecret();

        $currentDomain = $this->domainRepository->findOneByActiveRequest();
        $currentSite = $currentDomain !== null ? $currentDomain->getSite() : $this->siteRepository->findDefault();
        $currentSiteName = $currentSite->getName();
        $urlEncodedSiteName = urlencode($currentSiteName);

        $secondFactorAuthenticationTokens = $this->securityContext->getAuthenticationTokensOfType(UsernameAndPasswordWithSecondFactor::class);
        // TODO: error if empty
        // TODO: check token status (is authentication successful)

        $userIdentifier = $secondFactorAuthenticationTokens[0]->getAccount()->getAccountIdentifier();

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
        // TODO: validate Token
        $isValid = TOTPService::checkIfOtpIsValid($secret, $secondFactorFromApp);

        if (!$isValid) {
            $this->addFlashMessage('Submitted OTP was not correct', '', Message::SEVERITY_WARNING);
            $this->redirect('setupSecondFactor');
        }

        $secondFactorAuthenticationTokens = $this->securityContext->getAuthenticationTokensOfType(UsernameAndPasswordWithSecondFactor::class);
        // TODO: error if empty
        // TODO: check token status (is authentication successful)

        $account = $secondFactorAuthenticationTokens[0]->getAccount();

        $secondFactor = new SecondFactor();
        $secondFactor->setAccount($account);
        $secondFactor->setSecret($secret);
        $secondFactor->setType(SecondFactor::TYPE_TOTP);
        $this->secondFactorRepository->add($secondFactor);
        $this->persistenceManager->persistAll();

        $this->addFlashMessage('Successfully created otp');
        // TODO: login because 2fa is set up with valid otp or force re-login with new otp
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
