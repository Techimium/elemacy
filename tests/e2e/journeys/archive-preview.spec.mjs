import { test, expect } from '../shared/fixtures.mjs';
import { fixture } from '../shared/environment.mjs';
import { readyEditor, saveEditor, dismissPromotions } from '../shared/editor.mjs';
import { createTemplate } from '../shared/templates.mjs';

// Setup, not the feature under test: a heading bound to the Archive Title tag, put in
// through the editor's own command API so the journey can focus on the preview settings.
async function addArchiveTitleHeading(page) {
  await readyEditor(page);
  await dismissPromotions(page);
  await page.evaluate(() => {
    const container = $e.run('document/elements/create', {
      container: elementor.getPreviewContainer(),
      model: { elType: 'container' },
      options: { edit: false },
    });
    const tag = '[elementor-tag id="aptitle1" name="elemacy-archive-title" settings="' + encodeURIComponent(JSON.stringify({ remove_prefix: 'yes' })) + '"]';
    $e.run('document/elements/create', {
      container,
      model: { elType: 'widget', widgetType: 'heading', settings: { title: 'placeholder', __dynamic__: { title: tag } } },
      options: { edit: false },
    });
  });
}

async function openPreviewSettings(page, control = 'elemacy_preview_type') {
  await dismissPromotions(page);
  const settings = page.getByRole('button', { name: /Settings$/ }).first();
  await settings.click();
  const section = page.locator('.elementor-control-elemacy_preview_section');
  await expect(section).toBeVisible();
  const anchor = page.locator(`[data-setting="${control}"], .elementor-control-${control}`).first();
  if (!(await anchor.isVisible().catch(() => false))) await section.click();
  await expect(anchor).toBeVisible();
}

// Type into an elemacy_query picker and choose the result with this exact label.
async function pickFromQuery(page, control, search, label) {
  const field = page.locator(`.elementor-control-${control}`);
  const searchBox = page.locator('.select2-container--open .select2-search__field');
  // Clearing a chosen value can leave the dropdown open already; clicking again would close it.
  if (!(await searchBox.isVisible().catch(() => false))) await field.locator('.select2-selection').click();
  await searchBox.fill(search);
  await page.locator('.select2-container--open .select2-results__option').filter({ hasText: label }).click();
}

async function visitorSees(browser, base, path, text) {
  const guest = await browser.newContext();
  try {
    const visitor = await guest.newPage();
    await visitor.goto(new URL(path, base).href);
    await expect(visitor.locator('.elementor-widget-heading').first(), `${path} must show "${text}"`).toContainText(text);
    return visitor;
  } finally {
    await guest.close();
  }
}

// Apply & Preview saves the document and then reloads the preview. Wait for the save, so a
// reload that follows cannot cut it short (an unchanged document sends no save at all).
async function applyPreview(page) {
  const saved = page.waitForResponse(response => response.url().includes('admin-ajax.php')
    && (response.request().postData() || '').includes('save_builder'), { timeout: 8000 }).catch(() => null);
  await page.getByRole('button', { name: 'Apply & Preview', exact: true }).click();
  await saved;
}

test('elemacy:journey:template:archive @theme-builder', async ({ page, author, checkpoint, browser }) => {
  test.slow(); // Several editor loads (each can take tens of seconds on a cold site) in one journey.
  const seeded = fixture('seed-archive', author.manifest);
  const title = `Journey archive ${author.user}`;

  await createTemplate(page, { title, type: 'Archive', edit: true, navigation: 'commit' });
  await addArchiveTitleHeading(page);
  let frame = page.frameLocator('#elementor-preview-iframe');
  await expect(frame.locator('.elementor-widget-heading').first(), 'The Archive Title tag renders in the editor preview').toBeVisible({ timeout: 30000 });
  await expect(frame.locator('.elementor-widget-heading').first(), 'The default preview is the generic post type archive').toContainText('Archives');
  await openPreviewSettings(page);
  await checkpoint('preview-settings');

  // Taxonomy Term: type to search, pick the category, apply, and the preview becomes its archive.
  await page.locator('select[data-setting="elemacy_preview_type"]').selectOption('term');
  await expect(page.locator('.elementor-control-elemacy_preview_term')).toBeVisible();
  await expect(page.locator('.elementor-control-elemacy_preview_post_type'), 'The post type picker is for the post type archive only').toBeHidden();
  await pickFromQuery(page, 'elemacy_preview_term', seeded.category.name, `${seeded.category.name} (Category)`);
  await checkpoint('term-picked');
  await applyPreview(page);
  await expect(frame.locator('.elementor-widget-heading').first(), 'The preview shows the chosen category\'s own archive title').toContainText(seeded.category.name);
  await checkpoint('term-previewed');

  // Apply & Preview saves the document; reopened, the choice is still there, shown by name and not by id.
  await page.reload();
  await readyEditor(page);
  frame = page.frameLocator('#elementor-preview-iframe');
  await openPreviewSettings(page);
  await expect(page.locator('select[data-setting="elemacy_preview_type"]')).toHaveValue('term');
  await expect(page.locator('.elementor-control-elemacy_preview_term .select2-selection__rendered'), 'The saved term is shown by its name').toContainText(`${seeded.category.name} (Category)`);
  await checkpoint('term-persisted');

  // Author: found by display name, never by login or email.
  await page.locator('select[data-setting="elemacy_preview_type"]').selectOption('author');
  await pickFromQuery(page, 'elemacy_preview_author', seeded.writer.name, seeded.writer.name);
  await applyPreview(page);
  await expect(frame.locator('.elementor-widget-heading').first()).toContainText(seeded.writer.name);
  await checkpoint('author-previewed');

  // Month: any day in the month previews that monthly archive. Applying reloads the
  // preview and returns the panel to its widget list, so reopen the settings first.
  await openPreviewSettings(page);
  await page.locator('select[data-setting="elemacy_preview_type"]').selectOption('month');
  await page.locator('[data-setting="elemacy_preview_month"]').click({ force: true });
  const calendar = page.locator('.flatpickr-calendar.open');
  await expect(calendar).toBeVisible();
  await calendar.locator('.flatpickr-monthDropdown-months').selectOption({ label: 'March' });
  await calendar.locator('.cur-year').fill('2024');
  await calendar.locator('.cur-year').press('Enter');
  await calendar.locator('.flatpickr-day:not(.prevMonthDay):not(.nextMonthDay)').filter({ hasText: /^15$/ }).click();
  await checkpoint('month-picked');
  await applyPreview(page);
  await expect(frame.locator('.elementor-widget-heading').first()).toContainText('March 2024');
  await checkpoint('month-previewed');

  // A saved choice that no longer exists falls back to the post type archive, with no error.
  await openPreviewSettings(page);
  await page.locator('select[data-setting="elemacy_preview_type"]').selectOption('term');
  await page.locator('.elementor-control-elemacy_preview_term .select2-selection__clear').click();
  await pickFromQuery(page, 'elemacy_preview_term', seeded.tag.name, `${seeded.tag.name} (Tag)`);
  await applyPreview(page);
  await expect(frame.locator('.elementor-widget-heading').first()).toContainText(seeded.tag.name);
  fixture('delete-term', author.manifest, seeded.tag.id);
  await page.reload();
  await readyEditor(page);
  frame = page.frameLocator('#elementor-preview-iframe');
  await expect(frame.locator('.elementor-widget-heading').first(), 'A deleted term previews the generic archive').toContainText('Archives');
  await checkpoint('deleted-term-falls-back');

  // Visitors are unaffected by the preview choice: a real category archive shows its own title.
  await visitorSees(browser, page.url(), `/?cat=${seeded.category.id}`, seeded.category.name);
});

test('elemacy:journey:tag:elemacy-archive-title @theme-builder', async ({ page, author, checkpoint, browser }) => {
  const title = `Journey search title ${author.user}`;

  await createTemplate(page, { title, type: 'Search Results', edit: true, navigation: 'commit' });
  await addArchiveTitleHeading(page);
  const frame = page.frameLocator('#elementor-preview-iframe');
  await expect(frame.locator('.elementor-widget-heading').first(), 'The editor previews a sample search, not the generic archive')
    .toContainText('Search Results for:');
  await checkpoint('search-preview');
  await saveEditor(page);

  // Visitors searching get the search heading with their own term, never "Archives".
  const visitor = await visitorSees(browser, page.url(), '/?s=journey', 'Search Results for: journey');
  expect(visitor).toBeTruthy();
});

test('elemacy:journey:template:single @theme-builder', async ({ page, author, checkpoint }) => {
  test.slow(); // Two editor loads; each can take tens of seconds on a cold site.
  const seeded = fixture('seed-archive', author.manifest);
  const title = `Journey single picker ${author.user}`;

  await createTemplate(page, { title, type: 'Single', edit: true, navigation: 'commit' });
  await readyEditor(page);
  await openPreviewSettings(page, 'elemacy_preview_post');
  // The post picker finds a post by typing, labelled with its type, as it always has.
  await pickFromQuery(page, 'elemacy_preview_post', seeded.post.title, `${seeded.post.title} (Post)`);
  await checkpoint('post-picked');
  await applyPreview(page);

  await page.reload();
  await readyEditor(page);
  await openPreviewSettings(page, 'elemacy_preview_post');
  await expect(page.locator('.elementor-control-elemacy_preview_post .select2-selection__rendered'), 'The saved post is shown by title')
    .toContainText(`${seeded.post.title} (Post)`);
  await checkpoint('post-persisted');
});

test('elemacy:journey:template:loop @loop', async ({ page, author, checkpoint }) => {
  test.slow(); // Several editor loads in one journey.
  const seeded = fixture('seed-archive', author.manifest);
  const title = `Journey loop picker ${author.user}`;

  await page.goto('/wp-admin/admin.php?page=elemacy#/library');
  await page.getByRole('button', { name: 'Create New Template', exact: true }).click();
  const dialog = page.getByRole('dialog');
  await dialog.getByRole('textbox', { name: 'Title', exact: true }).fill(title);
  await dialog.getByRole('combobox', { name: 'Type', exact: true }).click();
  await page.getByRole('option', { name: 'Loop Item', exact: true }).click();
  await dialog.getByRole('button', { name: 'Create & Edit with Elementor', exact: true }).click();
  await page.waitForURL(url => url.searchParams.get('action') === 'elementor', { waitUntil: 'commit' });
  await readyEditor(page);
  await openPreviewSettings(page, 'preview_data_source');

  // Posts (the default source): the post picker works as it always has.
  await pickFromQuery(page, 'elemacy_preview_post', seeded.post.title, `${seeded.post.title} (Post)`);
  await applyPreview(page);
  await page.reload();
  await readyEditor(page);
  await openPreviewSettings(page, 'preview_data_source');
  await expect(page.locator('.elementor-control-elemacy_preview_post .select2-selection__rendered'), 'The saved post is shown by title')
    .toContainText(`${seeded.post.title} (Post)`);
  await checkpoint('loop-post-persisted');

  // Taxonomy Terms: search live for a term.
  await page.locator('select[data-setting="preview_data_source"]').selectOption('terms');
  await pickFromQuery(page, 'preview_term', seeded.tag.name, `${seeded.tag.name} (Tag)`);
  await checkpoint('loop-term-picked');
  await applyPreview(page);

  // Users: find a person who has no posts, by display name only.
  await page.reload();
  await readyEditor(page);
  await openPreviewSettings(page, 'preview_data_source');
  await expect(page.locator('.elementor-control-preview_term .select2-selection__rendered'), 'The saved term is shown by its name')
    .toContainText(`${seeded.tag.name} (Tag)`);
  await page.locator('select[data-setting="preview_data_source"]').selectOption('users');
  await pickFromQuery(page, 'preview_user', seeded.reader.name, seeded.reader.name);
  await checkpoint('loop-user-picked');
  await applyPreview(page);

  await page.reload();
  await readyEditor(page);
  await openPreviewSettings(page, 'preview_data_source');
  await expect(page.locator('.elementor-control-preview_user .select2-selection__rendered'), 'The saved user is shown by display name')
    .toContainText(seeded.reader.name);
  await checkpoint('loop-user-persisted');
});
