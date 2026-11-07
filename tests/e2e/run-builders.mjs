/** Real WordPress fixture runner; static popup state tests remain separate. */
import assert from 'node:assert/strict';
import { chromium } from 'playwright-core';
import { spawn, execFileSync } from 'node:child_process';
import { cpSync, writeFileSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { setTimeout as delay } from 'node:timers/promises';

const integration = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../integration');
const php = process.env.ELEMACY_TEST_PHP || 'php';
const phpRun = (...args) => execFileSync(php, args, { encoding: 'utf8', env: process.env, timeout: 30_000 });
// Validate with the same pre-WordPress guard before creating data or serving files.
const environment = JSON.parse(phpRun('-r',
  'require $argv[1]; echo json_encode(elemacy_test_environment((string) getenv("ELEMACY_TEST_ENV")));',
  path.join(integration, 'environment.php')));
const fixtureScript = path.join(integration, 'browser-fixture.php');
const base = new URL(environment.url);
let fixture;
let server;
let browser;
let serverOutput = '';
const errors = [];
const responses = [];
const results = [];
const report = path.join(path.dirname(environment.root), 'builders-report.json');

try {
  // Refresh only our fixtures inside the already validated disposable root.
  cpSync(path.join(integration, 'fixtures/mu-plugins'), path.join(environment.root, 'wp-content/mu-plugins'), { recursive: true });
  cpSync(path.join(integration, 'fixtures/theme'), path.join(environment.root, 'wp-content/themes/elemacy-fixture'), { recursive: true });
  fixture = JSON.parse(phpRun(fixtureScript, 'create'));
  server = spawn(php, ['-S', base.host, '-t', environment.root, path.join(integration, 'router.php')], {
    env: process.env, stdio: ['ignore', 'pipe', 'pipe'],
  });
  let serverError;
  server.on('error', error => { serverError = error; });
  server.stdout.on('data', data => { serverOutput += data; });
  server.stderr.on('data', data => { serverOutput += data; });
  let ready = false;
  for (let attempt = 0; attempt < 100; attempt++) {
    if (serverError) throw serverError;
    if (server.exitCode !== null) throw new Error(`PHP server exited: ${serverOutput}`);
    try {
      const response = await fetch(new URL('/__elemacy_integration_health', base), { signal: AbortSignal.timeout(500) });
      const health = await response.json();
      assert.equal(health.token, environment.token, 'Port belongs to a different server.');
      ready = true;
      break;
    } catch (error) {
      if (error instanceof assert.AssertionError) throw error;
      await delay(100);
    }
  }
  assert.ok(ready, `PHP server did not become ready: ${serverOutput}`);
  browser = await chromium.launch(process.env.ELEMACY_CHROMIUM
    ? { executablePath: process.env.ELEMACY_CHROMIUM } : {});
  const context = await browser.newContext({ viewport: { width: 1280, height: 800 } });
  const page = await context.newPage();
  page.on('pageerror', error => errors.push({ kind: 'pageerror', message: error.message }));
  page.on('console', message => {
    if (message.type() === 'error') errors.push({ kind: 'console', message: message.text() });
  });
  page.on('requestfailed', request => errors.push({
    kind: 'network', url: request.url(), message: request.failure()?.errorText,
  }));
  page.on('response', response => {
    responses.push({ url: response.url(), status: response.status(), type: response.request().resourceType() });
    if (response.status() >= 400) errors.push({ kind: 'http', url: response.url(), status: response.status() });
  });
  const response = await page.goto(fixture.url, { waitUntil: 'networkidle' });
  assert.equal(response.status(), 200);
  const document = page.locator(`[data-elementor-id="${fixture.document_id}"]`);
  await document.getByRole('heading', { name: fixture.marker, exact: true }).waitFor();
  assert.equal(await page.locator(`[data-elementor-id="${fixture.host_id}"]`).count(), 0);
  results.push('PASS: native WordPress host displays a real foreign Elementor document');
  assert.ok(await page.evaluate(() => Boolean(window.elementorFrontend)), 'Elementor frontend was not loaded.');
  assert.ok(responses.some(item => item.status === 200 && item.type === 'stylesheet' && item.url.includes('/elementor/')),
    'No Elementor stylesheet loaded on the native host.');
  results.push('PASS: Elementor frontend assets load on the native host');
  assert.deepEqual(errors, [], 'Browser console/network failures are recorded in builders-report.json');
  results.push('PASS: no browser JavaScript, console or network failures');
} catch (error) {
  process.exitCode = 1;
  results.push(`FAIL: ${error.stack || error.message}`);
} finally {
  if (browser) await browser.close();
  if (server && server.exitCode === null) {
    server.kill('SIGTERM');
    await Promise.race([new Promise(resolve => server.once('exit', resolve)), delay(3000)]);
    if (server.exitCode === null && server.signalCode === null) server.kill('SIGKILL');
  }
  if (fixture) {
    try {
      phpRun(fixtureScript, 'cleanup', fixture.manifest);
      results.push('PASS: browser fixture documents removed');
    } catch (error) {
      process.exitCode = 1;
      results.push(`FAIL: fixture cleanup: ${error.message}`);
    }
  }
  writeFileSync(report, JSON.stringify({ results, errors, responses, serverOutput }, null, 2) + '\n', { mode: 0o600 });
  console.log(results.join('\n'));
  console.log(`Report: ${report}`);
}
