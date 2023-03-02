<?php

namespace Sandstorm\NeosTwoFactorAuthentication\Security\Authentication\Provider;

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Security\Account;
use Neos\Flow\Security\Authentication\Provider\PersistedUsernamePasswordProvider;
use Neos\Flow\Security\Authentication\TokenInterface;
use Neos\Flow\Security\Exception\UnsupportedAuthenticationTokenException;
use Sandstorm\NeosTwoFactorAuthentication\Domain\Model\SecondFactor;
use Sandstorm\NeosTwoFactorAuthentication\Domain\Repository\SecondFactorRepository;
use Sandstorm\NeosTwoFactorAuthentication\Error\SecondFactorEnforcedSetupException;
use Sandstorm\NeosTwoFactorAuthentication\Error\SecondFactorRequiredException;
use Sandstorm\NeosTwoFactorAuthentication\Security\Authentication\Token\UsernameAndPasswordWithSecondFactor;
use Sandstorm\NeosTwoFactorAuthentication\Service\TOTPService;

class PersistentUsernameAndPasswordWithSecondFactorProvider extends PersistedUsernamePasswordProvider
{

    /**
     * @var SecondFactorRepository
     * @Flow\Inject(lazy=false)
     */
    protected SecondFactorRepository $secondFactorRepository;

    /**
     * @Flow\InjectConfiguration(path="enforceTwoFactorAuthentication")
     * @var bool
     */
    protected $enforceTwoFactorAuthentication;

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
            if ($this->enteredTokenMatchesAnySecondFactor($authenticationToken->getSecondFactor(), $account)) {
                $authenticationToken->setAuthenticationStatus(TokenInterface::AUTHENTICATION_SUCCESSFUL);

                // prevent second factor form from appearing again by persisting second factor was authenticated
                $authenticationToken->setAuthenticatedWithSecondFactor(true);
            } else {
                // deny access again because second factor was invalid
                $authenticationToken->setAuthenticationStatus(TokenInterface::AUTHENTICATION_NEEDED);
                throw new SecondFactorRequiredException();
            }
        }

        if ($authenticationToken->getAuthenticationStatus() !== TokenInterface::AUTHENTICATION_SUCCESSFUL) {
            return;
        }

        if ($authenticationToken->getAuthenticationStatus() === TokenInterface::AUTHENTICATION_SUCCESSFUL) {
            # if 2fa enforced && 2fa not set up (!isEnabledForAccount)
            # redirect to 2fa setup
            if ($this->enforceTwoFactorAuthentication && !$this->secondFactorRepository->isEnabledForAccount($account)) {
                // This exception gets caught inside the {@see SecondFactorRedirectMiddleware}
                // which leads to redirect to 2fa setup view in backend controller
                throw new SecondFactorEnforcedSetupException();
            }

            if ($this->secondFactorRepository->isEnabledForAccount($account) && !$authenticationToken->isAuthenticatedWithSecondFactor()) {
                // deny access again because second factor is required
                $authenticationToken->setAuthenticationStatus(TokenInterface::AUTHENTICATION_NEEDED);
                // This exception gets caught inside the {@see SecondFactorRedirectMiddleware}
                throw new SecondFactorRequiredException();
            }
        }
    }

    /**
     * Check if the given token matches any registered second factor
     *
     * @param string $enteredSecondFactor
     * @param Account $account
     * @return bool
     */
    protected function enteredTokenMatchesAnySecondFactor(string $enteredSecondFactor, Account $account): bool
    {
        /** @var SecondFactor[] $secondFactors */
        $secondFactors = $this->secondFactorRepository->findByAccount($account);
        foreach ($secondFactors as $secondFactor) {
            $isValid = TOTPService::checkIfOtpIsValid($secondFactor->getSecret(), $enteredSecondFactor);
            if ($isValid) {
                return true;
            }
        }

        return false;
    }
}
