import { execSync } from 'node:child_process';
import { dirname  } from 'node:path';
import { type Page } from "@playwright/test";

const CONTAINER = `${process.env.SUT || 'neos8'}-neos-1`;

export function createUser(name: string, password: string, roles: string[]) {
  execSync(
    `docker exec -u www-data -w /app ${CONTAINER} bash -c "./flow user:create ${name} ${password} Test${name} User${name} --roles ${roles.join(',')}"`,
    { stdio: 'ignore', cwd: dirname('.') }
  )
}

export function removeAllUsers() {
  // `./flow user:delete '*'` exits non-zero when there are no users to delete. A teardown
  // must not fail just because a scenario created no users (e.g. tests that only check the
  // logged-out login screen), so swallow that error.
  try {
    execSync(
      `docker exec -u www-data -w /app ${CONTAINER} bash -c "./flow user:delete --assume-yes '*'"`,
      { stdio: 'ignore', cwd: dirname('.') }
    )
  } catch {
    // no users to delete — nothing to clean up
  }
}

export async function logout(page: Page) {
  await page.context().request.post('/neos/logout');
}

// Destroys ALL Neos/Flow sessions server-side by flushing the session caches.
// This is the server-side equivalent of every logged-in user's session timing
// out at once — the next authenticated backend request they make answers 401.
export function destroyAllSessions() {
  execSync(
    `docker exec -u www-data -w /app ${CONTAINER} bash -c "./flow flow:session:destroyAll"`,
    { stdio: 'ignore', cwd: dirname('.') }
  )
}
