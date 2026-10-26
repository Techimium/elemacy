import { test, expect } from '../shared/fixtures.mjs';
import { readyEditor } from '../shared/editor.mjs';
import { createPopup, openPopupSettings, choose, setNumber, enableCloseButton, authorAndPublish, publish, visitor, popupRoot, geometry, near } from '../shared/popups.mjs';

// Each shipped popup type, authored through the admin and Elementor, published, reopened and seen by a visitor.
// Defaults come from the type, so every case also checks the animations a type opens and closes with.
const types = {
  popup: { id: 'popup', label: 'Popup', animation: ['fade-in', 'fade-out'], anchor: { option: 'bottom-right', name: 'Bottom Right', at: g => near(g.right, g.viewport.width - 20) && near(g.bottom, g.viewport.height - 20) } },
  topbar: { id: 'topbar', label: 'Top / Bottom Bar', animation: [null, 'slide-out-up'], anchor: { option: 'top', name: 'Top', at: g => near(g.y, 0) && near(g.width, g.viewport.width) } },
  banner: { id: 'banner', label: 'Banner', animation: ['slide-in-up', 'slide-out-down'], anchor: { option: 'top-right', name: 'Top Right', at: g => near(g.right, g.viewport.width - 20) && near(g.y, 20) } },
  floating: { id: 'floating', label: 'Floating Element', animation: ['fade-in', 'fade-out'], anchor: { option: 'top-left', name: 'Top Left', at: g => near(g.x, 20) && near(g.y, 20) } },
};

async function seeOpenAndClose(page, id, [animationIn, animationOut]) {
  const popup = popupRoot(page, id);
  await expect(popup).toBeVisible();
  await expect(popup).toHaveAttribute('aria-hidden', 'false');
  // A top bar with no trigger is printed already open (no shift of the page), so it has no entrance.
  if (animationIn) await expect.poll(() => page.evaluate(() => window.__animations)).toContain('elemacy-popup-' + animationIn);
  else expect(await page.evaluate(() => window.__animations)).not.toContain('elemacy-popup-slide-in-down');
  await popup.getByRole('button', { name: 'Close', exact: true }).click();
  await expect(popup).toBeHidden();
  await expect.poll(() => page.evaluate(() => window.__animations)).toContain('elemacy-popup-' + animationOut);
}

for (const type of Object.values(types)) {
  test(`elemacy:journey:popup-type:${type.id} @popups`, async ({ page, author, browser, checkpoint }) => {
    const title = `${type.label} journey ${author.user}`;
    const text = `${type.label} content ${author.user}`;
    const id = await createPopup(page, { title, type: type.label });
    await addHeadingThenSettings(page, text);
    await choose(page, 'Position', type.anchor.option);
    await enableCloseButton(page); // a Floating Element has none by default
    await publish(page);
    await checkpoint('authored-and-published');

    // Reopened, the document still holds the choice.
    await page.reload();
    await openPopupSettings(page);
    await expect(page.getByRole('combobox', { name: 'Position', exact: true })).toHaveValue(type.anchor.option);

    const guest = await visitor(browser);
    try {
      await guest.page.goto(new URL('/', page.url()).href);
      const g = await geometry(guest.page, id);
      expect(type.anchor.at(g), `${type.label} anchored ${type.anchor.name}: ${JSON.stringify(g)}`).toBe(true);
      await seeOpenAndClose(guest.page, id, type.animation);
      await checkpoint('visitor-view', guest.page);
    } finally { await guest.context.close(); }
  });
}

test('elemacy:journey:popup-type:topbar-bottom @popups', async ({ page, author, browser }) => {
  const id = await createPopup(page, { title: `Bottom bar ${author.user}`, type: 'Top / Bottom Bar' });
  await addHeadingThenSettings(page, `Bottom bar content ${author.user}`);
  await choose(page, 'Position', 'bottom');
  await publish(page);
  await page.reload();
  await openPopupSettings(page);
  await expect(page.getByRole('combobox', { name: 'Position', exact: true })).toHaveValue('bottom');

  const guest = await visitor(browser);
  try {
    await guest.page.goto(new URL('/', page.url()).href);
    const g = await geometry(guest.page, id);
    expect(near(g.bottom, g.viewport.height) && near(g.width, g.viewport.width), `A bottom bar sits on the bottom edge: ${JSON.stringify(g)}`).toBe(true);
    await seeOpenAndClose(guest.page, id, ['slide-in-down', 'slide-out-up']);
  } finally { await guest.context.close(); }
});

// Custom positioning: the offsets nudge the box from the top-left origin of the popup's layer.
const custom = [
  { type: 'Popup', id: 'popup', origin: 20 }, // a modal keeps its 20px layer padding
  { type: 'Banner', id: 'banner', origin: 0 },
  { type: 'Floating Element', id: 'floating', origin: 0 },
];
for (const entry of custom) {
  for (const mobile of [false, true]) {
    test(`elemacy:journey:popup-type:${entry.id}-custom-offsets${mobile ? '-mobile' : ''} @popups`, async ({ page, author, browser }) => {
      const id = await createPopup(page, { title: `${entry.type} custom ${author.user}`, type: entry.type });
      await addHeadingThenSettings(page, `${entry.type} custom content ${author.user}`);
      await choose(page, 'Position', 'custom');
      await setNumber(page, 'Offset X', 24);
      await setNumber(page, 'Offset Y', 36);
      await publish(page);
      await page.reload();
      await openPopupSettings(page);
      await expect(page.getByRole('combobox', { name: 'Position', exact: true })).toHaveValue('custom');
      await expect(page.getByRole('spinbutton', { name: 'Offset X', exact: true })).toHaveValue('24');
      await expect(page.getByRole('spinbutton', { name: 'Offset Y', exact: true })).toHaveValue('36');

      const guest = await visitor(browser, { viewport: mobile ? { width: 390, height: 800 } : { width: 1440, height: 1000 } });
      try {
        await guest.page.goto(new URL('/', page.url()).href);
        const g = await geometry(guest.page, id);
        expect(near(g.x, entry.origin + 24) && near(g.y, entry.origin + 36), `Custom offsets place the box at its origin plus 24/36: ${JSON.stringify(g)}`).toBe(true);
        // Offsets move the box and are the author's to check on small screens (the editor warns); the box
        // itself never grows past the screen.
        if (mobile) expect(g.width, 'On a phone the box is no wider than the screen').toBeLessThanOrEqual(g.viewport.width);
      } finally { await guest.context.close(); }
    });
  }
}

// Screen Gap: a modal set to gap 0 and width 100% reaches the screen edges; a per-device gap applies per device.
// (The full-height case, a container at 100dvh, is geometry only and is checked in run-popups.mjs.)
for (const perDevice of [false, true]) {
  test(`elemacy:journey:popup-type:popup-full-screen${perDevice ? '-per-device' : ''} @popups`, async ({ page, author, browser }) => {
    const id = await createPopup(page, { title: `Full screen ${author.user}`, type: 'Popup' });
    await addHeadingThenSettings(page, `Full screen content ${author.user}`);
    await choose(page, 'Position', 'top');
    await setUnit(page, 'elemacy_width', '%');
    await setNumber(page, 'Width', 100);
    await setNumber(page, 'Screen Gap', perDevice ? 40 : 0);
    if (perDevice) {
      await switchDevice(page, 'elemacy_screen_gap', 'mobile');
      await setNumber(page, 'Screen Gap', 0);
      await switchDevice(page, 'elemacy_screen_gap_mobile', 'desktop');
    }
    await choose(page, 'Content Overflow', 'hidden');
    await publish(page);
    await page.reload();
    await openPopupSettings(page);
    await expect(page.getByRole('spinbutton', { name: 'Screen Gap', exact: true })).toHaveValue(perDevice ? '40' : '0');
    await expect(page.getByRole('combobox', { name: 'Content Overflow', exact: true })).toHaveValue('hidden');

    for (const mobile of [false, true]) {
      const gap = perDevice && !mobile ? 40 : 0;
      const guest = await visitor(browser, { viewport: mobile ? { width: 390, height: 800 } : { width: 1440, height: 1000 } });
      try {
        await guest.page.goto(new URL('/', page.url()).href);
        const g = await geometry(guest.page, id);
        expect(near(g.x, gap) && near(g.y, gap) && near(g.width, g.viewport.width - 2 * gap), `${mobile ? 'Mobile' : 'Desktop'}: the box sits ${gap}px from the edges: ${JSON.stringify(g)}`).toBe(true);
        const overflow = await popupRoot(guest.page, id).locator('.elemacy-popup__scroll').evaluate(el => getComputedStyle(el).overflowY);
        expect(overflow, 'Content Overflow reaches the content').toBe('hidden');
      } finally { await guest.context.close(); }
    }
  });
}

test('elemacy:journey:popup-type:banner-flush @popups', async ({ page, author, browser }) => {
  const id = await createPopup(page, { title: `Flush banner ${author.user}`, type: 'Banner' });
  await addHeadingThenSettings(page, `Flush banner content ${author.user}`);
  await choose(page, 'Position', 'bottom');
  await setUnit(page, 'elemacy_width', '%');
  await setNumber(page, 'Width', 100);
  await setNumber(page, 'Screen Gap', 0);
  await publish(page);
  await page.reload();
  await openPopupSettings(page);
  await expect(page.getByRole('spinbutton', { name: 'Screen Gap', exact: true })).toHaveValue('0');
  await expect(page.getByRole('spinbutton', { name: 'Width', exact: true })).toHaveValue('100');
  await expect(page.getByRole('combobox', { name: 'Content Overflow', exact: true }), 'Content Overflow is a modal-only setting').toHaveCount(0);

  const guest = await visitor(browser);
  try {
    await guest.page.goto(new URL('/', page.url()).href);
    const g = await geometry(guest.page, id);
    // A percentage Width measures the screen, as in the editor, not the banner's own content.
    expect(near(g.bottom, g.viewport.height) && near(g.x, 0) && near(g.width, g.viewport.width), `The banner spans the bottom edge: ${JSON.stringify(g)}`).toBe(true);
  } finally { await guest.context.close(); }
});

/** Picks a slider's unit through its unit switcher. */
async function setUnit(page, control, unit) {
  await page.locator(`.elementor-control-${control} .e-units-switcher`).click();
  await page.locator(`.elementor-control-${control} .elementor-units-choices-label[data-choose="${unit}"]`).click();
}

/** Switches the editor to another device through a responsive control's own switcher. */
async function switchDevice(page, control, device) {
  const switchers = page.locator(`.elementor-control-${control} .elementor-control-responsive-switchers`);
  // Collapsed, the switcher shows only the current device's button; clicking it opens the list.
  await switchers.locator('.elementor-responsive-switcher').filter({ visible: true }).first().click();
  await switchers.locator(`.elementor-responsive-switcher-${device}`).click();
  await expect(page.locator('body')).toHaveClass(new RegExp(`elementor-device-${device}`));
}

async function addHeadingThenSettings(page, text) {
  await authorAndPublish(page, text);
}
