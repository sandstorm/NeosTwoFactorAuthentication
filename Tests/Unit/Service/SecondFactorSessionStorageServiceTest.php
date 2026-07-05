<?php

namespace Sandstorm\NeosTwoFactorAuthentication\Tests\Unit\Service;

use Neos\Flow\Session\SessionInterface;
use Neos\Flow\Session\SessionManagerInterface;
use Neos\Flow\Tests\UnitTestCase;
use Sandstorm\NeosTwoFactorAuthentication\Domain\AuthenticationStatus;
use Sandstorm\NeosTwoFactorAuthentication\Service\SecondFactorSessionStorageService;

/**
 * Unit tests for the package's session storage.
 *
 * Regression coverage for the passwordless-ceremony bug: a logged-out visitor who starts a
 * passwordless login writes `webAuthnPasswordlessOptions` into the shared session container
 * (via {@see SecondFactorSessionStorageService::putValue()}) but never an authentication status.
 * If that ceremony is abandoned, a later regular login must not trip over the half-populated
 * container.
 *
 * The tests back the mocked {@see SessionInterface} with a real in-memory array so they exercise
 * observable behavior (write options -> read status) rather than mock call sequences.
 */
class SecondFactorSessionStorageServiceTest extends UnitTestCase
{
    private SecondFactorSessionStorageService $service;

    /**
     * The session's data store, keyed exactly like Flow's real session
     * (top-level key -> value), so the whole package container lives under SESSION_OBJECT_ID.
     *
     * @var array<string, mixed>
     */
    private array $sessionData;

    private bool $sessionStarted;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sessionData = [];
        $this->sessionStarted = true;

        $session = $this->createMock(SessionInterface::class);
        $session->method('isStarted')->willReturnCallback(fn() => $this->sessionStarted);
        $session->method('start')->willReturnCallback(function () {
            $this->sessionStarted = true;
        });
        $session->method('getData')->willReturnCallback(fn(string $key) => $this->sessionData[$key] ?? null);
        $session->method('hasKey')->willReturnCallback(fn(string $key) => array_key_exists($key, $this->sessionData));
        $session->method('putData')->willReturnCallback(function (string $key, mixed $data) {
            $this->sessionData[$key] = $data;
        });

        $sessionManager = $this->createMock(SessionManagerInterface::class);
        $sessionManager->method('getCurrentSession')->willReturn($session);

        $this->service = new SecondFactorSessionStorageService();
        $this->inject($this->service, 'sessionManager', $sessionManager);
    }

    /**
     * The container exists (it holds abandoned passwordless options) but carries no
     * authentication status. Reading the status must yield AUTHENTICATION_NEEDED, not crash
     * on the `string` return type with a `null`.
     *
     * @test
     */
    public function getAuthenticationStatusReturnsAuthenticationNeededWhenStatusKeyMissing(): void
    {
        $this->sessionData[SecondFactorSessionStorageService::SESSION_OBJECT_ID] = [
            SecondFactorSessionStorageService::SESSION_OBJECT_WEBAUTHN_PASSWORDLESS_OPTIONS => '{"challenge":"x"}',
        ];

        self::assertSame(
            AuthenticationStatus::AUTHENTICATION_NEEDED,
            $this->service->getAuthenticationStatus()
        );
    }

    /**
     * The real abandoned-ceremony sequence: a passwordless login seeds the container with only its
     * options, then initialization runs on the next (regular) login. Initialization must recognise
     * the missing status and set it — keying off the status key, not merely the container's presence.
     *
     * @test
     */
    public function initializeSetsStatusWhenContainerExistsWithoutStatus(): void
    {
        $this->service->startSessionIfNotStarted();
        $this->service->putValue(
            SecondFactorSessionStorageService::SESSION_OBJECT_WEBAUTHN_PASSWORDLESS_OPTIONS,
            '{"challenge":"x"}'
        );

        $this->service->initializeTwoFactorSessionObject();

        $container = $this->sessionData[SecondFactorSessionStorageService::SESSION_OBJECT_ID];
        self::assertSame(
            AuthenticationStatus::AUTHENTICATION_NEEDED,
            $container[SecondFactorSessionStorageService::SESSION_OBJECT_AUTH_STATUS] ?? null,
            'initialization must set the authentication status when it is missing'
        );
        self::assertSame(
            '{"challenge":"x"}',
            $container[SecondFactorSessionStorageService::SESSION_OBJECT_WEBAUTHN_PASSWORDLESS_OPTIONS] ?? null,
            'initialization must preserve the already-stored passwordless options'
        );
    }

    /**
     * Initialization must be idempotent: a completed login (status AUTHENTICATED) must never be
     * downgraded back to AUTHENTICATION_NEEDED by a subsequent request re-running initialization.
     *
     * @test
     */
    public function initializeDoesNotOverwriteExistingAuthenticatedStatus(): void
    {
        $this->service->setAuthenticationStatus(AuthenticationStatus::AUTHENTICATED);

        $this->service->initializeTwoFactorSessionObject();

        self::assertSame(AuthenticationStatus::AUTHENTICATED, $this->service->getAuthenticationStatus());
    }

    /**
     * The original happy path: a fresh session with no package container gets initialized to
     * AUTHENTICATION_NEEDED.
     *
     * @test
     */
    public function initializeInitialisesFreshSessionToNeeded(): void
    {
        $this->service->initializeTwoFactorSessionObject();

        self::assertSame(AuthenticationStatus::AUTHENTICATION_NEEDED, $this->service->getAuthenticationStatus());
    }
}
