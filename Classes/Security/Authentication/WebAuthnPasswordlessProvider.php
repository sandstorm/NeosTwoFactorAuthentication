<?php

namespace Sandstorm\NeosTwoFactorAuthentication\Security\Authentication;

use Neos\Flow\Security\Authentication\Provider\AbstractProvider;
use Neos\Flow\Security\Authentication\TokenInterface;
use Sandstorm\NeosTwoFactorAuthentication\Security\Token\WebAuthnPasswordlessToken;

/**
 * Authentication provider for usernameless passwordless passkey login.
 *
 * The actual WebAuthn verification and token authentication happen in the
 * {@see \Sandstorm\NeosTwoFactorAuthentication\Controller\PasswordlessLoginController}
 * (it needs the session-stored challenge and the webauthn-lib validator). This provider
 * exists so the framework registers the {@see WebAuthnPasswordlessToken} for this provider
 * name, keeps it active (no request pattern), and restores it from the session on later
 * requests. With the `oneToken` authentication strategy, an authenticated parallel token is
 * enough for the request to count as authenticated; the resolved account is a regular
 * `Neos.Neos:Backend` account, so roles and the current Neos user resolve normally.
 */
class WebAuthnPasswordlessProvider extends AbstractProvider
{
    /**
     * @return array<string>
     */
    public function getTokenClassNames()
    {
        return [WebAuthnPasswordlessToken::class];
    }

    /**
     * The AuthenticationProviderManager only calls this for tokens in state
     * AUTHENTICATION_NEEDED. The controller drives this token straight from
     * NO_CREDENTIALS_GIVEN to AUTHENTICATION_SUCCESSFUL, so there is nothing to do here:
     * never downgrade an already-successful (session-restored) token.
     */
    public function authenticate(TokenInterface $authenticationToken)
    {
        if (!$authenticationToken instanceof WebAuthnPasswordlessToken) {
            return;
        }
        if ($authenticationToken->getAuthenticationStatus() !== TokenInterface::AUTHENTICATION_SUCCESSFUL) {
            $authenticationToken->setAuthenticationStatus(TokenInterface::NO_CREDENTIALS_GIVEN);
        }
    }
}
