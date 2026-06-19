import { expect } from '@playwright/test';
import { createBdd } from 'playwright-bdd';
import { BackendModulePage, SecondFactorLoginPage, SecondFactorSetupPage } from '../helpers/2fa-pages.ts';
import { NeosLoginPage } from "../helpers/general-pages.ts";
import { createUser, logout } from '../helpers/system.ts';
import { enableVirtualAuthenticator } from '../helpers/webauthn.ts';
import { state } from "../helpers/state.ts";

const { Given, When, Then } = createBdd();

// ── Background / Given ────────────────────────────────────────────────────────

Given('A user with username {string}, password {string} and role {string} with enrolled 2FA device with name {string} exists',
  async ({ page }, username: string, password: string, role: string, deviceName: string) => {
    createUser(username, password, [role]);

    const loginPage = new NeosLoginPage(page);
    await loginPage.goto();
    await loginPage.login(username, password);
    await page.waitForLoadState('networkidle');

    let secret: string;

    if (page.url().includes('second-factor-setup')) {
      // Enforced setup: the landing page is the method picker -> walk the TOTP workflow.
      const setupPage = new SecondFactorSetupPage(page);
      secret = await setupPage.setupTotpDevice(deviceName);
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

Given('I have a virtual security key', async ({ page }) => {
  await enableVirtualAuthenticator(page);
});

// ── When ──────────────────────────────────────────────────────────────────────

When('I set up a 2FA device with name {string}', async ({ page }, deviceName: string) => {
  // Enforced login setup -> method picker -> TOTP workflow.
  const setupPage = new SecondFactorSetupPage(page);
  await setupPage.waitForPage();
  const secret = await setupPage.setupTotpDevice(deviceName);

  state.deviceNameSecretMap.set(deviceName, secret);

  await page.waitForLoadState('networkidle');
});

When('I set up a WebAuthn 2FA device', async ({ page }) => {
  const setupPage = new SecondFactorSetupPage(page);
  await setupPage.waitForPage();
  await setupPage.setupWebAuthnDevice();

  await page.waitForLoadState('networkidle');
});

When('I set up a WebAuthn 2FA device with name {string}', async ({ page }, deviceName: string) => {
  const setupPage = new SecondFactorSetupPage(page);
  await setupPage.waitForPage();
  await setupPage.setupWebAuthnDevice(deviceName);

  await page.waitForLoadState('networkidle');
});

When('I enter a valid TOTP for device {string}', async ({ page }, deviceName: string) => {
  const secret = state.deviceNameSecretMap.get(deviceName);
  if (!secret) throw new Error(`No enrolled TOTP secret found for device "${deviceName}"`);

  const otpPage = new SecondFactorLoginPage(page);
  await otpPage.waitForPage();
  await otpPage.loginWithTotp(secret);
});

When('I authenticate with my security key', async ({ page }) => {
  // The second-factor-login page auto-starts the WebAuthn ceremony ~200ms after
  // load, so by the time this step runs the virtual authenticator may have already
  // completed it and navigated to the backend. Don't wait for the (possibly gone)
  // second-factor-login URL — just best-effort click the trigger and let the
  // following assertion wait for the redirect to the content page.
  const otpPage = new SecondFactorLoginPage(page);
  await otpPage.loginWithWebAuthn();

  await page.waitForLoadState('networkidle');
});

// ── Then ──────────────────────────────────────────────────────────────────────

Then('I should see the 2FA verification page', async ({ page }) => {
  await expect(page).toHaveURL(/second-factor-login/);
});

Then('I should see the 2FA setup page', async ({ page }) => {
  await expect(page).toHaveURL(/second-factor-setup/);
});

Then('I should see the 2FA method selection', async ({ page }) => {
  const setupPage = new SecondFactorSetupPage(page);
  expect(await setupPage.isMethodPickerVisible()).toBe(true);
});
