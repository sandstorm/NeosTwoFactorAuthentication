import { expect } from '@playwright/test';
import { createBdd } from 'playwright-bdd';
import { NeosLoginPage, NeosContentPage } from '../helpers/general-pages.ts';
import { createUser, logout } from '../helpers/system.ts';
import { armWebAuthnCancellation } from '../helpers/webauthn.ts';

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

When('I sign in with a passkey', async ({ page }) => {
  const loginPage = new NeosLoginPage(page);
  await loginPage.signInWithPasskey();
  await page.waitForLoadState('networkidle');
});

When('I open the login page', async ({ page }) => {
  await new NeosLoginPage(page).goto();
});

When('I start a passkey sign-in but cancel it', async ({ page }) => {
  // Arm the one-shot cancellation before navigating, so the assertion rejects with
  // NotAllowedError (as if the user dismissed the OS passkey prompt) instead of the
  // virtual authenticator silently approving it. webauthn.js then reveals the error
  // tooltip and the page stays on the login screen.
  await armWebAuthnCancellation(page);
  await new NeosLoginPage(page).goto();
  await page.locator('[data-webauthn-passwordless] [data-webauthn-trigger]').click();
  await page.locator('[data-webauthn-passwordless] [data-webauthn-error]').waitFor({ state: 'visible' });
});

Then('I should not see the passkey sign-in button', async ({ page }) => {
  await expect(page.locator('[data-webauthn-passwordless]')).toHaveCount(0);
});

Then('the passwordless verify endpoint is forbidden', async ({ page }) => {
  // The endpoint must reject everyone while passwordless login is disabled — server-side,
  // not just by hiding the button.
  const response = await page.request.post('/neos/passwordless-webauthn/verify', {
    data: { assertion: '{}' },
    failOnStatusCode: false,
  });
  expect(response.status()).toBe(403);
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
