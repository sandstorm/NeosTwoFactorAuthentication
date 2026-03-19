<?php

namespace Sandstorm\NeosTwoFactorAuthentication\Service;

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Session\Exception\SessionNotStartedException;
use Neos\Flow\Session\SessionManagerInterface;
use Sandstorm\NeosTwoFactorAuthentication\Domain\AuthenticationStatus;

class SecondFactorSessionStorageService
{
    const SESSION_OBJECT_ID = 'Sandstorm/NeosTwoFactorAuthentication';
    const SESSION_OBJECT_AUTH_STATUS = 'authenticationStatus';

    /**
     * @Flow\Inject
     * @var SessionManagerInterface
     */
    protected $sessionManager;

    /**
     * @throws SessionNotStartedException
     */
    public function setAuthenticationStatus(AuthenticationStatus $authenticationStatus): void
    {
        $this->sessionManager->getCurrentSession()->putData(
            self::SESSION_OBJECT_ID,
            [
                self::SESSION_OBJECT_AUTH_STATUS => $authenticationStatus->value,
            ]
        );
    }

    /**
     * @throws SessionNotStartedException
     */
    public function getAuthenticationStatus(): AuthenticationStatus
    {
        $storageObject = $this->sessionManager->getCurrentSession()->getData(self::SESSION_OBJECT_ID);

        return AuthenticationStatus::from($storageObject[self::SESSION_OBJECT_AUTH_STATUS]);
    }

    /**
     * @throws SessionNotStartedException
     */
    public function initializeTwoFactorSessionObject(): void
    {
        if (!$this->sessionManager->getCurrentSession()->hasKey(self::SESSION_OBJECT_ID)) {
            self::setAuthenticationStatus(AuthenticationStatus::AUTHENTICATION_NEEDED);
        }
    }

    /**
     * When the user requests to cancel the login attempt, we destroy the current session to get out
     * of the state between "logged in with username/password" and "fully authenticated with second factor".
     */
    public function cancelLoginAttempt(): void
    {
        if ($this->sessionManager->getCurrentSession()->hasKey(self::SESSION_OBJECT_ID)) {
            $this->sessionManager->getCurrentSession()->destroy('Termination requested by user.');
        }
    }
}
