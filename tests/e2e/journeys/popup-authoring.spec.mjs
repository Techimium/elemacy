import { test, expect } from '../shared/fixtures.mjs';
import { readyEditor, addHeading, saveDraft, saveEditor } from '../shared/editor.mjs';
import { templateCard } from '../shared/templates.mjs';

test('elemacy:journey:popup:modal-authoring @popups @smoke', async ({ page, author, browser, checkpoint }) => {
  const title = `Browser popup ${author.user}`;
  const text = `Human-authored popup ${author.user}`;
  await page.goto('/wp-admin/admin.php?page=elemacy#/popups');
  await page.getByRole('button', { name: 'Create New Popup', exact: true }).click();
  const dialog = page.getByRole('dialog');
  await dialog.getByRole('textbox', { name: 'Title', exact: true }).fill(title);
  await dialog.getByRole('button', { name: 'Create & Edit with Elementor', exact: true }).click();
  await page.waitForURL(url => url.searchParams.get('action') === 'elementor');
  const id = new URL(page.url()).searchParams.get('post');
  const guest = await browser.newContext();
  try {
    const visitor = await guest.newPage();
    const popup = visitor.locator('[data-elemacy-popup-id="' + id + '"]');
    await addHeading(page, text);
    await checkpoint('popup-authored');
    // A draft with content is what a publishing mistake would expose, so the visitor check
    // waits until the popup has content and is saved, still unpublished.
    await saveDraft(page);
    await visitor.goto(new URL('/', page.url()).href);
    await expect(popup, 'A draft popup, content and all, must not be delivered to visitors').toHaveCount(0);
    await expect(visitor.getByText(text, { exact: true }), 'Nor its content, even outside the popup root').toHaveCount(0);
    await saveEditor(page);
    await page.reload();
    const frame = await readyEditor(page);
    await expect(frame.getByText(text, { exact: true })).toBeVisible();
    await checkpoint('popup-saved-and-reopened');
    await visitor.reload();
    await expect(popup).toBeVisible();
    await expect(popup).toHaveAttribute('aria-hidden', 'false');
    await expect(popup.getByText(text, { exact: true })).toBeVisible();
    await expect(popup.locator('.elemacy-popup__overlay')).toHaveCount(1);
    const close = popup.getByRole('button', { name: 'Close', exact: true });
    await expect(close).toBeVisible();
    await expect(close).toBeFocused();
    await checkpoint('visitor-popup-open', visitor);
    await close.click();
    await expect(popup).toBeHidden();
    await expect(popup).toHaveAttribute('aria-hidden', 'true');
    await visitor.reload();
    await expect(popup).toBeVisible();
    await expect(popup.getByText(text, { exact: true })).toBeVisible();
    await visitor.keyboard.press('Escape');
    await expect(popup).toBeHidden();
    await checkpoint('visitor-popup-closed', visitor);
    await page.goto('/wp-admin/admin.php?page=elemacy#/popups');
    await templateCard(page, title).getByRole('button', { name: 'Actions', exact: true }).click();
    await page.getByRole('menuitem', { name: 'Edit', exact: true }).click();
    await expect(page.getByRole('dialog').getByRole('combobox', { name: 'Status', exact: true })).toHaveText('Published');
    await checkpoint('popup-publication-persisted');
    // A new popup starts with one Page Load trigger, saved like one the author added.
    const settings = page.getByRole('dialog');
    await settings.getByRole('tab', { name: 'Triggers' }).click();
    await expect(settings.getByRole('button', { name: 'Remove trigger', exact: true }), 'Exactly one trigger').toHaveCount(1);
    await expect(settings.getByRole('tabpanel').getByRole('combobox').first()).toHaveText('On Page Load');
    await checkpoint('popup-default-trigger-persisted');
  } finally { await guest.close(); }
});
