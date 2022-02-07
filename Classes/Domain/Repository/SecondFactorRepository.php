<?php

namespace Sandstorm\NeosTwoFactorAuthentication\Domain\Repository;

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Persistence\Repository;
use Neos\Flow\Security\Account;

/**
 * @Flow\Scope("singleton")
 */
class SecondFactorRepository extends Repository
{
    public function isEnabledForAccount(Account $account): bool
    {
        $factors = $this->findByAccount($account);
        return count($factors) > 0;
    }
}
