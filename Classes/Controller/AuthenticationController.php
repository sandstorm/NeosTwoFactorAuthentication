<?php

namespace Sandstorm\NeosTwoFactorAuthentication\Controller;

/*
 * This file is part of the Sandstorm.NeosTwoFactorAuthentication package.
 */

use Neos\Cache\Frontend\StringFrontend;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Mvc\ActionRequest;
use Neos\Flow\Mvc\Controller\ActionController;
use Neos\Flow\Security\Authentication\AuthenticationManagerInterface;
use Neos\Flow\Security\Authentication\Controller\AbstractAuthenticationController;
use Neos\Flow\Security\Context as SecurityContext;
use Neos\Flow\Security\Exception\AuthenticationRequiredException;
use Neos\Flow\Security\SessionDataContainer;
use Neos\Fusion\View\FusionView;
use Neos\Utility\ObjectAccess;

class AuthenticationController extends ActionController
{
    /**
     * trigger authentication again after user submitted second factor
     * @Flow\SkipCsrfProtection
     */
    public function checkOtpAction()
    {
        $this->redirect('authenticate', 'Login', 'Neos.Neos');
    }
}
