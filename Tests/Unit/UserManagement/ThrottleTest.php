<?php

namespace Sandstorm\NeosTwoFactorAuthentication\Tests\Unit\UserManagement;

use Neos\Flow\Tests\UnitTestCase;
use Sandstorm\NeosTwoFactorAuthentication\UserManagement\Application\Throttle;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;
use Symfony\Component\RateLimiter\Storage\StorageInterface;

class ThrottleTest extends UnitTestCase
{
    private Throttle $throttle;

    protected function setUp(): void
    {
        parent::setUp();
        $this->throttle = new class () extends Throttle {
            private InMemoryStorage $storage;

            protected function createStorage(): StorageInterface
            {
                return $this->storage ??= new InMemoryStorage();
            }

            protected function getLockFactory(): LockFactory
            {
                return new LockFactory(new InMemoryStore());
            }
        };
    }

    /**
     * @test
     */
    public function attemptsWithinTheLimitAreAllowed(): void
    {
        self::assertTrue($this->throttle->attempt('jane', 3, 'PT15M'));
        self::assertTrue($this->throttle->attempt('jane', 3, 'PT15M'));
        self::assertTrue($this->throttle->attempt('jane', 3, 'PT15M'));
        self::assertFalse($this->throttle->attempt('jane', 3, 'PT15M'));
    }

    /**
     * @test
     */
    public function bucketsAreCountedSeparately(): void
    {
        self::assertTrue($this->throttle->attempt('jane', 1, 'PT15M'));
        self::assertFalse($this->throttle->attempt('jane', 1, 'PT15M'));
        self::assertTrue($this->throttle->attempt('john', 1, 'PT15M'));
    }

    /**
     * @test
     */
    public function durationIsConvertedToSeconds(): void
    {
        self::assertSame(900, Throttle::durationInSeconds('PT15M'));
        self::assertSame(86400, Throttle::durationInSeconds('P1D'));
    }
}
