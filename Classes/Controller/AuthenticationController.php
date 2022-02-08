<?php

namespace Sandstorm\NeosTwoFactorAuthentication\Controller;

/*
 * This file is part of the Sandstorm.NeosTwoFactorAuthentication package.
 */

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Mvc\Controller\ActionController;

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
