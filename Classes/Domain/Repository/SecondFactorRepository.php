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
    protected $defaultOrderings = [
        'account' => 'ASC',
        'creationDate' => 'DESC'
    ];

    /**
     * @throws IllegalObjectTypeException
     */
    public function createSecondFactorForAccount(string $secret, Account $account, int $type = SecondFactor::TYPE_TOTP, string $name = ''): SecondFactor
    {
        $secondFactor = new SecondFactor();
        $secondFactor->setAccount($account);
        $secondFactor->setSecret($secret);
        $secondFactor->setType($type);
        $secondFactor->setName($name);
        $secondFactor->setCreationDate(new \DateTime());
        $this->add($secondFactor);
        $this->persistenceManager->persistAll();
        return $secondFactor;
    }

    /**
     * @return SecondFactor[]
     */
    public function findByAccountAndType(Account $account, int $type): array
    {
        $query = $this->createQuery();
        return $query
            ->matching(
                $query->logicalAnd(
                    $query->equals('account', $account),
                    $query->equals('type', $type)
                )
            )
            ->execute()
            ->toArray();
    }

    /**
     * @return SecondFactor[]
     */
    public function findAllByType(int $type): array
    {
        $query = $this->createQuery();
        return $query
            ->matching($query->equals('type', $type))
            ->execute()
            ->toArray();
    }
}
