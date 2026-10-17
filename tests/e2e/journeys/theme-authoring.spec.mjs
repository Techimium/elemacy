import { test, expect } from '../shared/fixtures.mjs';
import { readyEditor, addHeading, saveEditor, setHeadingColor } from '../shared/editor.mjs';
import { createTemplate, openTemplateSettings, templateCard, themeBuilderUrl } from '../shared/templates.mjs';

for (const type of ['Header', 'Footer']) {
  const slug = type.toLowerCase();
  test(`elemacy:journey:template:${slug} @theme-builder @smoke`, async ({ page, author, checkpoint, browser }) => {
    const title = `Journey ${slug} ${author.user}`;
    const text = `Human-authored ${slug} ${author.user}`;
    await createTemplate(page, { title, type, edit: true });
    const frame = await readyEditor(page);
    await checkpoint('editor-opened');
    await addHeading(page, text);
    await setHeadingColor(page, '#123456');
    await expect(frame.getByText(text, { exact: true })).toHaveCSS('color', 'rgb(18, 52, 86)');
    await checkpoint('heading-authored');
    await saveEditor(page);
    await page.reload();
    await expect(frame.getByText(text, { exact: true })).toBeVisible({ timeout: 60000 });
    await expect(frame.getByText(text, { exact: true })).toHaveCSS('color', 'rgb(18, 52, 86)');
    await checkpoint('saved-and-reopened');
    const guest = await browser.newContext();
    try {
      const visitor = await guest.newPage();
      await visitor.goto(new URL('/', page.url()).href);
      await expect(visitor.getByText(text, { exact: true })).toBeVisible();
      await expect(visitor.getByText(text, { exact: true })).toHaveCSS('color', 'rgb(18, 52, 86)');
      await expect(visitor.locator(`[data-elementor-type="elemacy_${slug}"]`)).toHaveCount(1);
      await checkpoint('visitor-rendered', visitor);
      // The theme's own chrome: a real theme's `.site-header`/`.site-footer`, or the harness fixture theme's id.
      const nativeChrome = part => visitor.locator(`.site-${part}, #fixture-native-${part}`);
      await expect(nativeChrome(slug), 'Replaced theme chrome must not be duplicated').toHaveCount(0);
      await expect(nativeChrome(slug === 'header' ? 'footer' : 'header'), 'The opposite theme chrome must remain').toHaveCount(1);
    } finally {
      await guest.close();
    }
    await page.goto(themeBuilderUrl);
    const dialog = await openTemplateSettings(page, title);
    await expect(dialog.getByRole('textbox', { name: 'Title', exact: true })).toHaveValue(title);
    await expect(dialog.getByRole('combobox', { name: 'Type', exact: true })).toHaveText(type);
    await checkpoint('template-settings-persisted');
    await dialog.getByRole('button', { name: 'Close', exact: true }).click();
    await expect(templateCard(page, title)).toBeVisible();
  });
}
