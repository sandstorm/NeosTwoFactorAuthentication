<?php

declare(strict_types=1);

namespace Sandstorm\NeosTwoFactorAuthentication\UserManagement\Controller;

use Neos\Error\Messages\Message;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\I18n\Translator;
use Neos\Neos\Validation\Validator\PasswordValidator;
use Sandstorm\NeosTwoFactorAuthentication\UserManagement\Application\PasswordResetRequestHandler;
use Sandstorm\NeosTwoFactorAuthentication\UserManagement\Application\UserActionTokenService;
use Sandstorm\NeosTwoFactorAuthentication\UserManagement\Application\UserCommandService;
use Sandstorm\NeosTwoFactorAuthentication\UserManagement\Domain\UserActionType;

/**
 * Forgot password (request a link) and set password (via a mailed link - reset or initial password).
 */
class PasswordController extends AbstractPublicController
{
    #[Flow\Inject]
    protected PasswordResetRequestHandler $passwordResetRequestHandler;

    #[Flow\Inject]
    protected UserActionTokenService $tokenService;

    #[Flow\Inject]
    protected UserCommandService $userCommandService;

    #[Flow\Inject]
    protected Translator $translator;

    /** @var array{minimumLength: int, maximumLength: int} */
    #[Flow\InjectConfiguration(path: 'userManagement.passwordPolicy')]
    protected array $passwordPolicy;

    public function requestAction(): void
    {
        $this->assignLoginLayout();
    }

    public function requestSubmitAction(string $usernameOrEmail = ''): void
    {
        $this->passwordResetRequestHandler->handle($usernameOrEmail, $this->clientIp());
        // always the same answer - never reveal whether an account exists
        $this->redirect('requestSent');
    }

    public function requestSentAction(): void
    {
        $this->assignLoginLayout();
    }

    public function setAction(string $token): void
    {
        $this->assignLoginLayout();
        $userActionToken = $this->tokenService->findValid($token, UserActionType::passwordActions());
        $this->view->assignMultiple([
            'token' => $token,
            'valid' => $userActionToken !== null,
            'action' => $userActionToken?->getAction()->value,
            'username' => $userActionToken?->getAccount()->getAccountIdentifier(),
            'passwordMinimumLength' => $this->passwordPolicy['minimumLength'],
        ]);
    }

    public function setSubmitAction(string $token, string $password = '', string $passwordConfirmation = ''): void
    {
        $userActionToken = $this->tokenService->findValid($token, UserActionType::passwordActions());
        if ($userActionToken === null) {
            $this->redirect('set', null, null, ['token' => $token]);
        }

        $passwordError = $this->validatePassword($password, $passwordConfirmation);
        if ($passwordError !== null) {
            $this->addFlashMessage($passwordError, '', Message::SEVERITY_ERROR);
            $this->redirect('set', null, null, ['token' => $token]);
        }

        $this->tokenService->consume($userActionToken);
        $this->userCommandService->setPasswordWithToken($userActionToken->getAccount(), $password);

        // no auto-login: the user logs in regularly, so 2FA stays in force
        $this->addFlashMessage(
            $this->translate('password.set.success'),
            '',
            Message::SEVERITY_OK
        );
        $this->redirect('index', 'Login', 'Neos.Neos');
    }

    /**
     * Same rules as the core user module ({@see PasswordValidator}), with translated messages.
     */
    private function validatePassword(string $password, string $passwordConfirmation): ?string
    {
        $minimum = $this->passwordPolicy['minimumLength'];
        $maximum = $this->passwordPolicy['maximumLength'];
        $validationResult = (new PasswordValidator([
            'allowEmpty' => false,
            'minimum' => $minimum,
            'maximum' => $maximum,
        ]))->validate([$password, $passwordConfirmation]);
        if (!$validationResult->hasErrors()) {
            return null;
        }
        if (trim($password) !== trim($passwordConfirmation)) {
            return $this->translate('password.error.mismatch');
        }
        return $this->translate('password.error.length', [$minimum, $maximum]);
    }

    /**
     * @param array<int|string, mixed> $arguments
     */
    private function translate(string $id, array $arguments = []): string
    {
        return (string)$this->translator->translateById($id, $arguments, null, null, 'UserManagement', 'Sandstorm.NeosTwoFactorAuthentication');
    }
}
