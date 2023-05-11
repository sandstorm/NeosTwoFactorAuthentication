<?php

namespace Sandstorm\NeosTwoFactorAuthentication\Controller;

use Neos\Error\Messages\Message;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Mvc\Exception\StopActionException;
use Neos\Flow\Mvc\FlashMessage\FlashMessageService;
use Neos\Flow\Persistence\Exception\IllegalObjectTypeException;
use Neos\Flow\Security\Context;
use Neos\Flow\Session\Exception\SessionNotStartedException;
use Neos\Neos\Controller\Module\AbstractModuleController;
use Neos\Fusion\View\FusionView;
use Neos\Neos\Domain\Model\User;
use Neos\Party\Domain\Service\PartyService;
use Sandstorm\NeosTwoFactorAuthentication\Domain\AuthenticationStatus;
use Sandstorm\NeosTwoFactorAuthentication\Domain\Model\SecondFactor;
use Sandstorm\NeosTwoFactorAuthentication\Domain\Model\Dto\SecondFactorDto;
use Sandstorm\NeosTwoFactorAuthentication\Domain\Repository\SecondFactorRepository;
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

    protected $defaultViewObjectName = FusionView::class;

    /**
     * @Flow\InjectConfiguration(path="enforceTwoFactorAuthentication")
     * @var bool
     */
    protected $enforceTwoFactorAuthentication;

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
            $this->addFlashMessage('Submitted OTP was not correct', '', Message::SEVERITY_WARNING);
            $this->redirect('new');
        }

        $this->secondFactorRepository->createSecondFactorForAccount($secret, $this->securityContext->getAccount());

        $this->secondFactorSessionStorageService->setAuthenticationStatus(AuthenticationStatus::AUTHENTICATED);

        $this->addFlashMessage('Successfully created otp');
        $this->redirect('index');
    }

    /**
     * @param SecondFactor $secondFactor
     * @return void
     */
    public function deleteAction(SecondFactor $secondFactor): void
    {
        $account = $this->securityContext->getAccount();

        if (
            $this->securityContext->hasRole('Neos.Neos:Administrator')
            || $secondFactor->getAccount() === $account
        ) {
            if (
                $this->enforceTwoFactorAuthentication
                && count($this->secondFactorRepository->findByAccount($account)) <= 1
            ) {
                $this->addFlashMessage(
                    'Can not remove last second factor! Second factor is enforced, you need at least one!',
                    'Error',
                    Message::SEVERITY_ERROR
                );
            } else {
                $this->secondFactorRepository->remove($secondFactor);
                $this->persistenceManager->persistAll();
                $this->addFlashMessage('Second factor was deleted');
            }
        }

        $this->redirect('index');
    }
}
