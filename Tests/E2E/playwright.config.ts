import { defineConfig, devices } from '@playwright/test';
import { defineBddConfig } from 'playwright-bdd';

const SUT = process.env.SUT || 'neos8';
const FLOW_CONTEXT = process.env.FLOW_CONTEXT || 'Production/E2E-SUT';
const HEADLESS = process.env.HEADLESS !== 'false';
const REUSE_SUT = process.env.REUSE_SUT == null ? true : !!process.env.REUSE_SUT;
const sutDir = `../sytem_under_test/${SUT}`;

console.log('### Loading config with env')
console.table({
  SUT, FLOW_CONTEXT, HEADLESS, REUSE_SUT
})

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
    headless: HEADLESS,
    trace: 'on-first-retry',
    screenshot: "only-on-failure",
  },
  globalTeardown: './global-teardown.ts',
  webServer: {
    command: `FLOW_CONTEXT=${FLOW_CONTEXT} docker compose -f ${sutDir}/docker-compose.yaml up --build`,
    url: 'http://localhost:8081/',
    reuseExistingServer: REUSE_SUT,
    timeout: 120_000,
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
