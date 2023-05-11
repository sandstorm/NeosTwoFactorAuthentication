<?php

namespace Sandstorm\NeosTwoFactorAuthentication\Domain;

// FIXME: Refactor to enum once we only support PHP >= 8.1
class AuthenticationStatus
{
    const AUTHENTICATION_NEEDED = 'AUTHENTICATION_NEEDED';
    const AUTHENTICATED = 'AUTHENTICATED';
}
