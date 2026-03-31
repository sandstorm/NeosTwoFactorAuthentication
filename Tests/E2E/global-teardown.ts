import { execSync } from 'node:child_process';
import { dirname  } from 'node:path';

export default async function globalTeardown() {
  const sut = process.env.SUT || 'neos8';
  execSync(
    `docker compose -f ../system_under_test/${sut}/docker-compose.yaml down -v`,
    { stdio: 'inherit', cwd: dirname('.') }
  );
}
