<?php

namespace Sandstorm\NeosTwoFactorAuthentication\Domain\Model\Dto;

use Neos\Neos\Domain\Model\User;
use Sandstorm\NeosTwoFactorAuthentication\Domain\Model\SecondFactor;

readonly class SecondFactorDto
{
    public function __construct(
        public SecondFactor $secondFactor,
        public User $user)
    {
    }
}
