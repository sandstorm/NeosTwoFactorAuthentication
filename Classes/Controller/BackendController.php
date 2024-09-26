<?php

namespace Sandstorm\NeosTwoFactorAuthentication\Controller;

use Neos\Error\Messages\Message;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\I18n\Translator;
use Neos\Flow\Mvc\Exception\StopActionException;
use Neos\Flow\Mvc\FlashMessage\FlashMessageService;
use Neos\Flow\Persistence\Exception\IllegalObjectTypeException;
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
     * show the form to register a new second factor
     */
    public function newAction(): void
    {
        $otp = TOTPService::generateNewTotp();
        $secret = $otp->getSecret();
        $qrCode = $this->tOTPService->generateQRCodeForTokenAndAccount($otp, $this->securityContext->getAccount());

        $this->view->assignMultiple([
            'secret' => $secret,
            'qrCode' => $qrCode,
            'flashMessages' => $this->flashMessageService
                ->getFlashMessageContainerForRequest($this->request)
                ->getMessagesAndFlush(),
        ]);
    }

    /**
     * save the registered second factor
     *
     * @throws SessionNotStartedException
     * @throws IllegalObjectTypeException
     * @throws StopActionException
     */
    public function createAction(string $secret, string $secondFactorFromApp): void
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
            $this->redirect('new');
        }

        $this->secondFactorRepository->createSecondFactorForAccount($secret, $this->securityContext->getAccount());

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
            $this->persistenceManager->persistAll();
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
}
