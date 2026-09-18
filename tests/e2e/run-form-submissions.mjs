import assert from 'node:assert/strict';
import { chromium } from 'playwright-core';
import { spawn, execFileSync } from 'node:child_process';
import { cpSync, writeFileSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { setTimeout as delay } from 'node:timers/promises';

const integration = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../integration');
const php = process.env.ELEMACY_TEST_PHP || 'php';
const phpRun = (...args) => execFileSync(php, args, { encoding: 'utf8', env: process.env, timeout: 60_000 });
const environment = JSON.parse(phpRun('-r',
  'require $argv[1]; echo json_encode(elemacy_test_environment((string) getenv("ELEMACY_TEST_ENV")));',
  path.join(integration, 'environment.php')));
const fixtureScript = path.join(integration, 'form-submissions-browser-fixture.php');
const base = new URL(environment.url);
let fixture;
let server;
let browser;
const consoleErrors = [];
const responses = [];
const results = [];
const report = path.join(path.dirname(environment.root), 'form-submissions-report.json');
const check = (name, ok) => { results.push(`${ok ? 'PASS' : 'FAIL'}: ${name}`); if (!ok) process.exitCode = 1; };

try {
  // Refresh only our fixtures inside the already validated disposable root.
  cpSync(path.join(integration, 'fixtures/mu-plugins'), path.join(environment.root, 'wp-content/mu-plugins'), { recursive: true });

  fixture = JSON.parse(phpRun(fixtureScript, 'create'));
  server = spawn(php, ['-S', base.host, '-t', environment.root, path.join(integration, 'router.php')], {
    env: process.env, stdio: ['ignore', 'pipe', 'pipe'],
  });
  let serverOutput = '';
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
  page.on('console', message => {
    if (message.type() === 'error') consoleErrors.push(message.text());
  });
  page.on('response', response => responses.push({ url: response.url(), status: response.status() }));

  const response = await page.goto(fixture.url, { waitUntil: 'networkidle' });
  assert.equal(response.status(), 200);

  const popup = page.locator(`[data-elemacy-popup-id="${fixture.popup_id}"]`);
  await popup.waitFor();
  const roots = [
    ['popup', popup.locator('.elemacy-form-wrapper')],
    ['ordinary page', page.locator(`.elementor-${fixture.host_id} .elemacy-form-wrapper`)],
    ['Theme Builder header', page.locator(`.elementor-${fixture.header_id} .elemacy-form-wrapper`)],
  ];
  for (const [name, root] of roots) {
    await root.waitFor();
    const form = root.locator('form');
    const input = root.locator('[name="form_field[0]"]');
    const email = root.locator('[name="form_field[1]"]');
    await input.fill('Ada');
    await email.fill('invalid-email');
    // Bypass native HTML validation to exercise the existing AJAX field-error UI.
    await form.evaluate(element => { element.noValidate = true; });
    let pending = page.waitForResponse(response => response.url().includes('admin-ajax.php'));
    await root.locator('button[type="submit"]').click();
    let result = await (await pending).json();
    check(`${name}: invalid email rejected`, result.success === false && Boolean(result.data.errors['1']));
    await root.locator('.elemacy-field-error').waitFor();
    check(`${name}: field error is accessible`, await email.getAttribute('aria-invalid') === 'true');
    await email.fill('ada@example.invalid');
    pending = page.waitForResponse(response => response.url().includes('admin-ajax.php'));
    await root.locator('button[type="submit"]').click();
    result = await (await pending).json();
    check(`${name}: valid submission accepted`, result.success === true);
    await root.locator('.elemacy-message-success').waitFor();
    check(`${name}: success clears prior errors and resets fields`, await input.inputValue() === '' && await root.locator('.elemacy-field-error').count() === 0);
    if (name === 'popup') await popup.locator('.elemacy-popup__close').click();
  }

  writeFileSync(report, JSON.stringify({ results, consoleErrors, responses }, null, 2) + '\n', { mode: 0o600 });
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
  writeFileSync(report, JSON.stringify({ results, consoleErrors, responses }, null, 2) + '\n', { mode: 0o600 });
  console.log(results.join('\n'));
  console.log(`Report: ${report}`);
}
