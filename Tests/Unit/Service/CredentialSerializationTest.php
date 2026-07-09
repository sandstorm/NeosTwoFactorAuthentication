<?php

namespace Sandstorm\NeosTwoFactorAuthentication\Tests\Unit\Service;

use Neos\Flow\Tests\UnitTestCase;
use ParagonIE\ConstantTime\Base64UrlSafe;
use Sandstorm\NeosTwoFactorAuthentication\Service\WebAuthnSerializerProvider;
use Webauthn\CredentialRecord;

/**
 * Backward-compatibility guard for the web-auth/webauthn-lib v4 -> v5 upgrade.
 *
 * Existing WebAuthn second factors were serialized to the `secret` column by v4's
 * PublicKeyCredentialSource::jsonSerialize(). Under v5 they are read back through the Symfony
 * serializer (a different code path). These tests feed real, captured v4-serialized credential
 * sources through the v5 serializer and assert that every field round-trips, so no already
 * registered key is silently locked out after the upgrade.
 *
 * The fixtures are real captures (with the account-identifying `userHandle` replaced by a dummy
 * UUID) from docs/20260709_publickey_fixtures_neostwofactorauth2-csv.csv.
 */
class CredentialSerializationTest extends UnitTestCase
{
    private const DUMMY_USER_HANDLE = '40b985a5-da1f-45b0-8864-321bdd63a918';

    /**
     * @return array<string, array{secret: string, credentialId: string, counter: int}>
     */
    public static function v4CredentialSourceProvider(): array
    {
        return [
            // Real YubiKey registered as a plain 2nd factor: none attestation, empty trust path,
            // zero aaguid, no uvInitialized field, and a non-zero counter from repeated use.
            'used security key (2nd factor, counter 227)' => [
                'secret' => '{"publicKeyCredentialId":"UfGOMXF0z46jrELBGylyN9aXUgs2OgkvY8WsLKefnvufoyb7fjw_2DpS81SiK8FT-F6X_y_9xC8WeyKOGrSnMw","type":"public-key","transports":[],"attestationType":"none","trustPath":{"type":"Webauthn\\\\TrustPath\\\\EmptyTrustPath"},"aaguid":"00000000-0000-0000-0000-000000000000","credentialPublicKey":"pQECAyYgASFYIE6HyqPfnnEnSfmdyNugRBUSyA1J30UFz5IaxLE6z7zHIlggYO5AtmknrOWx6bCwnjTQERc6NJm09LjrIbQrX-z0PBg","userHandle":"NDBiOTg1YTUtZGExZi00NWIwLTg4NjQtMzIxYmRkNjNhOTE4","counter":227,"backupEligible":false,"backupStatus":false}',
                'credentialId' => 'UfGOMXF0z46jrELBGylyN9aXUgs2OgkvY8WsLKefnvufoyb7fjw_2DpS81SiK8FT-F6X_y_9xC8WeyKOGrSnMw',
                'counter' => 227,
            ],
            // Fresh 2nd-factor registration: counter 0 and an explicit uvInitialized:false.
            'fresh security key (2nd factor, counter 0)' => [
                'secret' => '{"publicKeyCredentialId":"s_DI7_9m4sC5E1sVGCTC0fibI_qdPNTW1DeHA_uT_WQ6ywKuZ5H4rLiILvOEhQjoMLu_H9PIfLaDvnYycEdIhg","type":"public-key","transports":[],"attestationType":"none","trustPath":{"type":"Webauthn\\\\TrustPath\\\\EmptyTrustPath"},"aaguid":"00000000-0000-0000-0000-000000000000","credentialPublicKey":"pQECAyYgASFYIEEcAEv5PVrOo83R2FxnEloPMp8VUQ_CP4WUMALr8T32IlggitTdQTvOegyer6z3tf35_sgGvNcmzFePoM7a-xj2kWo","userHandle":"NDBiOTg1YTUtZGExZi00NWIwLTg4NjQtMzIxYmRkNjNhOTE4","counter":0,"backupEligible":false,"backupStatus":false,"uvInitialized":false}',
                'credentialId' => 's_DI7_9m4sC5E1sVGCTC0fibI_qdPNTW1DeHA_uT_WQ6ywKuZ5H4rLiILvOEhQjoMLu_H9PIfLaDvnYycEdIhg',
                'counter' => 0,
            ],
            // Resident, user-verified passkey (discoverable) from a platform authenticator:
            // real aaguid and uvInitialized:true — the passwordless-login shape.
            'resident passkey (discoverable, uvInitialized)' => [
                'secret' => '{"publicKeyCredentialId":"oI0Z1UcrXWmByWp-5ZQCrr1ETW81YDU6ptr26TqIAYU","type":"public-key","transports":[],"attestationType":"none","trustPath":{"type":"Webauthn\\\\TrustPath\\\\EmptyTrustPath"},"aaguid":"adce0002-35bc-c60a-648b-0b25f1f05503","credentialPublicKey":"pQECAyYgASFYIJeEuCpbvN5moHx9FI5r5msfOxxS54iXIerHSK4m073yIlggRhXJRyGLKYKya6Ba-aG-JvtFYrKKkVT5zekHf3YxlZQ","userHandle":"NDBiOTg1YTUtZGExZi00NWIwLTg4NjQtMzIxYmRkNjNhOTE4","counter":0,"backupEligible":false,"backupStatus":false,"uvInitialized":true}',
                'credentialId' => 'oI0Z1UcrXWmByWp-5ZQCrr1ETW81YDU6ptr26TqIAYU',
                'counter' => 0,
            ],
        ];
    }

    /**
     * @test
     * @dataProvider v4CredentialSourceProvider
     */
    public function readsV4SerializedCredentialSourceUnderV5(string $secret, string $credentialId, int $counter): void
    {
        $serializer = (new WebAuthnSerializerProvider())->getSerializer();

        $source = $serializer->deserialize($secret, CredentialRecord::class, 'json');

        self::assertInstanceOf(CredentialRecord::class, $source);
        self::assertSame($credentialId, Base64UrlSafe::encodeUnpadded($source->publicKeyCredentialId));
        self::assertSame(self::DUMMY_USER_HANDLE, $source->userHandle);
        self::assertSame($counter, $source->counter);
    }

    /**
     * The write path (used when persisting the counter bump after an assertion, and when storing a
     * freshly registered credential) must produce JSON that the same serializer can read back
     * unchanged, so a saved credential keeps working on the next login.
     *
     * @test
     * @dataProvider v4CredentialSourceProvider
     */
    public function serializedCredentialRecordRoundTrips(string $secret, string $credentialId, int $counter): void
    {
        $serializer = (new WebAuthnSerializerProvider())->getSerializer();

        $source = $serializer->deserialize($secret, CredentialRecord::class, 'json');
        $reSerialized = $serializer->serialize($source, 'json');
        $roundTripped = $serializer->deserialize($reSerialized, CredentialRecord::class, 'json');

        self::assertSame($credentialId, Base64UrlSafe::encodeUnpadded($roundTripped->publicKeyCredentialId));
        self::assertSame(self::DUMMY_USER_HANDLE, $roundTripped->userHandle);
        self::assertSame($counter, $roundTripped->counter);
        self::assertSame($source->credentialPublicKey, $roundTripped->credentialPublicKey);
    }
}
