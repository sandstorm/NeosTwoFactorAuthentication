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
use Sandstorm\NeosTwoFactorAuthentication\Domain\SecondFactorMethod\SecondFactorMethodRegistry;
use Sandstorm\NeosTwoFactorAuthentication\Service\SecondFactorSessionStorageService;
use Sandstorm\NeosTwoFactorAuthentication\Service\TOTPService;
use Sandstorm\NeosTwoFactorAuthentication\Service\WebAuthnService;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialRequestOptions;

class LoginController extends ActionController
{
    /**
     * @var string
     */
    protected $defaultViewObjectName = FusionView::class;

    protected $supportedMediaTypes = ['text/html', 'application/json'];

    protected $viewFormatToObjectNameMap = [
        'json' => \Neos\Flow\Mvc\View\JsonView::class,
        'html' => FusionView::class,
    ];

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
     * @var WebAuthnService
     */
    protected $webAuthnService;

    /**
     * @Flow\Inject
     * @var SecondFactorMethodRegistry
     */
    protected $methodRegistry;

    /**
     * @Flow\Inject
     * @var Translator
     */
    protected $translator;

    /**
     * Adaptive 2FA challenge screen — shows whichever methods the account has registered.
     */
    public function askForSecondFactorAction(?string $username = null): void
    {
        $account = $this->securityContext->getAccount();
        $currentDomain = $this->domainRepository->findOneByActiveRequest();
        $currentSite = $currentDomain !== null ? $currentDomain->getSite() : $this->siteRepository->findDefault();

        $availableMethodTypes = [];
        if ($account !== null) {
            foreach ($this->secondFactorRepository->findByAccount($account) as $factor) {
                /** @var SecondFactor $factor */
                $availableMethodTypes[$factor->getType()] = true;
            }
        }

        $this->view->assignMultiple([
            'styles' => array_filter($this->getNeosSettings()['userInterface']['backendLoginForm']['stylesheets']),
            'scripts' => array_filter($this->getNeosSettings()['userInterface']['backendLoginForm']['scripts']),
            'username' => $username,
            'site' => $currentSite,
            'hasTotp' => isset($availableMethodTypes[SecondFactor::TYPE_TOTP]),
            'hasWebAuthn' => isset($availableMethodTypes[SecondFactor::TYPE_PUBLIC_KEY]),
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

        $isValidOtp = $this->enteredTotpMatchesAnyTotpFactor($otp, $account);

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

        $this->redirectToInterceptedRequestOrBackend();
    }

    /**
     * Method-picker page shown when an account that doesn't have a 2FA yet is forced
     * to set one up before continuing.
     */
    public function setupSecondFactorAction(?string $username = null): void
    {
        $currentDomain = $this->domainRepository->findOneByActiveRequest();
        $currentSite = $currentDomain !== null ? $currentDomain->getSite() : $this->siteRepository->findDefault();

        $this->view->assignMultiple([
            'styles' => array_filter($this->getNeosSettings()['userInterface']['backendLoginForm']['stylesheets']),
            'scripts' => array_filter($this->getNeosSettings()['userInterface']['backendLoginForm']['scripts']),
            'username' => $username,
            'site' => $currentSite,
            'methods' => $this->methodRegistry->getAll(),
            'flashMessages' => $this->flashMessageService
                ->getFlashMessageContainerForRequest($this->request)
                ->getMessagesAndFlush(),
        ]);
    }

    /**
     * TOTP-specific setup wizard (QR code + manual secret + form).
     */
    public function setupTotpAction(?string $username = null): void
    {
        $otp = TOTPService::generateNewTotp();
        $secret = $otp->getSecret();
        $qrCode = $this->tOTPService->generateQRCodeForTokenAndAccount($otp, $this->securityContext->getAccount());

        $currentDomain = $this->domainRepository->findOneByActiveRequest();
        $currentSite = $currentDomain !== null ? $currentDomain->getSite() : $this->siteRepository->findDefault();

        $this->view->assignMultiple([
            'styles' => array_filter($this->getNeosSettings()['userInterface']['backendLoginForm']['stylesheets']),
            'scripts' => array_filter($this->getNeosSettings()['userInterface']['backendLoginForm']['scripts']),
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
     * WebAuthn-specific setup wizard. The page loads JS which calls the
     * register-options and register-verify XHR endpoints.
     */
    public function setupWebAuthnAction(?string $username = null): void
    {
        $currentDomain = $this->domainRepository->findOneByActiveRequest();
        $currentSite = $currentDomain !== null ? $currentDomain->getSite() : $this->siteRepository->findDefault();

        $this->view->assignMultiple([
            'styles' => array_filter($this->getNeosSettings()['userInterface']['backendLoginForm']['stylesheets']),
            'scripts' => array_filter($this->getNeosSettings()['userInterface']['backendLoginForm']['scripts']),
            'username' => $username,
            'site' => $currentSite,
            'flashMessages' => $this->flashMessageService
                ->getFlashMessageContainerForRequest($this->request)
                ->getMessagesAndFlush(),
        ]);
    }

    /**
     * @param string $secret
     * @param string $secondFactorFromApp
     * @param string $name
     * @return void
     * @throws IllegalObjectTypeException
     * @throws SessionNotStartedException
     * @throws StopActionException
     */
    public function createSecondFactorAction(string $secret, string $secondFactorFromApp, string $name = ''): void
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
            $this->redirect('setupTotp');
        }

        $account = $this->securityContext->getAccount();
        $this->secondFactorRepository->createSecondFactorForAccount($secret, $account, SecondFactor::TYPE_TOTP, $name);

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
        $this->redirectToInterceptedRequestOrBackend();
    }

    // ------------------------------------------------------------------
    // WebAuthn XHR endpoints (registration ceremony)
    // ------------------------------------------------------------------

    /**
     * @Flow\SkipCsrfProtection
     */
    public function webAuthnRegisterOptionsAction(): string
    {
        $account = $this->securityContext->getAccount();
        $hostname = $this->request->getHttpRequest()->getUri()->getHost();
        $options = $this->webAuthnService->createRegistrationOptions($account, $hostname);
        $this->secondFactorSessionStorageService->putValue(
            SecondFactorSessionStorageService::SESSION_OBJECT_WEBAUTHN_REGISTRATION_OPTIONS,
            json_encode($options, JSON_THROW_ON_ERROR)
        );
        $this->response->setContentType('application/json');
        return json_encode($options, JSON_THROW_ON_ERROR);
    }

    /**
     * @Flow\SkipCsrfProtection
     * @throws SessionNotStartedException
     * @throws StopActionException
     */
    public function webAuthnRegisterVerifyAction(string $attestation, string $name = ''): string
    {
        $serialized = $this->secondFactorSessionStorageService->getValue(
            SecondFactorSessionStorageService::SESSION_OBJECT_WEBAUTHN_REGISTRATION_OPTIONS
        );
        if (!is_string($serialized)) {
            return $this->jsonError('No registration in progress', 400);
        }
        $options = PublicKeyCredentialCreationOptions::createFromString($serialized);
        $account = $this->securityContext->getAccount();
        try {
            $this->webAuthnService->verifyAndPersistRegistration(
                $attestation,
                $options,
                $account,
                $this->request->getHttpRequest(),
                $name
            );
        } catch (\Throwable $e) {
            return $this->jsonError($e->getMessage(), 400);
        }

        $this->secondFactorSessionStorageService->removeValue(
            SecondFactorSessionStorageService::SESSION_OBJECT_WEBAUTHN_REGISTRATION_OPTIONS
        );
        $this->secondFactorSessionStorageService->setAuthenticationStatus(AuthenticationStatus::AUTHENTICATED);

        $this->response->setContentType('application/json');
        return json_encode(['status' => 'ok'], JSON_THROW_ON_ERROR);
    }

    // ------------------------------------------------------------------
    // WebAuthn XHR endpoints (authentication ceremony)
    // ------------------------------------------------------------------

    /**
     * @Flow\SkipCsrfProtection
     */
    public function webAuthnAuthenticateOptionsAction(): string
    {
        $account = $this->securityContext->getAccount();
        $options = $this->webAuthnService->createAuthenticationOptions($account);
        $this->secondFactorSessionStorageService->putValue(
            SecondFactorSessionStorageService::SESSION_OBJECT_WEBAUTHN_AUTHENTICATION_OPTIONS,
            json_encode($options, JSON_THROW_ON_ERROR)
        );
        $this->response->setContentType('application/json');
        return json_encode($options, JSON_THROW_ON_ERROR);
    }

    /**
     * @Flow\SkipCsrfProtection
     */
    public function webAuthnAuthenticateVerifyAction(string $assertion): string
    {
        $serialized = $this->secondFactorSessionStorageService->getValue(
            SecondFactorSessionStorageService::SESSION_OBJECT_WEBAUTHN_AUTHENTICATION_OPTIONS
        );
        if (!is_string($serialized)) {
            return $this->jsonError('No authentication in progress', 400);
        }
        $options = PublicKeyCredentialRequestOptions::createFromString($serialized);
        $account = $this->securityContext->getAccount();

        try {
            $this->webAuthnService->verifyAuthenticationResponse(
                $assertion,
                $options,
                $account,
                $this->request->getHttpRequest()
            );
        } catch (\Throwable $e) {
            return $this->jsonError($e->getMessage(), 400);
        }

        $this->secondFactorSessionStorageService->removeValue(
            SecondFactorSessionStorageService::SESSION_OBJECT_WEBAUTHN_AUTHENTICATION_OPTIONS
        );
        $this->secondFactorSessionStorageService->setAuthenticationStatus(AuthenticationStatus::AUTHENTICATED);

        $originalRequest = $this->securityContext->getInterceptedRequest();
        $redirectUri = $originalRequest !== null
            ? (string)$this->controllerContext->getUriBuilder()->uriFor(
                $originalRequest->getControllerActionName(),
                $originalRequest->getArguments(),
                $originalRequest->getControllerName(),
                $originalRequest->getControllerPackageKey()
            )
            : '/neos';

        $this->response->setContentType('application/json');
        return json_encode(['status' => 'ok', 'redirect' => $redirectUri], JSON_THROW_ON_ERROR);
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function jsonError(string $message, int $code): string
    {
        $this->response->setStatusCode($code);
        $this->response->setContentType('application/json');
        return json_encode(['status' => 'error', 'message' => $message], JSON_THROW_ON_ERROR);
    }

    /**
     * Check the submitted OTP against every TOTP factor for the account.
     */
    private function enteredTotpMatchesAnyTotpFactor(string $enteredSecondFactor, Account $account): bool
    {
        $totpFactors = $this->secondFactorRepository->findByAccountAndType($account, SecondFactor::TYPE_TOTP);
        foreach ($totpFactors as $secondFactor) {
            if (TOTPService::checkIfOtpIsValid($secondFactor->getSecret(), $enteredSecondFactor)) {
                return true;
            }
        }
        return false;
    }

    /**
     * @throws StopActionException
     */
    private function redirectToInterceptedRequestOrBackend(): void
    {
        $originalRequest = $this->securityContext->getInterceptedRequest();
        if ($originalRequest !== null) {
            $this->redirectToRequest($originalRequest);
        }
        $this->redirect('index', 'Backend\Backend', 'Neos.Neos');
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
