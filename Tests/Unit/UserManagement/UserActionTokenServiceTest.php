<?php

namespace Sandstorm\NeosTwoFactorAuthentication\Tests\Unit\UserManagement;

use Neos\Flow\Security\Account;
use Neos\Flow\Tests\UnitTestCase;
use Neos\Neos\Domain\Model\User;
use Neos\Party\Domain\Model\ElectronicAddress;
use Sandstorm\NeosTwoFactorAuthentication\UserManagement\Application\SimpleUserFinder;
use Sandstorm\NeosTwoFactorAuthentication\UserManagement\Application\UserActionTokenService;
use Sandstorm\NeosTwoFactorAuthentication\UserManagement\Domain\Model\UserActionToken;
use Sandstorm\NeosTwoFactorAuthentication\UserManagement\Domain\Repository\UserActionTokenRepository;
use Sandstorm\NeosTwoFactorAuthentication\UserManagement\Domain\UserActionType;

class UserActionTokenServiceTest extends UnitTestCase
{
    private UserActionTokenService $service;

    /**
     * In-memory token store, keyed by token hash.
     *
     * @var array<string, UserActionToken>
     */
    private array $tokens;

    private Account $account;

    private ElectronicAddress $primaryEmail;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tokens = [];
        $this->account = new Account();

        $this->primaryEmail = new ElectronicAddress();
        $this->primaryEmail->setType(SimpleUserFinder::EMAIL_ADDRESS_TYPE);
        $this->primaryEmail->setIdentifier('Jane@Example.com');

        $repository = $this->createMock(UserActionTokenRepository::class);
        $repository->method('add')->willReturnCallback(function (UserActionToken $token) {
            $this->tokens[$this->readProperty($token, 'tokenHash')] = $token;
        });
        $repository->method('findOneByTokenHash')->willReturnCallback(fn (string $hash) => $this->tokens[$hash] ?? null);
        $repository->method('findUnconsumedByAccountAndActions')->willReturnCallback(
            fn (Account $account, array $actions) => array_values(array_filter(
                $this->tokens,
                fn (UserActionToken $token) => $token->getAccount() === $account && !$token->isConsumed() && in_array($token->getAction(), $actions, true)
            ))
        );

        $user = new User();
        $finder = $this->createMock(SimpleUserFinder::class);
        $finder->method('findUserOfAccount')->willReturn($user);
        $finder->method('getPrimaryEmail')->willReturnCallback(fn () => $this->primaryEmail);

        $this->service = new UserActionTokenService();
        $this->inject($this->service, 'tokenRepository', $repository);
        $this->inject($this->service, 'simpleUserFinder', $finder);
        $this->inject($this->service, 'tokenLifetime', ['resetPassword' => 'PT24H', 'initialPassword' => 'P7D', 'verifyEmail' => 'P7D']);
    }

    /**
     * @test
     */
    public function onlyTheHashOfTheTokenIsStored(): void
    {
        $plainToken = $this->service->issue($this->account, UserActionType::RESET_PASSWORD, 'jane@example.com');

        self::assertMatchesRegularExpression('/^[A-Za-z0-9_-]{43}$/', $plainToken);
        self::assertArrayHasKey(hash('sha256', $plainToken), $this->tokens);
        self::assertArrayNotHasKey($plainToken, $this->tokens);
    }

    /**
     * @test
     */
    public function issuedTokenIsValidForItsActionOnly(): void
    {
        $plainToken = $this->service->issue($this->account, UserActionType::RESET_PASSWORD, 'jane@example.com');

        self::assertNotNull($this->service->findValid($plainToken, UserActionType::passwordActions()));
        self::assertNull($this->service->findValid($plainToken, [UserActionType::VERIFY_EMAIL]));
    }

    /**
     * @test
     */
    public function unknownOrEmptyTokenIsInvalid(): void
    {
        self::assertNull($this->service->findValid('', UserActionType::passwordActions()));
        self::assertNull($this->service->findValid('does-not-exist', UserActionType::passwordActions()));
    }

    /**
     * @test
     */
    public function consumedTokenIsInvalid(): void
    {
        $plainToken = $this->service->issue($this->account, UserActionType::RESET_PASSWORD, 'jane@example.com');
        $token = $this->service->findValid($plainToken, UserActionType::passwordActions());
        self::assertNotNull($token);

        $this->service->consume($token);

        self::assertNull($this->service->findValid($plainToken, UserActionType::passwordActions()));
    }

    /**
     * @test
     */
    public function expiredTokenIsInvalid(): void
    {
        $plainToken = $this->service->issue($this->account, UserActionType::RESET_PASSWORD, 'jane@example.com');
        $token = $this->tokens[hash('sha256', $plainToken)];
        $this->writeProperty($token, 'expiresAt', new \DateTimeImmutable('-1 second'));

        self::assertNull($this->service->findValid($plainToken, UserActionType::passwordActions()));
    }

    /**
     * @test
     */
    public function tokenIsInvalidAfterThePrimaryEmailChanged(): void
    {
        $plainToken = $this->service->issue($this->account, UserActionType::RESET_PASSWORD, 'jane@example.com');

        $this->primaryEmail->setIdentifier('jane@elsewhere.example');

        self::assertNull($this->service->findValid($plainToken, UserActionType::passwordActions()));
    }

    /**
     * @test
     */
    public function emailComparisonIsCaseInsensitiveAndTrimmed(): void
    {
        $plainToken = $this->service->issue($this->account, UserActionType::RESET_PASSWORD, ' JANE@example.com ');

        self::assertNotNull($this->service->findValid($plainToken, UserActionType::passwordActions()));
    }

    /**
     * @test
     */
    public function issuingANewTokenInvalidatesOlderTokensOfTheSameAction(): void
    {
        $first = $this->service->issue($this->account, UserActionType::RESET_PASSWORD, 'jane@example.com');
        $verify = $this->service->issue($this->account, UserActionType::VERIFY_EMAIL, 'jane@example.com');
        $second = $this->service->issue($this->account, UserActionType::RESET_PASSWORD, 'jane@example.com');

        self::assertNull($this->service->findValid($first, UserActionType::passwordActions()));
        self::assertNotNull($this->service->findValid($second, UserActionType::passwordActions()));
        self::assertNotNull($this->service->findValid($verify, [UserActionType::VERIFY_EMAIL]));
    }

    /**
     * @test
     */
    public function invalidatePasswordTokensKeepsEmailVerificationTokens(): void
    {
        $reset = $this->service->issue($this->account, UserActionType::RESET_PASSWORD, 'jane@example.com');
        $initial = $this->service->issue($this->account, UserActionType::INITIAL_PASSWORD, 'jane@example.com');
        $verify = $this->service->issue($this->account, UserActionType::VERIFY_EMAIL, 'jane@example.com');

        $this->service->invalidatePasswordTokens($this->account);

        self::assertNull($this->service->findValid($reset, UserActionType::passwordActions()));
        self::assertNull($this->service->findValid($initial, UserActionType::passwordActions()));
        self::assertNotNull($this->service->findValid($verify, [UserActionType::VERIFY_EMAIL]));
    }

    /**
     * @test
     */
    public function lifetimeComesFromSettings(): void
    {
        self::assertSame(7, $this->service->getLifetime(UserActionType::INITIAL_PASSWORD)->d);
        self::assertSame(24, $this->service->getLifetime(UserActionType::RESET_PASSWORD)->h);
    }

    private function readProperty(object $object, string $property): mixed
    {
        return (new \ReflectionProperty($object, $property))->getValue($object);
    }

    private function writeProperty(object $object, string $property, mixed $value): void
    {
        (new \ReflectionProperty($object, $property))->setValue($object, $value);
    }
}
