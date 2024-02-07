<?php

namespace Sandstorm\NeosTwoFactorAuthentication\Controller;

/*
 * This file is part of the Sandstorm.NeosTwoFactorAuthentication package.
 */

use Neos\Error\Messages\Message;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Configuration\ConfigurationManager;
use Neos\Flow\Configuration\Exception\InvalidConfigurationTypeException;
use Neos\Flow\I18n\Translator;
use Neos\Flow\Mvc\Controller\ActionController;
use Neos\Flow\Mvc\Exception\StopActionException;
use Neos\Flow\Mvc\FlashMessage\FlashMessageService;
use Neos\Flow\Persistence\Exception\IllegalObjectTypeException;
use Neos\Flow\Security\Account;
use Neos\Flow\Security\Context as SecurityContext;
use Neos\Flow\Session\Exception\SessionNotStartedException;
use Neos\Fusion\View\FusionView;
use Neos\Neos\Domain\Repository\DomainRepository;
use Neos\Neos\Domain\Repository\SiteRepository;
use Sandstorm\NeosTwoFactorAuthentication\Domain\AuthenticationStatus;
use Sandstorm\NeosTwoFactorAuthentication\Domain\Model\SecondFactor;
use Sandstorm\NeosTwoFactorAuthentication\Domain\Repository\SecondFactorRepository;
use Sandstorm\NeosTwoFactorAuthentication\Service\SecondFactorSessionStorageService;
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
     * @Flow\Inject
     * @var SecondFactorSessionStorageService
     */
    protected $secondFactorSessionStorageService;

    /**
     * @Flow\Inject
     * @var TOTPService
     */
    protected $tOTPService;

    /**
     * @Flow\Inject
     * @var Translator
     */
    protected $translator;

    /**
     * This action decides which tokens are already authenticated
     * and decides which is next to authenticate
     *
     * ATTENTION: this code is copied from the Neos.Neos:LoginController
     */
    public function askForSecondFactorAction(?string $username = null): void
    {
        $currentDomain = $this->domainRepository->findOneByActiveRequest();
        $currentSite = $currentDomain !== null ? $currentDomain->getSite() : $this->siteRepository->findDefault();

        $this->view->assignMultiple([
            'styles' => array_filter($this->getNeosSettings()['userInterface']['backendLoginForm']['stylesheets']),
            'username' => $username,
            'site' => $currentSite,
            'flashMessages' => $this->flashMessageService
                ->getFlashMessageContainerForRequest($this->request)
                ->getMessagesAndFlush(),
        ]);
    }

    /**
     * @throws StopActionException
     * @throws SessionNotStartedException
     */
    public function checkSecondFactorAction(string $otp): void
    {
        $account = $this->securityContext->getAccount();

        $isValidOtp = $this->enteredTokenMatchesAnySecondFactor($otp, $account);

        if ($isValidOtp) {
            $this->secondFactorSessionStorageService->setAuthenticationStatus(AuthenticationStatus::AUTHENTICATED);
        } else {
            $this->addFlashMessage(
                $this->translator->translateById(
                    'login.flashMessage.invalidOtp',
                    [],
                    null,
                    null,
                    'Main',
                    'Sandstorm.NeosTwoFactorAuthentication'
                ),
                $this->translator->translateById(
                    'login.flashMessage.errorHeader',
                    [],
                    null,
                    null,
                    'Main',
                    'Sandstorm.NeosTwoFactorAuthentication'
                ),
                Message::SEVERITY_ERROR
            );
        }

        $originalRequest = $this->securityContext->getInterceptedRequest();
        if ($originalRequest !== null) {
            $this->redirectToRequest($originalRequest);
        }

        $this->redirect('index', 'Backend\Backend', 'Neos.Neos');
    }

    /**
     * This action decides which tokens are already authenticated
     * and decides which is next to authenticate
     *
     * ATTENTION: this code is copied from the Neos.Neos:LoginController
     */
    public function setupSecondFactorAction(?string $username = null): void
    {
        $otp = TOTPService::generateNewTotp();
        $secret = $otp->getSecret();
        $qrCode = $this->tOTPService->generateQRCodeForTokenAndAccount($otp, $this->securityContext->getAccount());

        $currentDomain = $this->domainRepository->findOneByActiveRequest();
        $currentSite = $currentDomain !== null ? $currentDomain->getSite() : $this->siteRepository->findDefault();

        $this->view->assignMultiple([
            'styles' => array_filter($this->getNeosSettings()['userInterface']['backendLoginForm']['stylesheets']),
            'username' => $username,
            'site' => $currentSite,
            'secret' => $secret,
            'qrCode' => $qrCode,
            'flashMessages' => $this->flashMessageService
                ->getFlashMessageContainerForRequest($this->request)
                ->getMessagesAndFlush(),
        ]);
    }

    /**
     * @param string $secret
     * @param string $secondFactorFromApp
     * @return void
     * @throws IllegalObjectTypeException
     * @throws SessionNotStartedException
     * @throws StopActionException
     */
    public function createSecondFactorAction(string $secret, string $secondFactorFromApp): void
    {
        $isValid = TOTPService::checkIfOtpIsValid($secret, $secondFactorFromApp);

        if (!$isValid) {
            $this->addFlashMessage(
                $this->translator->translateById(
                    'login.flashMessage.submittedOtpIncorrect',
                    [],
                    null,
                    null,
                    'Main',
                    'Sandstorm.NeosTwoFactorAuthentication'
                ),
                '',
                Message::SEVERITY_WARNING
            );
            $this->redirect('setupSecondFactor');
        }

        $account = $this->securityContext->getAccount();

        $this->secondFactorRepository->createSecondFactorForAccount($secret, $account);

        $this->addFlashMessage(
            $this->translator->translateById(
                'login.flashMessage.successfullyRegisteredOtp',
                [],
                null,
                null,
                'Main',
                'Sandstorm.NeosTwoFactorAuthentication'
            ),
        );

        $this->secondFactorSessionStorageService->setAuthenticationStatus(AuthenticationStatus::AUTHENTICATED);

        $originalRequest = $this->securityContext->getInterceptedRequest();
        if ($originalRequest !== null) {
            $this->redirectToRequest($originalRequest);
        }

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
     * @return array
     * @throws InvalidConfigurationTypeException
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
