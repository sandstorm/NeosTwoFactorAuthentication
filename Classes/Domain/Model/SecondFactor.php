<?php

namespace Sandstorm\NeosTwoFactorAuthentication\Domain\Model;

use Neos\Flow\Security\Account;
use Doctrine\ORM\Mapping as ORM;
use Neos\Flow\Annotations as Flow;

/**
 * Store the secrets needed for two factor authentication
 *
 * @Flow\Entity
 */
class SecondFactor
{
    // For apps like the google authenticator
    const TYPE_OTP = 1;

    // using the webauthn standard supported by most modern browsers
    const TYPE_PUBLIC_KEY = 2;

    /**
     * @var Account
     * @ORM\ManyToOne
     */
    protected Account $account;

    /**
     * @var int
     */
    protected int $type;

    /**
     * @var string
     */
    protected string $secret;

    /**
     * @return Account
     */
    public function getAccount(): Account
    {
        return $this->account;
    }

    /**
     * @param Account $account
     */
    public function setAccount(Account $account): void
    {
        $this->account = $account;
    }

    /**
     * @return int
     */
    public function getType(): int
    {
        return $this->type;
    }

    /**
     * @param int $type
     */
    public function setType(int $type): void
    {
        $this->type = $type;
    }

    /**
     * @return string
     */
    public function getSecret(): string
    {
        return $this->secret;
    }

    /**
     * @param string $secret
     */
    public function setSecret(string $secret): void
    {
        $this->secret = $secret;
    }
}
