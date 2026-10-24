/**
 * Real browser journey for the Current Query loop data source: a Theme
 * Builder Archive template holding an AJAX-paginated Current Query Loop Grid,
 * visited as a visitor on a category archive.
 *
 * Proves what the spec promises: later pages are the page's own paginated
 * URLs (no loop endpoint request), the in-place swap equals visiting the URL,
 * the address bar / Back / Forward / reload follow it, swapped-in Elementor
 * widgets are hydrated, a blocked fetch falls back to a normal page load, and
 * there are no console failures.
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
const fixtureScript = path.join(integration, 'current-query-browser-fixture.php');
const base = new URL(environment.url);
let fixture;
let server;
let browser;
const errors = [];
const results = [];
const report = path.join(path.dirname(environment.root), 'current-query-report.json');

const GRID = '.elemacy-ajax-pagination[data-elemacy-loop-mode="page"]';

/** Post IDs of the rendered items, from their elemacy-loop-item-<ID>-<hash> class. */
async function itemIds(page) {
  return page.locator(`${GRID} .elemacy-loop-item`).evaluateAll(
    elements => elements.map(el => {
      const full = [...el.classList].find(c => c.startsWith('elemacy-loop-item-'))?.replace('elemacy-loop-item-', '');
      return Number(full?.match(/^\d+/)?.[0]);
    })
  );
}

async function allHydrated(page) {
  const widgets = page.locator(`${GRID} [data-elemacy-fixture-thirdparty]`);
  if (await widgets.count() === 0) return false;
  const flags = await widgets.evaluateAll(els => els.map(el => el.getAttribute('data-elemacy-fixture-thirdparty-initialized')));
  return flags.every(flag => flag === 'true');
}

async function waitForIds(page, expected, message) {
  await page.waitForFunction(
    ({ selector, expected }) => {
      const ids = [...document.querySelectorAll(`${selector} .elemacy-loop-item`)].map(el => {
        const full = [...el.classList].find(c => c.startsWith('elemacy-loop-item-'))?.replace('elemacy-loop-item-', '');
        return Number(full?.match(/^\d+/)?.[0]);
      });
      return ids.length === expected.length && ids.every((id, i) => id === expected[i]);
    },
    { selector: GRID, expected },
    { timeout: 8000 }
  ).catch(() => { throw new assert.AssertionError({ message: `${message}: got ${JSON.stringify([])}` }); });
  assert.deepEqual(await itemIds(page), expected, message);
}

try {
  fixture = JSON.parse(phpRun(fixtureScript, 'create'));
  const [p1, p2, p3, p4, p5] = fixture.post_ids; // oldest -> newest; the archive lists newest first, two per page.
  const pageOne = [p5, p4];
  const pageTwo = [p3, p2];
  const pageThree = [p1];

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
  let expectedFailures = 0;
  page.on('pageerror', error => errors.push({ kind: 'pageerror', message: error.message }));
  page.on('console', message => {
    if (message.type() === 'error') errors.push({ kind: 'console', message: message.text() });
  });
  const ajaxRequests = [];
  page.on('request', request => {
    if (request.url().includes('admin-ajax.php')) ajaxRequests.push(request.url());
  });
  const archiveUrl = fixture.archive_url;
  const pageTwoUrl = new URL('page/2/', archiveUrl).toString();

  // --- Page one: server-rendered, marked for page mode, no signed payload ---
  let response = await page.goto(archiveUrl, { waitUntil: 'networkidle' });
  assert.equal(response.status(), 200);
  const grid = page.locator(GRID).first();
  await grid.waitFor();
  assert.equal(await grid.getAttribute('data-elemacy-loop-request'), null, 'A Current Query grid must not emit a signed endpoint payload.');
  assert.deepEqual(await itemIds(page), pageOne, 'Page one lists the newest two posts of the category.');
  assert.ok(await allHydrated(page), 'Page one\'s items must be hydrated on a normal page load.');
  results.push('PASS: Current Query page one lists the archive\'s own posts, carries no signed payload, and is hydrated');

  // --- AJAX page two: same posts as the URL, address bar, no loop endpoint ---
  await page.evaluate(() => { window.__elemacyNoReload = true; });
  await grid.locator('.elemacy-pagination a.page-numbers', { hasText: '2' }).first().click();
  await waitForIds(page, pageTwo, 'AJAX page two must show the posts of the page-two URL');
  assert.equal(page.url(), pageTwoUrl, 'The address bar must follow the swapped page.');
  assert.equal(await page.evaluate(() => window.__elemacyNoReload === true), true, 'The swap must not reload the document.');
  assert.ok(await allHydrated(page), 'Swapped-in items must be hydrated through the lifecycle adapter.');
  assert.equal(ajaxRequests.length, 0, 'Current Query pagination must not call admin-ajax.php.');
  results.push('PASS: AJAX page two swaps in place, updates the address bar, hydrates new items and makes no loop endpoint request');

  const direct = await context.newPage();
  await direct.goto(pageTwoUrl, { waitUntil: 'networkidle' });
  assert.deepEqual(await itemIds(direct), pageTwo, 'Visiting the page-two URL directly shows the same posts.');
  await direct.close();
  results.push('PASS: the swapped page equals what visiting its URL shows');

  // --- A second consecutive swap, then history ---
  await grid.locator('.elemacy-pagination a.page-numbers', { hasText: '3' }).first().click();
  await waitForIds(page, pageThree, 'AJAX page three must show the last post');
  assert.ok(await allHydrated(page), 'A second consecutive swap must also hydrate its items.');
  await page.goBack();
  await waitForIds(page, pageTwo, 'Back must return to page two');
  assert.equal(page.url(), pageTwoUrl);
  await page.goBack();
  await waitForIds(page, pageOne, 'Back again must return to page one');
  assert.equal(page.url(), archiveUrl);
  await page.goForward();
  await waitForIds(page, pageTwo, 'Forward must return to page two');
  assert.equal(await page.evaluate(() => window.__elemacyNoReload === true), true, 'History navigation must not reload the document.');
  results.push('PASS: Back and Forward follow the swapped pages without reloading the document');

  // --- Reload lands on the URL's page ---
  await page.reload({ waitUntil: 'networkidle' });
  assert.deepEqual(await itemIds(page), pageTwo, 'Reloading page two must render page two.');
  results.push('PASS: reload on a swapped page renders that page');

  // --- A failing in-place request falls back to a normal page load ---
  await page.goto(archiveUrl, { waitUntil: 'networkidle' });
  await page.route('**/page/2/**', route => (route.request().resourceType() === 'fetch' ? route.abort() : route.continue()));
  await page.evaluate(() => { window.__elemacyNoReload = true; });
  await Promise.all([
    page.waitForURL(pageTwoUrl),
    page.locator(`${GRID} .elemacy-pagination a.page-numbers`, { hasText: '2' }).first().click(),
  ]);
  await page.waitForLoadState('networkidle');
  assert.equal(await page.evaluate(() => window.__elemacyNoReload === undefined), true, 'A blocked fetch must fall back to a real navigation.');
  assert.deepEqual(await itemIds(page), pageTwo, 'The fallback navigation shows page two.');
  await page.unroute('**/page/2/**');
  // Chromium logs the deliberately aborted fetch as a console error: expected, not a defect.
  for (let i = errors.length - 1; i >= 0; i--) {
    if (/ERR_FAILED|Failed to load resource/.test(errors[i].message)) { errors.splice(i, 1); expectedFailures++; }
  }
  results.push('PASS: a blocked in-place request falls back to normal navigation to the same page');

  // --- A modified-click opens the real URL, never intercepted ---
  await page.goto(archiveUrl, { waitUntil: 'networkidle' });
  const link = page.locator(`${GRID} .elemacy-pagination a.page-numbers`, { hasText: '2' }).first();
  assert.equal(new URL(await link.getAttribute('href'), archiveUrl).toString(), pageTwoUrl, 'Pagination links are the page\'s own paginated URLs.');
  results.push('PASS: pagination links are real, crawlable page URLs');

  // --- The same behaviour on a search results page ---
  await page.goto(fixture.search_url, { waitUntil: 'networkidle' });
  assert.deepEqual(await itemIds(page), pageOne, 'Search page one lists the newest two matches.');
  await page.evaluate(() => { window.__elemacyNoReload = true; });
  const searchLink = page.locator(`${GRID} .elemacy-pagination a.page-numbers`, { hasText: '2' }).first();
  assert.match(await searchLink.getAttribute('href'), /[?&]s=/, 'Search pagination links keep the search term.');
  await searchLink.click();
  await waitForIds(page, pageTwo, 'AJAX page two on a search page must show the posts of that search URL');
  assert.match(page.url(), /[?&]s=/, 'The address bar keeps the search term.');
  assert.equal(await page.evaluate(() => window.__elemacyNoReload === true), true, 'The search swap must not reload the document.');
  results.push('PASS: a Current Query loop on a search results page paginates in place and keeps the search term');

  assert.deepEqual(errors, [], 'Browser console failures are recorded in current-query-report.json');
  results.push('PASS: no unexpected browser JavaScript or console failures');
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
