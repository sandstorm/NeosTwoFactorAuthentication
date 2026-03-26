import { defineConfig, devices } from '@playwright/test';
import { defineBddConfig } from 'playwright-bdd';

// env API to select system under test (SUT) (neos8 | neos9) and flow context for the configuration to be used (default, enforce for all users, etc.)
const SUT = process.env.SUT || 'neos8';
const FLOW_CONTEXT = process.env.FLOW_CONTEXT || 'Production/E2E-SUT';

const sutDir = `../sytem_under_test/${SUT}`;

const testDir = defineBddConfig({
  features: 'features/**/*.feature',
  steps: 'steps/**/*.ts',
});

export default defineConfig({
  testDir,
  fullyParallel: false,
  workers: 1,
  retries: 0,
  use: {
    baseURL: 'http://localhost:8081',
    trace: 'on-first-retry',
    screenshot: "only-on-failure",
  },
  globalTeardown: './global-teardown.ts',
  webServer: {
    command: `echo "starting SUT ${SUT} with context ${FLOW_CONTEXT}"; FLOW_CONTEXT=${FLOW_CONTEXT} docker compose -f ${sutDir}/docker-compose.yaml up --build`,
    url: 'http://localhost:8081/',
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
