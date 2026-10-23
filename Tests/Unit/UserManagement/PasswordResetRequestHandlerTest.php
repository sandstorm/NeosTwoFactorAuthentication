<?php

namespace Sandstorm\NeosTwoFactorAuthentication\Tests\Unit\UserManagement;

use Neos\Flow\Persistence\PersistenceManagerInterface;
use Neos\Flow\Security\Account;
use Neos\Flow\Tests\UnitTestCase;
use Neos\Neos\Domain\Model\User;
use Neos\Party\Domain\Model\ElectronicAddress;
use Psr\Log\LoggerInterface;
use Sandstorm\NeosTwoFactorAuthentication\UserManagement\Application\PasswordResetRequestHandler;
use Sandstorm\NeosTwoFactorAuthentication\UserManagement\Application\SimpleUserFinder;
use Sandstorm\NeosTwoFactorAuthentication\UserManagement\Application\Throttle;
use Sandstorm\NeosTwoFactorAuthentication\UserManagement\Application\UserActionTokenService;
use Sandstorm\NeosTwoFactorAuthentication\UserManagement\Application\UserMailer;
use Sandstorm\NeosTwoFactorAuthentication\UserManagement\Domain\UserActionType;

/**
 * Resolution of "username or email" to accounts and recipients, incl. several accounts sharing one email.
 */
class PasswordResetRequestHandlerTest extends UnitTestCase
{
    private PasswordResetRequestHandler $handler;

    /**
     * account identifier => [account, user email, approved]
     *
     * @var array<string, array{Account, string, bool}>
     */
    private array $accounts = [];

    /**
     * @var list<array{recipient: string, links: list<array{username: string, token: string}>}>
     */
    private array $sentMails = [];

    private bool $throttleAllows = true;

    protected function setUp(): void
    {
        parent::setUp();
        $this->accounts = [];
        $this->sentMails = [];
        $this->throttleAllows = true;

        $this->addAccount('jane', 'shared@example.com', true);
        $this->addAccount('jane-admin', 'shared@example.com', true);
        $this->addAccount('john', 'john@example.com', true);
        $this->addAccount('unvalidated', 'unvalidated@example.com', false);

        $finder = $this->createMock(SimpleUserFinder::class);
        $finder->method('findActiveAccountsByAccountIdentifier')->willReturnCallback(
            fn (string $identifier) => isset($this->accounts[$identifier]) ? [$this->accounts[$identifier][0]] : []
        );
        $finder->method('findActiveAccountsByPrimaryEmail')->willReturnCallback(
            fn (string $email, bool $onlyApproved) => array_values(array_map(
                fn (array $entry) => $entry[0],
                array_filter($this->accounts, fn (array $entry) => $entry[1] === mb_strtolower(trim($email)) && (!$onlyApproved || $entry[2]))
            ))
        );
        $finder->method('findUserOfAccount')->willReturnCallback(fn (Account $account) => $this->userOf($account));
        $finder->method('getPrimaryEmail')->willReturnCallback(fn (User $user) => $user->getPrimaryElectronicAddress());

        $persistenceManager = $this->createMock(PersistenceManagerInterface::class);
        $persistenceManager->method('getIdentifierByObject')->willReturnCallback(fn (object $object) => spl_object_hash($object));

        $tokenService = $this->createMock(UserActionTokenService::class);
        $tokenService->method('issue')->willReturnCallback(fn (Account $account) => 'token-' . $account->getAccountIdentifier());
        $tokenService->method('getLifetime')->willReturn(new \DateInterval('PT24H'));

        $mailer = $this->createMock(UserMailer::class);
        $mailer->method('sendPasswordLinks')->willReturnCallback(
            function (string $recipient, ?User $user, UserActionType $action, array $links) {
                $this->sentMails[] = ['recipient' => $recipient, 'links' => $links];
            }
        );

        $throttle = $this->createMock(Throttle::class);
        $throttle->method('attempt')->willReturnCallback(fn () => $this->throttleAllows);

        $this->handler = new PasswordResetRequestHandler();
        $this->inject($this->handler, 'simpleUserFinder', $finder);
        $this->inject($this->handler, 'persistenceManager', $persistenceManager);
        $this->inject($this->handler, 'tokenService', $tokenService);
        $this->inject($this->handler, 'userMailer', $mailer);
        $this->inject($this->handler, 'throttle', $throttle);
        $this->inject($this->handler, 'securityLogger', $this->createMock(LoggerInterface::class));
        $this->inject($this->handler, 'requireValidatedEmail', true);
        $this->inject($this->handler, 'throttleSettings', [
            'perIdentifier' => ['limit' => 3, 'window' => 'PT15M'],
            'perIp' => ['limit' => 10, 'window' => 'PT15M'],
        ]);
    }

    /**
     * @test
     */
    public function usernameSendsMailToTheUsersEmail(): void
    {
        $this->handler->handle('john', '127.0.0.1');

        self::assertSame([['recipient' => 'john@example.com', 'links' => [['username' => 'john', 'token' => 'token-john']]]], $this->sentMails);
    }

    /**
     * @test
     */
    public function emailMatchingSeveralAccountsSendsOneMailWithOneLinkPerUsername(): void
    {
        $this->handler->handle(' Shared@Example.com ', '127.0.0.1');

        self::assertCount(1, $this->sentMails);
        self::assertSame('shared@example.com', $this->sentMails[0]['recipient']);
        self::assertSame(['jane', 'jane-admin'], array_column($this->sentMails[0]['links'], 'username'));
    }

    /**
     * @test
     */
    public function unknownInputSendsNoMail(): void
    {
        $this->handler->handle('nobody@example.com', '127.0.0.1');
        $this->handler->handle('nobody', '127.0.0.1');
        $this->handler->handle('   ', '127.0.0.1');

        self::assertSame([], $this->sentMails);
    }

    /**
     * @test
     */
    public function unvalidatedEmailIsIgnoredWhenValidationIsRequired(): void
    {
        $this->handler->handle('unvalidated', '127.0.0.1');
        $this->handler->handle('unvalidated@example.com', '127.0.0.1');

        self::assertSame([], $this->sentMails);
    }

    /**
     * @test
     */
    public function unvalidatedEmailIsUsedWhenValidationIsNotRequired(): void
    {
        $this->inject($this->handler, 'requireValidatedEmail', false);

        $this->handler->handle('unvalidated', '127.0.0.1');

        self::assertCount(1, $this->sentMails);
    }

    /**
     * @test
     */
    public function throttledRequestSendsNoMail(): void
    {
        $this->throttleAllows = false;

        $this->handler->handle('john', '127.0.0.1');

        self::assertSame([], $this->sentMails);
    }

    private function addAccount(string $identifier, string $email, bool $approved): void
    {
        $account = new Account();
        $account->setAccountIdentifier($identifier);
        $this->accounts[$identifier] = [$account, $email, $approved];
    }

    private function userOf(Account $account): User
    {
        foreach ($this->accounts as [$candidate, $email, $approved]) {
            if ($candidate === $account) {
                $electronicAddress = new ElectronicAddress();
                $electronicAddress->setType(SimpleUserFinder::EMAIL_ADDRESS_TYPE);
                $electronicAddress->setIdentifier($email);
                $electronicAddress->setApproved($approved);
                $user = new User();
                $user->addElectronicAddress($electronicAddress);
                $user->setPrimaryElectronicAddress($electronicAddress);
                return $user;
            }
        }
        throw new \RuntimeException('unknown account');
    }
}
