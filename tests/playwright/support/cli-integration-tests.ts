// Runs PHP integration tests using the existing pinned disposable stack.
// Execution is explicit; authoring this entry point does not start containers.
import { assertComposePrerequisites, compose } from './compose.js';

if (process.env.E2E_MODE !== 'isolated') {
  throw new Error('PHP integration tests require E2E_MODE=isolated.');
}

await assertComposePrerequisites();
const result = await compose(
  ['--profile', 'php-tests', 'run', '--rm', '--build', 'integration-tests'],
  { timeoutMs: 10 * 60 * 1000 }
);
process.stdout.write(result.stdout);
process.stderr.write(result.stderr);
