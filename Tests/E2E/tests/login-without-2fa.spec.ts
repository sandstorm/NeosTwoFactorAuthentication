import { test, expect } from '@playwright/test';
import { NeosLoginPage } from '../helpers/pages';

test('admin can log in without 2FA configured', async ({ page }) => {
  const loginPage = new NeosLoginPage(page);
  await loginPage.goto();
  await loginPage.login('e2eadmin', 'password123');

  // Should land in the Neos backend, not on a 2FA page
  await expect(page).not.toHaveURL(/second-factor/);
  await expect(page).toHaveURL(/neos/);
});
