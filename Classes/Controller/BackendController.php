<?php

namespace Sandstorm\NeosTwoFactorAuthentication\Controller;

use Neos\Error\Messages\Message;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Configuration\ConfigurationManager;
use Neos\Flow\Configuration\Exception\InvalidConfigurationTypeException;
use Neos\Flow\I18n\Translator;
use Neos\Flow\Mvc\Exception\StopActionException;
use Neos\Flow\Mvc\FlashMessage\FlashMessageService;
use Neos\Flow\Persistence\Exception\IllegalObjectTypeException;
use Neos\Flow\Persistence\PersistenceManagerInterface;
use Neos\Flow\Security\Context;
use Neos\Flow\Session\Exception\SessionNotStartedException;
use Neos\Fusion\View\FusionView;
use Neos\Neos\Controller\Module\AbstractModuleController;
use Neos\Neos\Domain\Model\User;
use Neos\Party\Domain\Service\PartyService;
use Sandstorm\NeosTwoFactorAuthentication\Domain\AuthenticationStatus;
use Sandstorm\NeosTwoFactorAuthentication\Domain\Model\Dto\SecondFactorDto;
use Sandstorm\NeosTwoFactorAuthentication\Domain\Model\SecondFactor;
use Sandstorm\NeosTwoFactorAuthentication\Domain\Repository\SecondFactorRepository;
use Sandstorm\NeosTwoFactorAuthentication\Domain\SecondFactorMethod\SecondFactorMethodRegistry;
use Sandstorm\NeosTwoFactorAuthentication\Service\SecondFactorService;
use Sandstorm\NeosTwoFactorAuthentication\Service\SecondFactorSessionStorageService;
use Sandstorm\NeosTwoFactorAuthentication\Service\TOTPService;

/**
 * @Flow\Scope("singleton")
 */
class BackendController extends AbstractModuleController
{
    /**
     * @var SecondFactorRepository
     * @Flow\Inject
     */
    protected $secondFactorRepository;

    /**
     * @var Context
     * @Flow\Inject
     */
    protected $securityContext;

    /**
     * @var PartyService
     * @Flow\Inject
     */
    protected $partyService;

    /**
     * @Flow\Inject
     * @var FlashMessageService
     */
    protected $flashMessageService;

    /**
     * @Flow\Inject
     * @var SecondFactorSessionStorageService
     */
    protected $secondFactorSessionStorageService;

    /**
     * @Flow\Inject
     * @var TOTPService
     */
    protected $tOTPService;

    /**
     * @Flow\Inject
     * @var Translator
     */
    protected $translator;

    protected $defaultViewObjectName = FusionView::class;

    /**
     * @Flow\Inject
     * @var SecondFactorService
     */
    protected $secondFactorService;

    /**
     * @Flow\Inject
     * @var SecondFactorMethodRegistry
     */
    protected $methodRegistry;

    /**
     * @Flow\Inject
     * @var PersistenceManagerInterface
     */
    protected $persistenceManager;

    /**
     * used to list all second factors of the current user
     */
    public function indexAction()
    {
        $account = $this->securityContext->getAccount();

        if ($this->securityContext->hasRole('Neos.Neos:Administrator')) {
            $factors = $this->secondFactorRepository->findAll();
        } else {
            $factors = $this->secondFactorRepository->findByAccount($account);
        }

        $factorsAndPerson = array_map(function ($factor) {
            /** @var SecondFactor $factor */
            $party = $this->partyService->getAssignedPartyOfAccount($factor->getAccount());
            $user = null;
            if ($party instanceof User) {
                $user = $party;
            }
            return new SecondFactorDto($factor, $user);
        }, $factors->toArray());

        $this->view->assignMultiple([
            'factorsAndPerson' => $factorsAndPerson,
            'flashMessages' => $this->flashMessageService
                ->getFlashMessageContainerForRequest($this->request)
                ->getMessagesAndFlush(),
        ]);
    }

    /**
     * Method picker shown when the user clicks "Add second factor" inside the backend module.
     */
    public function newAction(): void
    {
        $account = $this->securityContext->getAccount();
        $currentUser = $this->partyService->getAssignedPartyOfAccount($account);

        $this->view->assignMultiple([
            'currentUser' => $currentUser instanceof User ? $currentUser : null,
            'accountIdentifier' => $account->getAccountIdentifier(),
            'methods' => $this->methodRegistry->getAll(),
            'flashMessages' => $this->flashMessageService
                ->getFlashMessageContainerForRequest($this->request)
                ->getMessagesAndFlush(),
        ]);
    }

    /**
     * TOTP wizard (extracted from the previous newAction).
     */
    public function newTotpAction(): void
    {
        $otp = TOTPService::generateNewTotp();
        $secret = $otp->getSecret();
        $qrCode = $this->tOTPService->generateQRCodeForTokenAndAccount($otp, $this->securityContext->getAccount());

        $account = $this->securityContext->getAccount();
        $currentUser = $this->partyService->getAssignedPartyOfAccount($account);

        $this->view->assignMultiple([
            'secret' => $secret,
            'qrCode' => $qrCode,
            'currentUser' => $currentUser instanceof User ? $currentUser : null,
            'accountIdentifier' => $account->getAccountIdentifier(),
            'flashMessages' => $this->flashMessageService
                ->getFlashMessageContainerForRequest($this->request)
                ->getMessagesAndFlush(),
        ]);
    }

    /**
     * WebAuthn setup wizard. The JS on the page talks to LoginController's
     * webAuthnRegister(Options|Verify)Action XHR endpoints.
     */
    public function newWebAuthnAction(): void
    {
        $account = $this->securityContext->getAccount();
        $currentUser = $this->partyService->getAssignedPartyOfAccount($account);

        $this->view->assignMultiple([
            'currentUser' => $currentUser instanceof User ? $currentUser : null,
            'accountIdentifier' => $account->getAccountIdentifier(),
            'flashMessages' => $this->flashMessageService
                ->getFlashMessageContainerForRequest($this->request)
                ->getMessagesAndFlush(),
        ]);
    }

    /**
     * save the registered second factor (TOTP)
     *
     * @throws SessionNotStartedException
     * @throws IllegalObjectTypeException
     * @throws StopActionException
     */
    public function createAction(string $secret, string $secondFactorFromApp, string $name = ''): void
    {
        $isValid = TOTPService::checkIfOtpIsValid($secret, $secondFactorFromApp);

        if (!$isValid) {
            $this->addFlashMessage(
                $this->translator->translateById(
                    'module.new.flashMessage.submittedOtpIncorrect',
                    [],
                    null,
                    null,
                    'Backend',
                    'Sandstorm.NeosTwoFactorAuthentication'
                ),
                '',
                Message::SEVERITY_WARNING
            );
            $this->redirect('newTotp');
        }

        $this->secondFactorRepository->createSecondFactorForAccount(
            $secret,
            $this->securityContext->getAccount(),
            SecondFactor::TYPE_TOTP,
            $name,
        );

        $this->secondFactorSessionStorageService->setAuthenticationStatus(AuthenticationStatus::AUTHENTICATED);

        $this->addFlashMessage(
            $this->translator->translateById(
                'module.new.flashMessage.successfullyRegisteredOtp',
                [],
                null,
                null,
                'Backend',
                'Sandstorm.NeosTwoFactorAuthentication'
            )
        );
        $this->redirect('index');
    }

    /**
     * @param SecondFactor $secondFactor
     * @return void
     */
    public function deleteAction(SecondFactor $secondFactor): void
    {
        $account = $this->securityContext->getAccount();

        $isAdministrator = $this->securityContext->hasRole('Neos.Neos:Administrator');
        $isOwner = $secondFactor->getAccount() === $account;

        // Check, if user is allowed to remove second factor
        if ($isAdministrator || ($isOwner && $this->secondFactorService->canOneSecondFactorBeDeletedForAccount($account))) {
            // User is admin or has more than one second factor
            $this->secondFactorRepository->remove($secondFactor);
            // neos8 backwards compatibility
            $this->persistenceManager?->persistAll();

            $this->addFlashMessage(
                $this->translator->translateById(
                    'module.index.delete.flashMessage.secondFactorDeleted',
                    [],
                    null,
                    null,
                    'Backend',
                    'Sandstorm.NeosTwoFactorAuthentication'
                )
            );
        } elseif ($isOwner) {
            // User is owner (but not admin) and has only one second factor -> factor can not be deleted
            $this->addFlashMessage(
                $this->translator->translateById(
                    'module.index.delete.flashMessage.cannotRemoveLastSecondFactor',
                    [],
                    null,
                    null,
                    'Backend',
                    'Sandstorm.NeosTwoFactorAuthentication'
                ),
                $this->translator->translateById(
                    'module.index.delete.flashMessage.errorHeader',
                    [],
                    null,
                    null,
                    'Backend',
                    'Sandstorm.NeosTwoFactorAuthentication'
                ),
                Message::SEVERITY_ERROR
            );
        }

        $this->redirect('index');
    }

    /**
     * @return array
     * @throws InvalidConfigurationTypeException
     */
    protected function getNeosSettings(): array
    {
        $configurationManager = $this->objectManager->get(ConfigurationManager::class);
        return $configurationManager->getConfiguration(
            ConfigurationManager::CONFIGURATION_TYPE_SETTINGS,
            'Neos.Neos'
        );
    }
}
