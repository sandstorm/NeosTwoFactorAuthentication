import { expect } from '@playwright/test';
import { createBdd } from 'playwright-bdd';
import { BackendModulePage } from '../helpers/2fa-pages.ts';
import { state } from '../helpers/state.js';

const { When, Then } = createBdd();

When('I navigate to the 2FA management page', async ({ page }) => {
  const modulePage = new BackendModulePage(page);
  await modulePage.goto();
  await modulePage.waitForPage();
});

When('I add a new TOTP 2FA device with name {string}',
  async ({ page }, deviceName: string) => {
    const modulePage = new BackendModulePage(page);
    const secret = await modulePage.addDevice(deviceName);

    state.deviceNameSecretMap.set(deviceName, secret);
  },
);

When('I try to remove the 2FA device with the name {string}',
  async ({ page }, name: string) => {
    const modulePage = new BackendModulePage(page);
    await page.pause();
    await modulePage.tryDeleteDeviceByName(name);
  },
);

// "with name" and "with the name" are both used in feature files
When('I remove the 2FA device with the name {string}',
  async ({ page }, name: string) => {
    const modulePage = new BackendModulePage(page);
    await modulePage.deleteDeviceByName(name);
  },
);

Then('There should be {int} enrolled 2FA device(s)',
  async ({ page }, countStr: string) => {
    await expect(page.locator('.neos-table tbody tr')).toHaveCount(parseInt(countStr, 10));
  },
);

Then('There should be a 2FA device with the name {string}',
  async ({ page }, name: string) => {
    const modulePage = new BackendModulePage(page);
    await expect(modulePage.locatorForDeviceRow(name)).toBeVisible();
  },
);

Then('There should be no 2FA device with the name {string}',
  async ({ page }, name: string) => {
    const modulePage = new BackendModulePage(page);
    await expect(modulePage.locatorForDeviceRow(name)).toHaveCount(0);
  },
);
