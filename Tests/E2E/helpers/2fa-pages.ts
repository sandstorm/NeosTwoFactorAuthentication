import type { Page } from '@playwright/test';
import { generateOtp } from './totp.js';

/**
 * Accessible names (English) of the two method-picker entries, used to select
 * them by role+name instead of a test id. These mirror the `method.*.label`
 * translations the SUT renders in English.
 */
const METHOD_LINK_NAME = {
  totp: 'Authenticator app',
  webauthn: 'Passkey',
} as const;

/**
 * The 2FA verification page shown on login when the account already has an
 * enrolled second factor (route: /neos/second-factor-login).
 *
 * Depending on the enrolled factors this page can show a WebAuthn step, a TOTP
 * step, or both. The TOTP step is the form containing the `#secondFactor` input.
 */
export class SecondFactorLoginPage {
  constructor(private readonly page: Page) {}

  async waitForPage() {
    await this.page.waitForURL('**/neos/second-factor-login');
  }

  /** The TOTP form (the WebAuthn step also carries `.neos-login-btn`, so scope to this form). */
  private otpForm() {
    return this.page.locator('form', { has: this.page.locator('input#secondFactor') });
  }

  async enterOtp(otp: string) {
    const form = this.otpForm();
    await form.locator('input#secondFactor').fill(otp);
    await form.locator('.neos-login-btn:not(.neos-disabled):not(.neos-hidden)').first().click();
  }

  /**
   * Submit a freshly generated TOTP code, retrying on a boundary-rejected code.
   * On rejection the server re-renders the verification page; success leaves it.
   */
  async loginWithTotp(secret: string) {
    for (let attempt = 0; attempt < 4; attempt++) {
      await this.enterOtp(generateOtp(secret));
      await this.page.waitForLoadState('networkidle');
      if (!this.page.url().includes('second-factor-login')) return;
    }
    throw new Error('TOTP login code repeatedly rejected');
  }

  /**
   * Trigger the WebAuthn assertion. The page also auto-starts the ceremony
   * ~200ms after load, so the click is best-effort: if the auto-trigger has
   * already started (button disabled) or finished (page navigated away) the
   * click is swallowed, and the redirect to the backend is awaited by the
   * caller's assertion. With a virtual authenticator the ceremony auto-resolves.
   */
  async loginWithWebAuthn() {
    const trigger = this.page.locator('[data-webauthn-login] [data-webauthn-trigger]');
    try {
      await trigger.click({ timeout: 2000 });
    } catch {
      // auto-trigger already running or completed — nothing to do.
    }
  }

  /**
   * Wait for the auto-started WebAuthn ceremony to have been cancelled: on a
   * rejected assertion webauthn.js reveals the (initially hidden) error tooltip
   * and re-enables the trigger button. Waiting on the error becoming visible is a
   * reliable post-cancel signal (the button starts enabled, so waiting on the
   * button alone could match its initial state before the ceremony even ran).
   */
  async waitForWebAuthnCancelled() {
    await this.page.locator('[data-webauthn-login] [data-webauthn-error]').waitFor({ state: 'visible' });
  }

  /** Restart the WebAuthn ceremony by clicking the (re-enabled) trigger button. */
  async restartWebAuthn() {
    await this.page.locator('[data-webauthn-login] [data-webauthn-trigger]:not([disabled])').click();
  }

  async getErrorMessage(): Promise<string> {
    const el = this.page.locator('.neos-tooltip-error .neos-tooltip-inner');
    await el.waitFor();
    return el.innerText();
  }
}

/**
 * The enforced-login 2FA setup flow (route: /neos/second-factor-setup).
 *
 * Since the WebAuthn feature the setup landing page is a *method picker*: the
 * user first chooses TOTP or WebAuthn, which navigates to the method-specific
 * setup page (.../setup/totp or .../setup/webauthn).
 */
export class SecondFactorSetupPage {
  constructor(private readonly page: Page) {}

  /** Wait for the method-picker landing page. */
  async waitForPage() {
    await this.page.waitForURL('**/neos/second-factor-setup');
  }

  isMethodPickerVisible() {
    return this.page.locator('.neos-two-factor__method-picker').isVisible();
  }

  async chooseTotp() {
    await this.page.getByRole('link', { name: METHOD_LINK_NAME.totp, exact: true }).click();
    await this.page.waitForURL('**/neos/second-factor-setup/totp');
  }

  async chooseWebAuthn() {
    await this.page.getByRole('link', { name: METHOD_LINK_NAME.webauthn, exact: true }).click();
    await this.page.waitForURL('**/neos/second-factor-setup/webauthn');
  }

  async getSecret(): Promise<string> {
    const secret = await this.page.locator('input#secret').getAttribute('value');
    if (!secret) throw new Error('Secret not found on setup page');
    return secret;
  }

  /** Submit the OTP (and optional device name) on the TOTP setup form. */
  async submitOtp(secret: string, name?: string) {
    if (name) {
      await this.page.fill('input#name', name);
    }
    await this.page.locator('input#secondFactorFromApp').fill(generateOtp(secret));
    // Scope to the TOTP form's submit by its accessible name: the page also
    // renders the cancel button, which is itself a type="submit" button.
    await this.page.getByRole('button', { name: 'Register second factor', exact: true }).click();
  }

  /**
   * Walk the full TOTP setup workflow from the method picker and return the secret.
   * Retries on a boundary-rejected OTP (the server re-renders the setup form with a
   * fresh secret on failure; success leaves the setup flow).
   */
  async setupTotpDevice(name?: string): Promise<string> {
    await this.chooseTotp();
    for (let attempt = 0; attempt < 4; attempt++) {
      const secret = await this.getSecret();
      await this.submitOtp(secret, name);
      await this.page.waitForLoadState('networkidle');
      if (!this.page.url().includes('second-factor-setup')) return secret;
    }
    throw new Error('TOTP setup could not be completed (OTP repeatedly rejected)');
  }

  /**
   * Walk the WebAuthn setup workflow from the method picker. Requires a virtual
   * authenticator to be enabled on the browser context (see helpers/webauthn.ts).
   */
  async setupWebAuthnDevice(name?: string) {
    await this.chooseWebAuthn();
    if (name) {
      await this.page.fill('[data-webauthn-register] [data-webauthn-name]', name);
    }
    await this.page.locator('[data-webauthn-register] [data-webauthn-trigger]').click();
  }
}

export class BackendModulePage {
  constructor(private readonly page: Page) {}

  async goto() {
    await this.page.goto('/neos/management/twoFactorAuthentication');
  }

  async waitForPage() {
    await this.page.waitForURL('**/neos/management/twoFactorAuthentication**');
  }

  async getDeviceCount(): Promise<number> {
    return this.page.locator('.neos-table tbody tr').count();
  }

  /** Open the "add second factor" method picker and select a method. */
  async chooseMethod(method: 'totp' | 'webauthn') {
    await this.page.goto('/neos/management/twoFactorAuthentication/new');
    await this.page.getByRole('link', { name: METHOD_LINK_NAME[method], exact: true }).click();
  }

  /**
   * Add a TOTP device through the new method-picker workflow and return its secret.
   * Picks TOTP from the method picker, then completes the TOTP setup form.
   */
  async addDevice(name: string): Promise<string> {
    await this.chooseMethod('totp');

    // TOTP codes are time-windowed and the server validates with no leeway, so a
    // code generated near a 30s boundary can be rejected by the time it is checked
    // (the backend then re-renders the form with a *fresh* secret). Retry with the
    // newly rendered secret until it is accepted.
    const table = this.page.locator('.neos-table');
    for (let attempt = 0; attempt < 4; attempt++) {
      // Wait for the visible OTP field to confirm the TOTP form rendered, then read
      // the secret from the hidden input (getAttribute auto-waits for attachment;
      // a hidden input never becomes "visible", so we must not waitFor() it).
      await this.page.locator('input#secondFactorFromApp').waitFor();
      const secret = await this.page.locator('input#secret').getAttribute('value');
      if (!secret) throw new Error('TOTP secret not found on TOTP setup page');

      await this.page.fill('input#name', name);
      await this.page.fill('input#secondFactorFromApp', generateOtp(secret));
      await this.page.getByRole('button', { name: 'Register second factor', exact: true }).click();
      await this.page.waitForLoadState('networkidle');

      // Success redirects to the index (table visible); rejection re-renders the form.
      if (await table.isVisible()) return secret;
    }
    throw new Error('TOTP device could not be registered (OTP repeatedly rejected)');
  }

  /**
   * Add a WebAuthn (security key) device through the new method-picker workflow.
   * Requires a virtual authenticator on the browser context (see helpers/webauthn.ts).
   */
  async addWebAuthnDevice(name?: string): Promise<void> {
    await this.chooseMethod('webauthn');
    if (name) {
      await this.page.fill('[data-webauthn-register] [data-webauthn-name]', name);
    }
    await this.page.locator('[data-webauthn-register] [data-webauthn-trigger]').click();
    // Wait for redirect back to the index (table appears)
    await this.page.locator('.neos-table').waitFor();
  }

  /** The "register a passkey" CTA banner shown when passwordless login is enabled and the user has no passkey yet. */
  bannerLocator() {
    return this.page.locator('[data-test-id="register-passkey-banner"]');
  }

  /**
   * Follow the banner's call-to-action into the passkey registration wizard and complete it.
   * Requires a virtual authenticator on the browser context (see helpers/webauthn.ts).
   */
  async registerPasskeyFromBanner(name?: string): Promise<void> {
    await this.bannerLocator().locator('[data-test-id="register-passkey-cta"]').click();
    if (name) {
      await this.page.fill('[data-webauthn-register] [data-webauthn-name]', name);
    }
    await this.page.locator('[data-webauthn-register] [data-webauthn-trigger]').click();
    await this.page.locator('.neos-table').waitFor();
  }

  /** Find the table row for the named device and click the delete button, then confirm. */
  async deleteDeviceByName(name: string): Promise<void> {
    const row = this.locatorForDeviceRow(name);
    await row.getByRole('button', { name: 'Delete second factor' }).click();
    // The confirm button shares the same accessible name as the row's delete
    // button, so a unique aria-label selector isn't possible here — target it by
    // test id, scoped to the now-visible modal.
    await this.page.locator('button[data-test-id="confirm-delete"]:visible').click();
    await this.page.waitForLoadState('networkidle');
  }

  /**
   * Attempt to delete a device by name without assuming success.
   * If the delete button is disabled or no confirmation modal appears, the step passes silently.
   * Use this when the deletion may be blocked (e.g. last device with enforcement active).
   */
  async tryDeleteDeviceByName(name: string): Promise<void> {
    const row = this.locatorForDeviceRow(name);
    const deleteButton = row.getByRole('button', { name: 'Delete second factor' });

    if (await deleteButton.isDisabled()) {
      return;
    }

    await deleteButton.click();

    const confirmButton = this.page.locator('button[data-test-id="confirm-delete"]:visible');
    if (await confirmButton.isVisible({ timeout: 1000 })) {
      await confirmButton.click();
    }

    await this.page.waitForLoadState('networkidle');
  }

  /** Locator for the table row matching a device name (for assertions). */
  locatorForDeviceRow(name: string) {
    return this.page.locator('.neos-table tbody tr').filter({ hasText: name });
  }

  /**
   * Locator for table rows of a given type, e.g. "OTP code", "Passkey" or
   * "Passkey as 2nd factor". Matches the type cell exactly so "Passkey" does not also
   * match "Passkey as 2nd factor" (substring matching would conflate the two).
   */
  locatorForDeviceRowsOfType(typeLabel: string) {
    return this.page.locator('.neos-table tbody tr').filter({
      has: this.page.getByRole('cell', { name: typeLabel, exact: true }),
    });
  }
}
