/**
 * E2E suite for the popups frontend engine (engine.js), run in headless
 * Chromium. Serves popups.html — whose markup mirrors PopupRenderer output and
 * whose config mirrors PopupConfigResource — from a local HTTP server so
 * localStorage/sessionStorage behave as on a real site.
 *
 * Covers: template hydration, page_load + click triggers, the frequency_cap
 * rule across page loads, explicit open/close controls, overlay-click and ESC
 * behavior, the modal focus trap (both directions), body scroll lock, ARIA
 * state, storage bookkeeping, and the shown/closed lifecycle events.
 *
 * Usage:  npm install && npm test        (from tests/e2e)
 * Chromium is resolved by playwright-core; set ELEMACY_CHROMIUM to point at an
 * existing binary instead of a playwright-managed download.
 */
import { chromium } from 'playwright-core';
import { createServer } from 'node:http';
import { readFileSync, copyFileSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const dir = path.dirname(fileURLToPath(import.meta.url));
const repo = path.resolve(dir, '..', '..');
copyFileSync(path.join(repo, 'src/Modules/Popups/assets/scripts/engine.js'), path.join(dir, 'engine.js'));
copyFileSync(path.join(repo, 'src/Modules/Popups/assets/styles/popups.css'), path.join(dir, 'popups.css'));

const server = createServer((req, res) => {
  const file = req.url === '/' ? '/popups.html' : req.url.split('?')[0];
  try {
    const body = readFileSync(path.join(dir, file));
    const contentType = file.endsWith('.js')
      ? 'text/javascript'
      : file.endsWith('.css') ? 'text/css' : 'text/html';
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
const ctx = await browser.newContext({ viewport: { width: 1280, height: 800 } });
const page = await ctx.newPage();
const errors = [];
page.on('pageerror', (e) => errors.push(String(e)));

const results = [];
const check = async (name, fn) => {
  try {
    const ok = await fn();
    results.push(`${ok ? 'PASS' : 'FAIL'}  ${name}`);
    if (!ok) process.exitCode = 1;
  } catch (e) {
    results.push(`ERROR ${name}: ${e.message}`);
    process.exitCode = 1;
  }
};

const state = (id) => page.evaluate((pid) => {
  const root = document.querySelector(`[data-elemacy-popup-id="${pid}"]`);
  const overlay = root.querySelector('[data-elemacy-overlay]');
  return {
    open: root.classList.contains('is-open'),
    hidden: root.hasAttribute('hidden'),
    ariaHidden: root.getAttribute('aria-hidden'),
    hasOverlay: !!overlay,
    overlayBlur: overlay ? getComputedStyle(overlay).backdropFilter : '',
    overlayInlineOpacity: overlay?.style.opacity || '',
    overlayTintOpacity: overlay ? getComputedStyle(overlay, '::before').opacity : '',
    hasClose: !!root.querySelector('.elemacy-popup__close'),
    bodyOverflow: document.body.style.overflow,
  };
}, id);
const store = (key) => page.evaluate((k) => ({
  local: JSON.parse(window.localStorage.getItem(k) || 'null'),
  session: window.sessionStorage.getItem(k),
}), key);

/* ── Load 1: page_load trigger, hydration, storage, a11y ── */
await page.goto(base + '/');
await page.waitForTimeout(300);

await check('101 opens on page_load (is-open, unhidden, aria-hidden=false)', async () => {
  const s = await state(101);
  return s.open && !s.hidden && s.ariaHidden === 'false';
});
await check('101 hydrated with overlay + injected close button', async () => {
  const s = await state(101);
  return s.hasOverlay && s.hasClose;
});
await check('101 applies configured overlay background blur', async () =>
  (await state(101)).overlayBlur === 'blur(8px)');
await check('101 keeps tint opacity separate from its blur layer', async () => {
  const s = await state(101);
  return s.overlayInlineOpacity === '' && s.overlayTintOpacity === '0.6';
});
await check('101 modal: body scroll locked', async () => (await state(101)).bodyOverflow === 'hidden');
await check('101 focus moved inside popup', () =>
  page.evaluate(() => document.activeElement && document.activeElement.id === 'p101-link1'));
await check('101 storage recorded (shown_count=1, session mark)', async () => {
  const s = await store('elemacy_popup_101');
  return s.local && s.local.shown_count === 1 && s.local.last_shown_at > 0 && s.session === '1';
});
await check('103 topbar opens too, non-modal (no overlay)', async () => {
  const s = await state(103);
  return s.open && !s.hasOverlay;
});
await check('shown events fired for 101 and 103 only', () =>
  page.evaluate(() => {
    const shown = window.__events.filter((e) => e[0] === 'shown').map((e) => e[1]).sort();
    return JSON.stringify(shown) === JSON.stringify([101, 103]);
  }));

/* ── Focus trap: Tab on last focusable (the injected close button) wraps to
   first; Shift+Tab on first wraps back to last ── */
await page.evaluate(() => document.querySelector('.elemacy-popup-101 .elemacy-popup__close').focus());
await page.keyboard.press('Tab');
await check('101 modal focus trap wraps Tab from last to first', () =>
  page.evaluate(() => document.activeElement.id === 'p101-link1'));
await page.keyboard.press('Shift+Tab');
await check('101 modal focus trap wraps Shift+Tab from first to last', () =>
  page.evaluate(() => document.activeElement.classList.contains('elemacy-popup__close')));

/* ── ESC closes the modal, restores scroll, fires closed, topbar survives ── */
await page.keyboard.press('Escape');
await page.waitForTimeout(100);
await check('ESC closes modal 101 (hidden, scroll restored)', async () => {
  const s = await state(101);
  return !s.open && s.hidden && s.bodyOverflow === '';
});
await check('closed event fired for 101', () =>
  page.evaluate(() => window.__events.some((e) => e[0] === 'closed' && e[1] === 101)));
await check('topbar 103 (on_esc=false, non-modal) stays open after ESC', async () => (await state(103)).open);

/* ── Click trigger + explicit open + overlay/close-button behavior ── */
await page.click('.open-b');
await page.waitForTimeout(100);
await check('102 opens via click-trigger selector', async () => (await state(102)).open);
await check('102 zero blur leaves its overlay unfiltered', async () =>
  (await state(102)).overlayBlur === 'blur(0px)');
await check('102 click-trigger open recorded in storage', async () =>
  (await store('elemacy_popup_102')).local?.shown_count === 1);

// on_overlay_click=false for 102: clicking its overlay must NOT close it.
await page.evaluate(() => document.querySelector('.elemacy-popup-102 [data-elemacy-overlay]').click());
await page.waitForTimeout(100);
await check('102 overlay click ignored (on_overlay_click=false)', async () => (await state(102)).open);

await page.evaluate(() => document.querySelector('.elemacy-popup-102 .elemacy-popup__close').click());
await page.waitForTimeout(100);
await check('102 closes via injected close button', async () => !(await state(102)).open);

await page.click('.elemacy-open-popup');
await page.waitForTimeout(100);
await check('102 explicit open button opens without counting frequency', async () => {
  const s = await state(102);
  const st = await store('elemacy_popup_102');
  return s.open && st.local.shown_count === 1; // still 1 — explicit opens bypass recordShown
});

/* ── Load 2: frequency cap allows second show ── */
await page.goto(base + '/');
await page.waitForTimeout(300);
await check('101 opens on 2nd load (frequency_cap max 2, count=2)', async () => {
  const s = await state(101);
  const st = await store('elemacy_popup_101');
  return s.open && st.local.shown_count === 2;
});

/* ── Load 3: frequency cap blocks third show ── */
await page.goto(base + '/');
await page.waitForTimeout(300);
await check('101 blocked on 3rd load (cap reached), count stays 2', async () => {
  const s = await state(101);
  const st = await store('elemacy_popup_101');
  return !s.open && st.local.shown_count === 2;
});
await check('103 still opens on 3rd load (no rules)', async () => (await state(103)).open);

await check('no page errors across all loads', async () => errors.length === 0);

console.log(results.join('\n'));
if (errors.length) console.log('ERRORS:\n' + errors.join('\n'));
await browser.close();
server.close();
