<?php

namespace Sandstorm\NeosTwoFactorAuthentication\Service;

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Persistence\PersistenceManagerInterface;
use Psr\Log\LoggerInterface;
use Sandstorm\NeosTwoFactorAuthentication\Domain\Model\SecondFactor;
use Sandstorm\NeosTwoFactorAuthentication\Domain\Repository\SecondFactorRepository;
use Webauthn\CredentialRecord;
use Webauthn\PublicKeyCredentialUserEntity;

/**
 * Repository for stored WebAuthn credentials, backed by our generic {@see SecondFactorRepository}.
 *
 * Each row of TYPE_PUBLIC_KEY stores a JSON-serialized credential (written by
 * {@see WebAuthnService}) in the `secret` column. Under web-auth/webauthn-lib v5 these are read
 * back via the Symfony serializer as {@see CredentialRecord} objects.
 *
 * In v4 this class implemented the library's `PublicKeyCredentialSourceRepository` interface and the
 * ceremony validators called it back to look up and save credentials. v5 removed that interface —
 * the validators no longer touch a repository — so this is now plain application code that
 * {@see WebAuthnService} drives directly (look up before `check()`, save the counter bump after).
 *
 * @Flow\Scope("singleton")
 */
class PublicKeyCredentialSourceRepositoryAdapter
{
    /**
     * @Flow\Inject
     * @var WebAuthnSerializerProvider
     */
    protected $serializerProvider;

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

    public function findOneByCredentialId(string $publicKeyCredentialId): ?CredentialRecord
    {
        foreach ($this->iterateAllWebAuthnFactors() as [$factor, $source]) {
            if ($source->publicKeyCredentialId === $publicKeyCredentialId) {
                return $source;
            }
        }
        return null;
    }

    /**
     * @return CredentialRecord[]
     */
    public function findAllForUserEntity(PublicKeyCredentialUserEntity $publicKeyCredentialUserEntity): array
    {
        $userHandle = $publicKeyCredentialUserEntity->id;
        $sources = [];
        foreach ($this->iterateAllWebAuthnFactors() as [$factor, $source]) {
            if ($source->userHandle === $userHandle) {
                $sources[] = $source;
            }
        }
        return $sources;
    }

    /**
     * Persist an updated credential (e.g. the counter bump returned by the assertion ceremony).
     * In v5 the library no longer saves credentials itself, so {@see WebAuthnService} calls this
     * after a successful `check()`.
     */
    public function saveCredential(CredentialRecord $credentialRecord): void
    {
        foreach ($this->iterateAllWebAuthnFactors() as [$factor, $source]) {
            if ($source->publicKeyCredentialId === $credentialRecord->publicKeyCredentialId) {
                $factor->setSecret($this->serializerProvider->getSerializer()->serialize($credentialRecord, 'json'));
                $this->secondFactorRepository->update($factor);
                return;
            }
        }
        // No existing factor — initial registration is handled explicitly by
        // WebAuthnService::verifyAndPersistRegistration() so we ignore this branch.
    }

    /**
     * @return \Generator<array{0: SecondFactor, 1: CredentialRecord}>
     */
    private function iterateAllWebAuthnFactors(): \Generator
    {
        $serializer = $this->serializerProvider->getSerializer();
        foreach ($this->secondFactorRepository->findAllByType(SecondFactor::TYPE_PUBLIC_KEY) as $factor) {
            try {
                $source = $serializer->deserialize($factor->getSecret(), CredentialRecord::class, 'json');
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
