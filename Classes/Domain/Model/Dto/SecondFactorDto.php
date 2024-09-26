<?php

namespace Sandstorm\NeosTwoFactorAuthentication\Domain\Model\Dto;

use Neos\Neos\Domain\Model\User;
use Sandstorm\NeosTwoFactorAuthentication\Domain\Model\SecondFactor;

class SecondFactorDto
{
    protected SecondFactor $secondFactor;

    protected User $user;

    public function __construct(SecondFactor $secondFactor, User $user = null)
    {
        $this->user = $user;
        $this->secondFactor = $secondFactor;
    }

    /**
     * @return SecondFactor|string
     */
    public function getSecondFactor(): SecondFactor
    {
        return $this->secondFactor;
    }

    /**
     * @return User
     */
    public function getUser(): User
    {
        return $this->user;
    }
}
