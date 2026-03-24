/**
 * Tests for FLOW_CONTEXT=Production/E2E-SUT/IssuerNameChange
 * Config: issuerName: 'Test Issuer'
 *
 * 2FA is not enforced. The setup page is accessed directly after login.
 * Tests verify that the changed issuer name does not break the 2FA setup flow.
 */
import { test, expect } from '@playwright/test';
import { execSync } from 'node:child_process';
import { NeosLoginPage, SecondFactorSetupPage } from '../helpers/pages';

const CONTAINER = `${process.env.SUT || 'neos8'}-neos-1`;

test.afterEach(() => {
  execSync(
    `docker exec -u www-data -w /app ${CONTAINER} ./flow secondFactor:deleteForAccount --username e2eadmin`,
    { stdio: 'inherit' }
  );
});

test('setup page is reachable after login', async ({ page }) => {
  const loginPage = new NeosLoginPage(page);
  await loginPage.goto();
  await loginPage.login('e2eadmin', 'password123');

  await page.goto('/neos/second-factor-setup');

  await expect(page).toHaveURL(/second-factor-setup/);
  await expect(page.locator('img[style*="max-width"]')).toBeVisible();
  await expect(page.locator('input#secret')).toBeAttached();
});

test('2FA setup can be completed with the custom issuer name', async ({ page }) => {
  const loginPage = new NeosLoginPage(page);
  const setupPage = new SecondFactorSetupPage(page);

  await loginPage.goto();
  await loginPage.login('e2eadmin', 'password123');

  await page.goto('/neos/second-factor-setup');
  await setupPage.waitForPage();

  const secret = await setupPage.getSecret();
  await setupPage.submitOtp(secret);

  await expect(page).not.toHaveURL(/second-factor-setup/);
});
