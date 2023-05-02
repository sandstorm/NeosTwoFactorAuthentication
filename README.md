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
> TODO: Reflect re-implementation in docs!
* We extended the `PersistedUsernamePasswordProvider` to implement the second factor logic.
* The second factor is part of the `UsernameAndPasswordWithSecondFactor`-token, which extends the `UsernameAndPassword`-token.
* Whenever the `PersistentUsernameAndPasswordWithSecondFactorProvider` detects that the second factor is missing, it will throw a `SecondFactorRequiredException`.
* This Exception is caught by our custom http-middleware `SecondFactorMiddleware`
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
