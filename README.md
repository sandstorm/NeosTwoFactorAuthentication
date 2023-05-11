# Neos Backend 2FA

Extend the Neos backend login to support second factors. At the moment we only support TOTP tokens.

Support for WebAuthn is planed!

## What this package does

https://user-images.githubusercontent.com/12086990/153027757-ac715746-0575-4555-bce1-c44603747945.mov

This package allows all users to register their personal TOTP token (Authenticator App). As an Administrator you are
able to delete those token for the users again, in case they locked them self out.

![Screenshot 2022-02-08 at 17 11 01](https://user-images.githubusercontent.com/12086990/153028043-93e9220e-cc22-4879-9edb-3e156c9accc8.png)

## Tested 2FA apps

Thx to @Sebobo @Benjamin-K for creating a list of supported and testet apps!

**iOS**:
* Google Authenticator (used for development) ✅
* Authy ✅
* Microsoft Authenticator ✅
* 1Password ✅

**Android**:
* Google Authenticator ✅
* Microsoft Authenticator ✅
* Authy ✅

## How we did it
* We introduced a new middleware `SecondFactorMiddleware` which handles 2FA on a Neos `Session` basis.
  * This is an overview of the checks the `SecondFactorMiddleware` does for any request:
    ```
                            ┌─────────────────────────────┐
                            │           Request           │
                            └─────────────────────────────┘
                                           ▼
                                ... middleware chain ...
                                           ▼
                            ┌─────────────────────────────┐
                            │  SecurityEndpointMiddleware │
                            └─────────────────────────────┘
                                           ▼
            ┌───────────────────────────────────────────────────────────────────┐
            │                     SecondFactorMiddleware                        │
            │                                                                   │
            │  ┌─────────────────────────────────────────────────────────────┐  │
            │  │ 1. Skip, if no authentication tokens are present, because   │  │
            │  │    we're not on a secured route.                            │  │
            │  └─────────────────────────────────────────────────────────────┘  │
            │  ┌─────────────────────────────────────────────────────────────┐  │
            │  │ 2. Skip, if 'Neos.Backend:Backend' authentication token not │  │
            │  │    present, because we only support second factors for Neos │  │
            │  │    backend.                                                 │  │
            │  └─────────────────────────────────────────────────────────────┘  │
            │  ┌─────────────────────────────────────────────────────────────┐  │
            │  │ 3. Skip, if 'Neos.Backend:Backend' authentication token is  │  │
            │  │    not authenticated, because we need to be authenticated   │  │
            │  │    with the authentication provider of                      │  │
            │  │    'Neos.Backend:Backend' first.                            │  │
            │  └─────────────────────────────────────────────────────────────┘  │
            │  ┌─────────────────────────────────────────────────────────────┐  │
            │  │ 4. Skip, if second factor is not set up for account and not │  │
            │  │    enforced via settings.                                   │  │
            │  └─────────────────────────────────────────────────────────────┘  │
            │  ┌─────────────────────────────────────────────────────────────┐  │
            │  │ 5. Skip, if second factor is already authenticated.         │  │
            │  └─────────────────────────────────────────────────────────────┘  │
            │  ┌─────────────────────────────────────────────────────────────┐  │
            │  │ 6. Redirect to 2FA login, if second factor is set up for    │  │
            │  │    account but not authenticated.                           │  │
            │  │    Skip, if already on 2FA login route.                     │  │
            │  └─────────────────────────────────────────────────────────────┘  │
            │  ┌─────────────────────────────────────────────────────────────┐  │
            │  │ 7. Redirect to 2FA setup, if second factor is not set up for│  │
            │  │    account but is enforced by system.                       │  │
            │  │    Skip, if already on 2FA setup route.                     │  │
            │  └─────────────────────────────────────────────────────────────┘  │
            │  ┌─────────────────────────────────────────────────────────────┐  │
            │  │ X. Throw an error, because any check before should have     │  │
            │  │    succeeded.                                               │  │
            │  └─────────────────────────────────────────────────────────────┘  │
            └───────────────────────────────────────────────────────────────────┘
                                              ▼
                                     ... middlewares ...
     
    ```
  

## When updating Neos, those part will likely crash:

* the login screen for the second factor is a hard copy of the login screen from the `Neos.Neos` package
  * just replaced the username/password form with the form for the second factor
  * maybe has to be replaced when neos gets updated
* hopefully the rest of this package is solid enough to survive the next mayor Neos versions ;)

## Why not ...?

### Enhance the `UsernamePassword` authentication token

> This actually has been the approach up until version 1.0.5.

One issue with this is the fact, that we _want_ the user to be logged in with that token via the 
`PersistedUsernamePasswordProvider`, but at the same time to _not be logged in_ with that token as long as 2FA is
not authenticated as well.
We found it hard to find a secure way to model the 2FA setup solution when 2FA is enforced, but the user does not have a
second factor enabled, yet.

The middleware approach makes a clear distinction between "Logging in" and "Second Factor Authentication", while still
being session based and unable to bypass.

### Set the authenticationStrategy to `allTokens`

The AuthenticationProviderManager requires to authorize all tokens at the same time otherwise, it will throw
an Exception (see AuthenticationProviderManager Line 181

```php
if ($this->authenticationStrategy === Context::AUTHENTICATE_ALL_TOKENS) {
    throw new AuthenticationRequiredException('Could not authenticate all tokens, but authenticationStrategy was set to "all".', 1222203912);
}
```
)

This leads to an error where the `AuthenticationProviderManager` throws exceptions before the user is able to enter any
credentials. The `SecurityEntryPointMiddleware` catches those exceptions and redirects to the Neos Backend Login, which
causes the same exception again. We get caught in an endless redirect.

The [Neos Flow Security Documentation](https://flowframework.readthedocs.io/en/stable/TheDefinitiveGuide/PartIII/Security.html#multi-factor-authentication-strategy)
suggests how to implement a multi-factor-authentication, but this method seems like it was never tested. At the moment of writing
it seems like the `authenticationStrategy: allTokens` flag is broken and not usable.
