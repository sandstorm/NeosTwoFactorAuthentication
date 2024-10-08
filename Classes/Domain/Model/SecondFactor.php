<?php

namespace Sandstorm\NeosTwoFactorAuthentication\Domain\Model;

use DateTime;
use Neos\Flow\Http\InvalidArgumentException;
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
    const TYPE_TOTP = 1;

    // using the webauthn standard supported by most modern browsers
    const TYPE_PUBLIC_KEY = 2;

    /**
     * @var Account
     * @ORM\ManyToOne
     * If Account gets deleted also delete the second factors.
     * @ORM\JoinColumn(onDelete="CASCADE")
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
     * Introduced with version 1.4.0
     * Nullable for backwards compatibility. Null values will be shown as '-' in backend module.
     *
     * @var DateTime|null
     * @ORM\Column(type="datetime", nullable=true)
     */
    protected DateTime|null $creationDate;

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
     * @return string
     */
    public function getTypeAsName(): string
    {
        return self::typeToString($this->getType());
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

    public function getCreationDate(): DateTime|null
    {
        return $this->creationDate;
    }

    public function setCreationDate(DateTime $creationDate): void
    {
        $this->creationDate = $creationDate;
    }

    public function __toString(): string
    {
        return $this->account->getAccountIdentifier() . " with " . self::typeToString($this->type);
    }

    public static function typeToString(int $type): string
    {
        switch ($type) {
            case self::TYPE_TOTP:
                return 'OTP';
            case self::TYPE_PUBLIC_KEY:
                return 'Public Key';
            default:
                throw new InvalidArgumentException('Unsupported second factor type with index ' . $type);
        }
    }
}
