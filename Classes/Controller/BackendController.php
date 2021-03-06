<?php

namespace Sandstorm\NeosTwoFactorAuthentication\Controller;

use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Neos\Error\Messages\Message;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Mvc\FlashMessage\FlashMessageService;
use Neos\Flow\Security\Context;
use Neos\Neos\Controller\Module\AbstractModuleController;
use Neos\Fusion\View\FusionView;
use Neos\Neos\Domain\Model\User;
use Neos\Neos\Domain\Repository\DomainRepository;
use Neos\Neos\Domain\Repository\SiteRepository;
use Neos\Party\Domain\Service\PartyService;
use Sandstorm\NeosTwoFactorAuthentication\Domain\Model\SecondFactor;
use Sandstorm\NeosTwoFactorAuthentication\Domain\Model\Dto\SecondFactorDto;
use Sandstorm\NeosTwoFactorAuthentication\Domain\Repository\SecondFactorRepository;
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
     * @var DomainRepository
     * @Flow\Inject
     */
    protected $domainRepository;

    /**
     * @Flow\Inject
     * @var SiteRepository
     */
    protected $siteRepository;

    /**
     * @Flow\Inject
     * @var FlashMessageService
     */
    protected $flashMessageService;

    protected $defaultViewObjectName = FusionView::class;

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
            'flashMessages' => $this->flashMessageService->getFlashMessageContainerForRequest($this->request)->getMessagesAndFlush(),
        ]);
    }

    /**
     * show the form to register a new second factor
     */
    public function newAction()
    {
        $otp = TOTPService::generateNewTotp();
        $secret = $otp->getSecret();

        $currentDomain = $this->domainRepository->findOneByActiveRequest();
        $currentSite = $currentDomain !== null ? $currentDomain->getSite() : $this->siteRepository->findDefault();
        $currentSiteName = $currentSite->getName();
        $urlEncodedSiteName = urlencode($currentSiteName);

        $userIdentifier = $this->securityContext->getAccount()->getAccountIdentifier();
        $oauthData = "otpauth://totp/$userIdentifier?secret=$secret&period=30&issuer=$urlEncodedSiteName";
        $qrCode = (new QRCode(new QROptions([
            'outputType' => QRCode::OUTPUT_MARKUP_SVG
        ])))->render($oauthData);

        $this->view->assignMultiple([
            'secret' => $secret,
            'qrCode' => $qrCode,
            'flashMessages' => $this->flashMessageService->getFlashMessageContainerForRequest($this->request)->getMessagesAndFlush(),
        ]);
    }

    /**
     * save the registered second factor
     */
    public function createAction(string $secret, string $secondFactorFromApp)
    {
        $isValid = TOTPService::checkIfOtpIsValid($secret, $secondFactorFromApp);

        if (!$isValid) {
            $this->addFlashMessage('Submitted OTP was not correct', '', Message::SEVERITY_WARNING);
            $this->redirect('new');
        }

        $secondFactor = new SecondFactor();
        $secondFactor->setAccount($this->securityContext->getAccount());
        $secondFactor->setSecret($secret);
        $secondFactor->setType(SecondFactor::TYPE_TOTP);
        $this->secondFactorRepository->add($secondFactor);
        $this->persistenceManager->persistAll();

        $this->addFlashMessage('Successfully created otp');
        $this->redirect('index');
    }

    /**
     * @param SecondFactor $secondFactor
     * @return void
     */
    public function deleteAction(SecondFactor $secondFactor)
    {
        if (
            $this->securityContext->hasRole('Neos.Neos:Administrator')
            || $secondFactor->getAccount() === $this->securityContext->getAccount()
        ) {
            $this->secondFactorRepository->remove($secondFactor);
            $this->persistenceManager->persistAll();
            $this->addFlashMessage('Second factor was deleted');
        }

        $this->redirect('index');
    }
}
