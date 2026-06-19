<?php

namespace Sandstorm\NeosTwoFactorAuthentication\Service;

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Persistence\PersistenceManagerInterface;
use Psr\Log\LoggerInterface;
use Sandstorm\NeosTwoFactorAuthentication\Domain\Model\SecondFactor;
use Sandstorm\NeosTwoFactorAuthentication\Domain\Repository\SecondFactorRepository;
use Webauthn\PublicKeyCredentialSource;
use Webauthn\PublicKeyCredentialSourceRepository;
use Webauthn\PublicKeyCredentialUserEntity;

/**
 * Adapter implementing the web-auth library's credential repository on top of
 * our generic {@see SecondFactorRepository}.
 *
 * Each row of TYPE_PUBLIC_KEY stores a JSON-serialized PublicKeyCredentialSource
 * in the `secret` column.
 *
 * @Flow\Scope("singleton")
 */
class PublicKeyCredentialSourceRepositoryAdapter implements PublicKeyCredentialSourceRepository
{
    /**
     * @Flow\Inject
     * @var SecondFactorRepository
     */
    protected $secondFactorRepository;

    /**
     * @Flow\Inject(name="Neos.Flow:SecurityLogger")
     * @var LoggerInterface
     */
    protected $securityLogger;

    /**
     * @Flow\Inject
     * @var PersistenceManagerInterface
     */
    protected $persistenceManager;

    public function findOneByCredentialId(string $publicKeyCredentialId): ?PublicKeyCredentialSource
    {
        foreach ($this->iterateAllWebAuthnFactors() as [$factor, $source]) {
            if ($source->getPublicKeyCredentialId() === $publicKeyCredentialId) {
                return $source;
            }
        }
        return null;
    }

    /**
     * @return PublicKeyCredentialSource[]
     */
    public function findAllForUserEntity(PublicKeyCredentialUserEntity $publicKeyCredentialUserEntity): array
    {
        $userHandle = $publicKeyCredentialUserEntity->getId();
        $sources = [];
        foreach ($this->iterateAllWebAuthnFactors() as [$factor, $source]) {
            if ($source->getUserHandle() === $userHandle) {
                $sources[] = $source;
            }
        }
        return $sources;
    }

    public function saveCredentialSource(PublicKeyCredentialSource $publicKeyCredentialSource): void
    {
        // Update path: find the existing factor for this credential and bump the counter.
        foreach ($this->iterateAllWebAuthnFactors() as [$factor, $source]) {
            if ($source->getPublicKeyCredentialId() === $publicKeyCredentialSource->getPublicKeyCredentialId()) {
                $factor->setCredentialData($publicKeyCredentialSource->jsonSerialize());
                $this->secondFactorRepository->update($factor);
                return;
            }
        }
        // No existing factor — initial registration is handled explicitly by
        // WebAuthnService::persistNewCredential() so we ignore this branch.
    }

    /**
     * @return \Generator<array{0: SecondFactor, 1: PublicKeyCredentialSource}>
     */
    private function iterateAllWebAuthnFactors(): \Generator
    {
        foreach ($this->secondFactorRepository->findAllByType(SecondFactor::TYPE_PUBLIC_KEY) as $factor) {
            try {
                $source = PublicKeyCredentialSource::createFromArray($factor->getCredentialData());
            } catch (\Throwable $exception) {
                // A single corrupt/truncated credential row must not break the lookup for all
                // other users. Skip it and log so the broken factor can be investigated.
                $this->securityLogger->warning(
                    sprintf(
                        'Skipping WebAuthn second factor with corrupt credential data (id %s): %s',
                        $this->persistenceManager->getIdentifierByObject($factor),
                        $exception->getMessage()
                    )
                );
                continue;
            }
            yield [$factor, $source];
        }
    }
}
