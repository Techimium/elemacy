/**
 * Real browser journey for Loop Grid Load More and Infinite Scroll, over both
 * transports: Posts (the saved-definition endpoint) and Current Query (the
 * page's own URLs). Visitor behaviour only; the editor is covered by
 * run-loop-load-more-editor.mjs.
 *
 * Every scenario is a named function below, so a failure reads as the
 * behaviour that broke.
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
const fixtureScript = path.join(integration, 'loop-load-more-browser-fixture.php');
const base = new URL(environment.url);
const report = path.join(path.dirname(environment.root), 'loop-load-more-report.json');
const results = [];
const errors = [];
let fixture;
let server;
let browser;

const LOOP = '.elemacy-ajax-pagination';
const ITEMS = '.elemacy-loop-grid > [data-elemacy-item]';
const NEXT = 'a.elemacy-load-more';

/** Identities (post IDs) of a loop's items, in order. */
const itemIds = (page, nth = 0) => page.locator(LOOP).nth(nth).locator(ITEMS)
  .evaluateAll(els => els.map(el => Number(el.getAttribute('data-elemacy-item'))));

const waitForItemCount = (page, count, nth = 0) => page.waitForFunction(
  ([loop, items, index, expected]) => document.querySelectorAll(loop)[index].querySelectorAll(items).length === expected,
  [LOOP, ITEMS, nth, count],
  { timeout: 10_000 },
);

const hasNext = (page, nth = 0) => page.locator(LOOP).nth(nth).locator(NEXT).count().then(count => count > 0);

async function allHydrated(page, nth = 0) {
  const widgets = page.locator(LOOP).nth(nth).locator('[data-elemacy-fixture-thirdparty]');
  if (await widgets.count() === 0) return false;
  const flags = await widgets.evaluateAll(els => els.map(el => el.getAttribute('data-elemacy-fixture-thirdparty-initialized')));
  return flags.every(flag => flag === 'true');
}

/** Whether the next-page control is shown (its clipping nav is not squeezed to a pixel). */
async function nextIsVisible(page, nth = 0) {
  const box = await page.locator(LOOP).nth(nth).locator('> .elemacy-pagination').boundingBox();
  return !!box && box.width > 4 && box.height > 4;
}

const scrollToEnd = page => page.evaluate(() => window.scrollTo(0, document.body.scrollHeight));

/** Counts admin-ajax requests and the most that were ever in flight at once. */
function watchAjax(page) {
  const watch = { total: 0, inflight: 0, max: 0 };
  page.on('request', request => {
    if (!request.url().includes('admin-ajax.php')) return;
    watch.total++;
    watch.inflight++;
    watch.max = Math.max(watch.max, watch.inflight);
  });
  const done = request => { if (request.url().includes('admin-ajax.php')) watch.inflight--; };
  page.on('requestfinished', done);
  page.on('requestfailed', done);
  return watch;
}

async function open(context, url) {
  const page = await context.newPage();
  page.on('pageerror', error => errors.push({ kind: 'pageerror', message: error.message }));
  page.on('console', message => { if (message.type() === 'error') errors.push({ kind: 'console', message: message.text() }); });
  await page.goto(url, { waitUntil: 'networkidle' });
  await page.evaluate(() => { window.__elemacyNoReload = true; });
  return page;
}

const stayedOnPage = (page, url) => page.evaluate(() => window.__elemacyNoReload === true)
  .then(kept => kept && page.url() === url);

/** Scrolls until the loop has no next link, a step at a time. */
async function scrollUntilDone(page, nth = 0) {
  for (let step = 0; step < 20 && await hasNext(page, nth); step++) {
    const before = (await itemIds(page, nth)).length;
    await scrollToEnd(page);
    await page.waitForFunction(
      ([loop, items, index, count]) => document.querySelectorAll(loop)[index].querySelectorAll(items).length > count,
      [LOOP, ITEMS, nth, before],
      { timeout: 10_000 },
    ).catch(() => {});
  }
}

const scenarios = {
  async 'Load More appends each page in place until the last, then ends'(context) {
    const all = fixture.post_ids;
    const page = await open(context, fixture.load_more_url);
    const ajax = watchAjax(page);
    assert.deepEqual(await itemIds(page), all.slice(0, 2), 'The first page.');
    assert.equal(await page.locator(LOOP).first().getAttribute('data-elemacy-loop-style'), 'load_more');

    let clicks = 0;
    while (await hasNext(page)) {
      const shown = (await itemIds(page)).length;
      await page.locator(NEXT).first().click();
      clicks++;
      await waitForItemCount(page, Math.min(shown + 2, all.length));
      assert.deepEqual(await itemIds(page), all.slice(0, Math.min(shown + 2, all.length)), `After click ${clicks}, items continue in order.`);
      if (clicks === 1) {
        assert.equal(await page.evaluate(() => document.activeElement.getAttribute('data-elemacy-item')), String(all[2]), 'Focus moves to the first new item.');
        assert.equal(await page.locator('.elemacy-load-more-status').textContent(), 'More items loaded.', 'The result is announced.');
      }
    }

    assert.deepEqual(await itemIds(page), all, 'Every post, once.');
    assert.equal(await page.locator('.elemacy-load-more-end').isVisible(), true, 'The end message is shown.');
    assert.equal(await page.locator('.elemacy-load-more-end').textContent(), "That's all");
    assert.ok(await allHydrated(page), 'Appended items are hydrated.');
    assert.equal(ajax.total, clicks, 'One request per click.');
    assert.ok(await stayedOnPage(page, fixture.load_more_url), 'No reload and no URL change.');
    await page.close();
  },

  async 'Load More continues from a later page and works by keyboard'(context) {
    const all = fixture.post_ids;
    const page = await open(context, fixture.load_more_url);
    const secondPage = await page.locator(NEXT).first().getAttribute('href');
    await page.close();

    const later = await open(context, secondPage);
    assert.equal(await later.locator(LOOP).first().getAttribute('data-elemacy-loop-page'), '2');
    assert.deepEqual(await itemIds(later), all.slice(2, 4), 'The classic page two.');
    await later.locator(NEXT).first().focus();
    await later.keyboard.press('Enter');
    await waitForItemCount(later, 4);
    assert.deepEqual(await itemIds(later), all.slice(2, 6), 'Appending continues from page three.');
    assert.equal(await later.evaluate(() => document.activeElement.getAttribute('data-elemacy-item')), String(all[4]), 'Keyboard activation focuses the first new item.');
    await later.close();
  },

  async 'Infinite Scroll loads as the end nears, one request at a time, and stops'(context) {
    const all = fixture.post_ids;
    const page = await open(context, fixture.infinite_url);
    const ajax = watchAjax(page);
    assert.deepEqual(await itemIds(page), all.slice(0, 2), 'Nothing extra loads before scrolling.');
    assert.equal(await page.locator(LOOP).first().evaluate(el => el.classList.contains('elemacy-infinite-active')), true);
    assert.equal(await nextIsVisible(page), false, 'The next link is visually hidden while scrolling loads.');
    assert.equal(ajax.total, 0, 'No request before the visitor scrolls.');

    await scrollUntilDone(page);

    assert.deepEqual(await itemIds(page), all, 'Every post, once, in order.');
    assert.equal(ajax.max, 1, 'Never more than one request in flight.');
    assert.equal(await page.locator('.elemacy-load-more-end').isVisible(), true);
    const total = ajax.total;
    await scrollToEnd(page);
    await delay(800);
    assert.equal(ajax.total, total, 'No requests after the last page.');
    assert.ok(await allHydrated(page), 'Appended items are hydrated.');
    assert.ok(await stayedOnPage(page, fixture.infinite_url));
    await page.close();
  },

  async 'A failed Infinite Scroll stops, shows the button, and the button retries'(context) {
    const all = fixture.post_ids;
    const page = await open(context, fixture.infinite_url);
    const ajax = watchAjax(page);
    await page.route('**/admin-ajax.php', route => route.abort());

    await scrollToEnd(page);
    await delay(900);
    await scrollToEnd(page);
    await delay(500);
    assert.equal(ajax.total, 1, 'One failed attempt, then no automatic retries.');
    assert.deepEqual(await itemIds(page), all.slice(0, 2));
    assert.equal(await nextIsVisible(page), true, 'The button is shown.');

    await page.unroute('**/admin-ajax.php');
    await page.locator(NEXT).first().click();
    await waitForItemCount(page, 4);
    assert.deepEqual(await itemIds(page), all.slice(0, 4), 'The button loads the next page.');
    assert.ok(await stayedOnPage(page, fixture.infinite_url));
    await page.close();
  },

  async 'A failed Load More goes to the next page as a normal page load'(context) {
    const all = fixture.post_ids;
    const page = await open(context, fixture.load_more_url);
    await page.route('**/admin-ajax.php', route => route.abort());
    const href = await page.locator(NEXT).first().getAttribute('href');

    await Promise.all([page.waitForURL(href), page.locator(NEXT).first().click()]);
    await page.waitForLoadState('networkidle');
    assert.equal(await page.evaluate(() => window.__elemacyNoReload === undefined), true, 'A real navigation.');
    assert.deepEqual(await itemIds(page), all.slice(2, 4), 'The classic second page.');
    await page.close();
  },

  async 'Current Query Load More appends the page\'s own next pages with no loop endpoint'(context) {
    const category = fixture.category_post_ids;
    const page = await open(context, fixture.archive_url);
    const ajax = watchAjax(page);
    assert.equal(await page.locator(LOOP).first().getAttribute('data-elemacy-loop-mode'), 'page');
    assert.deepEqual(await itemIds(page), category.slice(0, 2));

    await page.locator(NEXT).first().click();
    await waitForItemCount(page, 4);
    assert.equal(await page.evaluate(() => document.activeElement.getAttribute('data-elemacy-item')), String(category[2]), 'Focus on the first new item.');
    await page.locator(NEXT).first().click();
    await waitForItemCount(page, 5);

    assert.deepEqual(await itemIds(page), category, 'All five, in the archive\'s order.');
    assert.equal(await hasNext(page), false, 'No button after the last page.');
    assert.equal(await page.locator('.elemacy-load-more-end').isVisible(), true);
    assert.ok(await allHydrated(page));
    assert.equal(ajax.total, 0, 'No admin-ajax request for Current Query.');
    assert.ok(await stayedOnPage(page, fixture.archive_url), 'The address bar is untouched.');
    await page.close();
  },

  async 'A failed Current Query Load More falls back to that page\'s URL'(context) {
    const category = fixture.category_post_ids;
    const page = await open(context, fixture.archive_url);
    const href = await page.locator(NEXT).first().getAttribute('href');
    await page.route(url => url.pathname.includes('/page/2'), route => (route.request().resourceType() === 'fetch' ? route.abort() : route.continue()));

    await Promise.all([page.waitForURL(href), page.locator(NEXT).first().click()]);
    await page.waitForLoadState('networkidle');
    assert.equal(await page.evaluate(() => window.__elemacyNoReload === undefined), true, 'A real navigation.');
    assert.deepEqual(await itemIds(page), category.slice(2, 4));
    await page.close();
  },

  async 'Current Query Infinite Scroll loads search pages through their own URLs'(context) {
    const category = fixture.category_post_ids;
    const page = await open(context, fixture.search_url);
    const ajax = watchAjax(page);
    assert.equal(await page.locator(LOOP).first().getAttribute('data-elemacy-loop-style'), 'infinite_scroll');

    await scrollUntilDone(page);

    assert.deepEqual([...await itemIds(page)].sort(), [...category].sort(), 'The five matches, once each.');
    assert.equal(ajax.total, 0);
    assert.ok(await stayedOnPage(page, fixture.search_url));
    await page.close();
  },

  async 'Two Load More grids on a page append independently'(context) {
    const all = fixture.post_ids;
    const page = await open(context, fixture.two_loops_url);
    assert.deepEqual(await itemIds(page, 0), all.slice(0, 2));
    assert.deepEqual(await itemIds(page, 1), all.slice(0, 3));

    await page.locator(LOOP).nth(0).locator(NEXT).click();
    await waitForItemCount(page, 4, 0);
    assert.deepEqual(await itemIds(page, 1), all.slice(0, 3), 'The second grid did not change.');

    await page.locator(LOOP).nth(1).locator(NEXT).click();
    await waitForItemCount(page, Math.min(6, all.length), 1);
    assert.deepEqual(await itemIds(page, 0), all.slice(0, 4), 'The first grid did not change.');
    await page.close();
  },

  async 'Without scripting the load control is a plain link to the next page'() {
    const context = await browser.newContext({ javaScriptEnabled: false });
    const page = await context.newPage();
    await page.goto(fixture.load_more_url, { waitUntil: 'load' });
    const href = await page.locator(NEXT).first().getAttribute('href');
    assert.match(href, /paged=2|\/page\/2\//);
    await page.locator(NEXT).first().click();
    await page.waitForLoadState('load');
    assert.deepEqual(await itemIds(page), fixture.post_ids.slice(2, 4), 'Following the link shows the next page.');
    await context.close();
  },

  // Last: it publishes a post, which moves every later scenario's pages.
  async 'Load More skips an item that content shifting would repeat'(context) {
    const before = [...fixture.post_ids];
    const page = await open(context, fixture.load_more_url);
    await page.locator(NEXT).first().click();
    await waitForItemCount(page, 4);

    // A newer post now exists: every later page starts one item earlier.
    phpRun(fixtureScript, 'shift', fixture.manifest);

    await page.locator(NEXT).first().click();
    // The next page starts with an item already on screen: only the new one is added.
    await waitForItemCount(page, 5);
    while (await hasNext(page)) {
      const shown = (await itemIds(page)).length;
      await page.locator(NEXT).first().click();
      await waitForItemCount(page, Math.min(shown + 2, before.length)).catch(() => {});
    }

    const ids = await itemIds(page);
    assert.equal(new Set(ids).size, ids.length, 'No item repeats.');
    assert.deepEqual(ids, before, 'Every earlier post is shown once; the newer post, already above the loop, is not.');
    await page.close();
  },
};

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

  browser = await chromium.launch(process.env.ELEMACY_CHROMIUM ? { executablePath: process.env.ELEMACY_CHROMIUM } : {});

  for (const [name, scenario] of Object.entries(scenarios)) {
    const context = await browser.newContext({ viewport: { width: 1280, height: 800 } });
    try {
      await scenario(context);
      results.push(`PASS: ${name}`);
    } catch (error) {
      process.exitCode = 1;
      results.push(`FAIL: ${name}: ${error.stack || error.message}`);
    } finally {
      await context.close();
    }
  }

  // Chromium logs the requests these scenarios abort on purpose.
  const unexpected = errors.filter(error => !/ERR_FAILED|Failed to load resource/.test(error.message));
  if (unexpected.length) {
    process.exitCode = 1;
    results.push(`FAIL: unexpected browser errors: ${JSON.stringify(unexpected.slice(0, 5))}`);
  } else {
    results.push('PASS: no unexpected browser JavaScript or console errors');
  }
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
