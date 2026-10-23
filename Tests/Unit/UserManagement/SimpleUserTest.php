<?php

namespace Sandstorm\NeosTwoFactorAuthentication\Tests\Unit\UserManagement;

use Neos\Flow\Tests\UnitTestCase;
use Sandstorm\NeosTwoFactorAuthentication\UserManagement\Domain\Dto\SimpleAccount;
use Sandstorm\NeosTwoFactorAuthentication\UserManagement\Domain\Dto\SimpleUser;

class SimpleUserTest extends UnitTestCase
{
    /**
     * @test
     */
    public function searchMatchesNameEmailAndUsernameCaseInsensitive(): void
    {
        $user = $this->user([$this->account('jdoe', true)]);

        self::assertTrue($user->matchesSearchTerm(''));
        self::assertTrue($user->matchesSearchTerm('DOE'));
        self::assertTrue($user->matchesSearchTerm('example.com'));
        self::assertTrue($user->matchesSearchTerm('jdo'));
        self::assertFalse($user->matchesSearchTerm('somebody else'));
    }

    /**
     * @test
     */
    public function userIsActiveIfOneAccountIsActive(): void
    {
        self::assertTrue($this->user([$this->account('a', false), $this->account('b', true)])->isActive());
        self::assertFalse($this->user([$this->account('a', false)])->isActive());
    }

    /**
     * @test
     */
    public function rolesAndSecondFactorsAreAggregatedOverAccounts(): void
    {
        $user = $this->user([
            $this->account('a', true, ['Neos.Neos:Editor'], 1),
            $this->account('b', true, ['Neos.Neos:Editor', 'Neos.Neos:Administrator'], 2),
        ]);

        self::assertSame(['Neos.Neos:Editor', 'Neos.Neos:Administrator'], $user->getRoleIdentifiers());
        self::assertSame(3, $user->getSecondFactorCount());
    }

    /**
     * @test
     */
    public function lastLoginIsTheLatestOfAllAccounts(): void
    {
        $user = $this->user([
            $this->account('a', true, [], 0, new \DateTime('2026-01-01')),
            $this->account('b', true, [], 0, new \DateTime('2026-03-01')),
            $this->account('c', true),
        ]);

        self::assertEquals(new \DateTime('2026-03-01'), $user->getLastSuccessfulAuthentication());
    }

    /**
     * @param list<SimpleAccount> $accounts
     */
    private function user(array $accounts): SimpleUser
    {
        return new SimpleUser('user-id', 'Jane', 'Doe', 'Jane Doe', 'jane@example.com', true, $accounts);
    }

    /**
     * @param list<string> $roles
     */
    private function account(string $identifier, bool $active, array $roles = [], int $secondFactors = 0, ?\DateTime $lastLogin = null): SimpleAccount
    {
        return new SimpleAccount('id-' . $identifier, $identifier, 'Neos.Neos:Backend', $roles, $active, null, $lastLogin, $secondFactors);
    }
}
