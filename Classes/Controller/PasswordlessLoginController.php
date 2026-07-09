<?php

namespace Sandstorm\NeosTwoFactorAuthentication\Controller;

/*
 * This file is part of the Sandstorm.NeosTwoFactorAuthentication package.
 */

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Mvc\Controller\ActionController;
use Neos\Flow\Mvc\View\JsonView;
use Neos\Flow\Security\Authentication\TokenInterface;
use Neos\Flow\Security\Context as SecurityContext;
use Sandstorm\NeosTwoFactorAuthentication\Domain\AuthenticationStatus;
use Sandstorm\NeosTwoFactorAuthentication\Security\Token\WebAuthnPasswordlessToken;
use Sandstorm\NeosTwoFactorAuthentication\Service\SecondFactorSessionStorageService;
use Sandstorm\NeosTwoFactorAuthentication\Service\WebAuthnService;

/**
 * XHR endpoints for usernameless, passwordless passkey login from the Neos login screen.
 *
 * Both actions are reachable while logged out (this controller is intentionally NOT behind
 * the Neos.Neos:Backend request pattern). They are hard-gated by the
 * `webAuthn.passwordlessLoginEnabled` setting (off by default) so that, while disabled, no
 * one can authenticate through this path even if the UI button were somehow present.
 */
class PasswordlessLoginController extends ActionController
{
    protected $supportedMediaTypes = ['application/json'];

    protected $defaultViewObjectName = JsonView::class;

    /**
     * @Flow\Inject
     * @var SecurityContext
     */
    protected $securityContext;

    /**
     * @Flow\Inject
     * @var WebAuthnService
     */
    protected $webAuthnService;

    /**
     * @Flow\Inject
     * @var SecondFactorSessionStorageService
     */
    protected $secondFactorSessionStorageService;

    /**
     * @Flow\InjectConfiguration(path="webAuthn.passwordlessLoginEnabled")
     * @var bool
     */
    protected $passwordlessLoginEnabled = false;

    /**
     * Ceremony step 1: return the request options for navigator.credentials.get().
     * Usernameless — no allowCredentials, so the browser offers any discoverable passkey.
     *
     * @Flow\SkipCsrfProtection
     */
    public function optionsAction(): string
    {
        if (!$this->passwordlessLoginEnabled) {
            return $this->jsonError('Passwordless login is disabled', 403);
        }
        // The visitor is not authenticated yet, so no session exists — start one to hold the
        // challenge across the options -> verify round trip.
        $this->secondFactorSessionStorageService->startSessionIfNotStarted();
        $hostname = $this->request->getHttpRequest()->getUri()->getHost();
        $options = $this->webAuthnService->createPasswordlessAuthenticationOptions($hostname);
        $optionsJson = $this->webAuthnService->optionsToJson($options);
        $this->secondFactorSessionStorageService->putValue(
            SecondFactorSessionStorageService::SESSION_OBJECT_WEBAUTHN_PASSWORDLESS_OPTIONS,
            $optionsJson
        );
        $this->response->setContentType('application/json');
        return $optionsJson;
    }

    /**
     * Ceremony step 2: verify the assertion, resolve the account, authenticate the parallel
     * WebAuthn token (which logs the user into the Neos backend without a password), and
     * return the URI for the client to redirect to.
     *
     * @Flow\SkipCsrfProtection
     */
    public function verifyAction(string $assertion): string
    {
        if (!$this->passwordlessLoginEnabled) {
            return $this->jsonError('Passwordless login is disabled', 403);
        }

        $serialized = $this->secondFactorSessionStorageService->getValue(
            SecondFactorSessionStorageService::SESSION_OBJECT_WEBAUTHN_PASSWORDLESS_OPTIONS
        );
        if (!is_string($serialized)) {
            return $this->jsonError('No passwordless login in progress', 400);
        }
        $options = $this->webAuthnService->requestOptionsFromJson($serialized);

        try {
            $account = $this->webAuthnService->verifyPasswordlessAssertion(
                $assertion,
                $options,
                $this->request->getHttpRequest()
            );
        } catch (\Throwable $e) {
            return $this->jsonError($e->getMessage(), 400);
        }

        $token = $this->securityContext->getAuthenticationTokensOfType(WebAuthnPasswordlessToken::class)[0] ?? null;
        if ($token === null) {
            return $this->jsonError('Passwordless authentication token not available', 500);
        }

        // Authenticate the parallel token with the resolved backend account and persist it to
        // the session so the authentication survives the redirect to the backend.
        $token->setAccount($account);
        $token->setAuthenticationStatus(TokenInterface::AUTHENTICATION_SUCCESSFUL);
        $this->securityContext->refreshTokens();

        // A user-verified passkey is itself multi-factor, so it also satisfies the 2FA gate.
        $this->secondFactorSessionStorageService->initializeTwoFactorSessionObject();
        $this->secondFactorSessionStorageService->setAuthenticationStatus(AuthenticationStatus::AUTHENTICATED);

        $this->secondFactorSessionStorageService->removeValue(
            SecondFactorSessionStorageService::SESSION_OBJECT_WEBAUTHN_PASSWORDLESS_OPTIONS
        );

        $this->response->setContentType('application/json');
        return json_encode([
            'status' => 'ok',
            'redirect' => $this->interceptedRequestOrBackendUri(),
        ], JSON_THROW_ON_ERROR);
    }

    private function jsonError(string $message, int $code): string
    {
        $this->response->setStatusCode($code);
        $this->response->setContentType('application/json');
        return json_encode(['status' => 'error', 'message' => $message], JSON_THROW_ON_ERROR);
    }

    /**
     * Resolve the post-login redirect: the originally intercepted request if there is one,
     * otherwise the Neos backend index — always through routing, never a hardcoded path.
     */
    private function interceptedRequestOrBackendUri(): string
    {
        $uriBuilder = $this->controllerContext->getUriBuilder();
        $originalRequest = $this->securityContext->getInterceptedRequest();
        if ($originalRequest !== null) {
            return (string)$uriBuilder->uriFor(
                $originalRequest->getControllerActionName(),
                $originalRequest->getArguments(),
                $originalRequest->getControllerName(),
                $originalRequest->getControllerPackageKey()
            );
        }
        return (string)$uriBuilder->uriFor('index', [], 'Backend\Backend', 'Neos.Neos');
    }
}
