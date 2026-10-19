import { test, expect } from '../shared/fixtures.mjs';
import { readyEditor, addHeading, saveEditor } from '../shared/editor.mjs';
import { openTemplateSettings, templateCard, themeBuilderUrl } from '../shared/templates.mjs';

async function openCreateDialog(page, title, type) {
  await page.goto(themeBuilderUrl, { waitUntil: 'domcontentloaded' });
  await page.getByRole('button', { name: 'Create New Template', exact: true }).click();
  const dialog = page.getByRole('dialog');
  await dialog.getByRole('textbox', { name: 'Title', exact: true }).fill(title);
  await selectType(page, dialog, type);
  return dialog;
}

async function selectType(page, dialog, type) {
  await dialog.getByRole('combobox', { name: 'Type', exact: true }).click();
  await page.getByRole('option', { name: type, exact: true }).click();
}

async function addConditionRow(dialog) {
  await dialog.getByRole('button', { name: '+ Add Condition' }).click();
}

async function chooseCondition(page, dialog, name, from = 'Select condition…') {
  await dialog.getByRole('combobox', { name: 'Condition', exact: true }).filter({ hasText: from instanceof RegExp ? from : new RegExp(`^${from}$`) }).click();
  await page.getByRole('option', typeof name === 'string' ? { name, exact: true } : { name }).click();
}

const NOT_DISPLAYED = 'No conditions — this template is not displayed anywhere. Add one to choose where it appears.';

// Elementor's "Meet Angie" promotion can pop up over the editor panel and
// hide the widget list; close it when it is there rather than fail on it.
async function dismissPromotions(page) {
  const close = page.getByRole('button', { name: /close angie/i });
  if (await close.first().isVisible().catch(() => false)) await close.first().click();
}

async function authorHeading(page, text) {
  await page.waitForURL(url => url.pathname === '/wp-admin/post.php' && url.searchParams.get('action') === 'elementor');
  await readyEditor(page);
  await dismissPromotions(page);
  await addHeading(page, text);
  await saveEditor(page);
}

async function visitorSees(browser, base, path, text) {
  const guest = await browser.newContext();
  try {
    const visitor = await guest.newPage();
    await visitor.goto(new URL(path, base).href);
    await expect(visitor.getByText(text, { exact: true }), `${path} must render "${text}"`).toBeVisible();
    return true;
  } finally {
    await guest.close();
  }
}

async function visitorDoesNotSee(browser, base, path, text) {
  const guest = await browser.newContext();
  try {
    const visitor = await guest.newPage();
    await visitor.goto(new URL(path, base).href);
    await expect(visitor.getByText(text, { exact: true }), `${path} must not render "${text}"`).toHaveCount(0);
  } finally {
    await guest.close();
  }
}

test('elemacy:journey:condition:search/all @theme-builder', async ({ page, author, checkpoint, browser }) => {
  const title = `Journey search all ${author.user}`;
  const text = `Human-authored search all ${author.user}`;

  const dialog = await openCreateDialog(page, title, 'Search Results');
  await expect(dialog.getByText('Display Conditions', { exact: true }), 'A Search template offers Display Conditions').toBeVisible();
  await expect(dialog.getByRole('button', { name: 'Remove condition' }), 'A new Search template starts with its broadest condition').toHaveCount(1);
  await expect(dialog.getByRole('combobox', { name: 'Condition', exact: true }).filter({ hasText: /^Search Results$/ })).toBeVisible();
  await checkpoint('search-condition-prefilled');

  // A row left without a condition is refused with a message, not saved.
  await addConditionRow(dialog);
  await dialog.getByRole('button', { name: 'Create & Edit with Elementor', exact: true }).click();
  await expect(dialog.getByText('Choose a condition for each row, or remove it.')).toBeVisible();
  await checkpoint('blank-row-refused');

  await dialog.getByRole('button', { name: 'Remove condition' }).last().click();
  await expect(dialog.getByText('Choose a condition for each row, or remove it.')).toBeHidden();
  await dialog.getByRole('button', { name: 'Create & Edit with Elementor', exact: true }).click();

  await authorHeading(page, text);

  // The condition persisted and reopens.
  await page.goto(themeBuilderUrl);
  const settings = await openTemplateSettings(page, title);
  await expect(settings.getByRole('button', { name: 'Remove condition' })).toHaveCount(1);
  await expect(settings.getByRole('combobox', { name: 'Show or hide', exact: true }).filter({ hasText: 'Show on' })).toBeVisible();
  await checkpoint('condition-persisted');
  await settings.getByRole('button', { name: 'Close', exact: true }).click();

  // Any search renders it, whatever the post types searched.
  await visitorSees(browser, page.url(), '/?s=a', text);
  await visitorSees(browser, page.url(), '/?s=a&post_type=post', text);
});

test('elemacy:journey:condition:search/post_type @theme-builder @pro-optional', async ({ page, author, checkpoint, browser }) => {
  const global = `Human-authored search global ${author.user}`;
  const typed = `Human-authored search posts ${author.user}`;
  const globalTitle = `Journey search global ${author.user}`;
  const typedTitle = `Journey search posts ${author.user}`;

  // Free-only and Pro profiles differ only in whether the option is selectable.
  const first = await openCreateDialog(page, globalTitle, 'Search Results');
  await first.getByRole('combobox', { name: 'Condition', exact: true }).filter({ hasText: /^Search Results$/ }).click();
  const option = page.getByRole('option', { name: /^Search Results: Post Type/ });
  await expect(option, 'The post type condition is listed on a Search template').toBeVisible();
  const proActive = (await option.getAttribute('aria-disabled')) !== 'true';
  await checkpoint('post-type-option');
  await page.getByRole('option', { name: 'Search Results', exact: true }).click();
  await first.getByRole('button', { name: 'Create & Edit with Elementor', exact: true }).click();
  await authorHeading(page, global);

  test.skip(!proActive, 'Elemacy Pro is not active: the post type option is locked, as asserted above.');

  const second = await openCreateDialog(page, typedTitle, 'Search Results');
  await chooseCondition(page, second, /^Search Results: Post Type/, 'Search Results');
  await second.getByRole('combobox', { name: 'Value', exact: true }).filter({ hasText: 'Any' }).click();
  await page.getByRole('option', { name: 'Post', exact: true }).click();
  await checkpoint('post-type-chosen');
  await second.getByRole('button', { name: 'Create & Edit with Elementor', exact: true }).click();
  await authorHeading(page, typed);

  await page.goto(themeBuilderUrl);
  const settings = await openTemplateSettings(page, typedTitle);
  await expect(settings.getByRole('combobox', { name: 'Value', exact: true }).filter({ hasText: /^Post$/ })).toBeVisible();
  await checkpoint('post-type-persisted');
  await settings.getByRole('button', { name: 'Close', exact: true }).click();

  const base = page.url();
  await visitorSees(browser, base, '/?s=a&post_type=post', typed);
  await visitorDoesNotSee(browser, base, '/?s=a&post_type=post', global);
  await visitorSees(browser, base, '/?s=a', global);
  await visitorDoesNotSee(browser, base, '/?s=a', typed);
  // WordPress itself ignores a post_type it does not publicly query (pages),
  // so that search carries none and only the global template applies.
  await visitorSees(browser, base, '/?s=a&post_type=page', global);
});

test('elemacy:journey:condition:search/type-switch @theme-builder', async ({ page, author, checkpoint }) => {
  const title = `Journey search switch ${author.user}`;

  const dialog = await openCreateDialog(page, title, 'Header');
  await expect(dialog.getByRole('combobox', { name: 'Condition', exact: true }).filter({ hasText: /^Entire Site$/ }), 'A new Header starts on Entire Site').toBeVisible();

  // Untouched, the starting condition follows the type.
  await selectType(page, dialog, 'Search Results');
  await expect(dialog.getByRole('combobox', { name: 'Condition', exact: true }).filter({ hasText: /^Search Results$/ })).toBeVisible();
  await expect(dialog.getByRole('button', { name: 'Remove condition' })).toHaveCount(1);
  await checkpoint('default-followed-type');

  // Once the author has chosen rules, switching type drops the ones the new type does not offer.
  await selectType(page, dialog, 'Header');
  await addConditionRow(dialog);
  await chooseCondition(page, dialog, 'Entire Site');
  await selectType(page, dialog, 'Search Results');
  await expect(dialog.getByRole('button', { name: 'Remove condition' }), 'A rule Search templates do not offer is dropped').toHaveCount(0);
  await expect(dialog.getByText(NOT_DISPLAYED)).toBeVisible();
  await checkpoint('rule-dropped');

  await dialog.getByRole('button', { name: 'Create Template', exact: true }).click();
  await expect(dialog).not.toBeVisible();
  await expect(templateCard(page, title)).toBeVisible();
  await expect(templateCard(page, title).locator('[data-display-state="unpublished"]'), 'A new template is a draft').toBeVisible();

  const settings = await openTemplateSettings(page, title);
  await expect(settings.getByRole('button', { name: 'Remove condition' })).toHaveCount(0);
  await expect(settings.getByText(NOT_DISPLAYED)).toBeVisible();
  await checkpoint('nothing-persisted');
});
