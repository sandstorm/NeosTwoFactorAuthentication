import { test, expect } from '@playwright/test';
import { execSync } from 'node:child_process';
import { NeosLoginPage, SecondFactorSetupPage } from '../helpers/pages';

/**
 * Tests the 2FA setup flow.
 * The setup page is accessible to any authenticated user regardless of enforcement,
 * so tests navigate to it directly after login.
 */

const CONTAINER = `${process.env.SUT || 'neos8'}-neos-1`;

function deleteSecondFactors(username: string) {
  execSync(
    `docker exec -u www-data -w /app ${CONTAINER} ./flow secondFactor:deleteForAccount --username ${username}`,
    { stdio: 'inherit' }
  );
}

test.afterEach(async () => {
  deleteSecondFactors('e2eadmin');
});

test('setup page is accessible to authenticated users', async ({ page }) => {
  const loginPage = new NeosLoginPage(page);
  await loginPage.goto();
  await loginPage.login('e2eadmin', 'password123');

  await page.goto('/neos/second-factor-setup');

  await expect(page).toHaveURL(/second-factor-setup/);
  await expect(page.locator('input#secret')).toBeAttached();
});

test('completing setup with a valid OTP redirects away from the setup page', async ({ page }) => {
  const loginPage = new NeosLoginPage(page);
  const setupPage = new SecondFactorSetupPage(page);

  await loginPage.goto();
  await loginPage.login('e2eadmin', 'password123');

  await page.goto('/neos/second-factor-setup');

  const secret = await setupPage.getSecret();
  await setupPage.submitOtp(secret);

  await expect(page).not.toHaveURL(/second-factor-setup/);
});
