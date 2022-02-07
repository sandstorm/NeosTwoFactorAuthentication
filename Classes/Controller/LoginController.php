<?php

namespace Sandstorm\NeosTwoFactorAuthentication\Controller;

/*
 * This file is part of the Sandstorm.NeosTwoFactorAuthentication package.
 */

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Mvc\Controller\ActionController;
use Neos\Flow\Security\Context as SecurityContext;
use Neos\Fusion\View\FusionView;

class LoginController extends ActionController
{
    /**
     * @var string
     */
    protected $defaultViewObjectName = FusionView::class;

    /**
     * @var SecurityContext
     * @Flow\Inject
     */
    protected $securityContext;


    /**
     * This action decides which tokens are already authenticated
     * and decides which is next to authenticate
     */
    public function neosBackendLoginRedirectAction()
    {
        die('ACTION begin');
        $authenticatedTokens = $this->securityContext->getAuthenticationTokens();
        if (count($authenticatedTokens) == 0) {
            \Neos\Flow\var_dump('####################');
            die();
        } else {
            \Neos\Flow\var_dump($authenticatedTokens);
            \Neos\Flow\var_dump('MORE TOKENS!!!');
            die();
        }
    }

    public function secondFactorAction()
    {

    }
}
