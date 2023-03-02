<?php

namespace Sandstorm\NeosTwoFactorAuthentication\Http\Middleware;

use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sandstorm\NeosTwoFactorAuthentication\Error\SecondFactorEnforcedSetupException;
use Sandstorm\NeosTwoFactorAuthentication\Error\SecondFactorRequiredException;

class SecondFactorRedirectMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $next): ResponseInterface
    {
        try {
            return $next->handle($request);
        } catch (SecondFactorRequiredException $exception) {
            return new Response(303, [
                'Location' => '/neos/two-factor-login'
            ]);
        } catch (SecondFactorEnforcedSetupException $exception) {
            return new Response(303, [
                'Location' => '/neos/setup-two-factor-login'
            ]);
        }
    }
}
