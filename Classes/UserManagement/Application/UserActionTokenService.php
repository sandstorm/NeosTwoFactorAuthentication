<?php

declare(strict_types=1);

namespace Sandstorm\NeosTwoFactorAuthentication\UserManagement\Application;

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Security\Account;
use Sandstorm\NeosTwoFactorAuthentication\UserManagement\Domain\Model\UserActionToken;
use Sandstorm\NeosTwoFactorAuthentication\UserManagement\Domain\Repository\UserActionTokenRepository;
use Sandstorm\NeosTwoFactorAuthentication\UserManagement\Domain\UserActionType;

/**
 * Issues, validates and consumes the single-use tokens behind mailed links.
 *
 * Tokens are validated on GET but only consumed on POST, because mail scanners and link previews prefetch GETs.
 * The plaintext token is never stored or logged.
 */
#[Flow\Scope('singleton')]
class UserActionTokenService
{
    #[Flow\Inject]
    protected UserActionTokenRepository $tokenRepository;

    #[Flow\Inject]
    protected SimpleUserFinder $simpleUserFinder;

    /** @var array<string, string> */
    #[Flow\InjectConfiguration(path: 'userManagement.tokenLifetime')]
    protected array $tokenLifetime = [];

    /**
     * Issues a new token and invalidates older unconsumed tokens of the same action for the account.
     *
     * @return string the plaintext token - goes into the mail only
     */
    public function issue(Account $account, UserActionType $action, string $email): string
    {
        $now = $this->now();
        foreach ($this->tokenRepository->findUnconsumedByAccountAndActions($account, [$action]) as $olderToken) {
            $olderToken->consume($now);
            $this->tokenRepository->update($olderToken);
        }

        $plainToken = self::generatePlainToken();
        $this->tokenRepository->add(new UserActionToken(
            $account,
            self::hash($plainToken),
            $action,
            SimpleUserFinder::normalizeEmail($email),
            $now,
            $now->add($this->getLifetime($action))
        ));
        return $plainToken;
    }

    /**
     * Returns the token if it is unconsumed, not expired, of one of the allowed actions, and the account's user
     * still has the primary email the token was issued for. Null otherwise - callers must not tell apart why.
     *
     * @param list<UserActionType> $allowedActions
     */
    public function findValid(string $plainToken, array $allowedActions): ?UserActionToken
    {
        if ($plainToken === '') {
            return null;
        }
        $token = $this->tokenRepository->findOneByTokenHash(self::hash($plainToken));
        if ($token === null || $token->isConsumed() || $token->isExpired($this->now())) {
            return null;
        }
        if (!in_array($token->getAction(), $allowedActions, true)) {
            return null;
        }
        $user = $this->simpleUserFinder->findUserOfAccount($token->getAccount());
        $currentEmail = $user !== null ? $this->simpleUserFinder->getPrimaryEmail($user) : null;
        if ($currentEmail === null || SimpleUserFinder::normalizeEmail($currentEmail->getIdentifier()) !== $token->getEmailAtIssue()) {
            return null;
        }
        return $token;
    }

    public function consume(UserActionToken $token): void
    {
        $token->consume($this->now());
        $this->tokenRepository->update($token);
    }

    /**
     * After a password was set, no other mailed password link of the account may still work.
     */
    public function invalidatePasswordTokens(Account $account): void
    {
        foreach ($this->tokenRepository->findUnconsumedByAccountAndActions($account, UserActionType::passwordActions()) as $token) {
            $this->consume($token);
        }
    }

    public function getLifetime(UserActionType $action): \DateInterval
    {
        return new \DateInterval($this->tokenLifetime[$action->value] ?? 'PT24H');
    }

    /**
     * Deletes expired tokens and tokens consumed more than a day ago.
     */
    public function prune(): int
    {
        $now = $this->now();
        return $this->tokenRepository->removeUnusable($now, $now->sub(new \DateInterval('P1D')));
    }

    public static function hash(string $plainToken): string
    {
        return hash('sha256', $plainToken);
    }

    /**
     * 32 random bytes, base64url without padding (43 chars) - safe in URL paths.
     */
    public static function generatePlainToken(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }

    protected function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable();
    }
}
