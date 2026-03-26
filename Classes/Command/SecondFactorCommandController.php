<?php

namespace Sandstorm\NeosTwoFactorAuthentication\Command;

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Cli\CommandController;
use Neos\Flow\Persistence\PersistenceManagerInterface;
use Sandstorm\NeosTwoFactorAuthentication\Domain\Repository\SecondFactorRepository;

/**
 * @Flow\Scope("singleton")
 */
class SecondFactorCommandController extends CommandController
{
    #[Flow\Inject]
    protected SecondFactorRepository $secondFactorRepository;

    #[Flow\Inject]
    protected PersistenceManagerInterface $persistenceManager;

    /**
     * Delete all second factors for a given account identifier
     *
     * @param string $username The account identifier (username) to delete second factors for
     */
    public function deleteForAccountCommand(string $username): void
    {
        $query = $this->secondFactorRepository->createQuery();
        $factors = $query->matching(
            $query->equals('account.accountIdentifier', $username)
        )->execute();

        $count = 0;
        foreach ($factors as $factor) {
            $this->secondFactorRepository->remove($factor);
            $count++;
        }
        $this->persistenceManager->persistAll();

        $this->outputLine('Deleted %d second factor(s) for account "%s".', [$count, $username]);
    }

    /**
     * Delete all second factors for all accounts
     */
    public function deleteAllCommand(): void
    {
        $factors = $this->secondFactorRepository->findAll();
        $count = 0;
        foreach ($factors as $factor) {
            $this->secondFactorRepository->remove($factor);
            $count++;
        }
        $this->persistenceManager->persistAll();

        $this->outputLine('Deleted %d second factor(s).', [$count]);
    }
}
