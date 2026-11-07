import { spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import path from 'node:path';
import { mkdirSync } from 'node:fs';
import { captureOptions } from './shared/options.mjs';

const here = path.dirname(fileURLToPath(import.meta.url));
const args = [];
const env = { ...process.env };
for (const argument of process.argv.slice(2)) {
  const match = argument.match(/^--(screenshots|video|trace|output)=(.+)$/);
  if (match) {
    env[match[1] === 'output' ? 'ELEMACY_TEST_OUTPUT' : `ELEMACY_CAPTURE_${match[1].toUpperCase()}`] = match[2];
  } else {
    args.push(argument);
  }
}
const capture = captureOptions(env);
env.ELEMACY_TEST_OUTPUT = capture.output;
// A run owns a fresh report directory; never overwrite unrelated artifacts.
mkdirSync(capture.output, { mode: 0o700 });
console.log(`Browser artifacts: ${capture.output}`);
console.log(`Capture: screenshots=${capture.screenshots}, video=${capture.video}, trace=${capture.trace}`);
const run = spawnSync(process.execPath, [path.join(here, 'node_modules/@playwright/test/cli.js'),
  'test', '--config=' + path.join(here, 'playwright.config.mjs'), ...args], { env, stdio: 'inherit' });
if (run.error) throw run.error;
const redaction = spawnSync('python3', [path.join(here, 'shared/redact.py'), capture.output], { stdio: 'inherit' });
if (redaction.error || redaction.status !== 0) throw new Error('Capture redaction failed; do not publish the raw report.');
process.exitCode = run.status ?? 1;
