<?php

declare(strict_types=1);

namespace Sandstorm\NeosTwoFactorAuthentication\UserManagement\Domain\Model;

use Doctrine\ORM\Mapping as ORM;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Security\Account;
use Sandstorm\NeosTwoFactorAuthentication\UserManagement\Domain\UserActionType;

/**
 * A single-use, expiring token mailed to a user as part of a link. Only the SHA-256 hash of the token is
 * stored; the plaintext exists solely in the mail.
 */
#[Flow\Entity]
#[ORM\Table(name: 'sandstorm_neostwofactorauthentication_useractiontoken')]
class UserActionToken
{
    /**
     * If the account gets deleted, its tokens are deleted as well.
     */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'CASCADE')]
    protected Account $account;

    #[ORM\Column(length: 64, unique: true)]
    protected string $tokenHash;

    #[ORM\Column(length: 40)]
    protected string $action;

    /**
     * The token is only valid while the user's primary email is still this address.
     */
    protected string $emailAtIssue;

    #[ORM\Column(type: 'datetime_immutable')]
    protected \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    protected \DateTimeImmutable $expiresAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    protected ?\DateTimeImmutable $consumedAt = null;

    public function __construct(
        Account $account,
        string $tokenHash,
        UserActionType $action,
        string $emailAtIssue,
        \DateTimeImmutable $createdAt,
        \DateTimeImmutable $expiresAt
    ) {
        $this->account = $account;
        $this->tokenHash = $tokenHash;
        $this->action = $action->value;
        $this->emailAtIssue = $emailAtIssue;
        $this->createdAt = $createdAt;
        $this->expiresAt = $expiresAt;
    }

    public function getAccount(): Account
    {
        return $this->account;
    }

    public function getAction(): UserActionType
    {
        return UserActionType::from($this->action);
    }

    public function getEmailAtIssue(): string
    {
        return $this->emailAtIssue;
    }

    public function getExpiresAt(): \DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function isConsumed(): bool
    {
        return $this->consumedAt !== null;
    }

    public function isExpired(\DateTimeImmutable $now): bool
    {
        return $this->expiresAt <= $now;
    }

    public function consume(\DateTimeImmutable $now): void
    {
        $this->consumedAt = $now;
    }
}
