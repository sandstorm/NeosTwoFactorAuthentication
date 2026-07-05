import { execSync } from 'node:child_process';
import { dirname } from 'node:path';

export default async function globalTeardown() {
  // KEEP_SUT=1 leaves the SUT running so the next run reuses it (see reuseExistingServer
  // in playwright.config.ts) instead of paying the multi-minute cold-boot every time.
  // Useful for a tight local dev loop; CI leaves it unset so the environment is torn down.
  if (process.env.KEEP_SUT) {
    return;
  }
  const sut = process.env.SUT || 'neos8';
  execSync(
    `docker compose -f ./system_under_test/${sut}/docker-compose.yaml down -v`,
    { stdio: 'inherit', cwd: dirname('.') }
  );
}
