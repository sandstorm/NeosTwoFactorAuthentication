<?php

namespace Sandstorm\NeosTwoFactorAuthentication\Domain\Model;

use DateTime;
use Neos\Flow\Http\InvalidArgumentException;
use Neos\Flow\Security\Account;
use Doctrine\ORM\Mapping as ORM;
use Neos\Flow\Annotations as Flow;

// TODO: refactor to PHP8 code

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
     * For TYPE_TOTP this is a base32-encoded shared secret.
     * For TYPE_PUBLIC_KEY this is the JSON-serialized WebAuthn credential source.
     *
     * @var string
     * @ORM\Column(type="text")
     */
    protected string $secret;

    /**
     * @var string
     */
    protected string $name;

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
     * Used in Fusion rendering
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

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Used in Fusion rendering
     */
    public function getCreationDate(): DateTime|null
    {
        return $this->creationDate;
    }

    public function setCreationDate(DateTime $creationDate): void
    {
        $this->creationDate = $creationDate;
    }

    /**
     * Decode the credential data stored in `secret` for non-TOTP factors.
     *
     * @return array<string, mixed>
     */
    public function getCredentialData(): array
    {
        $decoded = json_decode($this->secret, true);
        if (!is_array($decoded)) {
            throw new InvalidArgumentException('Stored credential data is not valid JSON');
        }
        return $decoded;
    }

    /**
     * Encode credential data as JSON for non-TOTP factors.
     *
     * @param array<string, mixed> $data
     */
    public function setCredentialData(array $data): void
    {
        $encoded = json_encode($data, JSON_THROW_ON_ERROR);
        $this->secret = $encoded;
    }

    public function __toString(): string
    {
        return $this->account->getAccountIdentifier() . " with " . self::typeToString($this->type);
    }

    public static function typeToString(int $type): string
    {
        switch ($type) {
            case self::TYPE_TOTP:
                return 'OTP code';
            case self::TYPE_PUBLIC_KEY:
                return 'Security Key';
            default:
                throw new InvalidArgumentException('Unsupported second factor type with index ' . $type);
        }
    }
}
