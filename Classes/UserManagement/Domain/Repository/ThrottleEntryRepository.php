<?php

declare(strict_types=1);

namespace Sandstorm\NeosTwoFactorAuthentication\UserManagement\Domain\Repository;

use Doctrine\ORM\EntityManagerInterface;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Persistence\Repository;
use Sandstorm\NeosTwoFactorAuthentication\UserManagement\Domain\Model\ThrottleEntry;

#[Flow\Scope('singleton')]
class ThrottleEntryRepository extends Repository
{
    #[Flow\Inject]
    protected EntityManagerInterface $entityManager;

    public function countByBucketSince(string $bucket, \DateTimeImmutable $since): int
    {
        $query = $this->createQuery();
        return $query->matching($query->logicalAnd(
            $query->equals('bucket', $bucket),
            $query->greaterThan('createdAt', $since)
        ))->count();
    }

    public function removeOlderThan(\DateTimeImmutable $before): int
    {
        $result = $this->entityManager->createQuery(
            'DELETE FROM ' . ThrottleEntry::class . ' e WHERE e.createdAt <= :before'
        )
            ->setParameter('before', $before, 'datetime_immutable')
            ->execute();
        return is_int($result) ? $result : 0;
    }
}
