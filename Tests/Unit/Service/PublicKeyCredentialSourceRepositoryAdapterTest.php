<?php

namespace Sandstorm\NeosTwoFactorAuthentication\Tests\Unit\Service;

use Neos\Flow\Persistence\PersistenceManagerInterface;
use Neos\Flow\Tests\UnitTestCase;
use ParagonIE\ConstantTime\Base64UrlSafe;
use Psr\Log\LoggerInterface;
use Sandstorm\NeosTwoFactorAuthentication\Domain\Model\SecondFactor;
use Sandstorm\NeosTwoFactorAuthentication\Domain\Repository\SecondFactorRepository;
use Sandstorm\NeosTwoFactorAuthentication\Service\PublicKeyCredentialSourceRepositoryAdapter;
use Sandstorm\NeosTwoFactorAuthentication\Service\WebAuthnSerializerProvider;
use Webauthn\CredentialRecord;
use Webauthn\PublicKeyCredentialUserEntity;

/**
 * The adapter reads WebAuthn credentials stored (as v4/v5 JSON) in SecondFactor rows and exposes
 * them as webauthn-lib CredentialRecords. These tests exercise the lookup behaviour through its
 * public interface against real stored JSON, without the Flow persistence layer.
 */
class PublicKeyCredentialSourceRepositoryAdapterTest extends UnitTestCase
{
    private const USER_HANDLE = '40b985a5-da1f-45b0-8864-321bdd63a918';

    private const YUBIKEY_SECRET = '{"publicKeyCredentialId":"UfGOMXF0z46jrELBGylyN9aXUgs2OgkvY8WsLKefnvufoyb7fjw_2DpS81SiK8FT-F6X_y_9xC8WeyKOGrSnMw","type":"public-key","transports":[],"attestationType":"none","trustPath":{"type":"Webauthn\\\\TrustPath\\\\EmptyTrustPath"},"aaguid":"00000000-0000-0000-0000-000000000000","credentialPublicKey":"pQECAyYgASFYIE6HyqPfnnEnSfmdyNugRBUSyA1J30UFz5IaxLE6z7zHIlggYO5AtmknrOWx6bCwnjTQERc6NJm09LjrIbQrX-z0PBg","userHandle":"NDBiOTg1YTUtZGExZi00NWIwLTg4NjQtMzIxYmRkNjNhOTE4","counter":227,"backupEligible":false,"backupStatus":false}';
    private const YUBIKEY_CRED_ID = 'UfGOMXF0z46jrELBGylyN9aXUgs2OgkvY8WsLKefnvufoyb7fjw_2DpS81SiK8FT-F6X_y_9xC8WeyKOGrSnMw';

    private const PASSKEY_SECRET = '{"publicKeyCredentialId":"oI0Z1UcrXWmByWp-5ZQCrr1ETW81YDU6ptr26TqIAYU","type":"public-key","transports":[],"attestationType":"none","trustPath":{"type":"Webauthn\\\\TrustPath\\\\EmptyTrustPath"},"aaguid":"adce0002-35bc-c60a-648b-0b25f1f05503","credentialPublicKey":"pQECAyYgASFYIJeEuCpbvN5moHx9FI5r5msfOxxS54iXIerHSK4m073yIlggRhXJRyGLKYKya6Ba-aG-JvtFYrKKkVT5zekHf3YxlZQ","userHandle":"NDBiOTg1YTUtZGExZi00NWIwLTg4NjQtMzIxYmRkNjNhOTE4","counter":0,"backupEligible":false,"backupStatus":false,"uvInitialized":true}';

    private PublicKeyCredentialSourceRepositoryAdapter $adapter;

    /** @var SecondFactorRepository&\PHPUnit\Framework\MockObject\MockObject */
    private $secondFactorRepository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->adapter = new PublicKeyCredentialSourceRepositoryAdapter();
        $this->secondFactorRepository = $this->createMock(SecondFactorRepository::class);
        $this->inject($this->adapter, 'serializerProvider', new WebAuthnSerializerProvider());
        $this->inject($this->adapter, 'secondFactorRepository', $this->secondFactorRepository);
        $this->inject($this->adapter, 'securityLogger', $this->createMock(LoggerInterface::class));
        $this->inject($this->adapter, 'persistenceManager', $this->createMock(PersistenceManagerInterface::class));
    }

    private function factorWithSecret(string $secret): SecondFactor
    {
        $factor = new SecondFactor();
        $factor->setSecret($secret);
        $factor->setType(SecondFactor::TYPE_PUBLIC_KEY);
        return $factor;
    }

    /**
     * @test
     */
    public function findOneByCredentialIdReturnsTheStoredCredential(): void
    {
        $this->secondFactorRepository->method('findAllByType')
            ->with(SecondFactor::TYPE_PUBLIC_KEY)
            ->willReturn([$this->factorWithSecret(self::YUBIKEY_SECRET)]);

        $rawId = Base64UrlSafe::decode(self::YUBIKEY_CRED_ID);
        $record = $this->adapter->findOneByCredentialId($rawId);

        self::assertInstanceOf(CredentialRecord::class, $record);
        self::assertSame($rawId, $record->publicKeyCredentialId);
    }

    /**
     * @test
     */
    public function findOneByCredentialIdReturnsNullForUnknownId(): void
    {
        $this->secondFactorRepository->method('findAllByType')
            ->willReturn([$this->factorWithSecret(self::YUBIKEY_SECRET)]);

        self::assertNull($this->adapter->findOneByCredentialId('no-such-credential-id'));
    }

    /**
     * @test
     */
    public function findAllForUserEntityReturnsOnlyCredentialsWithMatchingUserHandle(): void
    {
        $this->secondFactorRepository->method('findAllByType')->willReturn([
            $this->factorWithSecret(self::YUBIKEY_SECRET),
            $this->factorWithSecret(self::PASSKEY_SECRET),
        ]);

        $matching = $this->adapter->findAllForUserEntity(
            PublicKeyCredentialUserEntity::create('admin', self::USER_HANDLE, 'admin')
        );
        self::assertCount(2, $matching);

        $none = $this->adapter->findAllForUserEntity(
            PublicKeyCredentialUserEntity::create('other', 'a-different-user-handle', 'other')
        );
        self::assertCount(0, $none);
    }
}
