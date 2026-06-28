import { expect } from '@playwright/test';
import { createBdd } from 'playwright-bdd';
import { BackendModulePage, SecondFactorLoginPage, SecondFactorSetupPage } from '../helpers/2fa-pages.ts';
import { NeosLoginPage } from "../helpers/general-pages.ts";
import { createUser, logout } from '../helpers/system.ts';
import { enableVirtualAuthenticator, enableTouchOnlyAuthenticator, armWebAuthnCancellation } from '../helpers/webauthn.ts';
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

Given('I have a touch-only virtual security key', async ({ page }) => {
  await enableTouchOnlyAuthenticator(page);
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

When('I log in with username {string} and password {string} but cancel the WebAuthn challenge',
  async ({ page }, username: string, password: string) => {
    // Arm a one-shot cancellation before navigating, so the assertion that the
    // login page auto-starts ~200ms after load rejects as if the user dismissed
    // the passkey prompt — rather than the virtual authenticator silently
    // approving it and logging the user in via WebAuthn.
    await armWebAuthnCancellation(page);

    const loginPage = new NeosLoginPage(page);
    await loginPage.goto();
    await loginPage.login(username, password);

    const otpPage = new SecondFactorLoginPage(page);
    await otpPage.waitForPage();
    await otpPage.waitForWebAuthnCancelled();
  },
);

When('I restart the WebAuthn challenge and authenticate with my security key', async ({ page }) => {
  // The auto-started ceremony was cancelled; clicking the (now re-enabled) button
  // starts a fresh assertion, which the virtual authenticator approves.
  const otpPage = new SecondFactorLoginPage(page);
  await otpPage.restartWebAuthn();

  await page.waitForLoadState('networkidle');
});

When('I cancel the 2FA login', async ({ page }) => {
  // The cancel button is the same shared component on both the 2FA verification
  // and the enforced-setup screens. Submitting it tears down the half-authenticated
  // session; the browser then follows the redirect chain back to the login screen.
  // It carries an aria-label, so we target it by its accessible name (rendered in
  // English by the SUT) rather than a test id.
  await page.getByRole('button', { name: 'Cancel and return to login', exact: true }).click();
  await page.locator('input[type="password"]').waitFor();
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
