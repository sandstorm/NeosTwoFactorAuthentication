<?php

declare(strict_types=1);

namespace Sandstorm\NeosTwoFactorAuthentication\UserManagement\Controller;

use Neos\Flow\Annotations as Flow;
use Sandstorm\NeosTwoFactorAuthentication\UserManagement\Application\UserActionTokenService;
use Sandstorm\NeosTwoFactorAuthentication\UserManagement\Application\UserCommandService;
use Sandstorm\NeosTwoFactorAuthentication\UserManagement\Domain\UserActionType;

/**
 * Confirms that a user has access to their primary email. GET only shows a button (mail scanners prefetch links),
 * the POST approves the address.
 */
class EmailVerificationController extends AbstractPublicController
{
    #[Flow\Inject]
    protected UserActionTokenService $tokenService;

    #[Flow\Inject]
    protected UserCommandService $userCommandService;

    public function confirmAction(string $token): void
    {
        $this->assignLoginLayout();
        $this->view->assignMultiple([
            'token' => $token,
            'valid' => $this->tokenService->findValid($token, [UserActionType::VERIFY_EMAIL]) !== null,
        ]);
    }

    public function confirmSubmitAction(string $token): void
    {
        $this->assignLoginLayout();
        $userActionToken = $this->tokenService->findValid($token, [UserActionType::VERIFY_EMAIL]);
        if ($userActionToken !== null) {
            $this->tokenService->consume($userActionToken);
            $this->userCommandService->markEmailValidated(
                $this->userCommandService->requireUserOfAccount($userActionToken->getAccount())
            );
        }
        $this->view->assign('confirmed', $userActionToken !== null);
    }
}
