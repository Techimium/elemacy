import { test, expect } from '../shared/fixtures.mjs';
import { readyEditor, saveEditor } from '../shared/editor.mjs';
import { createTemplate, openTemplateSettings, templateCard, themeBuilderUrl } from '../shared/templates.mjs';

async function openCreateDialog(page, title, type) {
  await page.goto(themeBuilderUrl, { waitUntil: 'domcontentloaded' });
  await page.getByRole('button', { name: 'Create New Template', exact: true }).click();
  const dialog = page.getByRole('dialog');
  await dialog.getByRole('textbox', { name: 'Title', exact: true }).fill(title);
  await dialog.getByRole('combobox', { name: 'Type', exact: true }).click();
  await page.getByRole('option', { name: type, exact: true }).click();
  return dialog;
}

async function setStatus(page, dialog, label) {
  await dialog.getByRole('combobox', { name: 'Status', exact: true }).click();
  await page.getByRole('option', { name: label, exact: true }).click();
}

test('elemacy:journey:theme-builder:create-defaults @theme-builder @smoke', async ({ page, author, checkpoint, browser }) => {
  const title = `Journey default header ${author.user}`;

  const dialog = await openCreateDialog(page, title, 'Header');
  await expect(dialog.getByRole('combobox', { name: 'Status', exact: true })).toHaveText('Draft');
  await expect(dialog.getByRole('combobox', { name: 'Condition', exact: true }).filter({ hasText: /^Entire Site$/ })).toBeVisible();
  await checkpoint('defaults-shown');
  await dialog.getByRole('button', { name: 'Create Template', exact: true }).click();
  await expect(dialog).not.toBeVisible();

  const card = templateCard(page, title);
  await expect(card.getByText('(Draft)', { exact: true })).toBeVisible();
  await expect(card.getByText('Entire Site', { exact: true })).toBeVisible();
  await checkpoint('draft-listed');

  // Creating a template changes nothing for visitors until it is published.
  const guest = await browser.newContext();
  try {
    const visitor = await guest.newPage();
    await visitor.goto(new URL('/', page.url()).href);
    await expect(visitor.locator('[data-elementor-type="elemacy_header"]')).toHaveCount(0);
  } finally {
    await guest.close();
  }

  const settings = await openTemplateSettings(page, title);
  await expect(settings.getByRole('combobox', { name: 'Status', exact: true })).toHaveText('Draft');
  await expect(settings.getByRole('combobox', { name: 'Condition', exact: true }).filter({ hasText: /^Entire Site$/ })).toBeVisible();
  await checkpoint('defaults-persisted');
});

test('elemacy:journey:theme-builder:display-states @theme-builder', async ({ page, author, checkpoint }) => {
  const older = `Journey older header ${author.user}`;
  const newer = `Journey newer header ${author.user}`;
  const nowhere = `Journey nowhere single ${author.user}`;

  for (const title of [older, newer]) {
    const dialog = await openCreateDialog(page, title, 'Header');
    await setStatus(page, dialog, 'Published');
    await dialog.getByRole('button', { name: 'Create Template', exact: true }).click();
    await expect(dialog).not.toBeVisible();
  }

  const single = await openCreateDialog(page, nowhere, 'Single');
  await single.getByRole('button', { name: 'Remove condition' }).click();
  await setStatus(page, single, 'Published');
  await single.getByRole('button', { name: 'Create Template', exact: true }).click();
  await expect(single).not.toBeVisible();

  await page.reload();
  await expect(templateCard(page, older).locator(`[data-display-overridden-by]`), 'The older Entire Site header is overridden').toContainText(newer);
  await expect(templateCard(page, newer).locator('[data-display-overridden-by]')).toHaveCount(0);
  await expect(templateCard(page, nowhere).locator('[data-display-state="not_displayed"]'), 'A Single template with no condition is not displayed').toBeVisible();
  await checkpoint('display-states');
});

test('elemacy:journey:theme-builder:any-status @theme-builder', async ({ page, author, checkpoint }) => {
  const title = `Journey private footer ${author.user}`;

  await createTemplate(page, { title, type: 'Footer', edit: true });
  await readyEditor(page);
  await page.getByRole('button', { name: /Settings$/ }).first().click();
  await page.locator('select[data-setting="post_status"]').selectOption('private');
  await saveEditor(page);

  await page.goto(themeBuilderUrl, { waitUntil: 'domcontentloaded' });
  await expect(templateCard(page, title).getByText('(Private)', { exact: true }), 'A template made private in Elementor is still listed').toBeVisible();
  await checkpoint('private-listed');

  // Renaming it in the admin keeps the status Elementor set.
  const settings = await openTemplateSettings(page, title);
  await settings.getByRole('textbox', { name: 'Title', exact: true }).fill(`${title} renamed`);
  await settings.getByRole('button', { name: 'Update Template', exact: true }).click();
  await expect(settings).not.toBeVisible();
  await expect(templateCard(page, `${title} renamed`).getByText('(Private)', { exact: true })).toBeVisible();
  await checkpoint('private-kept');
});

test('elemacy:journey:theme-builder:list-error @theme-builder', async ({ page, author, checkpoint, diagnostics }) => {
  void author; // signs the admin in
  // Matches the list request whatever the permalink structure (`/wp-json/…` or `?rest_route=…`).
  const failing = url => decodeURIComponent(url.href).includes('/elemacy/v1/theme-builder/templates');
  await page.route(failing, route => route.fulfill({ status: 500, contentType: 'application/json', body: '{"message":"fixture failure"}' }));
  await page.goto(themeBuilderUrl, { waitUntil: 'domcontentloaded' });

  await expect(page.getByText('Templates could not be loaded', { exact: true })).toBeVisible();
  await expect(page.getByText('No Templates Yet', { exact: true }), 'A failed request is not shown as an empty list').toHaveCount(0);
  await checkpoint('error-shown');

  await page.unroute(failing);
  await page.getByRole('button', { name: 'Try again', exact: true }).click();
  await expect(page.getByText('Templates could not be loaded', { exact: true })).toHaveCount(0);
  await checkpoint('retried');

  // The 500s this journey staged are expected; any other browser failure still fails it.
  const staged = error => (error.type === 'http' && error.status === 500 && failing(new URL(error.url)))
    || (error.type === 'console' && error.message.includes('status of 500'));
  for (let i = diagnostics.length - 1; i >= 0; i--) {
    if (staged(diagnostics[i])) diagnostics.splice(i, 1);
  }
});

test('elemacy:journey:conditions:keyboard-picker @theme-builder @pro-optional', async ({ page, author, checkpoint }) => {
  const dialog = await openCreateDialog(page, `Journey keyboard picker ${author.user}`, 'Header');
  await dialog.getByRole('combobox', { name: 'Condition', exact: true }).filter({ hasText: /^Entire Site$/ }).click();
  const specific = page.getByRole('option', { name: /^Specific Post/ });
  test.skip((await specific.getAttribute('aria-disabled')) === 'true', 'Elemacy Pro is not active: no searchable condition to pick a value for.');
  await specific.click();

  // Specific Post always names a post: the picker offers no "All" choice.
  const trigger = dialog.getByRole('button', { name: 'Choose a post…', exact: true });
  await trigger.focus();
  await page.keyboard.press('Enter');
  const search = dialog.getByRole('combobox', { name: 'Search for a value', exact: true });
  await expect(search).toBeFocused();
  await expect(search).toHaveAttribute('aria-expanded', 'true');
  await expect(page.getByRole('listbox')).toBeVisible();
  // Wait for the search results: a post must be picked from them.
  await expect(page.getByRole('listbox').getByRole('option').nth(1)).toBeVisible();
  await page.keyboard.press('ArrowDown');
  const chosen = await page.getByRole('listbox').getByRole('option').nth(1).textContent();
  await page.keyboard.press('Enter');

  await expect(page.getByRole('listbox')).toHaveCount(0);
  await expect(dialog.getByRole('button', { name: chosen ?? '', exact: true }), 'The picked value shows and the picker has focus again').toBeFocused();
  await checkpoint('picked-by-keyboard');

  await dialog.getByRole('button', { name: chosen ?? '', exact: true }).press('Enter');
  await page.keyboard.press('Escape');
  await expect(page.getByRole('listbox')).toHaveCount(0);
  await expect(dialog.getByRole('button', { name: chosen ?? '', exact: true }), 'Escape keeps the value').toBeFocused();
});

// Run with ELEMACY_E2E_ELEMENTOR_PRO=1 in the Elementor Pro profile (setup.py --elementor-pro-plugin).
test('elemacy:journey:theme-builder:elementor-pro-notice @theme-builder', async ({ page, author, checkpoint }) => {
  void author; // signs the admin in
  const proActive = process.env.ELEMACY_E2E_ELEMENTOR_PRO === '1';
  await page.goto(themeBuilderUrl, { waitUntil: 'domcontentloaded' });
  await expect(page.getByRole('button', { name: 'Create New Template', exact: true })).toBeVisible();

  const notice = page.getByRole('note').filter({ hasText: "Elementor Pro's Theme Builder is active" });
  await expect(notice, proActive ? 'Elementor Pro is active: the screen says which builder serves a location' : 'No Elementor Pro: no notice').toHaveCount(proActive ? 1 : 0);
  await checkpoint('elementor-pro-notice');
});

// One condition id per display check: "every archive" is Post Type Archive with
// no post type, and Specific Post always names a post.
test('elemacy:journey:conditions:one-id-per-check @theme-builder', async ({ page, author, checkpoint }) => {
  const title = `Journey all archives header ${author.user}`;
  const dialog = await openCreateDialog(page, title, 'Header');
  await dialog.getByRole('combobox', { name: 'Condition', exact: true }).filter({ hasText: /^Entire Site$/ }).click();
  await expect(page.getByRole('option', { name: 'All Archives', exact: true }), 'No separate All Archives condition').toHaveCount(0);
  await expect(page.getByRole('option', { name: 'Search Results', exact: true }), 'One Search Results condition, offered on Header templates').toHaveCount(1);
  await page.getByRole('option', { name: 'Post Type Archive', exact: true }).click();
  await expect(dialog.getByRole('combobox', { name: 'Value', exact: true })).toHaveText('All');
  await dialog.getByRole('button', { name: 'Create Template', exact: true }).click();
  await expect(dialog).not.toBeVisible();
  await checkpoint('all-archives-created');

  const settings = await openTemplateSettings(page, title);
  await expect(settings.getByRole('combobox', { name: 'Condition', exact: true }).filter({ hasText: /^Post Type Archive$/ }), 'The choice persisted').toBeVisible();
  await expect(settings.getByRole('combobox', { name: 'Value', exact: true })).toHaveText('All');
  await checkpoint('all-archives-persisted');
  await page.keyboard.press('Escape');

  const specific = await openCreateDialog(page, `Journey valueless specific ${author.user}`, 'Header');
  await specific.getByRole('combobox', { name: 'Condition', exact: true }).filter({ hasText: /^Entire Site$/ }).click();
  const option = page.getByRole('option', { name: /^Specific Post/ });
  test.skip((await option.getAttribute('aria-disabled')) === 'true', 'Elemacy Pro is not active: Specific Post is a locked placeholder.');
  await option.click();
  await expect(specific.getByText('Choose a post for this condition.', { exact: true })).toBeVisible();
  await specific.getByRole('button', { name: 'Create Template', exact: true }).click();
  await expect(specific.getByText('Choose a post for each "Specific Post / Page" condition.', { exact: true }), 'The form asks for a post').toBeVisible();
  await expect(specific, 'Nothing is created').toBeVisible();
  await checkpoint('specific-post-needs-a-post');
});
