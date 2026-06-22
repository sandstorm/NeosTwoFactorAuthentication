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
    const SESSION_OBJECT_WEBAUTHN_REGISTRATION_OPTIONS = 'webAuthnRegistrationOptions';
    const SESSION_OBJECT_WEBAUTHN_AUTHENTICATION_OPTIONS = 'webAuthnAuthenticationOptions';

    /**
     * @Flow\Inject
     * @var SessionManagerInterface
     */
    protected $sessionManager;

    /**
     * @throws SessionNotStartedException
     */
    public function setAuthenticationStatus(string $authenticationStatus): void
    {
        $session = $this->sessionManager->getCurrentSession();
        $data = $session->getData(self::SESSION_OBJECT_ID) ?: [];
        $data[self::SESSION_OBJECT_AUTH_STATUS] = $authenticationStatus;
        $session->putData(self::SESSION_OBJECT_ID, $data);
    }

    /**
     * @throws SessionNotStartedException
     */
    public function getAuthenticationStatus(): string
    {
        $storageObject = $this->sessionManager->getCurrentSession()->getData(self::SESSION_OBJECT_ID);

        return $storageObject[self::SESSION_OBJECT_AUTH_STATUS];
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
     * Persist arbitrary data under a key inside the package's session object,
     * preserving the existing authentication status entry.
     *
     * @throws SessionNotStartedException
     */
    public function putValue(string $key, mixed $value): void
    {
        $session = $this->sessionManager->getCurrentSession();
        $data = $session->getData(self::SESSION_OBJECT_ID) ?: [];
        $data[$key] = $value;
        $session->putData(self::SESSION_OBJECT_ID, $data);
    }

    /**
     * @throws SessionNotStartedException
     */
    public function getValue(string $key): mixed
    {
        $session = $this->sessionManager->getCurrentSession();
        $data = $session->getData(self::SESSION_OBJECT_ID) ?: [];
        return $data[$key] ?? null;
    }

    /**
     * @throws SessionNotStartedException
     */
    public function removeValue(string $key): void
    {
        $session = $this->sessionManager->getCurrentSession();
        $data = $session->getData(self::SESSION_OBJECT_ID) ?: [];
        unset($data[$key]);
        $session->putData(self::SESSION_OBJECT_ID, $data);
    }

    /**
     * Abort the login attempt: destroy the whole session to leave the in-between state
     * of "authenticated with username/password, but not yet with the second factor".
     * This also drops the Neos backend authentication, so the next request lands on the
     * regular login screen again.
     */
    public function cancelLoginAttempt(): void
    {
        $session = $this->sessionManager->getCurrentSession();
        if ($session->isStarted()) {
            $session->destroy('Second factor login attempt cancelled by user.');
        }
    }
}
