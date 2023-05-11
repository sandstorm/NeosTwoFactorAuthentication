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
    const SECOND_FACTOR_LOGIN_URI = '/neos/second-factor-login';
    const SECOND_FACTOR_SETUP_URI = '/neos/second-factor-setup';

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
     * This middleware checks if the user is authenticated with a second factor "if necessary".
     * This middleware runs _after_ the 'securityEndpoint' middleware. This means if we are on a secured route we would
     * have an authenticated session by now.
     *
     * The the process looks like this:
     *                     ┌─────────────────────────────┐
     *                     │           Request           │
     *                     └─────────────────────────────┘
     *                                    ▼
     *                           ... middlewares ...
     *                                    ▼
     *                     ┌─────────────────────────────┐
     *                     │  SecurityEndpointMiddleware │
     *                     └─────────────────────────────┘
     *                                    ▼
     *     ┌───────────────────────────────────────────────────────────────────┐
     *     │                     SecondFactorMiddleware                        │
     *     │                                                                   │
     *     │  ┌─────────────────────────────────────────────────────────────┐  │
     *     │  │ 1. Skip, if no authentication tokens are present, because   │  │
     *     │  │    we're not on a secured route.                            │  │
     *     │  └─────────────────────────────────────────────────────────────┘  │
     *     │  ┌─────────────────────────────────────────────────────────────┐  │
     *     │  │ 2. Skip, if 'Neos.Backend:Backend' authentication token not │  │
     *     │  │    present, because we only support second factors for Neos │  │
     *     │  │    backend.                                                 │  │
     *     │  └─────────────────────────────────────────────────────────────┘  │
     *     │  ┌─────────────────────────────────────────────────────────────┐  │
     *     │  │ 3. Skip, if 'Neos.Backend:Backend' authentication token is  │  │
     *     │  │    not authenticated, because we need to be authenticated   │  │
     *     │  │    with the authentication provider of                      │  │
     *     │  │    'Neos.Backend:Backend' first.                            │  │
     *     │  └─────────────────────────────────────────────────────────────┘  │
     *     │  ┌─────────────────────────────────────────────────────────────┐  │
     *     │  │ 4. Skip, if second factor is not set up for account and not │  │
     *     │  │    enforced via settings.                                   │  │
     *     │  └─────────────────────────────────────────────────────────────┘  │
     *     │  ┌─────────────────────────────────────────────────────────────┐  │
     *     │  │ 5. Skip, if second factor is already authenticated.         │  │
     *     │  └─────────────────────────────────────────────────────────────┘  │
     *     │  ┌─────────────────────────────────────────────────────────────┐  │
     *     │  │ 6. Redirect to 2FA login, if second factor is set up for    │  │
     *     │  │    account but not authenticated.                           │  │
     *     │  │    Skip, if already on 2FA login route.                     │  │
     *     │  └─────────────────────────────────────────────────────────────┘  │
     *     │  ┌─────────────────────────────────────────────────────────────┐  │
     *     │  │ 7. Redirect to 2FA setup, if second factor is not set up for│  │
     *     │  │    account but is enforced by system.                       │  │
     *     │  │    Skip, if already on 2FA setup route.                     │  │
     *     │  └─────────────────────────────────────────────────────────────┘  │
     *     │  ┌─────────────────────────────────────────────────────────────┐  │
     *     │  │ X. Throw an error, because any check before should have     │  │
     *     │  │    succeeded.                                               │  │
     *     │  └─────────────────────────────────────────────────────────────┘  │
     *     └───────────────────────────────────────────────────────────────────┘
     *                                       ▼
     *                              ... middlewares ...
     *
     * @throws AuthenticationRequiredException
     * @throws SessionNotStartedException
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $authenticationTokens = $this->securityContext->getAuthenticationTokens();

        // 1. Skip, if no authentication tokens are present, because we're not on a secured route.
        if (empty($authenticationTokens)) {
            $this->log('No authentication tokens found, skipping second factor.');

            return $handler->handle($request);
        }

        // 2. Skip, if 'Neos.Backend:Backend' authentication token not present, because we only support second factors
        //    for Neos backend.
        if (!array_key_exists('Neos.Neos:Backend', $authenticationTokens)) {
            $this->log('No authentication token for "Neos.Neos:Backend" found, skipping second factor.');

            return $handler->handle($request);
        }

        $isAuthenticated = $authenticationTokens['Neos.Neos:Backend']->isAuthenticated();

        // 3. Skip, if 'Neos.Backend:Backend' authentication token is not authenticated, because we need to be
        //    authenticated with the authentication provider of 'Neos.Backend:Backend' first.
        if (!$isAuthenticated) {
            $this->log('Not authenticated on "Neos.Neos:Backend" authentication provider, skipping second factor.');

            return $handler->handle($request);
        }

        $account = $this->securityContext->getAccount();

        // 4. Skip, if second factor is not set up for account and not enforced via settings.
        if (
            !$this->secondFactorRepository->isEnabledForAccount($account)
            && !$this->enforceTwoFactorAuthentication
        ) {
            $this->log('Second factor not enabled for account and not enforced by system, skipping second factor.');

            return $handler->handle($request);
        }

        $this->secondFactorSessionStorageService->initializeTwoFactorSessionObject();

        $authenticationStatus = $this->secondFactorSessionStorageService->getAuthenticationStatus();

        // 5. Skip, if second factor is already authenticated.
        if ($authenticationStatus === AuthenticationStatus::AUTHENTICATED) {
            $this->log('Second factor already authenticated.');

            return $handler->handle($request);
        }

        // 6. Redirect to 2FA login, if second factor is set up for account but not authenticated.
        //    Skip, if already on 2FA login route.
        if (
            $this->secondFactorRepository->isEnabledForAccount($account)
            && $authenticationStatus === AuthenticationStatus::AUTHENTICATION_NEEDED
        ) {
            // WHY: We use the request URI as state here to keep the middleware from entering a redirect loop.
            $isAskingForOTP = str_ends_with($request->getUri()->getPath(), self::SECOND_FACTOR_LOGIN_URI);
            if ($isAskingForOTP) {
                return $handler->handle($request);
            }

            $this->log('Second factor enabled and not authenticated, redirecting to 2FA login.');

            // WHY: Set intercepted request to be able to redirect after 2FA login.
            //      See Sandstorm/NeosTwoFactorAuthentication/LoginController
            $this->registerOriginalRequestForRedirect($request);

            return new Response(303, ['Location' => self::SECOND_FACTOR_LOGIN_URI]);
        }

        // 7. Redirect to 2FA setup, if second factor is not set up for account but is enforced by system.
        //    Skip, if already on 2FA setup route.
        if (
            $this->enforceTwoFactorAuthentication &&
            !$this->secondFactorRepository->isEnabledForAccount($account)
        ) {
            // WHY: We use the request URI as state here to keep the middleware from entering a redirect loop.
            $isSettingUp2FA = str_ends_with($request->getUri()->getPath(), self::SECOND_FACTOR_SETUP_URI);
            if ($isSettingUp2FA) {
                return $handler->handle($request);
            }

            $this->log('Second factor enforced and not enabled for account, redirecting to 2FA setup.');

            // WHY: Set intercepted request to be able to redirect after 2FA setup.
            //      See Sandstorm/NeosTwoFactorAuthentication/LoginController
            $this->registerOriginalRequestForRedirect($request);

            return new Response(303, ['Location' => self::SECOND_FACTOR_SETUP_URI]);
        }

        // X. Throw an error, because any check before should have succeeded.
        throw new AuthenticationRequiredException("Second factor authentication failed!");
    }

    private function registerOriginalRequestForRedirect(ServerRequestInterface $request): void
    {
        $routingMatchResults = $request->getAttribute(ServerRequestAttributes::ROUTING_RESULTS) ?? [];
        $actionRequest = $this->actionRequestFactory->createActionRequest($request, $routingMatchResults);

        $this->securityContext->setInterceptedRequest($actionRequest);
    }

    private function log(string|\Stringable $message, array $context = []): void
    {
        $this->securityLogger->debug(self::LOGGING_PREFIX . $message, $context);
    }
}
