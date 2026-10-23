<?php

declare(strict_types=1);

namespace Sandstorm\NeosTwoFactorAuthentication\UserManagement\Application;

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Cache\CacheManager;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\StoreFactory;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\CacheStorage;
use Symfony\Component\RateLimiter\Storage\StorageInterface;

/**
 * Sliding-window throttle based on symfony/rate-limiter. The limiter state lives in the Flow cache
 * {@see self::CACHE_IDENTIFIER}, the lock in the store configured in `userManagement.throttle.lockStore`.
 */
#[Flow\Scope('singleton')]
class Throttle
{
    public const CACHE_IDENTIFIER = 'Sandstorm_NeosTwoFactorAuthentication_Throttle';

    #[Flow\Inject]
    protected CacheManager $cacheManager;

    #[Flow\InjectConfiguration(path: 'userManagement.throttle.lockStore')]
    protected string $lockStore = 'flock';

    private ?LockFactory $lockFactory = null;

    /**
     * Records an attempt for the bucket and returns whether it is within the limit. Attempts over the limit
     * are not recorded, so a blocked client is free again once the window has passed.
     *
     * @param string $window ISO 8601 duration, e.g. PT15M
     */
    public function attempt(string $bucketKey, int $limit, string $window): bool
    {
        $rateLimiterFactory = new RateLimiterFactory(
            [
                'id' => 'Sandstorm.NeosTwoFactorAuthentication',
                'policy' => 'sliding_window',
                'limit' => $limit,
                'interval' => self::durationInSeconds($window) . ' seconds',
            ],
            $this->createStorage(),
            $this->getLockFactory()
        );
        // hashed, so neither identifiers nor IPs end up in cache entries or lock names in plain text
        return $rateLimiterFactory->create(hash('sha256', $bucketKey))->consume()->isAccepted();
    }

    public static function durationInSeconds(string $isoDuration): int
    {
        $start = new \DateTimeImmutable('@0');
        return $start->add(new \DateInterval($isoDuration))->getTimestamp();
    }

    protected function createStorage(): StorageInterface
    {
        return new CacheStorage($this->cacheManager->getCacheItemPool(self::CACHE_IDENTIFIER));
    }

    protected function getLockFactory(): LockFactory
    {
        return $this->lockFactory ??= new LockFactory(StoreFactory::createStore($this->lockStore));
    }
}
