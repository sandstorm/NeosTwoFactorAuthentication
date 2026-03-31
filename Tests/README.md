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
cd Tests
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

# Run against Neos 8 only
make test-neos8

# Run against Neos 9 only
make test-neos9
```

Playwright starts the Docker containers automatically before each run and stops them afterwards. The first run may take a few minutes while Neos sets itself up inside the container (migrations, demo site import).

### SUT and FLOW_CONTEXT

Each npm test script sets two environment variables:

- **`SUT`** (`neos8` or `neos9`) — selects which Docker Compose environment to start.
- **`FLOW_CONTEXT`** — selects a Neos Flow configuration context. Both scripts default to `Production/E2E-SUT`, which loads the configuration files in `system_under_test/sut_file_system_overrides/app/Configuration/Production/E2E-SUT/`.

To test a different application configuration (e.g. with or without a feature enabled), add configuration files under a sub-context such as `Production/E2E-SUT/my-variant/` and add a matching npm script in `E2E/package.json` that sets `FLOW_CONTEXT=Production/E2E-SUT/my-variant`.

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

# Stop all containers and delete their volumes
make sut-down
```

## Directory structure

```
Tests/
├── Makefile
├── README.md                        # this file
├── E2E/
│   ├── features/                    # Gherkin feature files (.feature)
│   │   └── default/
│   │       └── login.feature
│   ├── steps/                       # TypeScript step definitions
│   │   ├── general-login.steps.ts
│   │   └── hooks.ts
│   ├── helpers/
│   │   ├── general-pages.ts         # Page Object Model classes
│   │   └── system.ts                # Docker/Flow CLI utilities
│   ├── playwright.config.ts
│   ├── global-teardown.ts
│   ├── package.json
│   └── tsconfig.json
└── system_under_test/
    ├── Dockerfile
    ├── sut-base-docker-compose.yaml # Shared compose base included by neos8/neos9
    ├── neos8/
    │   ├── docker-compose.yaml
    │   └── entrypoint.sh
    ├── neos9/
    │   ├── docker-compose.yaml
    │   └── entrypoint.sh
    └── sut_file_system_overrides/   # Neos/PHP/Caddy config mounted into containers
```

---

## Writing new tests

Tests are written in two parts: a **feature file** (what to test, in plain language) and a **steps file** (how to do it, in TypeScript).

### 1. Write a feature file

Create a `.feature` file under `E2E/features/`. Organise by feature area:

```gherkin
# E2E/features/my-feature/my-scenario.feature
Feature: My feature description

  Background:
    Given A user with username "admin", password "password" and role "Neos.Neos:Administrator" exists

  Scenario: Admin can do the thing
    When I log in with username "admin" and password "password"
    And I navigate to the thing
    Then I should see the expected result
```

### 2. Implement missing steps

Reuse existing steps from `steps/` where possible. If a step doesn't exist yet, add it to a new or existing steps file:

```typescript
// E2E/steps/my-feature.steps.ts
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

If you are testing a new page, add a class to `helpers/general-pages.ts` (or a new helpers file):

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

This is done automatically by `make setup` and `make test`, but you can run it manually during development.

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

### Cleanup

The `AfterScenario` hook in `steps/hooks.ts` logs out the current browser session and removes all users after every scenario, keeping tests isolated. If your tests create other persistent data, add cleanup logic there.

## Disclaimer

This is just a template. It is meant to jump start your own E2E test suite.

You can use all the playwright features you want (like `--ui`, `--debug`, `--grep`, etc.) — the Makefile scripts are just thin wrappers around `npx playwright test` that set up the environment variables and Docker containers for you. Feel free to modify the setup as needed.
