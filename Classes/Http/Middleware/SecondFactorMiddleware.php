<?php

namespace Sandstorm\NeosTwoFactorAuthentication\Http\Middleware;

use GuzzleHttp\Psr7\Response;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Http\ServerRequestAttributes;
use Neos\Flow\Mvc\ActionRequestFactory;
use Neos\Flow\Security\Context as SecurityContext;
use Neos\Flow\Security\Exception\AuthenticationRequiredException;
use Neos\Flow\Session\Exception\SessionNotStartedException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Sandstorm\NeosTwoFactorAuthentication\Domain\AuthenticationStatus;
use Sandstorm\NeosTwoFactorAuthentication\Domain\Repository\SecondFactorRepository;
use Sandstorm\NeosTwoFactorAuthentication\Service\SecondFactorSessionStorageService;

class SecondFactorMiddleware implements MiddlewareInterface
{
    const LOGGING_PREFIX = 'Sandstorm/NeosTwoFactorAuthentication: ';

    /**
     * @Flow\Inject
     * @var SecurityContext
     */
    protected $securityContext;

    /**
     * @Flow\Inject
     * @var SecondFactorRepository
     */
    protected $secondFactorRepository;

    /**
     * @Flow\Inject
     * @var ActionRequestFactory
     */
    protected $actionRequestFactory;

    /**
     * @Flow\Inject(name="Neos.Flow:SecurityLogger")
     * @var LoggerInterface
     */
    protected $securityLogger;

    /**
     * @Flow\Inject
     * @var SecondFactorSessionStorageService
     */
     protected $secondFactorSessionStorageService;

    /**
     * @Flow\InjectConfiguration(path="enforceTwoFactorAuthentication")
     * @var bool
     */
    protected $enforceTwoFactorAuthentication;

    /**
     * TODO: Document checks that are done in order (because the order matters here)!
     * @throws AuthenticationRequiredException
     * @throws SessionNotStartedException
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $next): ResponseInterface
    {
        $authenticationTokens = $this->securityContext->getAuthenticationTokens();

        if (empty($authenticationTokens)) {
            $this->securityLogger->debug(
                self::LOGGING_PREFIX .
                'No authentication tokens found, skipping second factor.'
            );
            return $next->handle($request);
        }

        // WHY: we currently only support 'Neos.Neos:Backend' provider (which ever is used) because the
        //      second factor feature is currently only build for Neos Editors use case
        if (!array_key_exists('Neos.Neos:Backend', $authenticationTokens)) {
            $this->securityLogger->debug(
                self::LOGGING_PREFIX .
                'No authentication token for "Neos.Neos:Backend" found, skipping second factor.'
            );
            return $next->handle($request);
        }

        $isAuthenticated = $authenticationTokens['Neos.Neos:Backend']->isAuthenticated();

        // ignore unauthenticated requests
        if (!$isAuthenticated) {
            $this->securityLogger->debug(
                self::LOGGING_PREFIX .
                'Not authenticated on "Neos.Neos:Backend" authentication provider, skipping second factor.'
            );
            return $next->handle($request);
        }

        $account = $this->securityContext->getAccount();

        // ignore if second factor is not enabled for account and second factor is not enforced
        if (
            !$this->secondFactorRepository->isEnabledForAccount($account)
            && !$this->enforceTwoFactorAuthentication
        ) {
            $this->securityLogger->debug(
                self::LOGGING_PREFIX .
                'Second factor not enabled for account but not enforced, skipping second factor.'
            );
            return $next->handle($request);
        }

        $this->secondFactorSessionStorageService->initializeTwoFactorSessionObject();

        $authenticationStatus = $this->secondFactorSessionStorageService->getAuthenticationStatus();

        // already authenticated
        if ($authenticationStatus === AuthenticationStatus::AUTHENTICATED) {
            $this->securityLogger->debug(
                self::LOGGING_PREFIX .
                'Second factor already authenticated.'
            );
            return $next->handle($request);
        }

        if (
            $this->secondFactorRepository->isEnabledForAccount($account)
            && $authenticationStatus === AuthenticationStatus::AUTHENTICATION_NEEDED
        ) {
            // WHY: We use the request URI as part of state. This prevents the middleware to enter a redirect loop.
            $isAskingForOTP = str_ends_with($request->getUri()->getPath(), 'neos/two-factor-login');
            if ($isAskingForOTP) {
                return $next->handle($request);
            }

            $this->securityLogger->debug(
                self::LOGGING_PREFIX .
                'Second factor enabled and not authenticated, redirecting to 2FA login.',
                [$authenticationStatus]
            );

            // WHY: Set intercepted request to be able to redirect after 2FA.
            //      See Sandstorm/NeosTwoFactorAuthentication/LoginController
            $this->registerOriginalRequestForRedirect($request);

            return new Response(303, ['Location' => '/neos/two-factor-login']);
        }

        if (
            $this->enforceTwoFactorAuthentication &&
            !$this->secondFactorRepository->isEnabledForAccount($account)
        ) {
            // WHY: We use the request URI as part of state. This prevents the middleware to enter a redirect loop.
            $isSettingUp2FA = str_ends_with($request->getUri()->getPath(), 'neos/setup-second-factor');
            if ($isSettingUp2FA) {
                return $next->handle($request);
            }

            $this->securityLogger->debug(
                self::LOGGING_PREFIX .
                'Second factor enforced and not enabled for account, redirecting to 2FA setup.'
            );

            // WHY: Set intercepted request to be able to redirect after 2FA.
            //      See Sandstorm/NeosTwoFactorAuthentication/LoginController
            $this->registerOriginalRequestForRedirect($request);

            return new Response(303, ['Location' => '/neos/setup-second-factor']);
        }

        throw new AuthenticationRequiredException("You have to be logged in with second factor!");
    }

    private function registerOriginalRequestForRedirect(ServerRequestInterface $request): void
    {
        $routingMatchResults = $request->getAttribute(ServerRequestAttributes::ROUTING_RESULTS) ?? [];
        $actionRequest = $this->actionRequestFactory->createActionRequest($request, $routingMatchResults);

        $this->securityContext->setInterceptedRequest($actionRequest);
    }


}
