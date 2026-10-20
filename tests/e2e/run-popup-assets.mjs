/**
 * Real browser popup asset delivery and hydration (tasks 3.1-3.5): a plain,
 * non-Elementor host page matches a popup containing Elemacy's own Form and
 * Loop Carousel widgets plus a fixture third-party addon widget. Observes
 * each widget's own runtime behavior — not merely whether a CSS/script
 * handle was registered — covering the full D3 chain: TemplateRenderer::
 * prepare() (3.2) discovers and queues each widget's script/style
 * dependencies early instead of at wp_footer priority 99 (after WordPress
 * already printed every footer script queued so far); PopupManager/
 * PopupRenderer (3.3) prepare matched popups before that deadline instead
 * of rendering them again at emission time; and the Elementor frontend
 * lifecycle adapter plus engine.js's hydrate() (3.5) actually initialize
 * the inserted subtree's widgets and retain any sibling markup a prepared
 * fragment carries alongside its .elementor root.
 */
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
const fixtureScript = path.join(integration, 'popup-assets-browser-fixture.php');
const base = new URL(environment.url);
let fixture;
let server;
let browser;
const consoleErrors = [];
const responses = [];
const results = [];
const report = path.join(path.dirname(environment.root), 'popup-assets-report.json');
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

  // The popup has the Page Load trigger every new popup starts with, no
  // delay, so it should already be open by the time the network is idle.
  const popupRoot = page.locator(`[data-elemacy-popup-id="${fixture.popup_id}"]`);
  await popupRoot.waitFor();
  await page.waitForFunction(
    (el) => !el.hasAttribute('hidden'),
    await popupRoot.elementHandle(),
    { timeout: 5000 }
  );
  check('the popup itself opens and its widget markup is present', await popupRoot.locator('.elemacy-form-wrapper').count() > 0
    && await popupRoot.locator('.elemacy-loop-carousel-container').count() > 0
    && await popupRoot.locator('[data-elemacy-fixture-thirdparty]').count() > 0);

  const requestedThirdPartyScript = responses.some(r => r.url.includes('popup-third-party-widget.js'));
  const requestedFormScript = responses.some(r => r.url.includes('/form.js'));
  const requestedCarouselScript = responses.some(r => r.url.includes('loop-carousel.js'));

  check(
    "the third-party widget's own script is delivered (tasks 3.2/3.3: prepared before the footer script deadline)",
    requestedThirdPartyScript
  );
  check(
    "the form widget's own script is delivered",
    requestedFormScript
  );
  check(
    "the carousel widget's own script is delivered",
    requestedCarouselScript
  );

  const thirdPartyInitialized = await page.evaluate(() => {
    const el = document.querySelector('[data-elemacy-fixture-thirdparty]');
    return el ? el.hasAttribute('data-elemacy-fixture-thirdparty-initialized') : null;
  });
  check(
    "the third-party widget's own handler actually runs (task 3.5: the Elementor frontend lifecycle adapter initializes the hydrated subtree)",
    thirdPartyInitialized === true
  );

  const carouselInitialized = await page.evaluate(() => {
    if (!window.jQuery) return null;
    const $el = window.jQuery('.elemacy-loop-carousel');
    return $el.length ? Boolean($el.data('swiper')) : null;
  });
  check(
    "the carousel's own handler runs (Swiper attaches to it)",
    carouselInitialized === true
  );

  // task 3.2/3.5: fixtures/mu-plugins/template-rendering-preparation.php
  // marks this popup so its prepared fragment carries a sibling <style>
  // tag before the .elementor root. hydrate() must append the complete
  // fragment, not just the located .elementor element, or this never
  // reaches the DOM.
  const siblingStylePresent = await page.evaluate(() =>
    Boolean(document.getElementById('elemacy-fixture-sibling-style'))
  );
  check(
    'a sibling <style> fragment accompanying the popup document root survives hydration',
    siblingStylePresent
  );

  // task 3.5: the form widget's own submit handler (form.js) must actually
  // be attached to the hydrated popup's form — not merely present as
  // markup — for its AJAX submission path to run at all.
  responses.length = 0;
  const submission = page.waitForResponse(response => response.url().includes('admin-ajax.php'));
  await popupRoot.locator('.elemacy-form button[type="submit"]').click();
  const formResponse = await submission;
  const formBody = await formResponse.json();
  check('the form submission returns an accepted application response: ' + JSON.stringify(formBody), formBody.success === true);
  await delay(500);
  // The action name travels in the POST body (FormData), not the URL — a
  // request reaching admin-ajax.php at all here can only be this submit,
  // since nothing else on this fixture page calls it.
  const submittedViaAjax = responses.some(r => r.url.includes('admin-ajax.php') && r.status === 200);
  check(
    "submitting the popup's form actually fires its AJAX handler (the submit listener attached, not just the markup)",
    submittedViaAjax
  );

  // task 3.5: reopen — closing and reopening the same real, Elementor-
  // hydrated popup must not lose or duplicate widget initialization. The
  // subtree is never re-inserted (engine.js hydrates a popup's <template>
  // at most once), so this also proves the lifecycle adapter's own-once
  // guard holds across a real close/reopen, not just the isolated fixture
  // in run-elementor-lifecycle.mjs.
  await popupRoot.locator('.elemacy-popup__close').click();
  await page.waitForFunction(
    (el) => el.hasAttribute('hidden'),
    await popupRoot.elementHandle(),
    { timeout: 5000 }
  );
  await page.evaluate((id) => window.elemacyPopups.open(id), fixture.popup_id);
  await page.waitForFunction(
    (el) => !el.hasAttribute('hidden'),
    await popupRoot.elementHandle(),
    { timeout: 5000 }
  );
  const reopenState = await page.evaluate(() => ({
    thirdPartyCount: document.querySelectorAll('[data-elemacy-fixture-thirdparty]').length,
    thirdPartyInitialized: document.querySelector('[data-elemacy-fixture-thirdparty]')
      ?.hasAttribute('data-elemacy-fixture-thirdparty-initialized'),
    swiperAttached: window.jQuery ? Boolean(window.jQuery('.elemacy-loop-carousel').data('swiper')) : null,
  }));
  check(
    'reopening the popup keeps exactly one instance of its widget markup (no duplicate re-hydration)',
    reopenState.thirdPartyCount === 1
  );
  check(
    'reopening the popup keeps the third-party widget initialized',
    reopenState.thirdPartyInitialized === true
  );
  check(
    'reopening the popup keeps the carousel initialized (Swiper still attached)',
    reopenState.swiperAttached === true
  );

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
