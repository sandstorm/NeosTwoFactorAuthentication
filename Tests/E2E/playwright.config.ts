import { defineConfig, devices } from '@playwright/test';

const SUT = process.env.SUT || 'neos8';
const FLOW_CONTEXT = process.env.FLOW_CONTEXT || 'Production/E2E-SUT';
const HEADLESS = process.env.HEADLESS !== 'false';
const sutDir = `../sytem_under_test/${SUT}`;

export default defineConfig({
  testDir: './tests',
  fullyParallel: false,
  workers: 1,
  retries: 0,
  use: {
    baseURL: 'http://localhost:8081',
    headless: HEADLESS,
    trace: 'on-first-retry',
  },
  globalTeardown: './global-teardown.ts',
  webServer: {
    command: `FLOW_CONTEXT=${FLOW_CONTEXT} docker compose -f ${sutDir}/docker-compose.yaml up --build`,
    url: 'http://localhost:8081/neos/login',
    reuseExistingServer: !!process.env.REUSE_SUT,
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
