import { execFileSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';

export const integration = fileURLToPath(new URL('../../integration/', import.meta.url));
export const php = process.env.ELEMACY_TEST_PHP || 'php';

export function environment() {
  // Reuse the existing ownership/config-hash checks before any WordPress boot.
  return JSON.parse(execFileSync(php, ['-r',
    'require $argv[1]; $e = elemacy_test_environment((string) getenv("ELEMACY_TEST_ENV")); echo json_encode(array_intersect_key($e, array_flip(["root", "url", "token", "transport"])));',
    integration + 'environment.php'], { encoding: 'utf8', timeout: 30000 }));
}

export function fixture(command, manifest = '', ...extra) {
  return JSON.parse(execFileSync(php, [integration + 'journey-fixture.php', command, manifest, ...extra.map(String)],
    { encoding: 'utf8', timeout: 30000 }));
}
