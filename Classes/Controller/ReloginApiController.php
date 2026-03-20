<?php

namespace Sandstorm\NeosTwoFactorAuthentication\Controller;

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Mvc\Controller\ActionController;
use Neos\Flow\Mvc\View\JsonView;
use Neos\Flow\Security\Context as SecurityContext;
use Sandstorm\NeosTwoFactorAuthentication\Domain\AuthenticationStatus;
use Sandstorm\NeosTwoFactorAuthentication\Service\SecondFactorService;
use Sandstorm\NeosTwoFactorAuthentication\Service\SecondFactorSessionStorageService;

class ReloginApiController extends ActionController
{
    /**
     * @var string
     */
    protected $defaultViewObjectName = JsonView::class;

    /**
     * @var array<string>
     */
    protected $supportedMediaTypes = ['application/json'];

    /**
     * @Flow\Inject
     */
    protected SecurityContext $securityContext;

    /**
     * @Flow\Inject
     */
    protected SecondFactorService $secondFactorService;

    /**
     * @Flow\Inject
     */
    protected SecondFactorSessionStorageService $secondFactorSessionStorageService;

    /**
     * Returns whether the currently authenticated account requires a second factor.
     */
    public function secondFactorStatusAction(): void
    {
        $account = $this->securityContext->getAccount();
        if ($account === null) {
            $this->response->setStatusCode(401);
            $this->view->assign('value', ['error' => 'Not authenticated']);
            return;
        }

        $required = $this->secondFactorService->isSecondFactorEnabledForAccount($account);
        $this->view->assign('value', ['secondFactorRequired' => $required]);
    }

    /**
     * Validates the submitted OTP and sets the session to AUTHENTICATED on success.
     *
     * CSRF protection is skipped because after session timeout + re-login, the CSRF token
     * context may not match. The endpoint is still protected by session authentication
     * and policy authorization (Policy.yaml).
     *
     * @Flow\SkipCsrfProtection
     */
    public function verifySecondFactorAction(): void
    {
        $account = $this->securityContext->getAccount();
        if ($account === null) {
            $this->response->setStatusCode(401);
            $this->view->assign('value', ['success' => false, 'error' => 'Not authenticated']);
            return;
        }

        // Read the raw body — rewind the stream first since Flow may have already read it
        $httpRequest = $this->request->getHttpRequest();
        $bodyStream = $httpRequest->getBody();
        $bodyStream->rewind();
        $body = json_decode($bodyStream->getContents(), true);
        $otp = $body['otp'] ?? '';

        if ($otp === '') {
            $this->response->setStatusCode(400);
            $this->view->assign('value', ['success' => false, 'error' => 'Missing OTP']);
            return;
        }

        $isValid = $this->secondFactorService->validateOtpForAccount($otp, $account);

        if ($isValid) {
            $this->secondFactorSessionStorageService->setAuthenticationStatus(AuthenticationStatus::AUTHENTICATED);
            $this->view->assign('value', ['success' => true]);
            return;
        }

        $this->response->setStatusCode(401);
        $this->view->assign('value', ['success' => false, 'error' => 'Invalid OTP']);
    }
}
