<?php

namespace Sandstorm\NeosTwoFactorAuthentication\Security\Token;

use Neos\Flow\Mvc\ActionRequest;
use Neos\Flow\Security\Authentication\Token\AbstractToken;

/**
 * Authentication token for usernameless, passwordless passkey login.
 *
 * Unlike the username/password token, this token carries no credentials extracted
 * from the request: the WebAuthn assertion is verified by the
 * {@see \Sandstorm\NeosTwoFactorAuthentication\Controller\PasswordlessLoginController},
 * which then sets the resolved account on this token and marks it authenticated.
 * {@see \Sandstorm\NeosTwoFactorAuthentication\Security\Authentication\WebAuthnPasswordlessProvider}
 * is registered for this token so the framework instantiates and persists it.
 */
class WebAuthnPasswordlessToken extends AbstractToken
{
    /**
     * No credentials are read from the request — the controller performs the WebAuthn
     * ceremony and authenticates this token directly. Must NOT change the authentication
     * status here, otherwise refreshTokens() (which calls updateCredentials on every active
     * token) would reset a token the controller just authenticated.
     */
    public function updateCredentials(ActionRequest $actionRequest)
    {
    }
}
