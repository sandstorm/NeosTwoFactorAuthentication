<?php

namespace Sandstorm\NeosTwoFactorAuthentication\Domain\SecondFactorMethod;

use Neos\Flow\Annotations as Flow;
use Sandstorm\NeosTwoFactorAuthentication\Domain\Model\SecondFactor;

/**
 * @Flow\Scope("singleton")
 */
class WebAuthnMethod implements SecondFactorMethodInterface
{
    public function getType(): int
    {
        return SecondFactor::TYPE_PUBLIC_KEY;
    }

    public function getIdentifier(): string
    {
        return 'webauthn';
    }

    public function getLabelTranslationKey(): string
    {
        return 'method.webauthn.label';
    }

    public function getDescriptionTranslationKey(): string
    {
        return 'method.webauthn.description';
    }

    public function getSetupActionName(): string
    {
        return 'setupWebAuthn';
    }
}
