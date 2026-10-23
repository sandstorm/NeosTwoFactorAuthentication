<?php

declare(strict_types=1);

namespace Sandstorm\NeosTwoFactorAuthentication\UserManagement\Controller;

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Mvc\Controller\ActionController;
use Neos\Flow\Mvc\FlashMessage\FlashMessageService;
use Neos\Fusion\View\FusionView;
use Neos\Neos\Domain\Repository\DomainRepository;
use Neos\Neos\Domain\Repository\SiteRepository;

/**
 * Base for the unauthenticated user management screens, rendered in the Neos login box layout.
 */
abstract class AbstractPublicController extends ActionController
{
    /**
     * @var string
     */
    protected $defaultViewObjectName = FusionView::class;

    #[Flow\Inject]
    protected DomainRepository $domainRepository;

    #[Flow\Inject]
    protected SiteRepository $siteRepository;

    #[Flow\Inject]
    protected FlashMessageService $flashMessageService;

    #[Flow\InjectConfiguration(path: 'userManagement.enabled')]
    protected bool $userManagementEnabled = false;

    /** @var array{stylesheets?: array<string, ?string>, scripts?: array<string, ?string>} */
    #[Flow\InjectConfiguration(package: 'Neos.Neos', path: 'userInterface.backendLoginForm')]
    protected array $backendLoginFormSettings = [];

    protected function initializeAction(): void
    {
        if (!$this->userManagementEnabled) {
            $this->throwStatus(404);
        }
        // the token is part of the URL - never leak it to other origins
        $this->response->setHttpHeader('Referrer-Policy', 'no-referrer');
        $this->response->setHttpHeader('Cache-Control', 'no-store');
    }

    protected function assignLoginLayout(): void
    {
        $currentDomain = $this->domainRepository->findOneByActiveRequest();
        $this->view->assignMultiple([
            'site' => $currentDomain !== null ? $currentDomain->getSite() : $this->siteRepository->findDefault(),
            'styles' => array_filter($this->backendLoginFormSettings['stylesheets'] ?? [], fn (?string $stylesheet) => $stylesheet !== null && $stylesheet !== ''),
            'flashMessages' => $this->flashMessageService->getFlashMessageContainerForRequest($this->request)->getMessagesAndFlush(),
        ]);
    }

    protected function clientIp(): string
    {
        $clientIp = $this->request->getHttpRequest()->getAttribute(\Neos\Flow\Http\ServerRequestAttributes::CLIENT_IP);
        return is_string($clientIp) ? $clientIp : 'unknown';
    }
}
