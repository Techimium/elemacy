import { test, expect } from '../shared/fixtures.mjs';
import { readyEditor, addHeading, saveEditor } from '../shared/editor.mjs';
import { createTemplate } from '../shared/templates.mjs';

// Elementor's "Meet Angie" promotion can pop up over the editor panel and
// intercept clicks; close it when it is there rather than fail on it.
async function dismissPromotions(page) {
  const close = page.getByRole('button', { name: /close angie/i });
  if (await close.first().isVisible().catch(() => false)) await close.first().click();
}

async function openDocumentSettings(page) {
  await dismissPromotions(page);
  await page.getByRole('button', { name: /Settings$/ }).first().click();
  const layout = page.locator('select[data-setting="template"]');
  await expect(layout).toBeVisible();
  return layout;
}

test('elemacy:journey:location:canvas @theme-builder', async ({ page, author, checkpoint, browser }) => {
  const title = `Journey canvas single ${author.user}`;
  const text = `Human-authored canvas single ${author.user}`;

  await createTemplate(page, { title, type: 'Single', edit: true });
  await readyEditor(page);
  await addHeading(page, text);
  await saveEditor(page);

  // Changing the layout reloads the preview, so do it on its own after the first
  // save and let the editor settle before saving again.
  await (await openDocumentSettings(page)).selectOption('elementor_canvas');
  await checkpoint('page-layout-canvas');
  await readyEditor(page);
  await expect(page.getByRole('button', { name: /^(Publish|Update|Save Changes)$/ })).toBeEnabled();
  await saveEditor(page);

  await page.reload();
  await readyEditor(page);
  await expect(await openDocumentSettings(page), 'The Page Layout persists after reopening').toHaveValue('elementor_canvas');
  await checkpoint('layout-persisted');

  const guest = await browser.newContext();
  try {
    const visitor = await guest.newPage();
    await visitor.goto(new URL('/?p=1', page.url()).href);
    await expect(visitor.getByText(text, { exact: true })).toBeVisible();
    await expect(visitor.locator('body.elementor-template-canvas'), 'Served as an Elementor Canvas page').toHaveCount(1);
    await expect(visitor.locator('.site-header, #site-header, header#masthead'), 'No theme header on a Canvas page').toHaveCount(0);
    await expect(visitor.locator('.site-footer, #site-footer, footer#colophon'), 'No theme footer on a Canvas page').toHaveCount(0);
    await checkpoint('visitor-rendered', visitor);
  } finally {
    await guest.close();
  }
});
