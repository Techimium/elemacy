/**
 * E2E suite for the shared Elementor frontend lifecycle adapter
 * (assets/frontend/js/elementor-lifecycle.js, task 3.5; centralized as a
 * shared Core-owned script for both engine.js and loop-grid.js in task 9.5),
 * run in headless Chromium against a minimal static fixture with a stubbed
 * elementorFrontend.elementsHandler.runReadyTrigger — isolating the
 * adapter's own readiness-queuing/once-only/release contract from real
 * WordPress, Elementor and network timing, which run-popup-assets.mjs
 * already exercises end-to-end for the popup endpoint, and
 * run-loop-pagination.mjs for the Loop Grid AJAX endpoint.
 *
 * Covers: delayed readiness (hydrate() called before Elementor's own
 * 'elementor/frontend/init' has fired must still initialize once it does),
 * already-ready (hydrate() called after must initialize synchronously),
 * initializing each subtree at most once (an already-hydrated subtree,
 * as when a popup is reopened, is not initialized a second time), and
 * release() letting a released subtree be initialized again.
 *
 * Usage:  npm install && node run-elementor-lifecycle.mjs
 */
import { chromium } from 'playwright-core';
import { createServer } from 'node:http';
import { readFileSync, copyFileSync, existsSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const dir = path.dirname(fileURLToPath(import.meta.url));
const repo = path.resolve(dir, '..', '..');
copyFileSync(path.join(repo, 'assets/frontend/js/elementor-lifecycle.js'), path.join(dir, 'elementor-lifecycle.js'));

const wpJquery = path.resolve(repo, '..', '..', '..', 'wp-includes/js/jquery/jquery.js');
if (!existsSync(wpJquery)) {
  throw new Error(`WordPress core jQuery not found at ${wpJquery}; this harness serves it locally rather than fetching a CDN copy.`);
}
copyFileSync(wpJquery, path.join(dir, 'jquery.js'));

const server = createServer((req, res) => {
  const file = req.url === '/' ? '/elementor-lifecycle.html' : req.url.split('?')[0];
  try {
    const body = readFileSync(path.join(dir, file));
    const contentType = file.endsWith('.js') ? 'text/javascript' : 'text/html';
    res.writeHead(200, { 'Content-Type': contentType });
    res.end(body);
  } catch {
    res.writeHead(404);
    res.end();
  }
});
await new Promise((r) => server.listen(0, '127.0.0.1', r));
const base = `http://127.0.0.1:${server.address().port}`;

const launchOptions = process.env.ELEMACY_CHROMIUM ? { executablePath: process.env.ELEMACY_CHROMIUM } : {};
const browser = await chromium.launch(launchOptions);
const ctx = await browser.newContext({ viewport: { width: 800, height: 600 } });
const page = await ctx.newPage();
const errors = [];
page.on('pageerror', (e) => errors.push(String(e)));

await page.goto(base + '/');

const results = [];
const check = async (name, fn) => {
  try {
    const ok = await fn();
    results.push(`${ok ? 'PASS' : 'FAIL'}: ${name}`);
    if (!ok) process.exitCode = 1;
  } catch (error) {
    results.push(`FAIL: ${name} (threw: ${error.message})`);
    process.exitCode = 1;
  }
};

const readyTriggerCount = (id) => page.evaluate((elId) => {
  const el = document.getElementById(elId).querySelector('.elementor-element');
  return parseInt(el.getAttribute('data-ready-trigger-count') || '0', 10);
}, id);

/* ---------------------------------------------------------------- */
/* Delayed readiness: hydrate() called BEFORE Elementor's own        */
/* readiness event has fired must still initialize once it fires.    */
/* ---------------------------------------------------------------- */

await check('subtree A is not initialized before hydrate() is even called', async () =>
  (await readyTriggerCount('subtree-a')) === 0
);

await page.evaluate(() => {
  window.elemacyElementorLifecycle.hydrate(document.getElementById('subtree-a'));
});

await check('calling hydrate() before elementor/frontend/init has fired does not initialize immediately', async () =>
  (await readyTriggerCount('subtree-a')) === 0
);

await page.evaluate(() => window.jQuery(window).trigger('elementor/frontend/init'));

await check('delayed readiness: firing elementor/frontend/init afterwards initializes the already-hydrated subtree exactly once', async () =>
  (await readyTriggerCount('subtree-a')) === 1
);

/* ---------------------------------------------------------------- */
/* Already ready: hydrate() called AFTER readiness initializes        */
/* synchronously, with no wait for a second event firing.             */
/* ---------------------------------------------------------------- */

await page.evaluate(() => {
  window.elemacyElementorLifecycle.hydrate(document.getElementById('subtree-b'));
});

await check('already-ready: hydrate() called after elementor/frontend/init has fired initializes synchronously', async () =>
  (await readyTriggerCount('subtree-b')) === 1
);

/* ---------------------------------------------------------------- */
/* Reopen: hydrate() called again for an already-initialized subtree */
/* (e.g. a popup closed and reopened) must not initialize it twice.  */
/* ---------------------------------------------------------------- */

await page.evaluate(() => {
  window.elemacyElementorLifecycle.hydrate(document.getElementById('subtree-a'));
  window.elemacyElementorLifecycle.hydrate(document.getElementById('subtree-b'));
});

await check('reopen: a second hydrate() call for an already-initialized subtree does not run its ready trigger again', async () =>
  (await readyTriggerCount('subtree-a')) === 1 && (await readyTriggerCount('subtree-b')) === 1
);

/* ---------------------------------------------------------------- */
/* release(): forgetting a subtree lets a later hydrate() treat it   */
/* as new again.                                                     */
/* ---------------------------------------------------------------- */

await page.evaluate(() => {
  window.elemacyElementorLifecycle.release(document.getElementById('subtree-b'));
  window.elemacyElementorLifecycle.hydrate(document.getElementById('subtree-b'));
});

await check('release() lets a later hydrate() call for the same subtree initialize it again', async () =>
  (await readyTriggerCount('subtree-b')) === 2
);

await check('no page errors across the whole run', () => errors.length === 0);

console.log(results.join('\n'));
await browser.close();
server.close();
