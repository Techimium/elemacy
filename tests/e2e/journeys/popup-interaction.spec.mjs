import { test, expect } from '../shared/fixtures.mjs';
import { fixture } from '../shared/environment.mjs';
import { publishedPopup, visitor, popupRoot, isOpen } from '../shared/popups.mjs';

const home = page => new URL('/', page.url()).href;
const open = (page, id) => page.evaluate(popup => window.ElemacyPopups.open(popup), Number(id));
const pad = ids => ids.map(Number);

// A modal popup that only opens when asked (its click trigger never matches), filled with a form, an image
// carousel and a video element. The popup is created and published through the UI.
async function interactionPopup(page, author, name, content = true) {
  const id = await publishedPopup(page, {
    title: `${name} ${author.user}`, type: 'Popup',
    triggers: [{ label: 'On Click', fields: [{ label: 'CSS selector', value: '.journey-never' }] }],
  });
  if (content) fixture('seed-popup-content', author.manifest, id);
  return id;
}

const closeButton = (page, id) => popupRoot(page, id).getByRole('button', { name: 'Close', exact: true });
const focusInside = (page, id) => page.evaluate(popup => document.querySelector(`[data-elemacy-popup-id="${popup}"]`).contains(document.activeElement), id);

test('elemacy:journey:popup-interaction:dismissal @popups', async ({ page, author, browser }) => {
  const id = await interactionPopup(page, author, 'Dismissal', false);
  const guest = await visitor(browser);
  try {
    await guest.page.goto(home(page));
    await open(guest.page, id);
    await expect(popupRoot(guest.page, id)).toBeVisible();
    await closeButton(guest.page, id).click();
    await expect(popupRoot(guest.page, id), 'The close button closes it').toBeHidden();

    await open(guest.page, id);
    await expect(popupRoot(guest.page, id)).toBeVisible();
    await guest.page.mouse.click(8, 8); // the overlay, outside the box
    await expect(popupRoot(guest.page, id), 'A click on the overlay closes it').toBeHidden();

    await open(guest.page, id);
    await expect(popupRoot(guest.page, id)).toBeVisible();
    await guest.page.keyboard.press('Escape');
    await expect(popupRoot(guest.page, id), 'Escape closes it').toBeHidden();
    expect(await guest.page.evaluate(() => document.body.style.overflow), 'Scrolling is released').toBe('');
  } finally { await guest.context.close(); }
});

test('elemacy:journey:popup-interaction:focus @popups', async ({ page, author, browser }) => {
  const first = await interactionPopup(page, author, 'Focus A');
  const second = await interactionPopup(page, author, 'Focus B', false);
  const guest = await visitor(browser);
  try {
    await guest.page.goto(home(page));
    await open(guest.page, first);
    await expect(popupRoot(guest.page, first)).toBeVisible();
    expect(await focusInside(guest.page, first), 'Focus moves into the popup').toBe(true);

    // Forward and back through every focusable element: focus never leaves the popup.
    for (let i = 0; i < 8; i++) { await guest.page.keyboard.press('Tab'); expect(await focusInside(guest.page, first), `Tab ${i + 1} stays inside`).toBe(true); }
    for (let i = 0; i < 8; i++) { await guest.page.keyboard.press('Shift+Tab'); expect(await focusInside(guest.page, first), `Shift+Tab ${i + 1} stays inside`).toBe(true); }

    // Two modals open together, the newer one on top.
    await open(guest.page, second);
    await expect(popupRoot(guest.page, second)).toBeVisible();
    await expect(popupRoot(guest.page, first)).toBeVisible();
    const z = id => popupRoot(guest.page, id).evaluate(root => Number(getComputedStyle(root).zIndex));
    expect(await z(second), 'The newer modal is above the older one').toBeGreaterThan(await z(first));
    expect(await focusInside(guest.page, second)).toBe(true);

    // The older one closes while the newer one is open: focus stays with the newer.
    await guest.page.evaluate(id => window.ElemacyPopups.close(id), Number(first));
    await expect(popupRoot(guest.page, first)).toBeHidden();
    expect(await focusInside(guest.page, second), 'Focus did not go back to the page').toBe(true);
    await guest.page.keyboard.press('Tab');
    expect(await focusInside(guest.page, second), 'Tab keeps cycling in the newer modal').toBe(true);
    await guest.page.keyboard.press('Escape');
    await expect(popupRoot(guest.page, second), 'Escape applies to the modal that is left').toBeHidden();
  } finally { await guest.context.close(); }
});

test('elemacy:journey:popup-interaction:content @popups', async ({ page, author, browser }) => {
  const id = await interactionPopup(page, author, 'Content');
  const guest = await visitor(browser);
  try {
    await guest.page.goto(home(page));
    const submissions = [];
    guest.page.on('request', request => { if (request.url().includes('admin-ajax.php') && (request.postData() ?? '').includes('elemacy_widgets_form_submit')) submissions.push(request.url()); });

    // A carousel in a popup is sized and shows its slides on the first open.
    await open(guest.page, id);
    const popup = popupRoot(guest.page, id);
    await expect(popup).toBeVisible();
    await expect.poll(() => popup.locator('.swiper-slide').count(), { message: 'The carousel renders slides' }).toBeGreaterThan(1);
    const slide = await popup.locator('.swiper-slide-active').first().boundingBox();
    expect(slide?.width ?? 0, 'The active slide has a real size').toBeGreaterThan(50);

    // A video stops when the popup closes.
    const playing = await guest.page.evaluate(async () => {
      const video = document.getElementById('journey-video');
      const canvas = document.createElement('canvas');
      canvas.width = 16; canvas.height = 16;
      const context = canvas.getContext('2d');
      window.__ticker = setInterval(() => { context.fillStyle = `hsl(${Date.now() % 360} 80% 50%)`; context.fillRect(0, 0, 16, 16); }, 40);
      video.srcObject = canvas.captureStream(20);
      await video.play();
      return !video.paused;
    });
    expect(playing, 'The video is playing').toBe(true);

    // Reopen three times; each time the form submits exactly once.
    for (let round = 1; round <= 3; round++) {
      if (round > 1) { await open(guest.page, id); await expect(popup).toBeVisible(); }
      await popup.getByRole('textbox').nth(0).fill(`Visitor ${round}`);
      await popup.getByRole('textbox').nth(1).fill(`visitor${round}@example.invalid`);
      const before = submissions.length;
      await popup.getByRole('button', { name: /send/i }).click();
      await expect.poll(() => submissions.length, { message: `Round ${round} submits` }).toBe(before + 1);
      await guest.page.waitForTimeout(400);
      expect(submissions.length, `Round ${round}: one request per submit, not one per time the popup was opened`).toBe(before + 1);
      if (round === 1) {
        await closeButton(guest.page, id).click();
        await expect(popup).toBeHidden();
        expect(await guest.page.evaluate(() => document.getElementById('journey-video').paused), 'Closing the popup stopped the video').toBe(true);
      } else if (round === 2) {
        await closeButton(guest.page, id).click();
        await expect(popup).toBeHidden();
      }
    }
    await guest.page.evaluate(() => clearInterval(window.__ticker));
  } finally { await guest.context.close(); }
});

test('elemacy:journey:popup-analytics @popups @pro', async ({ page, author, browser }) => {
  const id = await interactionPopup(page, author, 'Analytics');
  const counts = () => page.evaluate(async popup => {
    const base = window.elemacy.api_base.replace(/\/$/, '');
    const response = await fetch(`${base}/popups/${popup}/analytics`, { headers: { 'X-WP-Nonce': window.elemacy.nonce } });
    return (await response.json()).data;
  }, Number(id));
  await page.goto('/wp-admin/admin.php?page=elemacy#/popups');
  const before = await counts();

  const guest = await visitor(browser);
  try {
    const beacons = [];
    guest.page.on('request', async request => {
      if (request.url().includes('/track')) beacons.push({ headers: await request.allHeaders(), body: request.postData() });
    });

    await guest.page.goto(home(page));
    await open(guest.page, id);
    await expect(popupRoot(guest.page, id)).toBeVisible();
    await expect.poll(() => beacons.filter(b => b.body?.includes('impression')).length).toBe(1);

    await closeButton(guest.page, id).click();
    await expect(popupRoot(guest.page, id)).toBeHidden();
    await guest.page.waitForTimeout(500);
    expect(beacons.filter(b => b.body?.includes('conversion')).length, 'A close-button click is not a conversion').toBe(0);

    await open(guest.page, id);
    await expect(popupRoot(guest.page, id)).toBeVisible();
    // A display is counted once its entrance has finished.
    await expect.poll(() => beacons.filter(b => b.body?.includes('impression')).length).toBe(2);
    await popupRoot(guest.page, id).getByRole('textbox').nth(0).fill('Visitor');
    await popupRoot(guest.page, id).getByRole('textbox').nth(1).fill('visitor@example.invalid');
    await popupRoot(guest.page, id).getByRole('button', { name: /send/i }).click();
    await expect.poll(() => beacons.filter(b => b.body?.includes('conversion')).length).toBe(1);

    // The beacon needs nothing from the page: no token, no cookies, so a page cached long ago records just the same.
    expect(beacons.every(b => b.headers['x-wp-nonce'] === undefined && b.headers.cookie === undefined), 'No nonce and no cookie').toBe(true);
  } finally { await guest.context.close(); }

  await expect.poll(async () => (await counts()).impressions - before.impressions, { message: 'The dashboard counts two displays' }).toBe(2);
  const after = await counts();
  expect(after.conversions - before.conversions, 'And exactly one conversion').toBe(1);
});
