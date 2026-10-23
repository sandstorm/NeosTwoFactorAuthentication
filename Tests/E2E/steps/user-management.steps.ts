import { expect } from '@playwright/test';
import { createBdd } from 'playwright-bdd';
import { firstLinkInMailTo, linksInMail, waitForMailsTo, mailsTo } from '../helpers/mailpit.ts';
import { sendPasswordMailViaCli, setEmail, workspaceNames } from '../helpers/system.ts';
import { state } from '../helpers/state.ts';

const { Given, When, Then } = createBdd();

const MODULE_URL = '/neos/administration/userManagement';

// ── Given ─────────────────────────────────────────────────────────────────────

Given('user {string} has the validated email {string}', async ({}, username: string, email: string) => {
  setEmail(username, email, true);
});

Given('user {string} has the unvalidated email {string}', async ({}, username: string, email: string) => {
  setEmail(username, email, false);
});

Given('an initial password mail was sent to {string} via CLI', async ({}, username: string) => {
  sendPasswordMailViaCli('initialpassword', username);
});

// ── Forgot / set password ─────────────────────────────────────────────────────

When('I request a password reset for {string}', async ({ page }, usernameOrEmail: string) => {
  await page.goto('/neos/login');
  await page.locator('[data-test-id="forgot-password-link"]').click();
  await page.locator('#usernameOrEmail').fill(usernameOrEmail);
  await page.locator('[data-test-id="request-password-reset"]').click();
  await page.waitForLoadState('networkidle');
});

Then('I should see the generic password reset confirmation', async ({ page }) => {
  await expect(page.locator('[data-test-id="request-sent"]')).toBeVisible();
});

Then('{string} should receive {int} mail(s)', async ({}, recipient: string, count: number) => {
  const mails = await waitForMailsTo(recipient, count);
  expect(mails).toHaveLength(count);
});

Then('{string} should receive no mail', async ({}, recipient: string) => {
  // give a wrongly sent mail the chance to arrive
  await new Promise((resolve) => setTimeout(resolve, 1000));
  expect(await mailsTo(recipient)).toHaveLength(0);
});

Then('the mail to {string} should contain {int} password link(s)', async ({}, recipient: string, count: number) => {
  const mails = await waitForMailsTo(recipient, 1);
  expect(mails.flatMap((mail) => linksInMail(mail, '/neos/password/set/'))).toHaveLength(count);
});

When('I open the password link from the mail to {string}', async ({ page }, recipient: string) => {
  state.lastLink = await firstLinkInMailTo(recipient, '/neos/password/set/');
  await page.goto(state.lastLink);
});

When('I open the password link for {string} from the mail to {string}', async ({ page }, username: string, recipient: string) => {
  state.lastLink = await firstLinkInMailTo(recipient, '/neos/password/set/', username);
  await page.goto(state.lastLink);
});

When('I open the email confirmation link from the mail to {string}', async ({ page }, recipient: string) => {
  await page.goto(await firstLinkInMailTo(recipient, '/neos/email/confirm/'));
});

When('I open the same link again', async ({ page }) => {
  await page.goto(state.lastLink);
});

When('I set the new password {string}', async ({ page }, password: string) => {
  await page.locator('#password').fill(password);
  await page.locator('#passwordConfirmation').fill(password);
  await page.locator('[data-test-id="set-password"]').click();
  await page.waitForLoadState('networkidle');
});

When('I confirm my email address', async ({ page }) => {
  await page.locator('[data-test-id="confirm-email"]').click();
  await page.waitForLoadState('networkidle');
});

Then('I should see that the link is invalid', async ({ page }) => {
  await expect(page.locator('[data-test-id="link-invalid"]')).toBeVisible();
});

Then('I should see that my email address is confirmed', async ({ page }) => {
  await expect(page.locator('[data-test-id="email-confirmed"]')).toBeVisible();
});

// Neos 8 names the personal workspace after the username ("user-" + letters and digits); Neos 9 finds it by user id
Then(
  'the personal workspace of {string} should be moved to {string}',
  async ({}, oldUsername: string, newUsername: string) => {
    if (process.env.SUT !== 'neos8') {
      return;
    }
    const workspaceName = (username: string) => 'user-' + username.replace(/[^a-z0-9]/gi, '');
    const names = workspaceNames();
    expect(names).toContain(workspaceName(newUsername));
    expect(names).not.toContain(workspaceName(oldUsername));
  },
);

// ── Backend module ────────────────────────────────────────────────────────────

When('I open the user management module', async ({ page }) => {
  await page.goto(MODULE_URL);
});

When(
  'I create the user {string} with email {string} and the initial password mail',
  async ({ page }, username: string, email: string) => {
    await page.goto(MODULE_URL);
    await page.locator('[data-test-id="new-user"]').click();
    await page.locator('#firstName').fill('Test');
    await page.locator('#lastName').fill(username);
    await page.locator('#email').fill(email);
    await page.locator('#username').fill(username);
    await expect(page.locator('[data-test-id="send-password-mail"]')).toBeChecked();
    await page.locator('[data-test-id="create-user"]').click();
    await page.waitForLoadState('networkidle');
  },
);

When('I open the user {string} in the user management module', async ({ page }, username: string) => {
  await page.goto(MODULE_URL);
  await page.locator(`[data-test-id="user-row"][data-username="${username}"] a`).first().click();
  await page.waitForLoadState('networkidle');
});

When('I change the email to {string}', async ({ page }, email: string) => {
  await page.locator('[data-test-id="email"]').fill(email);
  await page.locator('[data-test-id="save-user"]').click();
  await page.waitForLoadState('networkidle');
});

When('I click {string} in the user details', async ({ page }, testId: string) => {
  await page.locator(`[data-test-id="${testId}"]`).first().click();
  await page.waitForLoadState('networkidle');
});

When('I delete the users {string} via bulk delete', async ({ page }, usernames: string) => {
  await page.goto(MODULE_URL);
  for (const username of usernames.split(',').map((name) => name.trim())) {
    await page.locator(`[data-test-id="user-row"][data-username="${username}"] input[type="checkbox"]`).check();
  }
  await page.locator('[data-test-id="delete-selected"]').click();
  await page.locator('[data-test-id="confirm-delete"]').click();
  await page.waitForLoadState('networkidle');
});

When('I send a test mail', async ({ page }) => {
  await page.goto(MODULE_URL);
  await page.locator('[data-test-id="send-test-mail"]').click();
  await page.waitForLoadState('networkidle');
});

Then('the email of the user should be shown as not validated', async ({ page }) => {
  await expect(page.locator('[data-test-id="send-email-verification"]')).toBeVisible();
});

Then('the email of the user should be shown as validated', async ({ page }) => {
  await expect(page.locator('[data-test-id="send-email-verification"]')).toHaveCount(0);
});

Then('the user list should contain {string}', async ({ page }, username: string) => {
  await page.goto(MODULE_URL);
  await expect(page.locator(`[data-test-id="user-row"][data-username="${username}"]`)).toHaveCount(1);
});

Then('the user list should not contain {string}', async ({ page }, username: string) => {
  await page.goto(MODULE_URL);
  await expect(page.locator(`[data-test-id="user-row"][data-username="${username}"]`)).toHaveCount(0);
});

Then('the core user module should be disabled', async ({ page }) => {
  const response = await page.goto('/neos/administration/users');
  expect(response?.status()).not.toBe(200);
});
