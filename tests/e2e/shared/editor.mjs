import { expect } from './fixtures.mjs';

export async function readyEditor(page) {
  await expect(page.locator('#elementor-loading')).toBeHidden({ timeout: 30000 });
  await expect(page.locator('#elementor-panel-elements-search-input')).toBeVisible({ timeout: 60000 });
  return page.frameLocator('#elementor-preview-iframe');
}

export async function addHeading(page, text) {
  const frame = await readyEditor(page);
  await page.locator('#elementor-panel-elements-search-input').fill('Heading');
  const heading = page.getByRole('button', { name: 'Heading', exact: true });
  await expect(heading).toBeVisible();
  const drop = frame.getByRole('region', { name: 'Add new layout element' });
  const start = await heading.boundingBox();
  const end = await drop.boundingBox();
  expect(start).not.toBeNull();
  expect(end).not.toBeNull();
  await page.mouse.move(start.x + start.width / 2, start.y + start.height / 2);
  await page.mouse.down();
  await page.mouse.move(end.x + end.width / 2, end.y + end.height / 2, { steps: 12 });
  await page.mouse.up();
  await expect(frame.locator('.elementor-widget-heading')).toBeVisible();
  await frame.locator('.elementor-widget-heading').click();
  const field = page.locator('[data-setting="title"]');
  await expect(field).toBeVisible();
  await field.fill('');
  await field.pressSequentially(text);
  await field.press('Tab');
  await expect(frame.getByText(text, { exact: true })).toBeVisible();
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
