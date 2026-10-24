/**
 * Real browser AJAX loop pagination (task 1.11): fresh-context pagination on
 * a flat page, cached-context replay (reload the same page, paginate
 * again), and genuinely nested pagination (an outer Loop Grid whose item
 * template contains another, AJAX-paginated Loop Grid). Archive/search
 * placement is exercised at the PHP level only (ThemeBuilderPlacementResolver
 * against real WordPress archive URLs) — see docs/RELEASE-VALIDATION.md for
 * what this file covers versus what remains browser-unverified.
 */
import assert from 'node:assert/strict';
import { chromium } from 'playwright-core';
import { spawn, execFileSync } from 'node:child_process';
import { writeFileSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { setTimeout as delay } from 'node:timers/promises';

const integration = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../integration');
const php = process.env.ELEMACY_TEST_PHP || 'php';
const phpRun = (...args) => execFileSync(php, args, { encoding: 'utf8', env: process.env, timeout: 60_000 });
const environment = JSON.parse(phpRun('-r',
  'require $argv[1]; echo json_encode(elemacy_test_environment((string) getenv("ELEMACY_TEST_ENV")));',
  path.join(integration, 'environment.php')));
const fixtureScript = path.join(integration, 'loop-pagination-browser-fixture.php');
const base = new URL(environment.url);
let fixture;
let server;
let browser;
const errors = [];
const results = [];
const report = path.join(path.dirname(environment.root), 'loop-pagination-report.json');

/**
 * Post IDs of the currently rendered items, from their
 * elemacy-loop-item-<ID>-<hash> class — the trailing hash is a render-
 * identity scope (design.md D8/task 8.7: it also encodes placement/batch,
 * deliberately differing between a classic page-1 render and an AJAX
 * request for that same page-1 content, so two fetches of identical
 * underlying items are never meant to share one scope) and is stripped
 * here, the same way loop-pagination-phase1-closeout.php's own PHP-side
 * regex helper already does, so this compares underlying item identity, not
 * incidental render scope.
 */
async function itemIds(container) {
  return container.locator('.elemacy-loop-item').evaluateAll(
    elements => elements.map(el => {
      const full = [...el.classList].find(c => c.startsWith('elemacy-loop-item-'))?.replace('elemacy-loop-item-', '');
      return full?.match(/^\d+/)?.[0];
    })
  );
}

/**
 * Whether every elemacy-fixture-thirdparty widget currently in the
 * container has actually been initialized by Elementor's own
 * frontend/element_ready mechanism (task 9.5's fixture — see
 * loop-pagination-browser-fixture.php), through the shared lifecycle
 * adapter rather than loop-grid.js's own now-removed duplicate of that
 * logic. Requires at least one such widget to be present at all, so a
 * selector typo can't silently report a false pass.
 */
async function allThirdPartyWidgetsHydrated(container) {
  const widgets = container.locator('[data-elemacy-fixture-thirdparty]');
  const count = await widgets.count();
  if (count === 0) return false;
  const flags = await widgets.evaluateAll(elements => elements.map(el => el.getAttribute('data-elemacy-fixture-thirdparty-initialized')));
  return flags.every(flag => flag === 'true');
}

async function waitForAjaxSuccess(page, container, action) {
  const [response] = await Promise.all([
    page.waitForResponse(res => res.url().includes('admin-ajax.php') && res.request().method() === 'POST'),
    action(),
  ]);
  assert.equal(response.status(), 200, 'AJAX pagination request must return HTTP 200.');
  const body = await response.json();
  assert.equal(body.success, true, `AJAX pagination must succeed: ${JSON.stringify(body)}`);
  // The network response resolves before jQuery's success/complete handlers
  // run and actually replace the grid's HTML — wait for that DOM update
  // (elemacy-is-loading is removed only in the complete() callback).
  await page.waitForFunction(
    (el) => !el.classList.contains('elemacy-is-loading'),
    await container.elementHandle(),
    { timeout: 5000 }
  );
  return body;
}

try {
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
  page.on('pageerror', error => errors.push({ kind: 'pageerror', message: error.message }));
  page.on('console', message => {
    if (message.type() === 'error') errors.push({ kind: 'console', message: message.text() });
  });

  // --- Fresh-context pagination on a flat page ---
  let response = await page.goto(fixture.flat_url, { waitUntil: 'networkidle' });
  assert.equal(response.status(), 200);
  const flatGrid = page.locator('.elemacy-ajax-pagination').first();
  await flatGrid.waitFor();
  assert.ok(await flatGrid.getAttribute('data-elemacy-loop-request'), 'Flat grid did not emit a request envelope.');
  const flatPage1 = await itemIds(flatGrid);
  assert.ok(flatPage1.length > 0, 'Flat grid rendered no items.');
  assert.ok(await allThirdPartyWidgetsHydrated(flatGrid), 'Page 1\'s items must already be hydrated on normal page load.');
  results.push('PASS: page 1\'s Elementor widgets are hydrated on normal (non-AJAX) page load');
  await waitForAjaxSuccess(page, flatGrid, () => flatGrid.locator('.elemacy-pagination a.page-numbers', { hasText: '2' }).first().click());
  const flatPage2 = await itemIds(flatGrid);
  assert.notDeepEqual(flatPage1, flatPage2, 'Page 2 must show different items than page 1.');
  assert.ok(await allThirdPartyWidgetsHydrated(flatGrid), 'Page 2\'s AJAX-loaded items must be hydrated through the shared Elementor lifecycle adapter.');
  results.push('PASS: fresh-context AJAX pagination updates the grid in a real browser, and hydrates the newly-loaded Elementor widgets exactly once each (task 9.5)');

  // --- Cached-context replay: reload the same page, paginate again ---
  response = await page.reload({ waitUntil: 'networkidle' });
  assert.equal(response.status(), 200);
  const reloadedGrid = page.locator('.elemacy-ajax-pagination').first();
  await reloadedGrid.waitFor();
  const reloadedPage1 = await itemIds(reloadedGrid);
  assert.deepEqual(reloadedPage1, flatPage1, 'A reloaded page must show the same page-1 items.');
  await waitForAjaxSuccess(page, reloadedGrid, () => reloadedGrid.locator('.elemacy-pagination a.page-numbers', { hasText: '2' }).first().click());
  const reloadedPage2 = await itemIds(reloadedGrid);
  assert.notDeepEqual(reloadedPage1, reloadedPage2);
  assert.deepEqual(reloadedPage2, flatPage2, 'A reloaded page\'s signed context must page to the exact same items.');
  assert.ok(await allThirdPartyWidgetsHydrated(reloadedGrid), 'Page 2\'s AJAX-loaded items must be hydrated after a page reload too.');
  results.push('PASS: cached-context (reloaded page) pagination succeeds identically');

  // A second, consecutive AJAX load onto the SAME grid container (task 9.5's
  // own regression risk: loop-grid.js's container node is never replaced by
  // .html(), only its contents are — without release() before hydrate(),
  // the shared adapter's own "never initialize an already-seen subtree
  // twice" guard would silently skip every AJAX load after the first).
  await waitForAjaxSuccess(page, reloadedGrid, () => reloadedGrid.locator('.elemacy-pagination a.page-numbers', { hasText: '1' }).first().click());
  const reloadedPage3 = await itemIds(reloadedGrid);
  assert.deepEqual(reloadedPage3, flatPage1, 'Paging back to page 1 must show the original page-1 items.');
  assert.ok(await allThirdPartyWidgetsHydrated(reloadedGrid), 'A SECOND consecutive AJAX load\'s items must also be hydrated, not silently skipped by the adapter\'s already-initialized guard.');
  results.push('PASS: a second, consecutive AJAX load onto the same grid container hydrates its new items too (not silently skipped)');

  // --- Genuinely nested AJAX pagination ---
  response = await page.goto(fixture.nested_url, { waitUntil: 'networkidle' });
  assert.equal(response.status(), 200);
  const nestedGrid = page.locator('.elemacy-ajax-pagination').first();
  await nestedGrid.waitFor();
  assert.ok(await nestedGrid.getAttribute('data-elemacy-loop-request'), 'Nested grid did not emit a request envelope.');
  const nestedPage1 = await itemIds(nestedGrid);
  assert.ok(nestedPage1.length > 0, 'Nested grid rendered no items.');
  assert.ok(await allThirdPartyWidgetsHydrated(nestedGrid), 'The nested grid\'s page-1 items must be hydrated on normal page load.');
  await waitForAjaxSuccess(page, nestedGrid, () => nestedGrid.locator('.elemacy-pagination a.page-numbers', { hasText: '2' }).first().click());
  const nestedPage2 = await itemIds(nestedGrid);
  assert.notDeepEqual(nestedPage1, nestedPage2, 'The nested grid\'s page 2 must show different items.');
  assert.ok(await allThirdPartyWidgetsHydrated(nestedGrid), 'The nested grid\'s AJAX-loaded page-2 items must be hydrated too.');
  results.push('PASS: a nested Loop Grid (inside another Loop Grid\'s item template) paginates via AJAX in a real browser, and hydrates its Elementor widgets at every level');

  assert.deepEqual(errors, [], 'Browser console/network failures are recorded in loop-pagination-report.json');
  results.push('PASS: no browser JavaScript or console failures across all scenarios');
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
  writeFileSync(report, JSON.stringify({ results, errors }, null, 2) + '\n', { mode: 0o600 });
  console.log(results.join('\n'));
  console.log(`Report: ${report}`);
}
