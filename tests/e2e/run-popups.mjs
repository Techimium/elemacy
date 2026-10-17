/**
 * E2E suite for the popups frontend engine (engine.js), run in headless
 * Chromium. Serves popups.html — whose markup mirrors PopupRenderer output and
 * whose config mirrors PopupConfigResource — from a local HTTP server so
 * localStorage/sessionStorage behave as on a real site.
 *
 * Covers: template hydration, page_load + click triggers, the frequency_cap
 * rule across page loads, explicit open/close controls, overlay-click and ESC
 * behavior, the modal focus trap (both directions), body scroll lock, ARIA
 * state, storage bookkeeping, the shown/closed/destroyed lifecycle events,
 * and (task 3.6) the explicit closed/opening/open/closing/destroyed state
 * machine — open-during-close, close-during-open, repeated destroy and
 * stale callback cancellation — plus (task 3.7) the modal stack: opening
 * order for z-index, out-of-order closes sharing one scroll lock, two
 * remaining modals, disconnected-opener focus fallback and nonmodal
 * topbar independence.
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
const zIndex = (id) => page.evaluate((pid) => {
  const root = document.querySelector(`[data-elemacy-popup-id="${pid}"]`);
  return parseInt(getComputedStyle(root).zIndex, 10);
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

/* ── Task 3.6: explicit closed/opening/open/closing/destroyed state
   machine — open-during-close, close-during-open, repeated destroy and
   stale callback cancellation. Popup 104 is animated (0.35s fade), giving
   a real window to interrupt a transition before it settles; 101-103 all
   use animation:none and so resolve every transition synchronously. ── */
await page.click('.open-d');
await page.waitForTimeout(450); // past the 0.35s open animation
await check('104 open settles (fade-in animation complete)', async () => (await state(104)).open);

await page.evaluate(() => { window.__events.length = 0; });
await page.evaluate(() => {
  window.ElemacyPopups.close(104);
  window.ElemacyPopups.open(104); // interrupts the close before it settles
});
await page.waitForTimeout(450); // past where the interrupted close would have settled
await check('open-during-close: popup ends up open, not stuck closed by the interrupted close', async () => {
  const s = await state(104);
  return s.open && !s.hidden;
});
await check('open-during-close: the interrupted close never fired its own closed event', () =>
  page.evaluate(() => window.__events.filter((e) => e[0] === 'closed' && e[1] === 104).length === 0));

// Close-during-open: settle closed first, then open and immediately close
// again before the open animation settles.
await page.evaluate(() => window.ElemacyPopups.close(104));
await page.waitForTimeout(450);
await page.evaluate(() => { window.__events.length = 0; });
await page.evaluate(() => {
  window.ElemacyPopups.open(104);
  window.ElemacyPopups.close(104); // interrupts the open before it settles
});
await page.waitForTimeout(450);
await check('close-during-open: popup ends up closed, not stuck open by the interrupted open', async () => {
  const s = await state(104);
  return !s.open && s.hidden;
});
await check('close-during-open: the interrupted open never fired its own shown event', () =>
  page.evaluate(() => window.__events.filter((e) => e[0] === 'shown' && e[1] === 104).length === 0));
await check('close-during-open: exactly one closed event fired (from the close that won, not a stale duplicate)', () =>
  page.evaluate(() => window.__events.filter((e) => e[0] === 'closed' && e[1] === 104).length === 1));

// Stale callback cancellation, explicit: rapid open -> close -> open in
// immediate succession must settle on the LAST requested state (open) with
// exactly one shown event — not two, which a stale timer/listener from the
// discarded middle transition would otherwise produce.
await page.evaluate(() => { window.__events.length = 0; });
await page.evaluate(() => {
  window.ElemacyPopups.open(104);
  window.ElemacyPopups.close(104);
  window.ElemacyPopups.open(104);
});
await page.waitForTimeout(450);
await check('stale callback cancellation: rapid open/close/open settles open with exactly one shown event', async () => {
  const s = await state(104);
  const shown = await page.evaluate(() => window.__events.filter((e) => e[0] === 'shown' && e[1] === 104).length);
  return s.open && shown === 1;
});

// Repeated destroy: idempotent (no throw), fires the destroyed event
// exactly once, and a destroyed popup can never be reopened.
await page.evaluate(() => { window.__events.length = 0; });
await page.evaluate(() => {
  window.ElemacyPopups.destroy(104);
  window.ElemacyPopups.destroy(104);
});
await check('repeated destroy is idempotent (no throw) and fires destroyed exactly once', () =>
  page.evaluate(() => window.__events.filter((e) => e[0] === 'destroyed' && e[1] === 104).length === 1));
await check('destroy hides the popup', async () => {
  const s = await state(104);
  return !s.open && s.hidden;
});
await check('a destroyed popup cannot be reopened', async () => {
  await page.evaluate(() => window.ElemacyPopups.open(104));
  await page.waitForTimeout(450);
  return !(await state(104)).open;
});

/* ── Task 3.7: modal stack — opening order (z-index), shared scroll lock
   across out-of-order closes, two remaining modals, disconnected openers
   and nonmodal topbar independence. Popups 105-107 share one authored
   z_index and all request the scroll lock; animation:none keeps their
   transitions synchronous so the stack itself, not timing, is tested. ── */

await page.evaluate(() => document.querySelector('.open-e').click());
await page.waitForTimeout(50);
await page.evaluate(() => document.querySelector('.open-f').click());
await page.waitForTimeout(50);
await check('105 and 106 are both open simultaneously', async () =>
  (await state(105)).open && (await state(106)).open);
await check('opening order: 106 (opened after 105) gets a higher z-index despite an equal authored z_index', async () =>
  (await zIndex(106)) > (await zIndex(105)));
await check('shared scroll lock: locked with two modals open', async () =>
  (await state(105)).bodyOverflow === 'hidden');

// Out-of-order close: close 105 (opened FIRST, not the topmost) while 106
// is still open.
await page.evaluate(() => document.querySelector('.elemacy-popup-105 .elemacy-popup__close').click());
await page.waitForTimeout(100);
await check('out-of-order close: 105 closes', async () => !(await state(105)).open);
await check('...but the shared scroll lock stays held (106 still open)', async () =>
  (await state(106)).bodyOverflow === 'hidden');

await page.evaluate(() => document.querySelector('.elemacy-popup-106 .elemacy-popup__close').click());
await page.waitForTimeout(100);
await check('the shared scroll lock finally releases once the LAST locking modal (106) closes', async () =>
  (await state(106)).bodyOverflow === '');

// Two remaining modals: open three, close the middle one out of order —
// the other two stay open, the lock stays held, and Escape still finds the
// real topmost remaining modal rather than whichever opened first.
await page.evaluate(() => document.querySelector('.open-e').click());
await page.waitForTimeout(50);
await page.evaluate(() => document.querySelector('.open-f').click());
await page.waitForTimeout(50);
await page.evaluate(() => document.querySelector('.open-g').click());
await page.waitForTimeout(50);
await page.evaluate(() => document.querySelector('.elemacy-popup-106 .elemacy-popup__close').click()); // middle
await page.waitForTimeout(100);
await check('two remaining modals: 105 and 107 stay open after the middle one (106) closes', async () =>
  (await state(105)).open && !(await state(106)).open && (await state(107)).open);
await check('two remaining modals: scroll stays locked (two lockers remain)', async () =>
  (await state(107)).bodyOverflow === 'hidden');

await page.keyboard.press('Escape');
await page.waitForTimeout(100);
await check('Escape closes the actual topmost remaining modal (107, opened last), not 105', async () => {
  const s105 = await state(105);
  const s107 = await state(107);
  return s105.open && !s107.open;
});
await page.keyboard.press('Escape');
await page.waitForTimeout(100);
await check('a second Escape then closes 105, and the lock fully releases', async () => {
  const s105 = await state(105);
  return !s105.open && s105.bodyOverflow === '';
});

// Disconnected opener: the element that opened a modal is removed from the
// DOM while that modal is still open. Closing it afterwards must not
// throw, and restores focus into the modal still open (106) rather than
// silently dropping it.
await page.evaluate(() => {
  const opener = document.createElement('button');
  opener.type = 'button';
  opener.id = 'disconnected-opener';
  document.body.appendChild(opener);
  opener.focus();
  window.ElemacyPopups.open(105);
});
await page.waitForTimeout(100);
await page.evaluate(() => document.querySelector('.open-f').click());
await page.waitForTimeout(100);
await check('106 opened on top of 105 (both open)', async () =>
  (await state(105)).open && (await state(106)).open);

await page.evaluate(() => document.getElementById('disconnected-opener').remove());
await check('no page errors from removing the opener while its modal is still open', async () => errors.length === 0);

await page.evaluate(() => document.querySelector('.elemacy-popup-105 .elemacy-popup__close').click());
await page.waitForTimeout(100);
await check('closing a modal whose opener was removed does not throw', async () => errors.length === 0);
await check('disconnected opener: focus falls back to the still-open modal (106), not the removed opener', () =>
  page.evaluate(() => {
    const box106 = document.querySelector('.elemacy-popup-106 .elemacy-popup__box');
    return box106 ? box106.contains(document.activeElement) : false;
  }));

/* ── Nonmodal topbars never join the stack: opening one alongside an open
   modal must not touch the shared scroll lock or the modal's own Escape
   targeting. ── */
await page.evaluate(() => window.ElemacyPopups.open(103));
await page.waitForTimeout(50);
await check('topbar 103 opens alongside the open modal without becoming modal chrome itself', async () => {
  const s103 = await state(103);
  return s103.open && !s103.hasOverlay;
});
await check('the still-open modal (106) keeps the scroll lock regardless of the topbar', async () =>
  (await state(106)).bodyOverflow === 'hidden');

await page.keyboard.press('Escape');
await page.waitForTimeout(100);
await check('Escape closes the topmost modal (106); the non-modal, on_esc:false topbar is unaffected', async () => {
  const s103 = await state(103);
  const s106 = await state(106);
  return s103.open && !s106.open;
});
await check('scroll lock fully releases once the last modal from this block closes', async () =>
  (await state(106)).bodyOverflow === '');

await page.evaluate(() => window.ElemacyPopups.close(103));

/* ── Close button placement: a button positioned outside the popup must still
   render and be clickable there (the box must not clip it), and long content
   must scroll without moving it. 108/109 push the button outside via CSS the
   way a negative "Distance From Edge" does; 110 keeps the default offset. ── */
const closeGeometry = (id) => page.evaluate((pid) => {
  const box = document.querySelector(`[data-elemacy-popup-id="${pid}"] .elemacy-popup__box`);
  const btn = box.querySelector(':scope > .elemacy-popup__close');
  const scroller = box.querySelector(':scope > .elemacy-popup__scroll');
  const b = box.getBoundingClientRect();
  const c = btn.getBoundingClientRect();
  const cx = c.left + c.width / 2;
  const cy = c.top + c.height / 2;
  return {
    box: { l: b.left, t: b.top, r: b.right, b: b.bottom, h: b.height },
    btn: { l: c.left, t: c.top, r: c.right, b: c.bottom },
    centerOutsideBox: cx > b.right || cx < b.left || cy < b.top || cy > b.bottom,
    hitIsButton: document.elementFromPoint(cx, cy) === btn,
    boxOverflow: getComputedStyle(box).overflow,
    hasScroller: !!scroller,
    scrollerOverflowY: scroller ? getComputedStyle(scroller).overflowY : null,
    scrollerScrollable: scroller ? scroller.scrollHeight > scroller.clientHeight : false,
    scrollTop: scroller ? scroller.scrollTop : null,
  };
}, id);

await page.evaluate(() => window.ElemacyPopups.open(108));
await page.waitForTimeout(50);
const g108 = await closeGeometry(108);
await check('floating: a close button placed outside the popup is not clipped by the box', async () =>
  g108.centerOutsideBox && g108.hitIsButton);
await page.evaluate(() => window.ElemacyPopups.close(108));

await page.evaluate(() => window.ElemacyPopups.open(109));
await page.waitForTimeout(50);
const g109 = await closeGeometry(109);
await check('modal: a close button placed outside the popup is not clipped by the box', async () =>
  g109.centerOutsideBox && g109.hitIsButton);
await check('the popup box is a plain frame (no overflow) and its content lives in its own scroll element', async () =>
  g109.boxOverflow === 'visible' && g109.hasScroller);
await page.evaluate(() => window.ElemacyPopups.close(109));

await page.evaluate(() => window.ElemacyPopups.open(110));
await page.waitForTimeout(50);
const g110 = await closeGeometry(110);
await check('default offset: the close button sits inside the popup top-right corner, 10px in', async () =>
  !g110.centerOutsideBox
  && Math.abs((g110.box.r - g110.btn.r) - 10) <= 1
  && Math.abs((g110.btn.t - g110.box.t) - 10) <= 1);
await check('tall content: the box stays within the viewport limit and its content scrolls in the scroll element', async () =>
  g110.box.h <= 800 - 40 + 1 && g110.scrollerOverflowY === 'auto' && g110.scrollerScrollable);
await page.evaluate(() => {
  const scroller = document.querySelector('.elemacy-popup-110 .elemacy-popup__scroll');
  if (scroller) scroller.scrollTop = 300;
});
const g110scrolled = await closeGeometry(110);
await check('scrolling the content (by 300px) leaves the close button where it was', async () =>
  g110scrolled.scrollTop === 300
  && Math.abs(g110scrolled.btn.t - g110.btn.t) < 0.5 && Math.abs(g110scrolled.btn.l - g110.btn.l) < 0.5);
await page.evaluate(() => window.ElemacyPopups.close(110));

/* ── The default run hides scrollbars (headless default), which is exactly the
   condition where a button-over-scrollbar overlap cannot show. Re-check the
   tall popup in a browser that draws classic scrollbars. ── */
const scrollbarBrowser = await chromium.launch({ ...launchOptions, ignoreDefaultArgs: ['--hide-scrollbars'] });
const scrollbarPage = await (await scrollbarBrowser.newContext({ viewport: { width: 1280, height: 800 } })).newPage();
await scrollbarPage.goto(base + '/');
await scrollbarPage.evaluate(() => { window.ElemacyPopups.close(101); window.ElemacyPopups.open(110); });
await scrollbarPage.waitForTimeout(150);
const sb = await scrollbarPage.evaluate(() => {
  const box = document.querySelector('.elemacy-popup-110 .elemacy-popup__box');
  const scroller = box.querySelector(':scope > .elemacy-popup__scroll');
  const btn = box.querySelector(':scope > .elemacy-popup__close');
  return {
    scrollbarWidth: scroller.offsetWidth - scroller.clientWidth,
    overlap: btn.getBoundingClientRect().right - (scroller.getBoundingClientRect().left + scroller.clientWidth),
  };
});
await scrollbarBrowser.close();
await check('classic scrollbars are actually drawn in this check (otherwise it proves nothing)', async () => sb.scrollbarWidth > 0);
await check('a visible scrollbar on a tall popup does not sit under the default-offset close button', async () => sb.overlap <= 0.5);

await check('no page errors across all loads', async () => errors.length === 0);

console.log(results.join('\n'));
if (errors.length) console.log('ERRORS:\n' + errors.join('\n'));
await browser.close();
server.close();
