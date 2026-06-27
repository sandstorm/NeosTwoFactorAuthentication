<?php

namespace Sandstorm\NeosTwoFactorAuthentication\Tests\Unit\Service;

use Neos\Flow\Persistence\PersistenceManagerInterface;
use Neos\Flow\Security\Account;
use Neos\Flow\Tests\UnitTestCase;
use Sandstorm\NeosTwoFactorAuthentication\Service\WebAuthnService;

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
}
