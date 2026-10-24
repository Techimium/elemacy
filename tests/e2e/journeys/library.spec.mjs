import { test, expect } from '../shared/fixtures.mjs';
import { templateCard, openTemplateSettings } from '../shared/templates.mjs';
import { readyEditor, addHeading, saveEditor } from '../shared/editor.mjs';

const libraryUrl = '/wp-admin/admin.php?page=elemacy#/library';

async function createLibraryTemplate(page, title, { draft = false, edit = false } = {}) {
  await page.goto(libraryUrl);
  await page.getByRole('button', { name: 'Create New Template', exact: true }).click();
  const dialog = page.getByRole('dialog');
  await dialog.getByRole('textbox', { name: 'Title', exact: true }).fill(title);
  if (draft) {
    await dialog.getByRole('combobox', { name: 'Status', exact: true }).click();
    await page.getByRole('option', { name: 'Draft', exact: true }).click();
  }
  await dialog.getByRole('button', { name: edit ? 'Create & Edit with Elementor' : 'Create Template', exact: true }).click();
  if (edit) await page.waitForURL(url => url.searchParams.get('action') === 'elementor');
  else await expect(dialog).not.toBeVisible();
}

test('elemacy:journey:library:management @library @smoke', async ({ page, author, checkpoint }) => {
  const title = `Library draft ${author.user}`;
  const renamed = `Library renamed ${author.user}`;
  await page.goto(libraryUrl);
  await page.getByRole('button', { name: 'Create New Template', exact: true }).click();
  let dialog = page.getByRole('dialog');
  await dialog.getByRole('button', { name: 'Create Template', exact: true }).click();
  await expect(dialog.getByText('Title is required', { exact: true })).toBeVisible();
  await dialog.getByRole('button', { name: 'Close', exact: true }).click();
  await createLibraryTemplate(page, title, { draft: true });
  await page.reload();
  dialog = await openTemplateSettings(page, title);
  await expect(dialog.getByRole('combobox', { name: 'Status', exact: true })).toHaveText('Draft');
  await dialog.getByRole('textbox', { name: 'Title', exact: true }).fill(renamed);
  await dialog.getByRole('combobox', { name: 'Status', exact: true }).click();
  await page.getByRole('option', { name: 'Published', exact: true }).click();
  await dialog.getByRole('button', { name: 'Update Template', exact: true }).click();
  await expect(dialog).not.toBeVisible();
  await page.reload();
  dialog = await openTemplateSettings(page, renamed);
  await expect(dialog.getByRole('textbox', { name: 'Title', exact: true })).toHaveValue(renamed);
  await expect(dialog.getByRole('combobox', { name: 'Status', exact: true })).toHaveText('Published');
  await dialog.getByRole('button', { name: 'Close', exact: true }).click();
  await page.getByPlaceholder(/^Search templates/).fill('no-matching-template-' + author.user);
  await expect(page.getByText('No templates match your search or filter.', { exact: true })).toBeVisible();
  await page.getByPlaceholder(/^Search templates/).fill(renamed);
  await expect(templateCard(page, renamed)).toBeVisible();
  await page.getByRole('tab', { name: 'Loop Item', exact: true }).click();
  await expect(templateCard(page, renamed)).toHaveCount(0);
  await page.getByRole('tab', { name: 'Section', exact: true }).click();
  await expect(templateCard(page, renamed)).toBeVisible();
  await templateCard(page, renamed).getByRole('button', { name: 'Actions', exact: true }).click();
  const duplicate = page.waitForResponse(response => response.url().endsWith('/duplicate') && response.request().method() === 'POST');
  await page.getByRole('menuitem', { name: 'Duplicate', exact: true }).click();
  const response = await duplicate;
  expect(response.status()).toBe(201);
  const copyId = (await response.json()).data.id;
  expect(copyId).toBeGreaterThan(0);
  const copy = renamed + ' (Copy)';
  dialog = await openTemplateSettings(page, copy);
  await expect(dialog.getByRole('combobox', { name: 'Status', exact: true })).toHaveText('Draft');
  await expect(dialog.getByRole('combobox', { name: 'Type', exact: true })).toHaveText('Section');
  await dialog.getByRole('button', { name: 'Close', exact: true }).click();
  await checkpoint('edited-and-duplicated');
  await templateCard(page, copy).getByRole('button', { name: 'Actions', exact: true }).click();
  await page.getByRole('menuitem', { name: 'Delete', exact: true }).click();
  await page.getByRole('dialog').getByRole('button', { name: 'Cancel', exact: true }).click();
  await expect(templateCard(page, copy)).toBeVisible();
  // The plugin has permanent Delete; recoverable Trash/Restore is WordPress UI.
  await page.goto('/wp-admin/edit.php?post_type=elemacy_library');
  const row = page.locator('#post-' + copyId);
  await row.hover();
  await row.getByRole('link', { name: /^Move .+ to the Trash$/ }).click();
  await expect(row).toHaveCount(0);
  await page.locator('.subsubsub').getByRole('link', { name: /^Trash/ }).click();
  await row.hover();
  await row.getByRole('link', { name: /^Restore/ }).click();
  await expect(row).toHaveCount(0);
  await page.goto(libraryUrl);
  await expect(templateCard(page, copy)).toBeVisible();
  await expect(templateCard(page, renamed)).toBeVisible();
  await checkpoint('restored-library-copy');
  await templateCard(page, copy).getByRole('button', { name: 'Actions', exact: true }).click();
  await page.getByRole('menuitem', { name: 'Delete', exact: true }).click();
  const removed = page.waitForResponse(response => response.request().method() === 'DELETE'
    && response.url().endsWith('/' + copyId));
  await page.getByRole('dialog').getByRole('button', { name: 'Delete Template', exact: true }).click();
  expect((await removed).ok()).toBe(true);
  await expect(templateCard(page, copy)).toHaveCount(0);
  await page.reload();
  await expect(templateCard(page, copy)).toHaveCount(0);
  await expect(templateCard(page, renamed)).toBeVisible();
  await checkpoint('owned-copy-deleted-source-preserved');
});

test('elemacy:journey:library:content-copy @library', async ({ page, author, browser, checkpoint }) => {
  const title = `Library content ${author.user}`;
  const text = `Reusable authored content ${author.user}`;
  await createLibraryTemplate(page, title, { edit: true });
  const id = new URL(page.url()).searchParams.get('post');
  await addHeading(page, text);
  await saveEditor(page);
  await page.reload();
  let frame = await readyEditor(page);
  await expect(frame.getByText(text, { exact: true })).toBeVisible();
  await checkpoint('library-content-reopened');
  const guest = await browser.newContext();
  try {
    const visitor = await guest.newPage();
    const publicResponse = await visitor.goto(new URL('/?post_type=elemacy_library&p=' + id, page.url()).href);
    expect(publicResponse.status()).toBe(200);
    await expect(visitor.getByText(text, { exact: true })).toBeVisible();
    await checkpoint('library-visitor-output', visitor);
    await page.goto(libraryUrl);
    await templateCard(page, title).getByRole('button', { name: 'Actions', exact: true }).click();
    const duplicated = page.waitForResponse(response => response.url().endsWith('/duplicate'));
    await page.getByRole('menuitem', { name: 'Duplicate', exact: true }).click();
    const copyId = (await (await duplicated).json()).data.id;
    const draftResponse = await visitor.goto(new URL('/?post_type=elemacy_library&p=' + copyId, page.url()).href);
    expect(draftResponse.status()).toBe(404);
    await expect(visitor.getByText(text, { exact: true })).toHaveCount(0);
    const dialog = await openTemplateSettings(page, title + ' (Copy)');
    await dialog.getByRole('button', { name: 'Save & Edit with Elementor', exact: true }).click();
    await page.waitForURL(url => url.searchParams.get('post') === String(copyId) && url.searchParams.get('action') === 'elementor');
    frame = await readyEditor(page);
    await expect(frame.getByText(text, { exact: true })).toBeVisible();
    await frame.locator('.elementor-widget-heading').click();
    await page.locator('[data-setting="title"]').fill('Independent copy ' + author.user);
    await saveEditor(page);
    await visitor.goto(new URL('/?post_type=elemacy_library&p=' + copyId, page.url()).href);
    await expect(visitor.getByText('Independent copy ' + author.user, { exact: true })).toBeVisible();
    await visitor.goto(new URL('/?post_type=elemacy_library&p=' + id, page.url()).href);
    await expect(visitor.getByText(text, { exact: true })).toBeVisible();
    await expect(visitor.getByText('Independent copy ' + author.user, { exact: true })).toHaveCount(0);
    await checkpoint('source-unaffected-by-copy', visitor);
  } finally { await guest.close(); }
});
