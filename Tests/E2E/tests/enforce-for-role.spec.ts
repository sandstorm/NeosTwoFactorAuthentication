/**
 * Tests for FLOW_CONTEXT=Production/E2E-SUT/EnforceForRole
 * Config: enforce2FAForRoles: ['Neos.Neos:Administrator']
 *
 * Only users with the Administrator role are required to set up 2FA.
 * Users with only the Editor role must be able to log in without it.
 */
import { test, expect } from '@playwright/test';
import { NeosLoginPage } from '../helpers/pages';

test('administrator is redirected to 2FA setup', async ({ page }) => {
  const loginPage = new NeosLoginPage(page);
  await loginPage.goto();
  await loginPage.login('e2eadmin', 'password123');

  await expect(page).toHaveURL(/second-factor-setup/);
});

test('editor is not redirected and accesses backend directly', async ({ page }) => {
  const loginPage = new NeosLoginPage(page);
  await loginPage.goto();
  await loginPage.login('e2eeditor', 'password123');

  await expect(page).not.toHaveURL(/second-factor/);
  await expect(page).toHaveURL(/neos/);
});
