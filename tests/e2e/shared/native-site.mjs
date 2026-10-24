/**
 * The disposable site for a standalone native-browser runner: its environment,
 * PHP fixture commands, and a PHP server started on the site's own port and
 * verified to belong to it.
 */
import assert from 'node:assert/strict';
import { spawn, execFileSync } from 'node:child_process';
import path from 'node:path';
import { setTimeout as delay } from 'node:timers/promises';
import { environment, integration, php } from './environment.mjs';

export const site = environment();
export const base = new URL(site.url);

/** Runs a PHP script under tests/integration and returns its stdout. */
export const phpRun = (script, ...args) => execFileSync(php, [path.join(integration, script), ...args.map(String)],
  { encoding: 'utf8', env: process.env, timeout: 120_000 });

/** A report path beside environment.json, outside the plugin checkout. */
export const reportPath = name => path.join(path.dirname(site.root), name);

export async function startServer() {
  const server = spawn(php, ['-S', base.host, '-t', site.root, path.join(integration, 'router.php')], {
    env: process.env, stdio: ['ignore', 'pipe', 'pipe'],
  });
  let output = '';
  let failure;
  server.on('error', error => { failure = error; });
  server.stdout.on('data', data => { output += data; });
  server.stderr.on('data', data => { output += data; });

  for (let attempt = 0; attempt < 100; attempt++) {
    if (failure) throw failure;
    if (server.exitCode !== null) throw new Error(`PHP server exited: ${output}`);
    try {
      const response = await fetch(new URL('/__elemacy_integration_health', base), { signal: AbortSignal.timeout(500) });
      const health = await response.json();
      assert.equal(health.token, site.token, 'Port belongs to a different server.');

      return {
        async stop() {
          if (server.exitCode !== null) return;
          server.kill('SIGTERM');
          await Promise.race([new Promise(resolve => server.once('exit', resolve)), delay(3000)]);
          if (server.exitCode === null && server.signalCode === null) server.kill('SIGKILL');
        },
      };
    } catch (error) {
      if (error instanceof assert.AssertionError) throw error;
      await delay(100);
    }
  }

  server.kill('SIGKILL');
  throw new Error(`PHP server did not become ready: ${output}`);
}

/** Signs the fixture administrator in (its password is set by the fixture's create command). */
export async function signIn(page) {
  await page.goto(new URL('/wp-login.php', base).href);
  await page.fill('#user_login', 'elemacy_test_admin');
  await page.fill('#user_pass', `pw-${site.token}`);
  await Promise.all([page.waitForNavigation(), page.click('#wp-submit')]);
}
