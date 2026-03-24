/**
 * Tests for FLOW_CONTEXT=Production/E2E-SUT/EnforceForProvider
 * Config: enforce2FAForAuthenticationProviders: ['Neos.Neos:Backend']
 *
 * All users authenticating via the Neos.Neos:Backend provider must set up 2FA.
 * In this SUT all backend users (admin and editor) use that provider.
 */
import { test, expect } from '@playwright/test';
import { NeosLoginPage } from '../helpers/pages';

test('administrator is redirected to 2FA setup', async ({ page }) => {
  const loginPage = new NeosLoginPage(page);
  await loginPage.goto();
  await loginPage.login('e2eadmin', 'password123');

  await expect(page).toHaveURL(/second-factor-setup/);
});

test('editor is redirected to 2FA setup', async ({ page }) => {
  const loginPage = new NeosLoginPage(page);
  await loginPage.goto();
  await loginPage.login('e2eeditor', 'password123');

  await expect(page).toHaveURL(/second-factor-setup/);
});
