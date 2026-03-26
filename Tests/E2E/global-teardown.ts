import { execSync } from 'node:child_process';
import { dirname  } from 'node:path';

export default async function globalTeardown() {
  if (process.env.REUSE_SUT) {
    return;
  }
  const sut = process.env.SUT || 'neos8';
  const flowContext = process.env.FLOW_CONTEXT || 'Production/E2E-SUT';
  execSync(
    `FLOW_CONTEXT=${flowContext} docker compose -f ../sytem_under_test/${sut}/docker-compose.yaml down -v`,
    { stdio: 'inherit', cwd: dirname('.') }
  );
}
