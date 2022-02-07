<?php

namespace Sandstorm\NeosTwoFactorAuthentication\Security\Authentication\Provider;

use Neos\Flow\Security\Authentication\Provider\PersistedUsernamePasswordProvider;
use Neos\Flow\Security\Authentication\TokenInterface;
use Neos\Flow\Security\Exception\UnsupportedAuthenticationTokenException;
use Sandstorm\NeosTwoFactorAuthentication\Domain\Repository\SecondFactorRepository;
use Sandstorm\NeosTwoFactorAuthentication\Error\SecondFactorRequiredException;
use Sandstorm\NeosTwoFactorAuthentication\Security\Authentication\Token\UsernameAndPasswordWithSecondFactor;
use Neos\Flow\Annotations as Flow;

class PersistentUsernameAndPasswordWithSecondFactorProvider extends PersistedUsernamePasswordProvider
{

    /**
     * @var SecondFactorRepository
     * @Flow\Inject(lazy=false)
     */
    protected SecondFactorRepository $secondFactorRepository;

    public function getTokenClassNames()
    {
        return [UsernameAndPasswordWithSecondFactor::class];
    }

    public function authenticate(TokenInterface $authenticationToken)
    {
        if (!($authenticationToken instanceof UsernameAndPasswordWithSecondFactor)) {
            throw new UnsupportedAuthenticationTokenException(sprintf('This provider cannot authenticate the given token. The token must implement %s', UsernameAndPasswordWithSecondFactor::class), 1217339840);
        }

        parent::authenticate($authenticationToken);

        $account = $authenticationToken->getAccount();
        if (!$account) {
            return;
        }

        // second factor was submitted, in this case username and password are not submitted
        if ($authenticationToken->secondFactorWasSubmitted()) {
            // TODO: check if second factor is correct
            $secondFactorIsCorrect = true;
            if ($secondFactorIsCorrect) {
                $authenticationToken->setAuthenticationStatus(TokenInterface::AUTHENTICATION_SUCCESSFUL);

                // prevent second factor form from appearing again by persisting second factor was authenticated
                $authenticationToken->setAuthenticatedWithSecondFactor(true);
            } else {
                throw new SecondFactorRequiredException();
            }
        }

        if ($authenticationToken->getAuthenticationStatus() !== TokenInterface::AUTHENTICATION_SUCCESSFUL) {
            return;
        }

        if ($authenticationToken->getAuthenticationStatus() === TokenInterface::AUTHENTICATION_SUCCESSFUL) {
            if ($this->secondFactorRepository->isEnabledForAccount($account) && !$authenticationToken->isAuthenticatedWithSecondFactor()) {
                // deny access again because second factor is required
                $authenticationToken->setAuthenticationStatus(TokenInterface::AUTHENTICATION_NEEDED);
                // This exception gets caught inside the {@see SecondFactorRedirectMiddleware}
                throw new SecondFactorRequiredException();
            }
        }
    }
}
