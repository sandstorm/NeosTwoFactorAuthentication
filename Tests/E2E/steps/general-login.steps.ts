import { expect } from '@playwright/test';
import { createBdd } from 'playwright-bdd';
import { NeosLoginPage, NeosContentPage } from '../helpers/general-pages.ts';
import { createUser, logout } from '../helpers/system.ts';

const { Given, When, Then } = createBdd();

// ── Background / Given ────────────────────────────────────────────────────────

Given('A user with username {string}, password {string} and role {string} exists',
  async ({}, username: string, password: string, role: string) => {
    createUser(username, password, [role]);
  },
);

// ── When ──────────────────────────────────────────────────────────────────────

When('I log in with username {string} and password {string}',
  async ({ page }, username: string, password: string) => {
    const loginPage = new NeosLoginPage(page);
    await loginPage.goto();
    await loginPage.login(username, password);
    await page.waitForLoadState('networkidle');
  },
);

When('I log out', async ({ page }) => {
  await logout(page);
});

When('I open {string} while logged out', async ({ page }, path: string) => {
  // Requesting a protected backend URL while unauthenticated makes Neos remember
  // it as the intercepted request and bounce to the login form. After the (2FA)
  // login completes the plugin should redirect back to exactly this URL.
  await page.goto(path);
  await page.locator('input[type="password"]').waitFor();
});


// ── Then ──────────────────────────────────────────────────────────────────────

Then('I should see the Neos content page', async ({ page }) => {
  const neosContentPage = new NeosContentPage(page);
  await expect(page).toHaveURL(neosContentPage.URL_REGEX);
});

Then('I should land on {string}', async ({ page }, path: string) => {
  const escaped = path.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
  await expect(page).toHaveURL(new RegExp(escaped));
});

Then('I should see the login page', async ({ page }) => {
  await expect(page).toHaveURL(/neos\/login/);
  await expect(page.locator('input[type="password"]')).toBeVisible();
});

Then('I cannot access the Neos content page', async ({ page }) => {
  const neosContentPage = new NeosContentPage(page);
  await neosContentPage.goto();

  // expecting to be redirected (e.g. to /neos/login)
  await expect(page).not.toHaveURL(neosContentPage.URL_REGEX)
});
