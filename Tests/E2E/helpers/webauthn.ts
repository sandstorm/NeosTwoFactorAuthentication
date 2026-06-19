import type { Page } from '@playwright/test';

/**
 * Install a CDP virtual authenticator on the page's browser context so WebAuthn
 * ceremonies (registration + assertion) complete without real hardware.
 *
 * The authenticator lives on the browser context, so a credential registered
 * during setup survives the logout/login that happens within the same scenario.
 * It only works with the Chromium project (which the suite uses).
 *
 * `isUserVerified` + `automaticPresenceSimulation` make the authenticator
 * auto-approve, matching the plugin's `userVerification: 'discouraged'` default.
 * The relying party id defaults to the request hostname (`localhost`), which is
 * what the browser derives from the http://localhost:8081 origin.
 */
export async function enableVirtualAuthenticator(page: Page): Promise<string> {
  const client = await page.context().newCDPSession(page);
  await client.send('WebAuthn.enable');
  const { authenticatorId } = await client.send('WebAuthn.addVirtualAuthenticator', {
    options: {
      protocol: 'ctap2',
      transport: 'internal',
      hasResidentKey: true,
      hasUserVerification: true,
      isUserVerified: true,
      automaticPresenceSimulation: true,
    },
  });
  return authenticatorId;
}
