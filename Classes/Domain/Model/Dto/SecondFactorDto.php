<?php

namespace Sandstorm\NeosTwoFactorAuthentication\Domain\Model\Dto;

use Neos\Neos\Domain\Model\User;
use Neos\Party\Domain\Model\Person;
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
    public function getSecondFactor(): SecondFactor|string
    {
        return $this->secondFactor;
    }

    /**
     * @return Person|string|null
     */
    public function getUser(): string|Person|null
    {
        return $this->user;
    }
}
