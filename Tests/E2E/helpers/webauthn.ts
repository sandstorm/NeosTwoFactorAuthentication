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
 *
 * We also install a small `navigator.credentials.get` wrapper (see below) so a
 * test can simulate the user dismissing the OS passkey prompt.
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

  // Wrap navigator.credentials.get so that, when `window.__webauthnCancelNext` is
  // set, the *next* assertion rejects with NotAllowedError — exactly what the
  // browser throws when the user dismisses/cancels the OS passkey prompt. The
  // wrapper resets the flag immediately, so only that one ceremony is cancelled
  // and a subsequent retry (e.g. clicking the WebAuthn button again) succeeds via
  // the virtual authenticator. Added as an init script so it is in place before
  // the login page auto-starts its ceremony ~200ms after load.
  //
  // Why a JS wrapper and not a "native" cancel (press ESC / click the dialog's
  // cancel button)? Because there is no UI to drive:
  //   - The passkey prompt is native OS / browser-chrome UI, rendered outside the
  //     page DOM. Playwright's keyboard/mouse/locators only reach the page, so
  //     `page.keyboard.press('Escape')` and friends never touch that dialog.
  //   - With a CDP virtual authenticator there is no prompt at all — it answers
  //     the request programmatically, which is the whole point of using one.
  //   - The CDP `WebAuthn.*` levers don't express "user cancelled" either:
  //       * `setAutomaticPresenceSimulation(false)` makes get() *hang* (then fail
  //         via the request timeout) — that models "user ignored the prompt", not
  //         a cancel, and never shows the app's error tooltip.
  //       * `removeVirtualAuthenticator` mid-ceremony can reject a pending request,
  //         but the timing is racy and the error type isn't a guaranteed
  //         NotAllowedError.
  //     There is no CDP command meaning "dismiss the current prompt".
  // Rejecting with NotAllowedError here is therefore the *most faithful* cancel:
  // it reproduces the exact DOMException the browser raises, which drives the real
  // app code path (webauthn.js catch -> error tooltip shown -> trigger re-enabled).
  // (The spec-native abort path, navigator.credentials.get({ signal }), isn't
  // usable from the test: the app's webauthn.js doesn't pass an AbortSignal.)
  await page.addInitScript(() => {
    const credentials = navigator.credentials as CredentialsContainer & { __cancelWrapped?: boolean };
    if (credentials.__cancelWrapped) return;
    const original = credentials.get.bind(credentials);
    credentials.get = function (options?: CredentialRequestOptions) {
      if ((window as unknown as { __webauthnCancelNext?: boolean }).__webauthnCancelNext) {
        (window as unknown as { __webauthnCancelNext?: boolean }).__webauthnCancelNext = false;
        return Promise.reject(new DOMException('WebAuthn ceremony cancelled by user', 'NotAllowedError'));
      }
      return original(options);
    };
    credentials.__cancelWrapped = true;
  });

  return authenticatorId;
}

/**
 * Arm a one-shot cancellation of the next WebAuthn assertion, simulating the user
 * dismissing the passkey prompt that the login page auto-starts.
 *
 * Added as an init script (rather than a one-off page.evaluate) because the login
 * page auto-fires its ceremony on load: the flag must already be set when the new
 * document's scripts run, which a post-navigation evaluate cannot guarantee.
 */
export async function armWebAuthnCancellation(page: Page): Promise<void> {
  await page.addInitScript(() => {
    (window as unknown as { __webauthnCancelNext?: boolean }).__webauthnCancelNext = true;
  });
}

/**
 * Install a CDP virtual authenticator that models a no-PIN roaming security key (e.g. a YubiKey
 * with no FIDO2 PIN set): it can prove user *presence* (a touch) but NOT user *verification*, and
 * it cannot store a resident/discoverable credential. Used to verify that such a key can still be
 * registered as a plain 2nd factor even while passwordless login is enabled — the case that
 * previously failed with "User authentication required." because registration forced UV.
 */
export async function enableTouchOnlyAuthenticator(page: Page): Promise<string> {
  const client = await page.context().newCDPSession(page);
  await client.send('WebAuthn.enable');
  const { authenticatorId } = await client.send('WebAuthn.addVirtualAuthenticator', {
    options: {
      protocol: 'ctap2',
      transport: 'usb',
      hasResidentKey: false,
      hasUserVerification: false,
      isUserVerified: false,
      automaticPresenceSimulation: true,
    },
  });
  return authenticatorId;
}
