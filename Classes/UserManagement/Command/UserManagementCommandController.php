<?php

declare(strict_types=1);

namespace Sandstorm\NeosTwoFactorAuthentication\UserManagement\Command;

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Cli\CommandController;
use Neos\Flow\Persistence\PersistenceManagerInterface;
use Neos\Neos\Domain\Model\User;
use Sandstorm\NeosTwoFactorAuthentication\UserManagement\Application\SimpleUserFinder;
use Sandstorm\NeosTwoFactorAuthentication\UserManagement\Application\Throttle;
use Sandstorm\NeosTwoFactorAuthentication\UserManagement\Application\UserActionTokenService;
use Sandstorm\NeosTwoFactorAuthentication\UserManagement\Application\UserCommandService;
use Sandstorm\NeosTwoFactorAuthentication\UserManagement\Application\UserManagementException;
use Sandstorm\NeosTwoFactorAuthentication\UserManagement\Domain\UserActionType;

/**
 * User management: send password mails, prune tokens, report duplicate emails.
 *
 * Links in mails are built from Neos.Flow.http.baseUri - it must be configured for these commands.
 */
class UserManagementCommandController extends CommandController
{
    #[Flow\Inject]
    protected SimpleUserFinder $simpleUserFinder;

    #[Flow\Inject]
    protected UserCommandService $userCommandService;

    #[Flow\Inject]
    protected UserActionTokenService $tokenService;

    #[Flow\Inject]
    protected Throttle $throttle;

    #[Flow\Inject]
    protected PersistenceManagerInterface $persistenceManager;

    /** @var array{enabled: bool, throttle: array{perIdentifier: array{window: string}, perIp: array{window: string}}} */
    #[Flow\InjectConfiguration(path: 'userManagement')]
    protected array $settings;

    /**
     * Send the "set your initial password" mail to a user
     *
     * @param string $username account identifier of the user
     * @param string|null $authenticationProvider defaults to the first configured provider
     */
    public function sendInitialPasswordCommand(string $username, ?string $authenticationProvider = null): void
    {
        $this->sendPasswordMail($username, $authenticationProvider, UserActionType::INITIAL_PASSWORD);
    }

    /**
     * Send the "reset your password" mail to a user
     *
     * @param string $username account identifier of the user
     * @param string|null $authenticationProvider defaults to the first configured provider
     */
    public function sendResetCommand(string $username, ?string $authenticationProvider = null): void
    {
        $this->sendPasswordMail($username, $authenticationProvider, UserActionType::RESET_PASSWORD);
    }

    /**
     * Set the primary email of a user, e.g. for legacy users without one
     *
     * Does not send a confirmation mail. Fails if another user already has this primary email.
     *
     * @param string $username account identifier of the user
     * @param string $email the new primary email
     * @param bool $validated mark the email as validated (default: not validated)
     * @param string|null $authenticationProvider defaults to any configured provider
     */
    public function setEmailCommand(string $username, string $email, bool $validated = false, ?string $authenticationProvider = null): void
    {
        $user = $this->findUser($username, $authenticationProvider);
        $name = $user->getName();
        try {
            $this->userCommandService->updateUser($user, $name->getFirstName(), $name->getLastName(), $email);
        } catch (UserManagementException $e) {
            $this->outputLine('<error>%s</error>', [$e->getMessage()]);
            $this->quit(1);
        }
        if ($validated) {
            $this->userCommandService->markEmailValidated($user);
        }
        $this->persistenceManager->persistAll();
        $this->outputLine('<success>Primary email of %s set to %s (%s).</success>', [$username, $email, $validated ? 'validated' : 'not validated']);
    }

    /**
     * Delete expired and consumed tokens and old throttle entries - run this as a cron job, e.g. daily
     */
    public function pruneTokensCommand(): void
    {
        $tokens = $this->tokenService->prune();
        $throttleEntries = $this->throttle->prune('P1D');
        $this->outputLine('Removed %d token(s) and %d throttle entr(y/ies).', [$tokens, $throttleEntries]);
    }

    /**
     * List users sharing the same primary email (the user management module prevents new duplicates)
     */
    public function duplicateEmailsCommand(): void
    {
        $duplicates = $this->simpleUserFinder->findDuplicatePrimaryEmails();
        if ($duplicates === []) {
            $this->outputLine('<success>No duplicate primary emails found.</success>');
            return;
        }
        $rows = [];
        foreach ($duplicates as $email => $users) {
            foreach ($users as $user) {
                $rows[] = [$email, $user->displayName, implode(', ', $user->getAccountIdentifiers())];
            }
        }
        $this->output->outputTable($rows, ['Email', 'Name', 'Usernames']);
        $this->quit(1);
    }

    private function sendPasswordMail(string $username, ?string $authenticationProvider, UserActionType $action): void
    {
        if (!$this->settings['enabled']) {
            $this->outputLine('<error>User management is disabled (Sandstorm.NeosTwoFactorAuthentication.userManagement.enabled).</error>');
            $this->quit(1);
        }
        $user = $this->findUser($username, $authenticationProvider);
        try {
            $this->userCommandService->sendPasswordMail($user, $action);
        } catch (UserManagementException $e) {
            $this->outputLine('<error>%s</error>', [$e->getMessage()]);
            $this->quit(1);
        }
        $this->persistenceManager->persistAll();
        $this->outputLine('<success>Mail sent to %s.</success>', [$this->simpleUserFinder->getPrimaryEmail($user)?->getIdentifier()]);
    }

    private function findUser(string $username, ?string $authenticationProvider): User
    {
        foreach ($this->simpleUserFinder->findActiveAccountsByAccountIdentifier($username) as $account) {
            if ($authenticationProvider === null || $account->getAuthenticationProviderName() === $authenticationProvider) {
                $user = $this->simpleUserFinder->findUserOfAccount($account);
                if ($user !== null) {
                    return $user;
                }
            }
        }
        $this->outputLine('<error>No active user "%s" found.</error>', [$username]);
        $this->quit(1);
    }
}
