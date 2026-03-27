import { expect } from '@playwright/test';
import { createBdd } from 'playwright-bdd';
import { BackendModulePage, NeosLoginPage, SecondFactorLoginPage, SecondFactorSetupPage } from '../helpers/pages.ts';
import { generateOtp } from '../helpers/totp.ts';
import { createUser, logout } from '../helpers/system.ts';
import { state } from "../helpers/state.ts";

const { Given, When, Then } = createBdd();

// ── Background / Given ────────────────────────────────────────────────────────

Given('A user with username {string}, password {string} and role {string} exists',
  async ({}, username: string, password: string, role: string) => {
    createUser(username, password, [role]);
  },
);

Given('A user with username {string}, password {string} and role {string} with enrolled 2FA device with name {string} exists',
  async ({ page }, username: string, password: string, role: string, deviceName: string) => {
    createUser(username, password, [role]);

    const loginPage = new NeosLoginPage(page);
    await loginPage.goto();
    await loginPage.login(username, password);
    await page.waitForLoadState('networkidle');

    let secret: string;

    if (page.url().includes('second-factor-setup')) {
      const setupPage = new SecondFactorSetupPage(page);
      secret = await setupPage.getSecret();
      await setupPage.submitOtp(secret, deviceName);
      await page.waitForLoadState('networkidle');
    } else {
      const modulePage = new BackendModulePage(page);
      await modulePage.goto();
      await modulePage.waitForPage();
      secret = await modulePage.addDevice(deviceName);
    }

    state.deviceNameSecretMap.set(deviceName, secret);

    await logout(page);
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
  await page.pause();
  await logout(page);
});

When('I set up a 2FA device with name {string}', async ({ page }, deviceName: string) => {
  const setupPage = new SecondFactorSetupPage(page);
  await setupPage.waitForPage();
  const secret = await setupPage.getSecret();
  await setupPage.submitOtp(secret, deviceName);

  state.deviceNameSecretMap.set(deviceName, secret);

  await page.waitForLoadState('networkidle');
});

When('I enter a valid TOTP for device {string}', async ({ page }, deviceName: string) => {
  const secret = state.deviceNameSecretMap.get(deviceName);
  if (!secret) throw new Error(`No enrolled TOTP secret found for device "${deviceName}"`);

  const otpPage = new SecondFactorLoginPage(page);
  await otpPage.waitForPage();
  await otpPage.enterOtp(generateOtp(secret));

  await page.waitForLoadState('networkidle');
});

// ── Then ──────────────────────────────────────────────────────────────────────

Then('I should see the Neos content page', async ({ page }) => {
  await expect(page).not.toHaveURL(/neos\/login/);
  await expect(page).not.toHaveURL(/second-factor/);
});

Then('I should see the 2FA verification page', async ({ page }) => {
  await expect(page).toHaveURL(/second-factor-login/);
});

Then('I should see the 2FA setup page', async ({ page }) => {
  await expect(page).toHaveURL(/second-factor-setup/);
});

Then('I cannot access the Neos content page', async ({ page }) => {
  await page.goto('/neos/content');

  await expect(page).not.toHaveURL(/neos\/content/);
});
