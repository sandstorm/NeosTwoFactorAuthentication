# Neos Backend 2FA

Extend the Neos backend login to support second factors. At the moment we only support TOTP tokens.

Support for WebAuthn is planed!

## What this package does

https://user-images.githubusercontent.com/12086990/153027757-ac715746-0575-4555-bce1-c44603747945.mov

This package allows all users to register their personal TOTP token (Authenticator App). As an Administrator you are
able to delete those token for the users again, in case they locked them self out.

![Screenshot 2022-02-08 at 17 11 01](https://user-images.githubusercontent.com/12086990/153028043-93e9220e-cc22-4879-9edb-3e156c9accc8.png)

## Versioning Scheme

| Package Version | Neos / Flow Version | Released? | Supported | Remarks       |
| --------------- | ------------------- | --------- | --------- |---------------|
| 2.x             | 9.x, 8.x, 7.x       | ✅        | ✅        | `main` branch |
| 1.x             | 9.x, 8.x, 7.x, 3.x  | ✅        |           |               |

## Settings

### Enforce 2FA

To enforce the setup and usage of 2FA you can add the following to your `Settings.yaml`.

```yml
Sandstorm:
  NeosTwoFactorAuthentication:
    # enforce 2FA for all users
    enforceTwoFactorAuthentication: true
```

With this setting, no user can login into the CMS without setting up a second factor first.

In addition, you can enforce 2FA for specific authentication providers and/or roles by adding following to your `Settings.yaml`

```yml
Sandstorm:
  NeosTwoFactorAuthentication:
    # enforce 2FA for specific authentication providers
    enforce2FAForAuthenticationProviders: ["Neos.Neos:Backend"]
    # enforce 2FA for specific roles
    enforce2FAForRoles: ["Neos.Neos:Administrator"]
```

### Issuer Naming

To override the default sitename as issuer label, you can define one via the configuration settings:

```yml
Sandstorm:
  NeosTwoFactorAuthentication:
    # (optional) if set this will be used as a naming convention for the TOTP. If empty the Site name will be used
    issuerName: ""
```

## Tested 2FA apps

Thx to @Sebobo @Benjamin-K for creating a list of supported and testet apps!

**iOS**:

- Google Authenticator (used for development) ✅
- Authy ✅
- Microsoft Authenticator ✅
- 1Password ✅

**Android**:

- Google Authenticator ✅
- Microsoft Authenticator ✅
- Authy ✅

## How we did it

- We introduced a new middleware `SecondFactorMiddleware` which handles 2FA on a Neos `Session` basis.
  - This is an overview of the checks the `SecondFactorMiddleware` does for any request:

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

- the login screen for the second factor is a hard copy of the login screen from the `Neos.Neos` package
  - just replaced the username/password form with the form for the second factor
  - maybe has to be replaced when neos gets updated
- hopefully the rest of this package is solid enough to survive the next mayor Neos versions ;)

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

## Contributing

### Testing

The package ships with end-to-end tests built on [Playwright](https://playwright.dev) and written in Gherkin syntax via [playwright-bdd](https://vitalets.github.io/playwright-bdd/).

#### Running the tests

Tests require Docker and Node.js. Install dependencies once (if [nvm](https://github.com/nvm-sh/nvm) is available it will automatically switch to the Node version from `.nvmrc`):

```bash
make setup-test
```

Re-generate Playwright spec files whenever a `.feature` file changes:
```bash
make generate-bdd-files
```

Use the Makefile targets from the repository root:

```bash
make test                   # run all tests (neos8 + neos9, all configurations)

make test-neos8             # run all neos8 tests
make test-neos8-defaults    # default configuration only
make test-neos8-enforce-all # enforceTwoFactorAuthentication: true
make test-neos8-enforce-role
make test-neos8-enforce-provider
make test-neos8-issuer-name

make test-neos9             # same targets for neos9 / PHP 8.3

make down                   # tear down all docker compose environments and remove volumes
```

#### Debugging tests
To debug a test, run the test with flags like this:

- `make test-neos8-enforce-all -- --debug` - to run the test in headed mode with Playwright Inspector
- `make test-neos8-enforce-all -- --ui` - to run the test in headed mode with Playwright Test Runner UI

If you just want to see the test running in the browser just `make test-neos8-enforce-all -- --headed`.

> While debugging you can also enter the SUT with `make enter-neos8` and `make enter-neos9` respectively.
>
> You can even the tests you want to debug with `make test-neos8-enforce-all -- --grep @debug` and adding the `@debug` tag to the scenario you want to debug. But using the --ui flag is usually more convenient for debugging.

#### System under test (SUT)

There are two docker compose environments in `Tests/sytem_under_test/`:

- `neos8/` — Neos with PHP 8.2
- `neos9/` — Neos with PHP 8.5

Both are built from the repository root as the Docker build context, so the local package source is copied into the container and installed via a Composer path repository. This means every test run tests the _current working tree_ of the package, not a published version.

#### Configuration variants

The `FLOW_CONTEXT` environment variable is passed into the docker compose environment via variable substitution, and Flow's hierarchical configuration loading picks up the corresponding `Settings.yaml` from the SUT:

| Playwright tag | `FLOW_CONTEXT` | What is tested |
|---|---|---|
| `@default-context` | `Production/E2E-SUT` | No enforcement — 2FA is optional |
| `@enforce-for-all` | `Production/E2E-SUT/EnforceForAll` | `enforceTwoFactorAuthentication: true` |
| `@enforce-for-role` | `Production/E2E-SUT/EnforceForRole` | Enforcement scoped to `Neos.Neos:Administrator` |
| `@enforce-for-provider` | `Production/E2E-SUT/EnforceForProvider` | Enforcement scoped to an authentication provider |
| `@issuer-name-change` | `Production/E2E-SUT/IssuerNameChange` | Custom `issuerName` setting |

#### Test isolation

Each scenario starts with a clean state. An `AfterScenario` hook runs after every scenario to:

1. Log the browser out via a POST to `/neos/logout`
2. Delete all Neos users (`./flow user:delete --assume-yes '*'`)

Deleting all users also cascades to their 2FA devices, so no separate cleanup step is needed. Users and devices are re-created by the Background steps at the start of each scenario.

#### Design decisions

**Gherkin / BDD over plain Playwright specs** — the feature files document the intended behaviour of each configuration variant at a level that is readable without knowing the implementation. The generated Playwright spec files (`.features-gen/`) are not committed; they are re-generated by `bddgen` before each test run.

**UI-only device enrolment** — 2FA devices are enrolled through the browser UI (the backend module or the setup page) rather than a dedicated CLI command. This avoids coupling the tests to internal persistence details and exercises the same enrolment path a real user would take. The `deviceNameSecretMap` in `helpers/state.ts` carries TOTP secrets across steps within a scenario (e.g. from the enrolment step to the OTP entry step).

**Sequential execution** — tests run with `workers: 1` and `fullyParallel: false` because all scenarios share a single running SUT container and a single database. Running them in parallel would cause interference between scenarios.

**User creation via `docker exec`** — Neos user creation is done through the Flow CLI (`./flow user:create`) rather than the UI because the UI path is not part of what this package tests, and using the CLI is faster and more reliable for setup.
