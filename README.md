# Neos Backend 2FA

Extend the Neos backend login to support second factors. At the moment we only support TOTP tokens.

Support for WebAuthn is planed!

## How we did it

* We extended the `PersistedUsernamePasswordProvider` to implement the second factor logic.
* The second factor is part of the `UsernameAndPasswordWithSecondFactor`-token, which extends the `UsernameAndPassword`-token.
* Whenever the `PersistentUsernameAndPasswordWithSecondFactorProvider` detects that the second factor is missing, it will throw a `SecondFactorRequiredException`.
* This Exception is caught by our custom http-middleware `SecondFactorRedirectMiddleware`
* The middleware triggers a redirect to show the second factor prompt.
* The `Neos.Neos:Backend` gets overridden in this package to allow second factors.

## When updating Neos, those part will likely crash:

* the login screen for the second factor is a hard copy of the login screen from the `Neos.Neos` package
  * just replaced the username/password form with the form for the second factor
  * maybe has to be replaced when neos gets updated
* hopefully the rest of this package is solid enough to survive the next mayor Neos versions ;)

## Why not ...?

### set the authenticationStrategy to `allTokens`

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
