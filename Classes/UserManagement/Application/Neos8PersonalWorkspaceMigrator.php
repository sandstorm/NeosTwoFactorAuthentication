<?php

declare(strict_types=1);

namespace Sandstorm\NeosTwoFactorAuthentication\UserManagement\Application;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Neos\Flow\Annotations as Flow;

/**
 * Neos 8 names the personal workspace after the username ("user-" + its letters and digits) and uses that name as
 * primary key, so renaming an account moves the workspace and its nodes to the new name. Plain SQL, because the
 * Neos 8 Workspace entity cannot be renamed.
 *
 * On Neos 9, which finds the personal workspace by user id, only the account is renamed.
 */
#[Flow\Scope('singleton')]
class Neos8PersonalWorkspaceMigrator
{
    // the class only exists in Neos 8
    private const NEOS8_WORKSPACE_CLASS = 'Neos\ContentRepository\Domain\Model\Workspace';

    private const WORKSPACE_TABLE = 'neos_contentrepository_domain_model_workspace';
    private const NODEDATA_TABLE = 'neos_contentrepository_domain_model_nodedata';
    private const EVENTLOG_TABLE = 'neos_neos_eventlog_domain_model_event';

    #[Flow\Inject]
    protected EntityManagerInterface $entityManager;

    /**
     * Same as Neos 8's UserUtility::getPersonalWorkspaceNameForUsername()
     */
    public static function workspaceName(string $username): string
    {
        return 'user-' . preg_replace('/[^a-z0-9]/i', '', $username);
    }

    /**
     * False if another user's personal workspace already has the new name.
     */
    public function canMigrate(string $oldUsername, string $newUsername): bool
    {
        $oldName = self::workspaceName($oldUsername);
        $newName = self::workspaceName($newUsername);
        return !$this->isNeos8() || $oldName === $newName || !$this->workspaceExists($newName);
    }

    /**
     * Copies the workspace row to the new name, moves nodes, base workspace references and the event log over,
     * then deletes the old row - in one transaction with $renameAccount, so workspace and account never disagree.
     *
     * @param \Closure(): void $renameAccount renames and persists the account
     */
    public function renameWithWorkspace(string $oldUsername, string $newUsername, \Closure $renameAccount): void
    {
        $oldName = self::workspaceName($oldUsername);
        $newName = self::workspaceName($newUsername);
        if (!$this->isNeos8() || $oldName === $newName || !$this->workspaceExists($oldName)) {
            $renameAccount();
            return;
        }
        $this->entityManager->getConnection()->transactional(function (Connection $connection) use ($oldName, $newName, $renameAccount) {
            $workspace = $connection->fetchAssociative('SELECT * FROM ' . self::WORKSPACE_TABLE . ' WHERE name = ?', [$oldName]);
            if ($workspace === false) {
                throw new \RuntimeException('Personal workspace ' . $oldName . ' disappeared during the rename.', 1760000110);
            }
            $workspace['name'] = $newName;
            $connection->insert(self::WORKSPACE_TABLE, $workspace);
            $connection->update(self::NODEDATA_TABLE, ['workspace' => $newName], ['workspace' => $oldName]);
            $connection->update(self::WORKSPACE_TABLE, ['baseworkspace' => $newName], ['baseworkspace' => $oldName]);
            $connection->update(self::EVENTLOG_TABLE, ['workspacename' => $newName], ['workspacename' => $oldName]);
            $connection->delete(self::WORKSPACE_TABLE, ['name' => $oldName]);
            $renameAccount();
        });
    }

    private function isNeos8(): bool
    {
        return class_exists(self::NEOS8_WORKSPACE_CLASS);
    }

    private function workspaceExists(string $name): bool
    {
        return $this->entityManager->getConnection()
            ->fetchOne('SELECT 1 FROM ' . self::WORKSPACE_TABLE . ' WHERE name = ?', [$name]) !== false;
    }
}
