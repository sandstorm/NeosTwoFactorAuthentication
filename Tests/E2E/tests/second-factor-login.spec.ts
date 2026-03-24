import { test, expect } from '@playwright/test';
import { execSync } from 'node:child_process';
import { NeosLoginPage, SecondFactorLoginPage, SecondFactorSetupPage } from '../helpers/pages';
import { generateOtp } from '../helpers/totp';

/**
 * Tests the 2FA OTP login flow.
 * A device is enrolled in beforeAll by navigating to the setup page directly.
 * After all tests, the device is removed via the Flow CLI command.
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

test('login redirects to OTP page when a 2FA device is enrolled', async ({ page }) => {
  const loginPage = new NeosLoginPage(page);
  const otpPage = new SecondFactorLoginPage(page);

  await loginPage.goto();
  await loginPage.login('e2eadmin', 'password123');

  await otpPage.waitForPage();
  await expect(page).toHaveURL(/second-factor-login/);
});

test('entering a valid OTP grants backend access', async ({ page }) => {
  const loginPage = new NeosLoginPage(page);
  const otpPage = new SecondFactorLoginPage(page);

  await loginPage.goto();
  await loginPage.login('e2eadmin', 'password123');

  await otpPage.waitForPage();
  await otpPage.enterOtp(generateOtp(enrolledSecret));

  await expect(page).not.toHaveURL(/second-factor/);
  await expect(page).toHaveURL(/neos/);
});

test('entering an invalid OTP shows an error and stays on the OTP page', async ({ page }) => {
  const loginPage = new NeosLoginPage(page);
  const otpPage = new SecondFactorLoginPage(page);

  await loginPage.goto();
  await loginPage.login('e2eadmin', 'password123');

  await otpPage.waitForPage();
  await otpPage.enterOtp('000000');

  await expect(page).toHaveURL(/second-factor-login/);
  const error = await otpPage.getErrorMessage();
  expect(error).toBeTruthy();
});
