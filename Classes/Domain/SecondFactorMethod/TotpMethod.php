<?php

namespace Sandstorm\NeosTwoFactorAuthentication\Domain\SecondFactorMethod;

use Neos\Flow\Annotations as Flow;
use Sandstorm\NeosTwoFactorAuthentication\Domain\Model\SecondFactor;

/**
 * @Flow\Scope("singleton")
 */
class TotpMethod implements SecondFactorMethodInterface
{
    public function getType(): int
    {
        return SecondFactor::TYPE_TOTP;
    }

    public function getIdentifier(): string
    {
        return 'totp';
    }

    public function getLabelTranslationKey(): string
    {
        return 'method.totp.label';
    }

    public function getDescriptionTranslationKey(): string
    {
        return 'method.totp.description';
    }

    public function getSetupActionName(): string
    {
        return 'setupTotp';
    }
}
