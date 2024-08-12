<?php

namespace Sandstorm\NeosTwoFactorAuthentication\Domain\Model\Dto;

use Neos\Neos\Domain\Model\User;
use Sandstorm\NeosTwoFactorAuthentication\Domain\Model\SecondFactor;

final class SecondFactorDto
{
    public function __construct(
        readonly public SecondFactor $secondFactor,
        readonly public ?User        $user = null
    )
    {
    }
}
