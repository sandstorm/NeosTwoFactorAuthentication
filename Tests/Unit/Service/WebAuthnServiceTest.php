<?php

namespace Sandstorm\NeosTwoFactorAuthentication\Tests\Unit\Service;

use Neos\Flow\Persistence\PersistenceManagerInterface;
use Neos\Flow\Security\Account;
use Neos\Flow\Tests\UnitTestCase;
use Sandstorm\NeosTwoFactorAuthentication\Service\PublicKeyCredentialSourceRepositoryAdapter;
use Sandstorm\NeosTwoFactorAuthentication\Service\WebAuthnService;
use Webauthn\AuthenticatorSelectionCriteria;

/**
 * Unit tests for the security-critical mapping from a passkey user handle to a Neos backend
 * account, used by passwordless login. This is the guard that ensures only Neos backend
 * accounts can authenticate through the usernameless passkey path.
 */
class WebAuthnServiceTest extends UnitTestCase
{
    private WebAuthnService $service;

    /**
     * @var PersistenceManagerInterface&\PHPUnit\Framework\MockObject\MockObject
     */
    private $persistenceManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new WebAuthnService();
        $this->persistenceManager = $this->createMock(PersistenceManagerInterface::class);
        $this->inject($this->service, 'persistenceManager', $this->persistenceManager);
    }

    /**
     * @test
     */
    public function resolvesBackendAccountByUserHandle(): void
    {
        $account = $this->createMock(Account::class);
        $account->method('getAuthenticationProviderName')->willReturn('Neos.Neos:Backend');
        $this->persistenceManager
            ->method('getObjectByIdentifier')
            ->with('the-user-handle', Account::class)
            ->willReturn($account);

        self::assertSame($account, $this->service->resolveBackendAccountByUserHandle('the-user-handle'));
    }

    /**
     * @test
     */
    public function throwsWhenNoAccountExistsForUserHandle(): void
    {
        $this->persistenceManager->method('getObjectByIdentifier')->willReturn(null);

        $this->expectException(\RuntimeException::class);
        $this->service->resolveBackendAccountByUserHandle('unknown-handle');
    }

    /**
     * @test
     */
    public function throwsWhenAccountIsNotANeosBackendAccount(): void
    {
        $account = $this->createMock(Account::class);
        $account->method('getAuthenticationProviderName')->willReturn('Some.Other:Provider');
        $this->persistenceManager->method('getObjectByIdentifier')->willReturn($account);

        $this->expectException(\RuntimeException::class);
        $this->service->resolveBackendAccountByUserHandle('the-user-handle');
    }

    /**
     * Configure the service for registration-option tests: inject the WebAuthn settings and a
     * credential-source repository that reports no already-registered credentials, and return a
     * minimal backend account.
     */
    private function configureRegistrationService(bool $passwordlessLoginEnabled, string $userVerification = 'discouraged'): Account
    {
        $this->inject($this->service, 'relyingPartyName', 'Neos Backend');
        $this->inject($this->service, 'relyingPartyId', null);
        $this->inject($this->service, 'userVerification', $userVerification);
        $this->inject($this->service, 'timeoutMs', 60000);
        $this->inject($this->service, 'passwordlessLoginEnabled', $passwordlessLoginEnabled);

        $credentialSourceRepository = $this->createMock(PublicKeyCredentialSourceRepositoryAdapter::class);
        $credentialSourceRepository->method('findAllForUserEntity')->willReturn([]);
        $this->inject($this->service, 'credentialSourceRepository', $credentialSourceRepository);

        $account = $this->createMock(Account::class);
        $account->method('getAccountIdentifier')->willReturn('admin');
        $this->persistenceManager->method('getIdentifierByObject')->willReturn('the-user-handle');

        return $account;
    }

    /**
     * The 2nd-factor path must stay touch-friendly even while passwordless login is enabled:
     * registering with discoverable=false must NOT demand a resident key or user verification, so
     * a no-PIN security key (touch only) can register. This is the regression that produced the
     * "User authentication required." 400.
     *
     * @test
     */
    public function registersTouchOnlySecondFactorWhilePasswordlessEnabled(): void
    {
        $account = $this->configureRegistrationService(passwordlessLoginEnabled: true, userVerification: 'discouraged');

        $options = $this->service->createRegistrationOptions($account, 'localhost', discoverable: false);

        self::assertNotSame(
            AuthenticatorSelectionCriteria::RESIDENT_KEY_REQUIREMENT_REQUIRED,
            $options->authenticatorSelection?->residentKey,
            'A 2nd-factor registration must not require a resident key.'
        );
        self::assertSame(
            'discouraged',
            $options->authenticatorSelection?->userVerification,
            'A 2nd-factor registration must use the configured user-verification level, not force it.'
        );
    }

    /**
     * The passkey path is unchanged: opting into a discoverable passkey while passwordless login
     * is enabled forces a resident key and user verification (a verified passkey is itself a full
     * multi-factor login).
     *
     * @test
     */
    public function registersDiscoverablePasskeyWhilePasswordlessEnabled(): void
    {
        $account = $this->configureRegistrationService(passwordlessLoginEnabled: true, userVerification: 'discouraged');

        $options = $this->service->createRegistrationOptions($account, 'localhost', discoverable: true);

        self::assertSame(
            AuthenticatorSelectionCriteria::RESIDENT_KEY_REQUIREMENT_REQUIRED,
            $options->authenticatorSelection?->residentKey,
        );
        self::assertSame(
            AuthenticatorSelectionCriteria::USER_VERIFICATION_REQUIREMENT_REQUIRED,
            $options->authenticatorSelection?->userVerification,
        );
    }

    /**
     * Guard: a discoverable passkey can never be minted while passwordless login is disabled, even
     * if the client asks for one — the credential stays a plain, configured-UV 2nd factor.
     *
     * @test
     */
    public function ignoresDiscoverableRequestWhilePasswordlessDisabled(): void
    {
        $account = $this->configureRegistrationService(passwordlessLoginEnabled: false, userVerification: 'discouraged');

        $options = $this->service->createRegistrationOptions($account, 'localhost', discoverable: true);

        self::assertNotSame(
            AuthenticatorSelectionCriteria::RESIDENT_KEY_REQUIREMENT_REQUIRED,
            $options->authenticatorSelection?->residentKey,
        );
        self::assertSame('discouraged', $options->authenticatorSelection?->userVerification);
    }

    /**
     * The stored `discoverable` flag is derived from the registration options at verify time
     * (single source of truth), so it round-trips with the choice made at options time: a passkey
     * registration is discoverable, a 2nd-factor registration is not.
     *
     * @test
     */
    public function discoverabilityIsDerivedFromTheRegistrationOptions(): void
    {
        $account = $this->configureRegistrationService(passwordlessLoginEnabled: true);

        $passkeyOptions = $this->service->createRegistrationOptions($account, 'localhost', discoverable: true);
        $secondFactorOptions = $this->service->createRegistrationOptions($account, 'localhost', discoverable: false);

        self::assertTrue($this->service->isDiscoverableRegistration($passkeyOptions));
        self::assertFalse($this->service->isDiscoverableRegistration($secondFactorOptions));
    }
}
