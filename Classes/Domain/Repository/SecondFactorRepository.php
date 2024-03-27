<?php

namespace Sandstorm\NeosTwoFactorAuthentication\Domain\Repository;

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Persistence\Doctrine\QueryResult;
use Neos\Flow\Persistence\Exception\IllegalObjectTypeException;
use Neos\Flow\Persistence\Repository;
use Neos\Flow\Security\Account;
use Sandstorm\NeosTwoFactorAuthentication\Domain\Model\SecondFactor;

/**
 * @Flow\Scope("singleton")
 *
 * @method QueryResult findByAccount(Account $account)
 */
class SecondFactorRepository extends Repository
{
    /**
     * @throws IllegalObjectTypeException
     */
    public function createSecondFactorForAccount(string $secret, Account $account): void
    {
        $secondFactor = new SecondFactor();
        $secondFactor->setAccount($account);
        $secondFactor->setSecret($secret);
        $secondFactor->setType(SecondFactor::TYPE_TOTP);
        $this->add($secondFactor);
        $this->persistenceManager->persistAll();
    }
}
