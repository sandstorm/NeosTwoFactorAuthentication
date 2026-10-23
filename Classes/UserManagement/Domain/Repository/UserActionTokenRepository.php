<?php

declare(strict_types=1);

namespace Sandstorm\NeosTwoFactorAuthentication\UserManagement\Domain\Repository;

use Doctrine\ORM\EntityManagerInterface;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Persistence\Repository;
use Neos\Flow\Security\Account;
use Sandstorm\NeosTwoFactorAuthentication\UserManagement\Domain\Model\UserActionToken;
use Sandstorm\NeosTwoFactorAuthentication\UserManagement\Domain\UserActionType;

#[Flow\Scope('singleton')]
class UserActionTokenRepository extends Repository
{
    #[Flow\Inject]
    protected EntityManagerInterface $entityManager;

    public function findOneByTokenHash(string $tokenHash): ?UserActionToken
    {
        $query = $this->createQuery();
        $token = $query->matching($query->equals('tokenHash', $tokenHash))->execute()->getFirst();
        return $token instanceof UserActionToken ? $token : null;
    }

    /**
     * @param list<UserActionType> $actions
     * @return list<UserActionToken>
     */
    public function findUnconsumedByAccountAndActions(Account $account, array $actions): array
    {
        $query = $this->createQuery();
        $tokens = $query->matching($query->logicalAnd(
            $query->equals('account', $account),
            $query->in('action', array_map(fn (UserActionType $action) => $action->value, $actions)),
            $query->equals('consumedAt', null)
        ))->execute()->toArray();
        return array_values(array_filter($tokens, fn ($token) => $token instanceof UserActionToken));
    }

    /**
     * Deletes tokens that can never be used again: expired, or consumed before $consumedBefore.
     */
    public function removeUnusable(\DateTimeImmutable $now, \DateTimeImmutable $consumedBefore): int
    {
        $result = $this->entityManager->createQuery(
            'DELETE FROM ' . UserActionToken::class . ' t WHERE t.expiresAt <= :now OR t.consumedAt <= :consumedBefore'
        )
            ->setParameter('now', $now, 'datetime_immutable')
            ->setParameter('consumedBefore', $consumedBefore, 'datetime_immutable')
            ->execute();
        return is_int($result) ? $result : 0;
    }
}
