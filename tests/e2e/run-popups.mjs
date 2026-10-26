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
  // Stand-ins for a video embed and for a link to another page.
  if (file.startsWith('/stub/') || file === '/other') {
    res.writeHead(200, { 'Content-Type': 'text/html' });
    res.end('<!DOCTYPE html><title>stub</title><p>stub</p>');
    return;
  }
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
  const s = await store('elemacy_1_popup_101');
  return s.local && s.local.shown_count === 1 && s.local.last_shown_at > 0 && s.session === '1';
});
await check('103 topbar opens too, non-modal (no overlay)', async () => {
  const s = await state(103);
  return s.open && !s.hasOverlay;
});
await check('widgets initialize after their popup is visible (non-zero size at init)', () =>
  page.evaluate(() => window.__hydrated.length >= 2 && window.__hydrated.every((h) => h.width > 0)));
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
  (await store('elemacy_1_popup_102')).local?.shown_count === 1);

// on_overlay_click=false for 102: clicking its overlay must NOT close it.
await page.evaluate(() => document.querySelector('.elemacy-popup-102 [data-elemacy-overlay]').click());
await page.waitForTimeout(100);
await check('102 overlay click ignored (on_overlay_click=false)', async () => (await state(102)).open);

await page.evaluate(() => document.querySelector('.elemacy-popup-102 .elemacy-popup__close').click());
await page.waitForTimeout(100);
await check('102 closes via injected close button', async () => !(await state(102)).open);

await page.click('.explicit-open-b');
await page.waitForTimeout(100);
await check('the retired data-elemacy-popup-open attribute opens nothing', async () => !(await state(102)).open);
await page.evaluate(() => document.querySelector('.link-open-b').click());
await page.waitForTimeout(100);
await check('102 opened by its #elemacy-popup-102 link does not count towards its frequency', async () => {
  const s = await state(102);
  const st = await store('elemacy_1_popup_102');
  const ok = s.open && st.local.shown_count === 1; // still 1 — explicit opens bypass recordShown
  await page.evaluate(() => window.elemacyPopups.close(102));
  await page.waitForTimeout(100);
  return ok;
});

/* ── Load 2: frequency cap allows second show ── */
await page.goto(base + '/');
await page.waitForTimeout(300);
await check('101 opens on 2nd load (frequency_cap max 2, count=2)', async () => {
  const s = await state(101);
  const st = await store('elemacy_1_popup_101');
  return s.open && st.local.shown_count === 2;
});

/* ── Load 3: frequency cap blocks third show ── */
await page.goto(base + '/');
await page.waitForTimeout(300);
await check('101 blocked on 3rd load (cap reached), count stays 2', async () => {
  const s = await state(101);
  const st = await store('elemacy_1_popup_101');
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
  window.elemacyPopups.close(104);
  window.elemacyPopups.open(104); // interrupts the close before it settles
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
await page.evaluate(() => window.elemacyPopups.close(104));
await page.waitForTimeout(450);
await page.evaluate(() => { window.__events.length = 0; });
await page.evaluate(() => {
  window.elemacyPopups.open(104);
  window.elemacyPopups.close(104); // interrupts the open before it settles
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
  window.elemacyPopups.open(104);
  window.elemacyPopups.close(104);
  window.elemacyPopups.open(104);
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
  window.elemacyPopups.destroy(104);
  window.elemacyPopups.destroy(104);
});
await check('repeated destroy is idempotent (no throw) and fires destroyed exactly once', () =>
  page.evaluate(() => window.__events.filter((e) => e[0] === 'destroyed' && e[1] === 104).length === 1));
await check('destroy hides the popup', async () => {
  const s = await state(104);
  return !s.open && s.hidden;
});
await check('a destroyed popup cannot be reopened', async () => {
  await page.evaluate(() => window.elemacyPopups.open(104));
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
  window.elemacyPopups.open(105);
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
await page.evaluate(() => window.elemacyPopups.open(103));
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

await page.evaluate(() => window.elemacyPopups.close(103));

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

await page.evaluate(() => window.elemacyPopups.open(108));
await page.waitForTimeout(50);
const g108 = await closeGeometry(108);
await check('floating: a close button placed outside the popup is not clipped by the box', async () =>
  g108.centerOutsideBox && g108.hitIsButton);
await page.evaluate(() => window.elemacyPopups.close(108));

await page.evaluate(() => window.elemacyPopups.open(109));
await page.waitForTimeout(50);
const g109 = await closeGeometry(109);
await check('modal: a close button placed outside the popup is not clipped by the box', async () =>
  g109.centerOutsideBox && g109.hitIsButton);
await check('the popup box is a plain frame (no overflow) and its content lives in its own scroll element', async () =>
  g109.boxOverflow === 'visible' && g109.hasScroller);
await page.evaluate(() => window.elemacyPopups.close(109));

await page.evaluate(() => window.elemacyPopups.open(110));
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
await page.evaluate(() => window.elemacyPopups.close(110));

/* ── Page-markup contract: #elemacy-popup-{id} opens, #elemacy-popup-close and
   .elemacy-popup-close close; the retired and unprefixed legacy markup do nothing ── */
await page.goto(base + '/');
await page.waitForTimeout(200);
const urlBefore = page.url();
await page.evaluate(() => document.querySelector('.link-open-b').click());
await page.waitForTimeout(100);
await check('<a href="#elemacy-popup-102"> opens popup 102 and leaves the URL unchanged', async () =>
  (await state(102)).open && page.url() === urlBefore);
await page.evaluate(() => document.getElementById('p102-closelink')?.click());
await page.waitForTimeout(100);
await check('<a href="#elemacy-popup-close"> inside a popup closes it', async () => !(await state(102)).open && page.url() === urlBefore);

await page.evaluate(() => window.elemacyPopups.open(104));
await page.waitForTimeout(450);
await page.evaluate(() => document.getElementById('p104-retired-class')?.click());
await page.waitForTimeout(450);
await check('the retired elemacy-close-popup class does not close a popup', async () => (await state(104)).open);
await page.evaluate(() => document.getElementById('p104-retired-attr')?.click());
await page.waitForTimeout(450);
await check('the retired data-elemacy-popup-close attribute does not close a popup', async () => (await state(104)).open);
await page.evaluate(() => document.getElementById('p104-customclose')?.click());
await page.waitForTimeout(450);
await check('a custom button with the elemacy-popup-close class closes its popup', async () => !(await state(104)).open);
await check('the API is window.elemacyPopups; the retired ElemacyPopups global is not defined', () =>
  page.evaluate(() => typeof window.elemacyPopups.open === 'function' && typeof window.ElemacyPopups === 'undefined'));

await page.evaluate(() => window.scrollTo(0, 500));
await page.evaluate(() => document.querySelector('.link-open-missing').click());
await page.waitForTimeout(100);
await check('a link to a popup that is not on the page opens nothing, keeps the URL and does not scroll', () =>
  page.evaluate((url) => location.href === url && Math.abs(window.scrollY - 500) < 1 && !document.querySelector('.is-open[data-elemacy-popup-id="102"]'), urlBefore));
await page.evaluate(() => window.scrollTo(0, 0));

await page.evaluate(() => document.getElementById('legacy-open').click());
await page.waitForTimeout(100);
await check('the unprefixed .elemacy-open-popup + data-popup-id markup no longer opens anything', async () => !(await state(102)).open);

/* ── Click triggers on links that go nowhere must not navigate or jump ── */
// The last one is how Elementor prints a button: the selector's class is on the wrapper, the link inside it.
const deadLinkCases = [['.dead-hash', '#'], ['.dead-empty', ''], ['.dead-js', 'javascript:void(0)'], ['.inner-dead-label', '# inside a matching wrapper']];
for (const [selector, label] of deadLinkCases) {
  await page.goto(base + '/');
  await page.waitForTimeout(150);
  const start = page.url();
  await page.evaluate(() => window.scrollTo(0, 600));
  await page.evaluate((sel) => document.querySelector(sel).click(), selector);
  await page.waitForTimeout(100);
  await check(`click trigger on <a href="${label}">: opens the popup, no URL change, no jump to the top`, async () =>
    (await state(121)).open && await page.evaluate((url) => location.href === url && Math.abs(window.scrollY - 600) < 1, start));
}

await page.goto(base + '/');
await page.waitForTimeout(150);
await page.evaluate(() => window.scrollTo(0, 600));
await page.evaluate(() => document.querySelector('.capped-hash').click());
await page.waitForTimeout(100);
await page.evaluate(() => window.elemacyPopups.close(122));
await page.evaluate(() => document.querySelector('.capped-hash').click()); // blocked by the one-show cap
await page.waitForTimeout(100);
await check('a frequency cap that blocks the open still does not navigate or jump', async () =>
  !(await state(122)).open && await page.evaluate(() => !location.href.endsWith('#') && Math.abs(window.scrollY - 600) < 1));

await page.evaluate(() => window.scrollTo(0, 0));
await page.evaluate(() => document.querySelector('.dead-other').click());
await page.waitForURL(/\/other$/, { timeout: 3000 }).catch(() => {});
await check('a click trigger on a link to another page leaves its navigation alone', () => page.url().endsWith('/other'));

/* ── Animations: every In/Out choice combines, in both directions ── */
const animNames = {
  in: { none: 'none', fade: 'elemacy-popup-fade-in', 'slide-down': 'elemacy-popup-slide-in-down', 'slide-up': 'elemacy-popup-slide-in-up' },
  out: { none: 'none', fade: 'elemacy-popup-fade-out', 'slide-down': 'elemacy-popup-slide-out-down', 'slide-up': 'elemacy-popup-slide-out-up' },
};
const boxAnimation = () => page.evaluate(() => getComputedStyle(document.querySelector('.elemacy-popup-120 .elemacy-popup__box')).animationName);
await page.goto(base + '/');
await page.waitForTimeout(150);
for (const animIn of Object.keys(animNames.in)) {
  for (const animOut of Object.keys(animNames.out)) {
    await page.evaluate(([i, o]) => {
      window.elemacyPopupsConfig.popups.find((c) => c.id === 120).display.animation = { in: i, out: o };
    }, [animIn, animOut]);

    // Reading the computed style in the same task as open()/close() sees the
    // class the engine just added, with no timing involved.
    const opening = await page.evaluate(() => {
      window.elemacyPopups.open(120);
      return getComputedStyle(document.querySelector('.elemacy-popup-120 .elemacy-popup__box')).animationName;
    });
    await page.waitForTimeout(animIn === 'none' ? 50 : 450);
    const closing = await page.evaluate(() => {
      window.__closedAt = performance.now();
      window.elemacyPopups.close(120);
      return getComputedStyle(document.querySelector('.elemacy-popup-120 .elemacy-popup__box')).animationName;
    });
    const hiddenIn = await page.waitForFunction(
      () => document.querySelector('[data-elemacy-popup-id="120"]').hasAttribute('hidden'),
      null,
      { timeout: 450, polling: 16 },
    ).then(() => true, () => false);
    await check(`animation in=${animIn} out=${animOut}: opens as ${animNames.in[animIn]}, closes as ${animNames.out[animOut]} and is hidden within 450ms`, async () =>
      opening === animNames.in[animIn] && closing === animNames.out[animOut] && hiddenIn);
  }
}

// A widget's own short animation inside the box must not settle the box's transition.
await page.evaluate(() => {
  window.elemacyPopupsConfig.popups.find((c) => c.id === 120).display.animation = { in: 'fade', out: 'fade' };
  window.elemacyPopups.open(120);
});
await page.waitForTimeout(200); // the 50ms child animation is long over; the box's 350ms one is not
await check("a child element's animationend does not settle the popup's own entrance early", () =>
  page.evaluate(() => document.querySelector('[data-elemacy-popup-id="120"]').classList.contains('is-animating')));
await page.waitForTimeout(400);
await page.evaluate(() => window.elemacyPopups.close(120));
await page.waitForTimeout(450);

const reducedCtx = await browser.newContext({ viewport: { width: 1280, height: 800 }, reducedMotion: 'reduce' });
const reducedPage = await reducedCtx.newPage();
await reducedPage.goto(base + '/');
const reduced = await reducedPage.evaluate(() => {
  window.elemacyPopupsConfig.popups.find((c) => c.id === 120).display.animation = { in: 'fade', out: 'slide-up' };
  window.elemacyPopups.open(120);
  const root = document.querySelector('[data-elemacy-popup-id="120"]');
  const opened = { animating: root.classList.contains('is-animating'), name: getComputedStyle(root.querySelector('.elemacy-popup__box')).animationName, open: root.classList.contains('is-open') };
  window.elemacyPopups.close(120);
  return { opened, hiddenAtOnce: root.hasAttribute('hidden') };
});
await reducedCtx.close();
await check('reduced motion: popups appear and disappear with no animation', async () =>
  reduced.opened.open && !reduced.opened.animating && reduced.opened.name === 'none' && reduced.hiddenAtOnce);

/* ── Focus: stays in the newest modal when an older one closes; a focus that escapes comes back ── */
await page.goto(base + '/');
await page.waitForTimeout(150);
await page.focus('.open-e');
await page.keyboard.press('Enter'); // opens 105 from a real button
await page.waitForTimeout(80);
await page.evaluate(() => document.querySelector('.open-f').click()); // opens 106 on top of it
await page.waitForTimeout(80);
const inModal = (id) => page.evaluate((pid) => {
  const box = document.querySelector(`.elemacy-popup-${pid} .elemacy-popup__box`);
  return !!box && box.contains(document.activeElement);
}, id);
await check('105 opened from a page button, 106 opened on top: focus is in 106', () => inModal(106));
await page.evaluate(() => window.elemacyPopups.close(105));
await page.waitForTimeout(80);
await check('the older modal (105) closing leaves focus in 106, not on the page button that opened 105', () => inModal(106));
await page.keyboard.press('Tab');
await check('Tab keeps cycling inside 106 after the older modal closed', () => inModal(106));
await page.keyboard.press('Shift+Tab');
await check('Shift+Tab keeps cycling inside 106 as well', () => inModal(106));
await page.evaluate(() => document.querySelector('.open-g').focus());
await page.keyboard.press('Tab');
await check('focus moved to a page link while 106 is open comes back into 106 on Tab', () => inModal(106));
await page.evaluate(() => window.elemacyPopups.close(106));
await page.waitForTimeout(80);
// A real keyboard open from a page button, then close: focus goes back to that button.
await page.focus('.open-f');
await page.keyboard.press('Enter');
await page.waitForTimeout(80);
await page.evaluate(() => window.elemacyPopups.close(106));
await page.waitForTimeout(80);
await check('closing the last modal (106) returns focus to the connected opener that opened it', () =>
  page.evaluate(() => document.activeElement && document.activeElement.classList.contains('open-f')));

/* ── Media stops when its popup closes ── */
await page.goto(base + '/');
await page.waitForTimeout(150);
await page.evaluate(() => window.elemacyPopups.open(123));
await page.waitForTimeout(80);
await page.evaluate(async () => {
  const video = document.getElementById('p123-video');
  const canvas = document.createElement('canvas');
  canvas.width = 32; canvas.height = 32;
  const ctx = canvas.getContext('2d');
  window.__ticker = setInterval(() => { ctx.fillStyle = '#' + Math.floor(Math.random() * 0xffffff).toString(16).padStart(6, '0'); ctx.fillRect(0, 0, 32, 32); }, 40);
  video.srcObject = canvas.captureStream(20);
  await video.play();
  window.__srcWrites = [];
  new MutationObserver((records) => records.forEach((r) => window.__srcWrites.push(r.target.id)))
    .observe(document.querySelector('.elemacy-popup-123'), { subtree: true, attributes: true, attributeFilter: ['src'] });
});
await check('the popup video is really playing before it is closed (otherwise the check proves nothing)', () =>
  page.evaluate(() => !document.getElementById('p123-video').paused));
await page.evaluate(() => window.elemacyPopups.close(123));
await page.waitForTimeout(100);
await check('closing the popup pauses its video', () => page.evaluate(() => document.getElementById('p123-video').paused));
await check('closing the popup reloads its YouTube embed, and only that iframe', () =>
  page.evaluate(() => window.__srcWrites.includes('p123-embed') && !window.__srcWrites.includes('p123-other')));
await page.evaluate(() => clearInterval(window.__ticker));

/* ── Automatic opening, fault isolation, ids, closed events ── */
const consoleMessages = [];
const onConsole = (m) => consoleMessages.push({ type: m.type(), text: m.text() });
page.on('console', onConsole);
await page.goto(base + '/popups-auto.html');
await page.waitForTimeout(500);
const open201 = () => page.evaluate(() => document.querySelector('[data-elemacy-popup-id="201"]').classList.contains('is-open'));
await check('a popup beside one whose trigger handler throws still opens on page load', open201);
await check('a throwing trigger handler is reported, not thrown', async () =>
  consoleMessages.some((m) => m.type === 'error' && m.text.includes('trigger "boom" of popup 202 threw')));
await check('a popup whose rule handler throws is blocked, not opened', () =>
  page.evaluate(() => !document.querySelector('[data-elemacy-popup-id="203"]').classList.contains('is-open')));
await check('registered trigger cleanups ran exactly once when the popup opened automatically', () =>
  page.evaluate(() => window.__cleanups === 1));

await page.keyboard.press('Escape');
await page.waitForTimeout(100);
await check('Escape does nothing to a non-modal popup', open201);
await page.evaluate(() => document.querySelector('.elemacy-popup-201 .elemacy-popup__close')?.click());
await page.waitForTimeout(100);
await check('its close button still closes it although another popup\'s trigger threw', async () => !(await open201()));
await page.evaluate(() => window.__autoApis[201].open());
await page.waitForTimeout(100);
await check('an automatic trigger firing after the popup was dismissed does not reopen it', async () => !(await open201()));
await page.evaluate(() => document.querySelector('.reopen-201').click());
await page.waitForTimeout(100);
await check('a click trigger still reopens a popup that opened automatically', open201);
await page.evaluate(() => document.querySelector('.elemacy-popup-201 .elemacy-popup__close')?.click());
await page.waitForTimeout(100);
await check('the close button still works', async () => !(await open201()));
await page.evaluate(() => window.elemacyPopups.destroy(201));
await check('cleanups are not run a second time on destroy', () => page.evaluate(() => window.__cleanups === 1));

await page.evaluate(() => { window.__events.length = 0; });
await page.evaluate(() => document.querySelector('.open-204').click());
await page.waitForTimeout(80);
await page.evaluate(() => window.elemacyPopups.close(204));
await page.waitForTimeout(80);
await check('closed reports wasShown=true for a popup that was displayed', () =>
  page.evaluate(() => window.__events.some((e) => e[0] === 'closed' && e[1] === 204 && e[2] === true)));
await page.evaluate(() => { window.elemacyPopups.open(205); window.elemacyPopups.destroy(205); });
await check('destroy dispatches destroyed and never closed', () =>
  page.evaluate(() => window.__events.some((e) => e[0] === 'destroyed' && e[1] === 205) && !window.__events.some((e) => e[0] === 'closed' && e[1] === 205)));
await page.evaluate(() => { window.elemacyPopups.open(206); window.elemacyPopups.close(206); });
await page.waitForTimeout(450); // the fade-out has to finish before closed is dispatched
await check('closed reports wasShown=false for a popup closed while it was still animating in', () =>
  page.evaluate(() => window.__events.some((e) => e[0] === 'closed' && e[1] === 206 && e[2] === false)));

await page.evaluate(() => { window.__events.length = 0; window.elemacyPopups.open('207'); });
await page.waitForTimeout(80);
await check('a popup opened with a string id locks the page scroll', () => page.evaluate(() => document.body.style.overflow === 'hidden'));
await page.evaluate(() => document.querySelector('.elemacy-popup-207 .elemacy-popup__close')?.click());
await page.waitForTimeout(80);
await check('closing it with its close button releases the scroll lock', () => page.evaluate(() => document.body.style.overflow === ''));
await check('the closed event reports the numeric popup id', () =>
  page.evaluate(() => window.__events.some((e) => e[0] === 'closed' && e[1] === 207 && e[3] === 'number')));
await page.evaluate(() => window.elemacyPopups.open(207));
await page.waitForTimeout(80);
await page.keyboard.press('Escape');
await page.waitForTimeout(80);
await check('Escape closes the popup once reopened with a numeric id, leaving nothing locked', () =>
  page.evaluate(() => !document.querySelector('[data-elemacy-popup-id="207"]').classList.contains('is-open') && document.body.style.overflow === ''));

await check('debug mode names the handler a waiting popup is missing', async () =>
  consoleMessages.some((m) => m.type === 'warning' && m.text.includes('popup 208') && m.text.includes('trigger "never_registered"')));
await check('debug mode does not warn about popups that are armed', async () =>
  !consoleMessages.some((m) => m.type === 'warning' && m.text.includes('popup 201')));
page.off('console', onConsole);

/* ── Extension load order ── */
const scrollOpens = async () => {
  await page.evaluate(() => window.scrollTo(0, 400));
  await page.waitForTimeout(150);
  return page.evaluate(() => document.querySelector('[data-elemacy-popup-id="301"]').classList.contains('is-open'));
};
await page.goto(base + '/popups-order.html?variant=late');
await page.waitForTimeout(800); // the scroll handler registers 500ms after the engine started
await check('a trigger handler registered 500ms after init still opens its popup on scroll', scrollOpens);

await page.goto(base + '/popups-order.html?variant=late');
await page.waitForTimeout(100);
await page.evaluate(() => window.scrollTo(0, 400));
await page.waitForTimeout(100);
await check('before its handler arrives the popup stays closed (fail closed)', () =>
  page.evaluate(() => !document.querySelector('[data-elemacy-popup-id="301"]').classList.contains('is-open')));

await page.goto(base + '/popups-order.html?variant=queue');
await page.waitForTimeout(200);
await check('an extension pushed before the engine loads is called exactly once and its trigger works', async () =>
  (await page.evaluate(() => window.__extCalls)) === 1 && await scrollOpens());

await page.goto(base + '/popups-order.html?variant=queue-late');
await page.waitForTimeout(200);
await check('an extension pushed after the engine loads is called exactly once and its trigger works', async () =>
  (await page.evaluate(() => window.__extCalls)) === 1 && await scrollOpens());

await page.goto(base + '/popups-order.html?variant=none');
await page.waitForTimeout(200);
await page.evaluate(() => document.querySelector('.open-302').click());
await page.waitForTimeout(100);
await check('a popup whose client rule has no handler is not opened by its trigger', () =>
  page.evaluate(() => !document.querySelector('[data-elemacy-popup-id="302"]').classList.contains('is-open')));
await page.evaluate(() => window.elemacyPopups.open(302));
await page.waitForTimeout(100);
await check('...but an explicit open still works', () =>
  page.evaluate(() => document.querySelector('[data-elemacy-popup-id="302"]').classList.contains('is-open')));

/* ── Top bars the server printed ── */
await page.goto(base + '/popups-topbar.html');
await page.waitForTimeout(1000);
const topbar = await page.evaluate(() => {
  const sig = (el) => ({ tag: el.tagName, cls: [...el.classList].map((c) => c.replace(/\d+$/, 'N')).sort(), aria: el.getAttribute('aria-label'), kids: [...el.children].map(sig) });
  const root = document.querySelector('[data-elemacy-popup-id="401"]');
  return {
    firstPaintTop: window.__firstPaintTop,
    contentTop: document.getElementById('content').getBoundingClientRect().top,
    open: root.classList.contains('is-open') && !root.hasAttribute('hidden'),
    shown401: window.__events.filter((e) => e[0] === 'opened' && e[1] === 401).length,
    relocatedFirst: document.body.firstElementChild.getAttribute('data-elemacy-popup-id') === '403',
    boxSig: JSON.stringify(sig(root.querySelector('.elemacy-popup__box'))),
  };
});
await check('a prerendered top bar occupies space at first paint and the page does not shift afterwards', async () =>
  topbar.firstPaintTop > 0 && Math.abs(topbar.firstPaintTop - topbar.contentTop) < 0.5);
await check('the engine adopts a prerendered top bar: it stays open and fires shown exactly once', async () =>
  topbar.open && topbar.shown401 === 1);
await check('a top bar printed late is moved to the top of <body> before it is ever opened', async () => topbar.relocatedFirst);
await page.evaluate(() => window.elemacyPopups.open(402));
await page.waitForTimeout(450);
const hydratedSig = await page.evaluate(() => {
  const sig = (el) => ({ tag: el.tagName, cls: [...el.classList].map((c) => c.replace(/\d+$/, 'N')).sort(), aria: el.getAttribute('aria-label'), kids: [...el.children].map(sig) });
  return JSON.stringify(sig(document.querySelector('[data-elemacy-popup-id="402"] .elemacy-popup__box')));
});
await check('a prerendered top bar has the same box structure as one the engine hydrated', async () => topbar.boxSig === hydratedSig);
await page.evaluate(() => document.querySelector('.elemacy-popup-401 .elemacy-popup__close')?.click());
await page.waitForTimeout(500);
await check('a prerendered top bar closes with its close button', () =>
  page.evaluate(() => document.querySelector('[data-elemacy-popup-id="401"]').hasAttribute('hidden') && window.__events.some((e) => e[0] === 'closed' && e[1] === 401)));

/* ── The default run hides scrollbars (headless default), which is exactly the
   condition where a button-over-scrollbar overlap cannot show. Re-check the
   tall popup in a browser that draws classic scrollbars. ── */
const scrollbarBrowser = await chromium.launch({ ...launchOptions, ignoreDefaultArgs: ['--hide-scrollbars'] });
const scrollbarPage = await (await scrollbarBrowser.newContext({ viewport: { width: 1280, height: 800 } })).newPage();
await scrollbarPage.goto(base + '/');
await scrollbarPage.evaluate(() => { window.elemacyPopups.close(101); window.elemacyPopups.open(110); });
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
const lockPage = await (await scrollbarBrowser.newContext({ viewport: { width: 1280, height: 800 } })).newPage();
await lockPage.addInitScript(() => localStorage.setItem('elemacy_1_popup_101', JSON.stringify({ shown_count: 5 }))); // keep 101 from opening on load
await lockPage.goto(base + '/');
await lockPage.waitForTimeout(150);
const lockLeft = () => lockPage.evaluate(() => document.getElementById('centered').getBoundingClientRect().left);
const before = {
  left: await lockLeft(),
  scrollbar: await lockPage.evaluate(() => window.innerWidth - document.documentElement.clientWidth),
};
await lockPage.evaluate(() => window.elemacyPopups.open(105));
await lockPage.waitForTimeout(100);
const locked = await lockPage.evaluate(() => ({
  left: document.getElementById('centered').getBoundingClientRect().left,
  htmlOverflow: document.documentElement.style.overflow,
  bodyOverflow: document.body.style.overflow,
}));
await lockPage.evaluate(() => window.elemacyPopups.close(105));
await lockPage.waitForTimeout(100);
const restored = await lockPage.evaluate(() => ({
  left: document.getElementById('centered').getBoundingClientRect().left,
  bodyStyle: document.body.style.cssText,
  htmlStyle: document.documentElement.style.cssText,
}));
await scrollbarBrowser.close();
await check('the page has a classic scrollbar in this check (otherwise it proves nothing)', async () => before.scrollbar > 0);
await check('locking scroll does not shift centered content sideways when the scrollbar disappears', async () =>
  Math.abs(locked.left - before.left) < 0.5);
await check('locking scroll hides overflow on both <body> and <html>', async () =>
  locked.bodyOverflow === 'hidden' && locked.htmlOverflow === 'hidden');
await check('closing restores the page styles exactly', async () =>
  restored.bodyStyle === '' && restored.htmlStyle === '' && Math.abs(restored.left - before.left) < 0.5);
await check('classic scrollbars are actually drawn in this check (otherwise it proves nothing)', async () => sb.scrollbarWidth > 0);
await check('a visible scrollbar on a tall popup does not sit under the default-offset close button', async () => sb.overlap <= 0.5);

/* ── A popup with no triggers never opens on its own ── */
const linkOnly = await ctx.newPage();
linkOnly.on('pageerror', (e) => errors.push(String(e)));
await linkOnly.addInitScript(() => localStorage.setItem('elemacy_1_popup_101', JSON.stringify({ shown_count: 5 })));
await linkOnly.goto(base + '/');
await linkOnly.waitForTimeout(400);
const open132 = () => linkOnly.evaluate(() => document.querySelector('[data-elemacy-popup-id="132"]').classList.contains('is-open'));
await check('a popup with no triggers does not open on page load', async () => !(await open132()));
await linkOnly.click('.link-open-132');
await linkOnly.waitForTimeout(100);
await check('it opens from a link to #elemacy-popup-{id}', open132);
await linkOnly.close();

/* ── Escape follows each modal's Close on Esc; layers opened above a modal ── */
const layers = await ctx.newPage();
layers.on('pageerror', (e) => errors.push(String(e)));
await layers.addInitScript(() => localStorage.setItem('elemacy_1_popup_101', JSON.stringify({ shown_count: 5 }))); // keep 101 from opening on load
await layers.goto(base + '/');
await layers.waitForTimeout(150);
const isOpen = (id) => layers.evaluate((pid) => document.querySelector(`[data-elemacy-popup-id="${pid}"]`).classList.contains('is-open'), id);

await layers.click('.open-no-esc');
await layers.waitForTimeout(100);
await layers.keyboard.press('Escape');
await layers.waitForTimeout(100);
await check('a modal with Close on Esc turned off stays open on Escape', () => isOpen(130));
await check('its close button still closes it', async () => {
  if (!(await isOpen(130))) return false;
  await layers.click('.elemacy-popup-130 .elemacy-popup__close');
  await layers.waitForTimeout(100);
  return !(await isOpen(130));
});

await layers.click('.open-b');
await layers.waitForTimeout(100);
await layers.keyboard.press('Escape');
await layers.waitForTimeout(100);
await check('a modal with the default setting closes on Escape', async () => !(await isOpen(102)));

await layers.click('.open-gallery');
await layers.waitForTimeout(100);
await layers.click('#p131-image');
await layers.waitForTimeout(100);
await check('the lightbox opened from inside a modal is visible above it', () => layers.evaluate(() => {
  const box = document.querySelector('.elemacy-popup-131 .elemacy-popup__box').getBoundingClientRect();
  const hit = document.elementFromPoint(box.left + box.width / 2, box.top + box.height / 2);
  return !!hit && !!hit.closest('.elementor-lightbox');
}));
await layers.evaluate(() => document.querySelector('#elementor-lightbox-stub .lb-next').focus());
await layers.waitForTimeout(50);
await check('focus can move into the lightbox and stays there', () =>
  layers.evaluate(() => document.activeElement && document.activeElement.classList.contains('lb-next')));
await layers.keyboard.press('Escape');
await layers.waitForTimeout(100);
await check('Escape closes only the lightbox: the popup stays open', async () =>
  (await isOpen(131)) && (await layers.evaluate(() => !document.getElementById('elementor-lightbox-stub'))));
if (await isOpen(131)) await layers.click('#p131-image');
await layers.waitForTimeout(100);
await layers.keyboard.press('Escape');
await layers.waitForTimeout(100);
await check('Escape closes only the lightbox even while focus is still on the image link in the popup', async () =>
  (await isOpen(131)) && (await layers.evaluate(() => !document.getElementById('elementor-lightbox-stub'))));
await check('with no layer above it, focus moved to the page behind the modal is pulled back into it', async () => {
  if (!(await isOpen(131))) {
    await layers.click('.open-gallery');
    await layers.waitForTimeout(100);
  }
  await layers.evaluate(() => document.querySelector('.open-g').focus());
  await layers.waitForTimeout(50);
  return layers.evaluate(() => !!document.activeElement.closest('[data-elemacy-popup-id="131"]'));
});
await layers.keyboard.press('Escape');
await layers.waitForTimeout(100);
await check('then Escape closes the popup', async () => !(await isOpen(131)));
await check('no modal-open class is left on <html> once every modal has closed', () =>
  layers.evaluate(() => !document.documentElement.classList.contains('elemacy-has-modal')));
await layers.close();

/* ── Sizing: Screen Gap, the modal height cap and Content Overflow ── */
const sizing = await ctx.newPage();
sizing.on('pageerror', (e) => errors.push(String(e)));
await sizing.goto(base + '/popups-sizing.html');
const geo = (id) => sizing.evaluate((pid) => {
  const box = document.querySelector(`.elementor-${pid}`);
  const scroller = box.querySelector(':scope > .elemacy-popup__scroll');
  const r = box.getBoundingClientRect();
  return {
    left: r.left, top: r.top, right: r.right, bottom: r.bottom, width: r.width, height: r.height,
    vw: document.documentElement.clientWidth, vh: window.innerHeight,
    overflow: getComputedStyle(scroller).overflowY,
    scrolls: scroller.scrollHeight > scroller.clientHeight,
  };
}, id);
const near = (a, b) => Math.abs(a - b) <= 1;

await check('sizing: a default modal keeps 20px from the screen and caps its height there', async () => {
  const g = await geo(901);
  return near(g.top, 20) && near(g.bottom, g.vh - 20) && near(g.height, g.vh - 40) && g.overflow === 'auto' && g.scrolls;
});
await check('sizing: a modal at gap 0 with width 100% and a full-height container covers the screen', async () => {
  const g = await geo(902);
  return near(g.left, 0) && near(g.top, 0) && near(g.width, g.vw) && near(g.height, g.vh);
});
await check('sizing: author CSS can cap a modal at 300px through --elemacy-popup-max-height', async () => {
  const g = await geo(903);
  return near(g.height, 300) && g.scrolls;
});
await check('sizing: Content Overflow hidden / visible reach the content scroller', async () =>
  (await geo(904)).overflow === 'hidden' && (await geo(905)).overflow === 'visible');
await check('sizing: default floating and banner keep 20px from their edges', async () => {
  const f = await geo(911);
  const b = await geo(912);
  return near(f.right, f.vw - 20) && near(f.bottom, f.vh - 20) && near(b.bottom, b.vh - 20);
});
await check('sizing: at gap 0 a floating element and a banner touch their edges', async () => {
  const f = await geo(913);
  const b = await geo(914);
  return near(f.right, f.vw) && near(f.bottom, f.vh) && near(b.bottom, b.vh);
});
await check('sizing: at gap 8px a floating element sits 8px from its corner', async () => {
  const g = await geo(915);
  return near(g.left, 8) && near(g.top, 8);
});
await check('sizing: a floating element is never wider than the screen minus twice the gap', async () => {
  const g = await geo(916);
  return near(g.width, g.vw - 16) && near(g.right, g.vw - 8);
});
await check('sizing: Custom position ignores the gap and uses only its offsets', async () => {
  const g = await geo(917);
  return near(g.left, 24) && near(g.top, 36);
});
await check('sizing: a banner at Width 100% and gap 0 spans the whole screen width', async () => {
  const g = await geo(918);
  return near(g.left, 0) && near(g.width, g.vw) && near(g.bottom, g.vh);
});
await check('sizing: a floating Width of 50% measures the screen minus the gap, as in the editor', async () => {
  const g = await geo(919);
  return near(g.left, 20) && near(g.width, (g.vw - 40) / 2) && near(g.bottom, g.vh - 20);
});
await sizing.goto(base + '/popups-sizing.html?non-modal');
await check('sizing: page content behind open floating elements and banners stays clickable', async () => {
  const hit = await sizing.evaluate(() => document.elementFromPoint(640, 400)?.id);
  await sizing.mouse.click(640, 400);
  return hit === 'behind' && (await sizing.evaluate(() => window.__behindClicks)) === 1;
});
await check('sizing: ...while the floating box itself still takes the click', async () =>
  sizing.evaluate(() => {
    const r = document.querySelector('.elementor-919').getBoundingClientRect();
    return !!document.elementFromPoint(r.left + r.width / 2, r.top + r.height / 2)?.closest('.elementor-919');
  }));
await sizing.close();

await check('no page errors across all loads', async () => errors.length === 0);

console.log(results.join('\n'));
if (errors.length) console.log('ERRORS:\n' + errors.join('\n'));
await browser.close();
server.close();
