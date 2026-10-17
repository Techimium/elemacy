import { expect } from './fixtures.mjs';

export const themeBuilderUrl = '/wp-admin/admin.php?page=elemacy#/theme-builder';

export function templateCard(page, title) {
  return page.locator('[data-slot="card"]').filter({ has: page.getByText(title, { exact: true }) });
}

export async function createTemplate(page, { title, type = 'Header', edit = false }) {
  await page.goto(themeBuilderUrl, { waitUntil: 'domcontentloaded' });
  await page.getByRole('button', { name: 'Create New Template', exact: true }).click();
  const dialog = page.getByRole('dialog');
  await dialog.getByRole('textbox', { name: 'Title', exact: true }).fill(title);
  await dialog.getByRole('combobox', { name: 'Type', exact: true }).click();
  await page.getByRole('option', { name: type, exact: true }).click();
  await dialog.getByRole('button', { name: edit ? 'Create & Edit with Elementor' : 'Create Template', exact: true }).click();
  if (edit) {
    await page.waitForURL(url => url.pathname === '/wp-admin/post.php' && url.searchParams.get('action') === 'elementor');
    await expect(page.locator('#elementor-preview-iframe')).toBeAttached({ timeout: 60000 });
  } else {
    await expect(dialog).not.toBeVisible();
    await expect(templateCard(page, title)).toBeVisible();
  }
}

export async function openTemplateSettings(page, title) {
  await templateCard(page, title).getByRole('button', { name: 'Actions', exact: true }).click();
  await page.getByRole('menuitem', { name: 'Edit', exact: true }).click();
  return page.getByRole('dialog');
}
