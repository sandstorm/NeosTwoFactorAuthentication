# Neos Backend 2FA

## How we did it

* Extended `PersistedUsernamePasswordProvider` to allow for some second factor logic

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
