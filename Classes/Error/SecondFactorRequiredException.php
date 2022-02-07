<?php

namespace Sandstorm\NeosTwoFactorAuthentication\Error;

/**
 * This Exception get thrown inside the authentication provider to trigger a redirect by the middleware.
 * This is to redirect the user to the form for the second factor.
 */
class SecondFactorRequiredException extends \Exception
{

}
