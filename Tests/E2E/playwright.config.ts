import { defineConfig, devices } from '@playwright/test';
import { defineBddConfig } from 'playwright-bdd'; // env API to select system under test (SUT) (neos8 | neos9) and flow context for the configuration to be used (default, enforce for all users, etc.)

// env API to select system under test (SUT) (neos8 | neos9) and flow context for the configuration to be used (default, enforce for all users, etc.)
const SUT = process.env.SUT;
const FLOW_CONTEXT = process.env.FLOW_CONTEXT;

if (SUT == null || FLOW_CONTEXT == null) {
  throw new Error('SUT and FLOW_CONTEXT environment variables must be set!');
}

// In CI all variants run sequentially in the same job and write to the same
// reporter output. Give every FLOW_CONTEXT its own report folder so the runs
// don't overwrite each other (the whole `playwright-report/` tree is uploaded).
const reportDir = `playwright-report/${FLOW_CONTEXT.replace(/[^a-zA-Z0-9]+/g, '-')}`;

const testDir = defineBddConfig({
  features: 'features/**/*.feature',
  steps: 'steps/**/*.ts',
});

export default defineConfig({
  testDir,
  fullyParallel: false,
  // workers: 1 is required: the SUT uses a single MariaDB instance and scenarios mutate global state
  workers: 1,
  retries: process.env.CI ? 1 : 0,
  timeout: 60_000,
  expect: {
    timeout: 30_000,
  },
  forbidOnly: Boolean(process.env.CI),
  reporter: process.env.CI
    ? [
        ['html', { open: 'never', outputFolder: reportDir }],
        ['json', { outputFile: `${reportDir}/results.json` }],
      ]
    : [['html', { open: 'on-failure' }], ['list']],
  use: {
    baseURL: 'http://localhost:8081',
    actionTimeout: 10_000,
    trace: 'on-first-retry',
    screenshot: 'only-on-failure',
    video: 'on-first-retry',
  },
  globalTeardown: './global-teardown.ts',
  webServer: {
    // No `--build`: the image (`neos-ui-e2e-sut:9.0`) is built once by CI
    // (or by `make setup-sut` locally) and re-used here.
    // After editing the Dockerfile: Re-run `make setup-sut` to pick up the changes.
    command: `echo "starting system under test ${SUT} with context ${FLOW_CONTEXT}"; FLOW_CONTEXT=${FLOW_CONTEXT} docker compose -f ./system_under_test/${SUT}/docker-compose.yaml up`,
    url: 'http://localhost:8081/',
    // Reuse an already-running SUT instead of erroring when port 8081 is taken.
    // The SUT cold-boot re-downloads composer packages (~3-4 min) on every start,
    // so reusing a healthy container keeps the local dev loop fast. In CI nothing
    // is listening yet, so Playwright still boots a fresh one.
    reuseExistingServer: true,
    timeout: 600_000,
    stdout: 'pipe',
    stderr: 'pipe',
  },
  projects: [
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'] },
    },
  ],
});
