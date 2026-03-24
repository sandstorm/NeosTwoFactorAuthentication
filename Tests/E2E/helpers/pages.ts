import { Page, expect } from '@playwright/test';
import { generateOtp } from './totp';

export class NeosLoginPage {
  constructor(private page: Page) {}

  async goto() {
    await this.page.goto('/neos/login');
  }

  async login(username: string, password: string) {
    await this.page.locator('input[type="text"]').fill(username);
    await this.page.locator('input[type="password"]').fill(password);
    await this.page.locator('.neos-login-btn:not(.neos-disabled):not(.neos-hidden)').click();
  }
}

export class SecondFactorLoginPage {
  constructor(private page: Page) {}

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
  constructor(private page: Page) {}

  async waitForPage() {
    await this.page.waitForURL('**/neos/second-factor-setup');
  }

  async getSecret(): Promise<string> {
    const secret = await this.page.locator('input#secret').getAttribute('value');
    if (!secret) throw new Error('Secret not found on setup page');
    return secret;
  }

  async submitOtp(secret: string) {
    const otp = generateOtp(secret);
    await this.page.locator('input#secondFactorFromApp').fill(otp);
    await this.page.locator('button[type="submit"]').click();
  }
}

export class BackendModulePage {
  constructor(private page: Page) {}

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
}
