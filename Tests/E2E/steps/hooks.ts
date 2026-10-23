import { createBdd } from 'playwright-bdd';
import { state } from "../helpers/state.ts";
import { clearUserManagementThrottle, logout, removeAllUsers } from "../helpers/system.ts";
import { deleteAllMails } from "../helpers/mailpit.ts";

const { AfterScenario, BeforeScenario } = createBdd();

// reset db after each scenario
AfterScenario(async ({ page }) => {
  await logout(page)
  removeAllUsers();

  // clear state
  state.deviceNameSecretMap.clear();
  state.lastLink = '';
});

// user management scenarios read mails and are subject to the forgot-password throttle
BeforeScenario({ tags: '@user-management or @username-is-email' }, async () => {
  await deleteAllMails();
  clearUserManagementThrottle();
});
