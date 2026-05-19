<?php

namespace Sandstorm\NeosTwoFactorAuthentication\Domain\SecondFactorMethod;

use Sandstorm\NeosTwoFactorAuthentication\Domain\Model\SecondFactor;

/**
 * Describes one supported second-factor method (TOTP, WebAuthn, ...).
 *
 * Implementations are registered by their {@see SecondFactor} TYPE_* constant
 * and contribute the metadata controllers / templates need to render the
 * method-specific setup and challenge UI.
 */
interface SecondFactorMethodInterface
{
    /**
     * One of the {@see SecondFactor} TYPE_* constants.
     */
    public function getType(): int;

    /**
     * Short stable identifier used in URLs (e.g. "totp", "webauthn").
     */
    public function getIdentifier(): string;

    /**
     * Translation key for the method label (e.g. "method.totp.label").
     */
    public function getLabelTranslationKey(): string;

    /**
     * Translation key for the method description.
     */
    public function getDescriptionTranslationKey(): string;

    /**
     * Route action name on LoginController / BackendController that renders
     * the setup wizard for this method (e.g. "setupTotp", "setupWebAuthn").
     */
    public function getSetupActionName(): string;
}
