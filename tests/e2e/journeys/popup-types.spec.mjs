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

async function addHeadingThenSettings(page, text) {
  await authorAndPublish(page, text);
}
