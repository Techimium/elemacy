import { expect } from './fixtures.mjs';

export const themeBuilderUrl = '/wp-admin/admin.php?page=elemacy#/theme-builder';

export function templateCard(page, title) {
  return page.locator('[data-slot="card"]').filter({ has: page.getByText(title, { exact: true }) });
}

// `navigation` is when the editor URL counts as reached: the default waits for the page's load
// event; a slow site can pass 'commit' and rely on the editor's own readiness checks instead.
export async function createTemplate(page, { title, type = 'Header', edit = false, navigation = 'load' }) {
  await page.goto(themeBuilderUrl, { waitUntil: 'domcontentloaded' });
  await page.getByRole('button', { name: 'Create New Template', exact: true }).click();
  const dialog = page.getByRole('dialog');
  await dialog.getByRole('textbox', { name: 'Title', exact: true }).fill(title);
  await dialog.getByRole('combobox', { name: 'Type', exact: true }).click();
  await page.getByRole('option', { name: type, exact: true }).click();
  await dialog.getByRole('button', { name: edit ? 'Create & Edit with Elementor' : 'Create Template', exact: true }).click();
  if (edit) {
    await page.waitForURL(url => url.pathname === '/wp-admin/post.php' && url.searchParams.get('action') === 'elementor', { waitUntil: navigation });
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
