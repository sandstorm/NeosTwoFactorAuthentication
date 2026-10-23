<?php

declare(strict_types=1);

namespace Sandstorm\NeosTwoFactorAuthentication\UserManagement\Application;

use Neos\Flow\Annotations as Flow;
use Sandstorm\NeosTwoFactorAuthentication\UserManagement\Domain\Model\ThrottleEntry;
use Sandstorm\NeosTwoFactorAuthentication\UserManagement\Domain\Repository\ThrottleEntryRepository;

/**
 * Simple sliding-window throttle backed by the database. Not race-free (two parallel requests may both pass
 * the last free slot) - good enough to stop mail flooding, not meant as a hard rate limit.
 */
#[Flow\Scope('singleton')]
class Throttle
{
    #[Flow\Inject]
    protected ThrottleEntryRepository $throttleEntryRepository;

    /**
     * Records an attempt for the bucket and returns whether it is within the limit. Attempts over the limit
     * are not recorded, so a blocked client is free again once the window has passed.
     */
    public function attempt(string $bucketKey, int $limit, string $window): bool
    {
        $now = new \DateTimeImmutable();
        $bucket = hash('sha256', $bucketKey);
        $since = $now->sub(new \DateInterval($window));

        if ($this->throttleEntryRepository->countByBucketSince($bucket, $since) >= $limit) {
            return false;
        }
        $this->throttleEntryRepository->add(new ThrottleEntry($bucket, $now));
        return true;
    }

    public function prune(string $longestWindow): int
    {
        return $this->throttleEntryRepository->removeOlderThan((new \DateTimeImmutable())->sub(new \DateInterval($longestWindow)));
    }
}
