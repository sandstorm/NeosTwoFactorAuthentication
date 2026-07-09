<?php

namespace Sandstorm\NeosTwoFactorAuthentication\Service;

use Neos\Flow\Annotations as Flow;
use Symfony\Component\Serializer\SerializerInterface;
use Webauthn\AttestationStatement\AttestationStatementSupportManager;
use Webauthn\AttestationStatement\FidoU2FAttestationStatementSupport;
use Webauthn\AttestationStatement\NoneAttestationStatementSupport;
use Webauthn\Denormalizer\WebauthnSerializerFactory;

/**
 * Builds (and caches) the Symfony serializer that web-auth/webauthn-lib v5 uses to load and
 * persist WebAuthn value objects — credential sources, options and browser responses.
 *
 * In v4 this was done by the now-removed PublicKeyCredentialLoader and by
 * PublicKeyCredentialSource::createFromArray()/jsonSerialize(). v5 moved all (de)serialization to
 * the Symfony serializer assembled by {@see WebauthnSerializerFactory}. Centralising it here keeps
 * webauthn-lib serialization in one place and lets it be reused by {@see WebAuthnService} and
 * {@see PublicKeyCredentialSourceRepositoryAdapter}.
 *
 * The attestation-statement support (None + FidoU2F) must match what the ceremony validators
 * accept, so that attestation objects serialize/deserialize consistently.
 *
 * @Flow\Scope("singleton")
 */
class WebAuthnSerializerProvider
{
    private ?SerializerInterface $serializer = null;

    public function getSerializer(): SerializerInterface
    {
        if ($this->serializer === null) {
            $attestationStatementSupportManager = AttestationStatementSupportManager::create();
            $attestationStatementSupportManager->add(NoneAttestationStatementSupport::create());
            // FidoU2F is needed for U2F-only authenticators registered via the browser's
            // U2F-compat fallback (see WebAuthnService); their attestation must round-trip too.
            $attestationStatementSupportManager->add(FidoU2FAttestationStatementSupport::create());

            $this->serializer = (new WebauthnSerializerFactory($attestationStatementSupportManager))->create();
        }
        return $this->serializer;
    }
}
