<?php

declare(strict_types=1);

namespace Sandstorm\NeosTwoFactorAuthentication\UserManagement\Domain\Model;

use Doctrine\ORM\Mapping as ORM;
use Neos\Flow\Annotations as Flow;

/**
 * One attempt within a throttle bucket (e.g. "forgot password for identifier X", "forgot password from IP Y").
 * Stored in the database, not in a cache, so the limit holds across several containers.
 */
#[Flow\Entity]
#[ORM\Table(
    name: 'sandstorm_neostwofactorauthentication_throttleentry',
    indexes: [new ORM\Index(name: 'bucket_createdat', columns: ['bucket', 'createdat'])]
)]
class ThrottleEntry
{
    /**
     * SHA-256 of the bucket key, so neither identifiers nor IPs are stored in plain text.
     */
    #[ORM\Column(length: 64)]
    protected string $bucket;

    #[ORM\Column(type: 'datetime_immutable')]
    protected \DateTimeImmutable $createdAt;

    public function __construct(string $bucket, \DateTimeImmutable $createdAt)
    {
        $this->bucket = $bucket;
        $this->createdAt = $createdAt;
    }
}
