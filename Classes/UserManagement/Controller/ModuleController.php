<?php

declare(strict_types=1);

namespace Sandstorm\NeosTwoFactorAuthentication\UserManagement\Controller;

use Neos\Error\Messages\Message;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\I18n\Translator;
use Neos\Flow\Mvc\FlashMessage\FlashMessageService;
use Neos\Flow\Mvc\Routing\UriBuilder;
use Neos\Flow\Persistence\PersistenceManagerInterface;
use Neos\Flow\Security\Account;
use Neos\Flow\Security\Authorization\PrivilegeManagerInterface;
use Neos\Flow\Security\Policy\PolicyService;
use Neos\Flow\Security\Policy\Role;
use Neos\Fusion\View\FusionView;
use Neos\Neos\Controller\Module\AbstractModuleController;
use Neos\Neos\Domain\Model\User;
use Neos\Neos\Domain\Service\UserService;
use Neos\Neos\Service\ImpersonateService;
use Neos\Neos\Validation\Validator\PasswordValidator;
use Sandstorm\NeosTwoFactorAuthentication\UserManagement\Application\SimpleUserFinder;
use Sandstorm\NeosTwoFactorAuthentication\UserManagement\Application\UserCommandService;
use Sandstorm\NeosTwoFactorAuthentication\UserManagement\Application\UserMailer;
use Sandstorm\NeosTwoFactorAuthentication\UserManagement\Application\UserManagementException;
use Sandstorm\NeosTwoFactorAuthentication\UserManagement\Domain\UserActionType;

/**
 * Backend module replacing the core user module (administration/users).
 *
 * Mirrors the core permission rules: administrators may manage everybody, user managers only users having the same
 * or a subset of their own roles, and nobody may delete, deactivate or lock out themselves.
 */
class ModuleController extends AbstractModuleController
{
    /**
     * @var string
     */
    protected $defaultViewObjectName = FusionView::class;

    #[Flow\Inject]
    protected SimpleUserFinder $simpleUserFinder;

    #[Flow\Inject]
    protected UserCommandService $userCommandService;

    #[Flow\Inject]
    protected UserMailer $userMailer;

    #[Flow\Inject]
    protected UserService $userService;

    #[Flow\Inject]
    protected PolicyService $policyService;

    #[Flow\Inject]
    protected PrivilegeManagerInterface $privilegeManager;

    #[Flow\Inject]
    protected ImpersonateService $impersonateService;

    #[Flow\Inject]
    protected PersistenceManagerInterface $persistenceManager;

    #[Flow\Inject]
    protected FlashMessageService $flashMessageService;

    #[Flow\Inject]
    protected Translator $translator;

    #[Flow\InjectConfiguration(path: 'userManagement.usernameIsEmail')]
    protected bool $usernameIsEmail = false;

    /** @var array{minimumLength: int, maximumLength: int} */
    #[Flow\InjectConfiguration(path: 'userManagement.passwordPolicy')]
    protected array $passwordPolicy;

    #[Flow\InjectConfiguration(path: 'userManagement.mail.from.address')]
    protected ?string $mailFromAddress = null;

    #[Flow\InjectConfiguration(package: 'Neos.SymfonyMailer', path: 'mailer.dsn')]
    protected ?string $mailerDsn = null;

    public function indexAction(string $searchTerm = '', string $authenticationProviderName = ''): void
    {
        $this->view->assignMultiple([
            'users' => $this->simpleUserFinder->findAll($searchTerm, $authenticationProviderName),
            'searchTerm' => $searchTerm,
            'authenticationProviderName' => $authenticationProviderName,
            'providers' => $this->simpleUserFinder->getManagedProviders(),
            'currentUserId' => $this->currentUserId(),
            'mailConfigurationWarning' => $this->mailConfigurationWarning(),
            'flashMessages' => $this->flushFlashMessages(),
        ]);
    }

    /**
     * Sends a test mail to the current user, to check the SMTP configuration.
     */
    public function sendTestMailAction(): void
    {
        $currentUser = $this->userService->getCurrentUser();
        $primaryEmail = $currentUser !== null ? $this->simpleUserFinder->getPrimaryEmail($currentUser) : null;
        if ($primaryEmail === null) {
            $this->addTranslatedFlashMessage('error.noEmailForTestMail', [], Message::SEVERITY_ERROR);
            $this->redirect('index');
        }
        try {
            $this->userMailer->sendTestMail($primaryEmail->getIdentifier(), $currentUser);
            $this->addTranslatedFlashMessage('flash.testMailSent', [$primaryEmail->getIdentifier()]);
        } catch (\Throwable $exception) {
            $this->addFlashMessage(
                $exception->getMessage(),
                $this->translate('flash.testMailFailed'),
                Message::SEVERITY_ERROR
            );
        }
        $this->redirect('index');
    }

    /**
     * @param array<string, mixed> $formValues values of a failed create attempt
     */
    public function newAction(array $formValues = []): void
    {
        $this->view->assignMultiple([
            'formValues' => array_merge([
                'firstName' => '',
                'lastName' => '',
                'email' => '',
                'username' => '',
                'roleIdentifiers' => ['Neos.Neos:Editor'],
                'authenticationProviderName' => $this->simpleUserFinder->getManagedProviders()[0] ?? '',
                'sendPasswordMail' => true,
            ], $formValues),
            'roles' => $this->getAllowedRoles(),
            'providers' => $this->simpleUserFinder->getManagedProviders(),
            'usernameIsEmail' => $this->usernameIsEmail,
            'flashMessages' => $this->flushFlashMessages(),
        ]);
    }

    /**
     * @param array<string> $roleIdentifiers
     */
    public function createAction(
        string $firstName,
        string $lastName,
        string $email,
        string $username = '',
        array $roleIdentifiers = [],
        string $authenticationProviderName = '',
        bool $sendPasswordMail = false,
        string $password = '',
        string $passwordConfirmation = ''
    ): void {
        $formValues = [
            'firstName' => $firstName,
            'lastName' => $lastName,
            'email' => $email,
            'username' => $username,
            'roleIdentifiers' => $roleIdentifiers,
            'authenticationProviderName' => $authenticationProviderName,
            'sendPasswordMail' => $sendPasswordMail,
        ];

        if (!$this->areRolesAssignable($roleIdentifiers)) {
            $this->addTranslatedFlashMessage('error.rolesNotAllowed', [], Message::SEVERITY_ERROR);
            $this->forward('new', null, null, ['formValues' => $formValues]);
        }
        if (!$sendPasswordMail) {
            $passwordError = $this->validatePassword($password, $passwordConfirmation);
            if ($passwordError !== null) {
                $this->addFlashMessage($passwordError, '', Message::SEVERITY_ERROR);
                $this->forward('new', null, null, ['formValues' => $formValues]);
            }
        }

        try {
            $user = $this->userCommandService->createUser(
                $firstName,
                $lastName,
                $email,
                $username,
                array_values($roleIdentifiers),
                $authenticationProviderName,
                $sendPasswordMail ? null : $password
            );
        } catch (UserManagementException $e) {
            $this->addTranslatedFlashMessage($e->getMessage(), [], Message::SEVERITY_ERROR);
            $this->forward('new', null, null, ['formValues' => $formValues]);
        }
        $this->persistenceManager->persistAll();
        $this->addTranslatedFlashMessage('flash.userCreated', [$user->getName()->getFullName()]);

        // the initial password link validates the email implicitly, so only one of the two mails is needed
        $this->sendMail(fn () => $sendPasswordMail
            ? $this->userCommandService->sendPasswordMail($user, UserActionType::INITIAL_PASSWORD)
            : $this->userCommandService->sendEmailVerificationMail($user), $sendPasswordMail ? 'flash.initialPasswordMailSent' : 'flash.emailVerificationMailSent');

        $this->redirect('edit', null, null, ['userId' => $this->persistenceManager->getIdentifierByObject($user)]);
    }

    public function editAction(string $userId): void
    {
        $this->requireEditableUser($userId);
        $isSelf = $userId === $this->currentUserId();
        $this->view->assignMultiple([
            'user' => $this->simpleUserFinder->findById($userId),
            'roles' => $this->getAllowedRoles(),
            'isSelf' => $isSelf,
            'canImpersonate' => !$isSelf && !$this->impersonateService->isActive() && $this->privilegeManager->isPrivilegeTargetGranted('Neos.Neos:Impersonate'),
            'usernameIsEmail' => $this->usernameIsEmail,
            'passwordMinimumLength' => $this->passwordPolicy['minimumLength'],
            'flashMessages' => $this->flushFlashMessages(),
        ]);
    }

    public function updateAction(string $userId, string $firstName, string $lastName, string $email, bool $active = false): void
    {
        $user = $this->requireEditableUser($userId);
        $isSelf = $userId === $this->currentUserId();

        try {
            $emailChanged = $this->userCommandService->updateUser($user, $firstName, $lastName, $email);
        } catch (UserManagementException $e) {
            $this->addTranslatedFlashMessage($e->getMessage(), [], Message::SEVERITY_ERROR);
            $this->redirect('edit', null, null, ['userId' => $userId]);
        }
        $simpleUser = $this->simpleUserFinder->findById($userId);
        if (!$isSelf && $simpleUser !== null && $simpleUser->isActive() !== $active) {
            $this->userCommandService->setActive($user, $active);
        }
        $this->persistenceManager->persistAll();
        $this->addTranslatedFlashMessage('flash.userUpdated', [$user->getName()->getFullName()]);

        if ($emailChanged) {
            $this->sendMail(fn () => $this->userCommandService->sendEmailVerificationMail($user), 'flash.emailVerificationMailSent');
        }
        $this->redirect('edit', null, null, ['userId' => $userId]);
    }

    /**
     * @param array<string> $roleIdentifiers
     */
    public function updateAccountRolesAction(string $userId, string $accountId, array $roleIdentifiers = []): void
    {
        $this->requireEditableUser($userId);
        $account = $this->requireAccountOfUser($userId, $accountId);

        if (!$this->areRolesAssignable($roleIdentifiers)) {
            $this->addTranslatedFlashMessage('error.rolesNotAllowed', [], Message::SEVERITY_ERROR);
            $this->redirect('edit', null, null, ['userId' => $userId]);
        }
        if ($userId === $this->currentUserId() && !$this->rolesKeepModuleAccess($roleIdentifiers)) {
            $this->addTranslatedFlashMessage('error.doNotLockYourselfOut', [], Message::SEVERITY_WARNING);
            $this->redirect('edit', null, null, ['userId' => $userId]);
        }
        $this->userCommandService->setRolesForAccount($account, array_values($roleIdentifiers));
        $this->addTranslatedFlashMessage('flash.rolesUpdated', [$account->getAccountIdentifier()]);
        $this->redirect('edit', null, null, ['userId' => $userId]);
    }

    public function setPasswordAction(string $userId, string $password = '', string $passwordConfirmation = ''): void
    {
        $user = $this->requireEditableUser($userId);
        $passwordError = $this->validatePassword($password, $passwordConfirmation);
        if ($passwordError !== null) {
            $this->addFlashMessage($passwordError, '', Message::SEVERITY_ERROR);
            $this->redirect('edit', null, null, ['userId' => $userId]);
        }
        $this->userCommandService->setPassword($user, $password);
        $this->addTranslatedFlashMessage('flash.passwordSet');
        $this->redirect('edit', null, null, ['userId' => $userId]);
    }

    /**
     * @param string $type 'resetPassword' or 'initialPassword'
     */
    public function sendPasswordMailAction(string $userId, string $type): void
    {
        $user = $this->requireEditableUser($userId);
        $action = UserActionType::tryFrom($type);
        if ($action === null || !$action->isPasswordAction()) {
            $this->throwStatus(400);
        }
        $this->sendMail(
            fn () => $this->userCommandService->sendPasswordMail($user, $action),
            $action === UserActionType::INITIAL_PASSWORD ? 'flash.initialPasswordMailSent' : 'flash.resetPasswordMailSent'
        );
        $this->redirect('edit', null, null, ['userId' => $userId]);
    }

    public function sendEmailVerificationAction(string $userId): void
    {
        $user = $this->requireEditableUser($userId);
        $this->sendMail(fn () => $this->userCommandService->sendEmailVerificationMail($user), 'flash.emailVerificationMailSent');
        $this->redirect('edit', null, null, ['userId' => $userId]);
    }

    public function markEmailValidatedAction(string $userId): void
    {
        $user = $this->requireEditableUser($userId);
        $usernameChanges = $this->userCommandService->markEmailValidated($user);
        $this->addTranslatedFlashMessage('flash.emailMarkedValidated');
        foreach ($usernameChanges as $usernameChange) {
            $usernameChange->changed
                ? $this->addTranslatedFlashMessage('flash.usernameChanged', [$usernameChange->oldUsername, $usernameChange->newUsername])
                : $this->addTranslatedFlashMessage('flash.usernameNotChanged', [$usernameChange->oldUsername, $usernameChange->newUsername], Message::SEVERITY_WARNING);
        }
        $this->redirect('edit', null, null, ['userId' => $userId]);
    }

    public function resetSecondFactorsAction(string $userId): void
    {
        $user = $this->requireEditableUser($userId);
        $count = $this->userCommandService->resetSecondFactors($user);
        $this->addTranslatedFlashMessage('flash.secondFactorsReset', [$count]);
        $this->redirect('edit', null, null, ['userId' => $userId]);
    }

    public function impersonateAction(string $userId, string $accountId): void
    {
        $this->requireEditableUser($userId);
        $account = $this->requireAccountOfUser($userId, $accountId);
        if ($userId === $this->currentUserId() || !$this->privilegeManager->isPrivilegeTargetGranted('Neos.Neos:Impersonate')) {
            $this->throwStatus(403);
        }
        $this->impersonateService->impersonate($account);
        // the module runs in a sub request - build the backend URI from the main request
        $uriBuilder = new UriBuilder();
        $uriBuilder->setRequest($this->request->getMainRequest());
        $this->redirectToUri($uriBuilder->setCreateAbsoluteUri(true)->uriFor('index', [], 'Backend\Backend', 'Neos.Neos'));
    }

    /**
     * Confirmation page for deleting one or several (bulk) users.
     *
     * @param array<string> $userIds
     */
    public function confirmDeleteAction(array $userIds = []): void
    {
        $userIds = $this->filterDeletableUserIds($userIds);
        if ($userIds === []) {
            $this->addTranslatedFlashMessage('error.nothingToDelete', [], Message::SEVERITY_WARNING);
            $this->redirect('index');
        }
        $this->view->assignMultiple([
            'users' => array_values(array_filter(array_map(fn (string $userId) => $this->simpleUserFinder->findById($userId), $userIds))),
            'flashMessages' => $this->flushFlashMessages(),
        ]);
    }

    /**
     * @param array<string> $userIds
     */
    public function deleteAction(array $userIds = []): void
    {
        $deleted = 0;
        foreach ($this->filterDeletableUserIds($userIds) as $userId) {
            $user = $this->simpleUserFinder->findUserEntityById($userId);
            if ($user !== null) {
                $this->userCommandService->deleteUser($user);
                $deleted++;
            }
        }
        $this->addTranslatedFlashMessage('flash.usersDeleted', [$deleted], Message::SEVERITY_NOTICE);
        $this->redirect('index');
    }

    /**
     * Drops unknown ids, the current user and users the current user may not edit.
     *
     * @param array<mixed> $userIds
     * @return list<string>
     */
    private function filterDeletableUserIds(array $userIds): array
    {
        $result = [];
        foreach ($userIds as $userId) {
            if (!is_string($userId) || $userId === $this->currentUserId()) {
                continue;
            }
            $user = $this->simpleUserFinder->findUserEntityById($userId);
            if ($user !== null && $this->isEditingAllowed($user)) {
                $result[] = $userId;
            }
        }
        return array_values(array_unique($result));
    }

    /**
     * @param \Closure(): void $send
     */
    private function sendMail(\Closure $send, string $successMessageId): void
    {
        try {
            $send();
            $this->addTranslatedFlashMessage($successMessageId);
        } catch (UserManagementException $e) {
            $this->addTranslatedFlashMessage($e->getMessage(), [], Message::SEVERITY_ERROR);
        } catch (\Throwable $e) {
            $this->addFlashMessage($e->getMessage(), $this->translate('flash.mailFailed'), Message::SEVERITY_ERROR);
        }
    }

    private function requireEditableUser(string $userId): User
    {
        $user = $this->simpleUserFinder->findUserEntityById($userId);
        if ($user === null || $this->simpleUserFinder->getManagedAccounts($user) === []) {
            $this->throwStatus(404);
        }
        if (!$this->isEditingAllowed($user)) {
            $this->addTranslatedFlashMessage('error.editingNotAllowed', [], Message::SEVERITY_ERROR);
            $this->redirect('index');
        }
        return $user;
    }

    private function requireAccountOfUser(string $userId, string $accountId): Account
    {
        $account = $this->simpleUserFinder->findAccountEntityById($accountId);
        $owner = $account !== null ? $this->simpleUserFinder->findUserOfAccount($account) : null;
        if ($account === null || $owner === null || $this->persistenceManager->getIdentifierByObject($owner) !== $userId) {
            $this->throwStatus(404);
        }
        return $account;
    }

    private function currentUserId(): ?string
    {
        $currentUser = $this->userService->getCurrentUser();
        return $currentUser !== null ? (string)$this->persistenceManager->getIdentifierByObject($currentUser) : null;
    }

    /**
     * Same rule as the core user module: administrators may edit anybody, others only users having the same or a
     * subset of their roles.
     */
    private function isEditingAllowed(User $user): bool
    {
        if ($this->userService->currentUserIsAdministrator()) {
            return true;
        }
        $currentUser = $this->userService->getCurrentUser();
        if ($currentUser === null) {
            return false;
        }
        return array_diff(
            array_keys($this->userService->getAllRoles($user)),
            array_keys($this->userService->getAllRoles($currentUser))
        ) === [];
    }

    /**
     * Same rule as the core user module: administrators may assign any role, others only their own roles.
     *
     * @return list<Role>
     */
    private function getAllowedRoles(): array
    {
        if ($this->userService->currentUserIsAdministrator()) {
            $roles = $this->policyService->getRoles();
        } else {
            $currentUser = $this->userService->getCurrentUser();
            $roles = $currentUser !== null ? $this->userService->getAllRoles($currentUser) : [];
        }
        $roles = array_values(array_filter($roles, fn (Role $role) => !$role->isAbstract()));
        usort($roles, fn (Role $a, Role $b) => strcmp($a->getLabel(), $b->getLabel()));
        return $roles;
    }

    /**
     * @param array<mixed> $roleIdentifiers
     */
    private function areRolesAssignable(array $roleIdentifiers): bool
    {
        $allowed = array_map(fn (Role $role) => $role->getIdentifier(), $this->getAllowedRoles());
        foreach ($roleIdentifiers as $roleIdentifier) {
            if (!in_array($roleIdentifier, $allowed, true)) {
                return false;
            }
        }
        return true;
    }

    /**
     * @param array<mixed> $roleIdentifiers
     */
    private function rolesKeepModuleAccess(array $roleIdentifiers): bool
    {
        $roles = [];
        foreach ($roleIdentifiers as $roleIdentifier) {
            if (is_string($roleIdentifier) && $this->policyService->hasRole($roleIdentifier)) {
                $roles[$roleIdentifier] = $this->policyService->getRole($roleIdentifier);
            }
        }
        return $this->privilegeManager->isPrivilegeTargetGrantedForRoles($roles, 'Sandstorm.NeosTwoFactorAuthentication:Backend.Module.Administration.UserManagement');
    }

    private function validatePassword(string $password, string $passwordConfirmation): ?string
    {
        $minimum = $this->passwordPolicy['minimumLength'];
        $maximum = $this->passwordPolicy['maximumLength'];
        $validationResult = (new PasswordValidator(['allowEmpty' => false, 'minimum' => $minimum, 'maximum' => $maximum]))
            ->validate([$password, $passwordConfirmation]);
        if (!$validationResult->hasErrors()) {
            return null;
        }
        if (trim($password) !== trim($passwordConfirmation)) {
            return $this->translate('password.error.mismatch');
        }
        return $this->translate('password.error.length', [$minimum, $maximum]);
    }

    /**
     * Cheap configuration check shown on the index page; the real check is the test mail.
     */
    private function mailConfigurationWarning(): ?string
    {
        if ($this->mailFromAddress === null || $this->mailFromAddress === '') {
            return $this->translate('warning.mailFromMissing');
        }
        if ($this->mailerDsn === null || $this->mailerDsn === '' || str_starts_with($this->mailerDsn, 'null://')) {
            return $this->translate('warning.mailerDsnMissing');
        }
        return null;
    }

    /**
     * @return array<Message>
     */
    private function flushFlashMessages(): array
    {
        return $this->flashMessageService->getFlashMessageContainerForRequest($this->request)->getMessagesAndFlush();
    }

    /**
     * @param array<int|string, mixed> $arguments
     */
    private function addTranslatedFlashMessage(string $id, array $arguments = [], string $severity = Message::SEVERITY_OK): void
    {
        $this->addFlashMessage($this->translate($id, $arguments), '', $severity);
    }

    /**
     * @param array<int|string, mixed> $arguments
     */
    private function translate(string $id, array $arguments = []): string
    {
        return (string)$this->translator->translateById($id, $arguments, null, null, 'UserManagement', 'Sandstorm.NeosTwoFactorAuthentication');
    }
}
