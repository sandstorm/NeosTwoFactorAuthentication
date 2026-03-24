import { test, expect } from '@playwright/test';
import { execSync } from 'node:child_process';
import { NeosLoginPage, SecondFactorLoginPage, SecondFactorSetupPage, BackendModulePage } from '../helpers/pages';
import { generateOtp } from '../helpers/totp';

/**
 * Tests the Neos backend module for managing 2FA devices.
 * A device is enrolled in beforeAll by navigating to the setup page directly.
 * After all tests, any remaining devices are removed via the Flow CLI command.
 */

const CONTAINER = `${process.env.SUT || 'neos8'}-neos-1`;

let enrolledSecret: string;

test.beforeAll(async ({ browser }) => {
  const page = await browser.newPage();
  const loginPage = new NeosLoginPage(page);
  const setupPage = new SecondFactorSetupPage(page);

  await loginPage.goto();
  await loginPage.login('e2eadmin', 'password123');
  await page.goto('/neos/second-factor-setup');
  await setupPage.waitForPage();

  enrolledSecret = await setupPage.getSecret();
  await setupPage.submitOtp(enrolledSecret);

  await page.close();
});

test.afterAll(() => {
  execSync(
    `docker exec -u www-data -w /app ${CONTAINER} ./flow secondFactor:deleteForAccount --username e2eadmin`,
    { stdio: 'inherit' }
  );
});

async function loginWithOtp(page: any) {
  const loginPage = new NeosLoginPage(page);
  const otpPage = new SecondFactorLoginPage(page);

  await loginPage.goto();
  await loginPage.login('e2eadmin', 'password123');

  await otpPage.waitForPage();
  await otpPage.enterOtp(generateOtp(enrolledSecret));
}

test('backend module shows enrolled 2FA devices', async ({ page }) => {
  await loginWithOtp(page);

  const modulePage = new BackendModulePage(page);
  await modulePage.goto();
  await modulePage.waitForPage();

  const deviceCount = await modulePage.getDeviceCount();
  expect(deviceCount).toBeGreaterThan(0);
});

test('backend module allows deleting a 2FA device', async ({ page }) => {
  await loginWithOtp(page);

  const modulePage = new BackendModulePage(page);
  await modulePage.goto();
  await modulePage.waitForPage();

  const before = await modulePage.getDeviceCount();
  await modulePage.deleteFirstDevice();

  await page.waitForLoadState('networkidle');
  const after = await modulePage.getDeviceCount();
  expect(after).toBe(before - 1);
});
