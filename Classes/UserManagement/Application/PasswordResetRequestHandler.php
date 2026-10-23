<?php

declare(strict_types=1);

namespace Sandstorm\NeosTwoFactorAuthentication\UserManagement\Application;

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Persistence\PersistenceManagerInterface;
use Neos\Flow\Security\Account;
use Psr\Log\LoggerInterface;
use Sandstorm\NeosTwoFactorAuthentication\UserManagement\Domain\UserActionType;

/**
 * "Forgot password": the user enters a username OR an email address.
 *
 * The caller ALWAYS shows the same generic response - this handler never reveals whether an account exists,
 * and swallows (but logs) mail errors for the same reason.
 *
 * Resolution:
 *   - accounts whose identifier equals the input (any managed provider), mailed to their user's primary email
 *   - plus accounts whose user's primary email equals the input (case-insensitive)
 * Several accounts sharing one email get ONE mail with one link per username.
 */
#[Flow\Scope('singleton')]
class PasswordResetRequestHandler
{
    #[Flow\Inject]
    protected SimpleUserFinder $simpleUserFinder;

    #[Flow\Inject]
    protected UserActionTokenService $tokenService;

    #[Flow\Inject]
    protected UserMailer $userMailer;

    #[Flow\Inject]
    protected Throttle $throttle;

    #[Flow\Inject]
    protected PersistenceManagerInterface $persistenceManager;

    #[Flow\Inject(name: 'Neos.Flow:SecurityLogger', lazy: false)]
    protected LoggerInterface $securityLogger;

    #[Flow\InjectConfiguration(path: 'userManagement.requireValidatedEmail')]
    protected bool $requireValidatedEmail = true;

    /** @var array{perIdentifier: array{limit: int, window: string}, perIp: array{limit: int, window: string}} */
    #[Flow\InjectConfiguration(path: 'userManagement.throttle')]
    protected array $throttleSettings;

    public function handle(string $usernameOrEmail, string $clientIp): void
    {
        $input = trim($usernameOrEmail);
        if ($input === '') {
            return;
        }

        $identifierAllowed = $this->throttle->attempt(
            'forgotPassword:identifier:' . mb_strtolower($input),
            $this->throttleSettings['perIdentifier']['limit'],
            $this->throttleSettings['perIdentifier']['window']
        );
        $ipAllowed = $this->throttle->attempt(
            'forgotPassword:ip:' . $clientIp,
            $this->throttleSettings['perIp']['limit'],
            $this->throttleSettings['perIp']['window']
        );
        if (!$identifierAllowed || !$ipAllowed) {
            $this->securityLogger->notice('Sandstorm/NeosTwoFactorAuthentication: forgot password request throttled.', ['clientIp' => $clientIp]);
            return;
        }

        foreach ($this->resolveAccountsByRecipient($input) as $recipient => $accounts) {
            $this->sendMail($recipient, $accounts);
        }
    }

    /**
     * @return array<string, list<Account>> recipient email => accounts
     */
    public function resolveAccountsByRecipient(string $input): array
    {
        /** @var array<string, Account> $accountsById */
        $accountsById = [];
        foreach ([
            ...$this->simpleUserFinder->findActiveAccountsByAccountIdentifier($input),
            ...$this->simpleUserFinder->findActiveAccountsByPrimaryEmail($input, $this->requireValidatedEmail),
        ] as $account) {
            $accountsById[(string)$this->persistenceManager->getIdentifierByObject($account)] = $account;
        }

        $byRecipient = [];
        foreach ($accountsById as $account) {
            $user = $this->simpleUserFinder->findUserOfAccount($account);
            $primaryEmail = $user !== null ? $this->simpleUserFinder->getPrimaryEmail($user) : null;
            if ($primaryEmail === null || ($this->requireValidatedEmail && !$primaryEmail->isApproved())) {
                continue;
            }
            $byRecipient[SimpleUserFinder::normalizeEmail($primaryEmail->getIdentifier())][] = $account;
        }
        return $byRecipient;
    }

    /**
     * @param list<Account> $accounts
     */
    private function sendMail(string $recipient, array $accounts): void
    {
        $links = [];
        foreach ($accounts as $account) {
            $links[] = [
                'username' => $account->getAccountIdentifier(),
                'token' => $this->tokenService->issue($account, UserActionType::RESET_PASSWORD, $recipient),
            ];
        }
        // with several users sharing the email, the mail is in the first user's language
        $user = $this->simpleUserFinder->findUserOfAccount($accounts[0]);
        try {
            $this->userMailer->sendPasswordLinks(
                $recipient,
                $user,
                UserActionType::RESET_PASSWORD,
                $links,
                (new \DateTimeImmutable())->add($this->tokenService->getLifetime(UserActionType::RESET_PASSWORD))
            );
        } catch (\Throwable $e) {
            $this->securityLogger->error('Sandstorm/NeosTwoFactorAuthentication: could not send password reset mail: ' . $e->getMessage());
        }
    }
}
