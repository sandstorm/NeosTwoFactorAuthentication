<?php

namespace Sandstorm\NeosTwoFactorAuthentication\Security\Authentication\Token;

use Neos\Flow\Mvc\ActionRequest;
use Neos\Flow\Security\Authentication\Token\UsernamePassword;
use Neos\Flow\Security\Exception\InvalidAuthenticationStatusException;
use Neos\Utility\ObjectAccess;
use Neos\Flow\Annotations as Flow;

class UsernameAndPasswordWithSecondFactor extends UsernamePassword
{
    private const DEFAULT_SECOND_FACTOR_POST_FIELD = '__authentication.Sandstorm.NeosTwoFactorAuthentication.Security.Authentication.Token.UsernamePasswordWithSecondFactor.secondFactor';

    /**
     * The username/password credentials with second factor
     * @var array
     */
    protected $credentials = ['username' => '', 'password' => '', 'secondFactor' => ''];

    /**
     * @var bool
     */
    protected bool $authenticatedWithSecondFactor = false;

    /**
     * @param ActionRequest $actionRequest
     * @return void
     * @throws InvalidAuthenticationStatusException
     */
    public function updateCredentials(ActionRequest $actionRequest)
    {
        parent::updateCredentials($actionRequest);

        if ($this->authenticationStatus !== self::AUTHENTICATION_NEEDED) {
            return;
        }

        $allArguments = array_merge($actionRequest->getArguments(), $actionRequest->getInternalArguments());
        $secondFactorFieldName = self::DEFAULT_SECOND_FACTOR_POST_FIELD;
        $secondFactor = ObjectAccess::getPropertyPath($allArguments, $secondFactorFieldName);

        if (!empty($secondFactor)) {
            $this->credentials['secondFactor'] = $secondFactor;
            $this->setAuthenticationStatus(self::AUTHENTICATION_NEEDED);
        }
    }

    public function secondFactorWasSubmitted(): bool
    {
        return !empty($this->credentials['secondFactor']);
    }


    /**
     * @return string The second factor this token extracted from the request, or an empty string
     */
    public function getSecondFactor(): string
    {
        return $this->credentials['secondFactor'] ?? '';
    }

    /**
     * @return bool
     */
    public function isAuthenticatedWithSecondFactor(): bool
    {
        return $this->authenticatedWithSecondFactor;
    }

    /**
     * @param bool $authenticatedWithSecondFactor
     */
    public function setAuthenticatedWithSecondFactor(bool $authenticatedWithSecondFactor): void
    {
        $this->authenticatedWithSecondFactor = $authenticatedWithSecondFactor;
    }
}
