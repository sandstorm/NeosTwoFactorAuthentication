# Concept: Reusable E2E Infrastructure as a Composer Package

## Goal

Extract the E2E test infrastructure from this plugin into a standalone Composer package
(`sandstorm/neos-e2e-testing`) that any Neos plugin can install to get a working
end-to-end test scaffold with minimal effort.

**Scope:** Infrastructure only — Docker/SUT setup, Playwright project scaffold (config,
package.json, tsconfig, teardown), Makefile, and GitHub Actions workflow. Step definitions,
helpers (system, pages, totp, etc.), and feature files are entirely the plugin developer's
responsibility.

---

## Package Structure

### Type: `library`

Not `neos-package` — this is a dev tool. It belongs in `vendor/`, not `Packages/`, and
has no Flow/Neos runtime dependency.

```
sandstorm/neos-e2e-testing/
├── composer.json
├── bin/
│   └── neos-e2e-setup              ← self-contained PHP CLI script
└── templates/
    ├── Tests/
    │   ├── E2E/
    │   │   ├── package.json
    │   │   ├── playwright.config.ts
    │   │   ├── tsconfig.json
    │   │   ├── global-teardown.ts
    │   │   └── .nvmrc
    │   └── system_under_test/
    │       ├── neos8/
    │       │   ├── Dockerfile
    │       │   ├── docker-compose.yaml
    │       │   └── sut-files/
    │       │       ├── entrypoint.sh
    │       │       ├── etc/caretakerd.yaml
    │       │       ├── etc/frankenphp/Caddyfile
    │       │       └── usr/local/etc/php/conf.d/php-ini-overrides.ini
    │       └── neos9/
    │           └── (same)
    ├── .dockerignore
    ├── Makefile
    └── .github/
        └── workflows/
            └── e2e.yml
```

### `composer.json`

```json
{
  "name": "sandstorm/neos-e2e-testing",
  "type": "library",
  "description": "Scaffolds E2E test infrastructure for Neos plugins",
  "require": { "php": "^8.1" },
  "bin": ["bin/neos-e2e-setup"]
}
```

---

## Usage

```sh
composer require --dev sandstorm/neos-e2e-testing
vendor/bin/neos-e2e-setup --plugin-package sandstorm/neostwofactorauthentication
```

The script copies templates into the plugin root, substitutes tokens, and skips files that
already exist (safe to re-run).

---

## Template Tokens

Only two things vary between plugins:

| Token | Example | Used in |
|---|---|---|
| `{{PLUGIN_PACKAGE}}` | `sandstorm/neostwofactorauthentication` | Dockerfile, docker-compose project name, Makefile, GH Actions |
| `{{PLUGIN_SLUG}}` | `sandstorm-2fa` | Docker container/volume names |

The slug is derived automatically from the package name (everything after `/`, hyphens
preserved), so the developer only needs to provide `--plugin-package`.

---

## The Dockerfile Template

The only plugin-specific lines in the Dockerfile are the `composer require` call. These
are made generic via a build arg:

```dockerfile
ARG PLUGIN_PACKAGE={{PLUGIN_PACKAGE}}

COPY . /tmp/plugin/
RUN --mount=type=cache,target=/root/.composer \
    composer config repositories.plugin \
      '{"type":"path","url":"/tmp/plugin","options":{"symlink":false}}' \
    && composer require ${PLUGIN_PACKAGE}:@dev
```

Everything else (PHP extensions, caretakerd, FrankenPHP, Neos base distribution, config
copy) is already generic across plugins.

---

## `.dockerignore` (generated at plugin root)

Without this, `COPY . /tmp/plugin/` in the Dockerfile would include the test
infrastructure itself inside the image:

```
Tests/E2E/node_modules
Tests/E2E/.playwright
Tests/system_under_test
```

---

## The `bin/neos-e2e-setup` Script

A self-contained PHP script — no autoloading, no framework. It:

1. Parses `--plugin-package` from `$argv`
2. Derives `{{PLUGIN_SLUG}}` from the package name
3. Iterates over `templates/` recursively
4. For each template file: substitutes tokens, copies to the target path
5. Skips files that already exist (idempotent)

---

## What the Developer Adds After Scaffolding

The scaffolded layout has intentional empty directories (with `.gitkeep`) for the
plugin-specific content:

```
Tests/E2E/features/
    ← write .feature files here

Tests/E2E/steps/
    ← write step definitions here

Tests/system_under_test/neos8/sut-files/app/Configuration/Production/E2E-SUT/
Tests/system_under_test/neos9/sut-files/app/Configuration/Production/E2E-SUT/
    ← plugin-specific Settings.yaml, Policy.yaml, context subdirectories, etc.
```

The scaffold writes the base `Configuration/Production/E2E-SUT/Settings.yaml` (DB + Redis
connection) and `Caches.yaml`. The plugin only adds its own configuration on top.

---

## Open Question: Container Name Convention

The `SUT` environment variable (used in step definitions as `${SUT}-neos-1` to target
`docker exec`) is a contract between the scaffolded `docker-compose.yaml` and the
developer's step helpers. Since step helpers are entirely the plugin developer's business,
this convention needs to be documented somewhere visible — either in a generated `README`
in `Tests/E2E/`, or as a comment in the scaffolded `playwright.config.ts`.

A decision is needed on where this contract lives before the package is built.
