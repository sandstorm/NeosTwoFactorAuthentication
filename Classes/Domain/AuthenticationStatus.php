<?php

namespace Sandstorm\NeosTwoFactorAuthentication\Domain;

enum AuthenticationStatus: string
{
    case AUTHENTICATION_NEEDED = 'AUTHENTICATION_NEEDED';
    case AUTHENTICATED = 'AUTHENTICATED';
}
