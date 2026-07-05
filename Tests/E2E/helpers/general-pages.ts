import type { Page } from '@playwright/test';

export class NeosLoginPage {
  constructor(private readonly page: Page) {}

  async goto() {
    await this.page.goto('/neos/login');
  }

  async login(username: string, password: string) {
    await this.page.locator('input[type="text"]').fill(username);
    await this.page.locator('input[type="password"]').fill(password);
    await this.page.locator('.neos-login-btn:not(.neos-disabled):not(.neos-hidden)').click();
  }

  /**
   * Start the passwordless (usernameless) passkey ceremony from the login screen:
   * navigate to the login page and click the "Sign in with a passkey" button.
   * With a virtual authenticator the ceremony auto-resolves and the page redirects
   * to the backend; the caller asserts the resulting URL.
   */
  async signInWithPasskey() {
    await this.goto();
    await this.page.locator('[data-webauthn-passwordless] [data-webauthn-trigger]').click();
  }
}

export class NeosContentPage {
  public readonly URL_REGEX = /neos\/content/;

  constructor(private readonly page: Page) {}

  async goto() {
    await this.page.goto('/neos/content');
  }
}
