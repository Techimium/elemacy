import { expect } from './fixtures.mjs';

export async function readyEditor(page) {
  await expect(page.locator('#elementor-loading')).toBeHidden({ timeout: 30000 });
  await expect(page.locator('#elementor-panel-elements-search-input')).toBeVisible({ timeout: 60000 });
  return page.frameLocator('#elementor-preview-iframe');
}

// Elementor can show its "Angie" promotion over the editor a few seconds after it loads, as a small
// bubble or as a full-screen modal. Either one swallows the editor's input, so close whichever is up.
export async function dismissPromotions(page) {
  const bubble = page.getByRole('button', { name: /close angie/i });
  if (await bubble.first().isVisible().catch(() => false)) await bubble.first().click();
  const modal = page.getByText('Pages, layouts, widgets and more');
  if (await modal.isVisible().catch(() => false)) {
    await page.keyboard.press('Escape');
    await expect(modal).toBeHidden();
  }
}

// Adds a heading by dragging it in and typing its text, as a person would. Now and then Elementor's
// preview stops following the panel (the panel shows the text, the preview keeps its placeholder) and
// does not recover on its own, so, as a person would, reload the editor and do it again. This is the
// first edit of an opened document in every journey, so a reload loses nothing. The preview showing the
// text is still what must be true at the end.
export async function addHeading(page, text) {
  for (let attempt = 1; ; attempt++) {
    try {
      await addHeadingOnce(page, text);
      return;
    } catch (error) {
      if (attempt >= 3) throw error;
      await page.reload();
    }
  }
}

// Drags a widget from the panel (found by its title) onto the empty document's drop area, as a person
// would, and waits for it in the preview. Elementor then opens the widget's panel.
export async function addWidget(page, title, selector) {
  const frame = await readyEditor(page);
  await dismissPromotions(page);
  await page.locator('#elementor-panel-elements-search-input').fill(title);
  const widget = page.getByRole('button', { name: title, exact: true });
  await expect(widget).toBeVisible();
  const drop = frame.getByRole('region', { name: 'Add new layout element' });
  const start = await widget.boundingBox();
  const end = await drop.boundingBox();
  expect(start).not.toBeNull();
  expect(end).not.toBeNull();
  await page.mouse.move(start.x + start.width / 2, start.y + start.height / 2);
  await page.mouse.down();
  await page.mouse.move(end.x + end.width / 2, end.y + end.height / 2, { steps: 12 });
  await page.mouse.up();
  await expect(frame.locator(selector)).toBeVisible();
  return frame;
}

async function addHeadingOnce(page, text) {
  const frame = await addWidget(page, 'Heading', '.elementor-widget-heading');
  await frame.locator('.elementor-widget-heading').click();
  const field = page.locator('[data-setting="title"]');
  await expect(field).toBeVisible();
  await field.fill('');
  await field.pressSequentially(text);
  await field.press('Tab');
  await expect(frame.getByText(text, { exact: true })).toBeVisible({ timeout: 8000 });
}

export async function saveEditor(page) {
  const save = page.waitForResponse(response => response.url().includes('admin-ajax.php')
    && response.request().postData()?.includes('save_builder'));
  await page.getByRole('button', { name: /^(Publish|Update|Save Changes)$/ }).click();
  const response = await save;
  expect(response.ok()).toBe(true);
  expect((await response.json()).success, 'Elementor must accept the native save').toBe(true);
}

export async function setHeadingColor(page, hex) {
  await page.getByRole('button', { name: /Style$/ }).click();
  await page.locator('.elementor-control-title_color .pcr-button').click();
  const input = page.locator('.pcr-app.visible .pcr-result');
  await expect(input).toBeVisible();
  await input.fill(hex);
  await input.press('Enter');
  await page.getByRole('button', { name: /Style$/ }).click();
}
