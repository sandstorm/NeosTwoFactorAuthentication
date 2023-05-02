<?php

namespace Sandstorm\NeosTwoFactorAuthentication\Http\Middleware;

use GuzzleHttp\Psr7\Response;
use Neos\Flow\Security\Context as SecurityContext;
use Neos\Flow\Session\SessionManagerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Sandstorm\NeosTwoFactorAuthentication\Domain\Repository\SecondFactorRepository;
use Neos\Flow\Annotations as Flow;

class SecondFactorMiddleware implements MiddlewareInterface
{
    const SESSION_OBJECT_ID = 'Sandstorm/NeosTwoFactorAuthentication';
    const SESSION_OBJECT_AUTH_STATUS = 'authenticationStatus';

    const SECOND_FACTOR_AUTHENTICATION_NEEDED = 'SECOND_FACTOR_AUTHENTICATION_NEEDED';
    const SECOND_FACTOR_AUTHENTICATED = 'SECOND_FACTOR_AUTHENTICATED';

    /**
     * TODO: Why lazy false?
     * @Flow\Inject(lazy=false)
     * @var SessionManagerInterface
     */
    protected $sessionManager;

    /**
     * @Flow\Inject(lazy=false)
     * @var SecurityContext
     */
    protected $securityContext;

    /**
     * @Flow\Inject(lazy=false)
     * @var SecondFactorRepository
     */
    protected $secondFactorRepository;

    /**
     * @Flow\InjectConfiguration(path="enforceTwoFactorAuthentication")
     * @var bool
     */
    protected $enforceTwoFactorAuthentication;

    /**
     * @Flow\Inject(name="Neos.Flow:SecurityLogger")
     * @var LoggerInterface
     */
    protected $securityLogger;

    // TODO: break up into smaller functions to remove complexity
    public function process(ServerRequestInterface $request, RequestHandlerInterface $next): ResponseInterface
    {
        $authenticationTokens = $this->securityContext->getAuthenticationTokens();

        if (empty($authenticationTokens)) {
            $this->securityLogger->info(
                'Sandstorm/NeosTwoFactorAuthentication: ' .
                'No authentication tokens found, skipping second factor'
            );
            return $next->handle($request);
        }

        // WHY: we currently only support 'Neos.Neos:Backend' provider (which ever is used) because the
        //      second factor feature is currently only build for Neos Editors use case
        if (!array_key_exists('Neos.Neos:Backend', $authenticationTokens)) {
            $this->securityLogger->error(
                'Sandstorm/NeosTwoFactorAuthentication: ' .
                'No authentication token for "Neos.Neos:Backend" found, skipping second factor'
            );
            return $next->handle($request);
        }

        $isAuthenticated = $authenticationTokens['Neos.Neos:Backend']->isAuthenticated();

        // ignore unauthenticated requests
        if (!$isAuthenticated) {
            $this->securityLogger->info(
                'Sandstorm/NeosTwoFactorAuthentication: ' .
                'Not authenticated on "Neos.Neos:Backend" auth provider, skipping second factor'
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
                'Sandstorm/NeosTwoFactorAuthentication: ' .
                'Second factor not enforced and set up for account, skipping second factor'
            );
            return $next->handle($request);
        }

        // get 2FA data from session
        $currentSession = $this->sessionManager->getCurrentSession();
        // TODO: ValueObject/DTO
        $twoFactorData = $currentSession->getData(self::SESSION_OBJECT_ID);

        // if session has no 2FA data object, we initialize a default
        if (empty($twoFactorData)) {
            $currentSession->putData(
                self::SESSION_OBJECT_ID,
                [
                    self::SESSION_OBJECT_AUTH_STATUS => self::SECOND_FACTOR_AUTHENTICATION_NEEDED,
                ]
            );
            $twoFactorData = $currentSession->getData(self::SESSION_OBJECT_ID);
        }

        // already authenticated
        if ($twoFactorData[self::SESSION_OBJECT_AUTH_STATUS] === self::SECOND_FACTOR_AUTHENTICATED) {
            return $next->handle($request);
        }

        if (
            $this->secondFactorRepository->isEnabledForAccount($account)
            && $twoFactorData[self::SESSION_OBJECT_AUTH_STATUS] === self::SECOND_FACTOR_AUTHENTICATION_NEEDED
        ) {
            // TODO: discuss
            // WHY: We use the request URI as part of the state
            $isAskingFor2FA = str_ends_with($request->getUri()->getPath(), 'neos/two-factor-login');
            if ($isAskingFor2FA) {
                return $next->handle($request);
            }

            if ($request->getMethod() === 'POST') {
                return new Response(401);
            }

            return new Response(303, ['Location' => '/neos/two-factor-login']);
        }

        if (
            $this->enforceTwoFactorAuthentication &&
            !$this->secondFactorRepository->isEnabledForAccount($account)
        ) {
            // ignore if setup is in progress
            $isSettingUp2FA = str_ends_with($request->getUri()->getPath(), 'neos/setup-second-factor');
            if ($isSettingUp2FA) {
                return $next->handle($request);
            }

            if ($request->getMethod() === 'POST') {
                return new Response(401);
            }

            return new Response(303, ['Location' => '/neos/setup-second-factor']);
        }

        // TODO: Throw here?
        die('this should not happen^^');
    }
}
