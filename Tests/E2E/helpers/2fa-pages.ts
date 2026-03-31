import type { Page } from '@playwright/test';
import { generateOtp } from './totp.js';

export class SecondFactorLoginPage {
  constructor(private readonly page: Page) {}

  async waitForPage() {
    await this.page.waitForURL('**/neos/second-factor-login');
  }

  async enterOtp(otp: string) {
    await this.page.locator('input#secondFactor').fill(otp);
    await this.page.locator('.neos-login-btn:not(.neos-disabled):not(.neos-hidden)').first().click();
  }

  async getErrorMessage(): Promise<string> {
    const el = this.page.locator('.neos-tooltip-error .neos-tooltip-inner');
    await el.waitFor();
    return el.innerText();
  }
}

export class SecondFactorSetupPage {
  constructor(private readonly page: Page) {}

  async waitForPage() {
    await this.page.waitForURL('**/neos/second-factor-setup');
  }

  async getSecret(): Promise<string> {
    const secret = await this.page.locator('input#secret').getAttribute('value');
    if (!secret) throw new Error('Secret not found on setup page');
    return secret;
  }

  async submitOtp(secret: string, name?: string) {
    if (name) {
      await this.page.fill('input#name', name);
    }
    const otp = generateOtp(secret);
    await this.page.locator('input#secondFactorFromApp').fill(otp);
    await this.page.locator('button[type="submit"]').click();
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

  async deleteFirstDevice() {
    await this.page.locator('button.neos-button-danger').first().click();
    // Confirm in modal
    await this.page.locator('.neos-modal-footer button.neos-button-danger').click();
  }

  /** Navigate to the new-device form, complete setup, and return the TOTP secret. */
  async addDevice(name: string): Promise<string> {
    await this.page.goto('/neos/management/twoFactorAuthentication/new');

    const secretInput = this.page.locator('input#secret');
    const secret = await secretInput.getAttribute('value');
    if (!secret) throw new Error('TOTP secret not found on new-device page');

    await this.page.fill('input#name', name);
    await this.page.fill('input#secondFactorFromApp', generateOtp(secret));
    await this.page.locator('button[data-test-id="create-second-factor-submit-button"]').click();

    // Wait for redirect back to the index (table appears)
    await this.page.locator('.neos-table').waitFor();

    return secret;
  }

  /** Find the table row for the named device and click the delete button, then confirm. */
  async deleteDeviceByName(name: string): Promise<void> {
    const row = this.locatorForDeviceRow(name);
    await row.locator('button[data-test-id="delete-second-factor-button"]').click();
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
    const deleteButton = row.locator('button[data-test-id="delete-second-factor-button"]');

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
}
