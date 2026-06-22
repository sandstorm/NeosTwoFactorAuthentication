import { createBdd } from 'playwright-bdd';
import { destroyAllSessions } from '../helpers/system.ts';

const { When } = createBdd();

When('the Neos backend session is destroyed on the server', async () => {
  destroyAllSessions();
});

When('the Neos UI makes a backend request', async ({ page }) => {
  // Reproduce what the Neos UI does on any backend interaction: issue an
  // authenticated AJAX request through the core's own fetchWithErrorHandling.
  // With the session gone the firewall answers 401, the core dispatches
  // @neos/neos-ui/System/AUTHENTICATION_TIMEOUT, and our reload saga then calls
  // window.location.reload() — which, without a session, lands on the login page.
  await page.waitForFunction(
    () =>
      Boolean(
        (window as any)['@Neos:HostPluginAPI']?.['@NeosProjectPackages']?.()
          ?.NeosUiBackendConnector?.fetchWithErrorHandling,
      ),
  );

  await page.evaluate(() => {
    const { fetchWithErrorHandling } = (window as any)['@Neos:HostPluginAPI'][
      '@NeosProjectPackages'
    ]().NeosUiBackendConnector;

    // The route exists in both Neos 8 and 9; an empty change set is harmless if
    // it ever did reach the controller, but the firewall intercepts first.
    void fetchWithErrorHandling.withCsrfToken((csrfToken: string) => ({
      url: '/neos/ui-services/change',
      method: 'POST',
      credentials: 'include',
      headers: {
        'Content-Type': 'application/json',
        'X-Flow-Csrftoken': csrfToken,
      },
      body: JSON.stringify({ changes: [] }),
    }));
  });
});
