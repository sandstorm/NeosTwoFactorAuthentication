import { createBdd } from 'playwright-bdd';
import { state } from "../helpers/state.ts";
import { logout, removeAllUsers } from "../helpers/system.ts";

const { AfterScenario } = createBdd();

// reset db after each scenario
AfterScenario(async ({ page }) => {
  await logout(page)
  removeAllUsers();

  // clear state
  state.deviceNameSecretMap.clear();
});
