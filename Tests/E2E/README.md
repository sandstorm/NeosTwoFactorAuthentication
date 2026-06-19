# E2E Tests

End-to-end tests for `sandstorm/neostwofactorauthentication`, using [Playwright](https://playwright.dev) with [playwright-bdd](https://vitalets.github.io/playwright-bdd/) for Gherkin-style BDD scenarios. Tests run against a Dockerised Neos instance (the *system under test*, SUT) — no local Neos installation required.

Tests are executed against both **Neos 8** (PHP 8.2, MariaDB 10.11) and **Neos 9** (PHP 8.5, MariaDB 11.4).

## Prerequisites

- Docker
- Node.js >= 24 (or [nvm](https://github.com/nvm-sh/nvm) — the setup script uses it automatically if available)
- make

## Setup

Run once after cloning:

```bash
cd Tests/E2E
make setup
```

This will:
1. Build the Docker images for both Neos 8 and Neos 9
2. Install npm dependencies
3. Install the Playwright Chromium browser
4. Generate the Playwright test files from the Gherkin feature files

## Running tests

```bash
# Run against both Neos 8 and Neos 9
make test

# Run all variants against Neos 8 only
make test-neos8

# Run all variants against Neos 9 only
make test-neos9
```

Playwright starts the Docker containers automatically before each run and stops them afterwards. The first run may take a few minutes while Neos sets itself up inside the container (migrations, demo site import).

### Test variants

The plugin behaves differently depending on how 2FA is configured. Each variant boots the SUT with a matching Flow configuration context and runs only the scenarios tagged for it. There is a `make` target per variant:

| Variant            | Neos 8 target                  | Neos 9 target                  | 2FA configuration                |
| ------------------ | ------------------------------ | ------------------------------ | -------------------------------- |
| Default settings   | `make test-neos8-defaults`         | `make test-neos9-defaults`         | 2FA available but not enforced   |
| Enforced for all   | `make test-neos8-enforce-all`      | `make test-neos9-enforce-all`      | 2FA enforced for every user      |
| Enforced for role  | `make test-neos8-enforce-role`     | `make test-neos9-enforce-role`     | 2FA enforced for a specific role |
| Enforced for provider | `make test-neos8-enforce-provider` | `make test-neos9-enforce-provider` | 2FA enforced for an auth provider |

`make test-neos8` / `make test-neos9` run all four variants.

### SUT and FLOW_CONTEXT

Each variant is driven by an npm script in `package.json` (e.g. `test:neos8:enforce-all`) that sets two environment variables:

- **`SUT`** (`neos8` or `neos9`) — selects which Docker Compose environment to start.
- **`FLOW_CONTEXT`** — selects a Neos Flow configuration context. The defaults variant uses `Production/E2E-SUT`; the enforced variants use sub-contexts such as `Production/E2E-SUT/EnforceForAll`. Each context loads the configuration files in `system_under_test/sut_file_system_overrides/app/Configuration/<context>/`.

Scenarios are matched to a variant with Gherkin **tags** (`@default-context`, `@enforce-for-all`, `@enforce-for-role`, `@enforce-for-provider`). The npm script passes the matching tag to `playwright test --grep`, so only the relevant scenarios run against that configuration.

To add a new variant:
1. Add configuration files under a new sub-context, e.g. `system_under_test/sut_file_system_overrides/app/Configuration/Production/E2E-SUT/my-variant/`.
2. Tag the relevant scenarios in the feature files (e.g. `@my-variant`).
3. Add npm scripts in `package.json` that set `FLOW_CONTEXT=Production/E2E-SUT/my-variant` and `--grep @my-variant`.
4. Optionally add `make` targets that call those scripts.

## Container management

When you need to inspect a running container or debug a failure:

```bash
# Start containers in the background (without running tests)
make start-sut-neos8
make start-sut-neos9

# Stream container logs
make log-sut-neos8
make log-sut-neos9

# Open a bash shell inside a running container
make enter-sut-neos8
make enter-sut-neos9

# Stop containers (volumes kept)
make stop-sut-neos8
make stop-sut-neos9

# Stop all containers and delete their volumes
make sut-prune
```

Run `make help` to see all available targets.

## Directory structure

```
Tests/E2E/
├── Makefile
├── README.md                        # this file
├── package.json
├── tsconfig.json
├── playwright.config.ts
├── global-teardown.ts
├── features/                        # Gherkin feature files (.feature), grouped by variant
│   ├── default/
│   ├── enforce-for-all/
│   ├── enforce-for-provider/
│   └── enforce-for-role/
├── steps/                           # TypeScript step definitions
│   ├── general-login.steps.ts
│   ├── 2fa-login.steps.ts
│   ├── backend-module.steps.ts
│   └── hooks.ts
├── helpers/
│   ├── general-pages.ts             # Page Object Model classes
│   ├── 2fa-pages.ts                 # Page Objects for the 2FA flows
│   ├── system.ts                    # Docker/Flow CLI utilities
│   ├── totp.ts                      # TOTP code generation (otplib)
│   └── state.ts                     # Cross-step shared state (e.g. device secrets)
└── system_under_test/
    ├── Dockerfile
    ├── sut-base-docker-compose.yaml # Shared compose base included by neos8/neos9
    ├── neos8/
    │   ├── docker-compose.yaml
    │   ├── compose-overrides-neos8.yaml
    │   └── entrypoint.sh
    ├── neos9/
    │   ├── docker-compose.yaml
    │   ├── compose-overrides-neos9.yaml
    │   └── entrypoint.sh
    └── sut_file_system_overrides/   # Neos/PHP/Caddy config mounted into containers
        └── app/Configuration/Production/E2E-SUT/   # one folder per FLOW_CONTEXT variant
```

---

## Writing new tests

Tests are written in two parts: a **feature file** (what to test, in plain language) and a **steps file** (how to do it, in TypeScript).

### 1. Write a feature file

Create a `.feature` file under `features/`. Organise by variant, and tag the feature (or individual scenarios) so it runs in the right configuration context:

```gherkin
# features/default/my-scenario.feature
@default-context
Feature: My feature description

  Background:
    Given A user with username "admin", password "password" and role "Neos.Neos:Administrator" exists

  Scenario: Admin can do the thing
    When I log in with username "admin" and password "password"
    And I navigate to the thing
    Then I should see the expected result
```

The tag (e.g. `@default-context`) must match the `--grep` of the variant you want it to run in — see [Test variants](#test-variants).

### 2. Implement missing steps

Reuse existing steps from `steps/` where possible. If a step doesn't exist yet, add it to a new or existing steps file:

```typescript
// steps/my-feature.steps.ts
import { expect } from "@playwright/test";
import { createBdd } from "playwright-bdd";
import { MyPage } from "../helpers/my-pages.ts";

const { Given, When, Then } = createBdd();

When("I navigate to the thing", async ({ page }) => {
  await page.goto("/my-path");
});

Then("I should see the expected result", async ({ page }) => {
  await expect(page.locator(".my-selector")).toBeVisible();
});
```

Steps are matched by exact string (including `{string}` parameters). A step defined in any file under `steps/` is available in all feature files.

### 3. Add Page Objects for new pages

If you are testing a new page, add a class to `helpers/general-pages.ts`, `helpers/2fa-pages.ts`, or a new helpers file:

```typescript
export class MyFeaturePage {
  constructor(private readonly page: Page) {}

  async goto() {
    await this.page.goto("/my-path");
  }

  async clickTheButton() {
    await this.page.locator(".my-button").click();
  }
}
```

### 4. Regenerate test files

playwright-bdd generates Playwright test files from your feature files. After adding or changing feature files run:

```bash
make generate-bdd-files
```

This is done automatically by `make setup` and by every test run, but you can run it manually during development.

### 5. Use Flow CLI in steps

`helpers/system.ts` exposes utilities that run Neos Flow CLI commands inside the Docker container:

```typescript
import { createUser, removeAllUsers } from "../helpers/system.ts";

// Create a Flow user (synchronous — runs docker exec)
createUser("myuser", "password", ["Neos.Neos:Administrator"]);

// Remove all users (used in AfterScenario hooks for cleanup)
removeAllUsers();
```

You can add more Flow CLI wrappers to `system.ts` following the same pattern.

### 6. Generating TOTP codes

To complete a 2FA login a test needs a valid one-time code. `helpers/totp.ts` generates one from a TOTP secret (via `otplib`), and `helpers/state.ts` keeps a shared map of device-name → secret so a code can be generated for a device created earlier in the same scenario.

### 7. The "add second factor" workflow

Adding a second factor (both in the backend module at `/new` and during enforced login setup at `/neos/second-factor-setup`) starts on a **method picker** where the user chooses TOTP or WebAuthn. The picker buttons carry `data-test-id="select-method-totp"` / `select-method-webauthn`. The page objects in `helpers/2fa-pages.ts` (`BackendModulePage.chooseMethod`, `SecondFactorSetupPage.chooseTotp/chooseWebAuthn`) walk this picker before driving the method-specific form.

### 8. Testing WebAuthn (security keys)

WebAuthn ceremonies need an authenticator. Instead of real hardware the tests install a **CDP virtual authenticator** on the browser context (`helpers/webauthn.ts`, exposed via the `Given I have a virtual security key` step). It auto-approves registration and assertion (`isUserVerified` + `automaticPresenceSimulation`), and the registered credential survives the logout/login within a scenario because it lives on the context, not the page. This only works with the Chromium project (which the suite uses). WebAuthn devices can be named (`I add a new WebAuthn 2FA device with name "..."`), so they can be asserted by name like TOTP devices, or by type — `There should be N enrolled "Security Key" 2FA device(s)`.

### Cleanup

The `AfterScenario` hook in `steps/hooks.ts` logs out the current browser session and removes all users after every scenario, keeping tests isolated. If your tests create other persistent data, add cleanup logic there.

## Disclaimer

This is just a template. It is meant to jump start your own E2E test suite.

You can use all the playwright features you want (like `--ui`, `--debug`, `--grep`, etc.) — the Makefile targets are just thin wrappers around `npx playwright test` that set up the environment variables and Docker containers for you. Feel free to modify the setup as needed.
