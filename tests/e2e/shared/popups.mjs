import { expect } from './fixtures.mjs';
import { readyEditor, addHeading, saveEditor, dismissPromotions } from './editor.mjs';
import { templateCard } from './templates.mjs';

export const popupsUrl = '/wp-admin/admin.php?page=elemacy#/popups';

/**
 * Adds one trigger or rule in the popup dialog, as a person would. `entry`:
 *   label    the type as the select lists it ("On Page Load", "Show up to X times", …)
 *   fields   [{ label, value }] text and number inputs, found by the label printed above them
 *   selects  [{ label, option }] select parameters, found the same way
 *   check / uncheck   checkbox labels to tick or clear
 */
export async function addEntry(page, dialog, kind, entry) {
  const trigger = kind === 'trigger';
  await dialog.getByRole('tab', { name: trigger ? 'Triggers' : 'Advanced Rules' }).click();
  await dialog.getByRole('button', { name: trigger ? '+ Add Trigger' : '+ Add Rule' }).click();
  await dialog.getByRole('combobox').filter({ hasText: trigger ? 'Select trigger' : 'Select rule' }).click();
  await page.getByRole('option', { name: entry.label, exact: true }).click();
  const panel = dialog.getByRole('tabpanel');
  for (const field of entry.fields ?? []) {
    await panel.getByText(field.label, { exact: true }).last().locator('xpath=following::input[1]').fill(String(field.value));
  }
  for (const select of entry.selects ?? []) {
    await panel.getByText(select.label, { exact: true }).last().locator('xpath=following::button[@role="combobox"][1]').click();
    await page.getByRole('option', { name: select.option, exact: true }).click();
  }
  for (const name of entry.check ?? []) await panel.getByRole('checkbox', { name, exact: true }).check();
  for (const name of entry.uncheck ?? []) await panel.getByRole('checkbox', { name, exact: true }).uncheck();
}

/** Opens the create dialog with a title and type chosen; the caller adds entries and submits. */
export async function openCreateDialog(page, { title, type = 'Popup' }) {
  await page.goto(popupsUrl);
  await page.getByRole('button', { name: 'Create New Popup', exact: true }).click();
  const dialog = page.getByRole('dialog');
  await dialog.getByRole('textbox', { name: 'Title', exact: true }).fill(title);
  await dialog.getByRole('button', { name: new RegExp('^' + type.replace(/[.*+?^${}()|[\]\\/]/g, '\\$&') + '( |$)') }).click();
  return dialog;
}

/**
 * Creates a popup of the given type, with its triggers and rules, through the admin dialog and lands in the
 * Elementor editor, as a person would. `type` is the type's button label as the dialog shows it.
 */
export async function createPopup(page, { title, type = 'Popup', triggers = [], rules = [] }) {
  const dialog = await openCreateDialog(page, { title, type });
  for (const entry of triggers) await addEntry(page, dialog, 'trigger', entry);
  for (const entry of rules) await addEntry(page, dialog, 'rule', entry);
  await dialog.getByRole('button', { name: 'Create & Edit with Elementor', exact: true }).click();
  await page.waitForURL(url => url.searchParams.get('action') === 'elementor');
  return new URL(page.url()).searchParams.get('post');
}

/** Creates a popup, gives it content, and publishes it. Returns its id. */
export async function publishedPopup(page, options) {
  const id = await createPopup(page, options);
  await addHeading(page, options.text ?? `Journey popup ${options.title}`);
  // Some types ship without a close button; a visitor needs one to dismiss the popup.
  await openPopupSettings(page);
  await enableCloseButton(page);
  await saveEditor(page);
  return id;
}

/** Opens the document's own settings panel in the Elementor editor, where the popup controls live. */
export async function openPopupSettings(page) {
  // Not readyEditor(): once a widget is selected the panel no longer shows the elements search.
  await expect(page.locator('#elementor-loading')).toBeHidden({ timeout: 30000 });
  await dismissPromotions(page);
  await page.getByRole('button', { name: /Settings/ }).first().click();
  await expect(page.getByRole('combobox', { name: 'Position', exact: true })).toBeVisible();
}

export async function choose(page, label, value) {
  await page.getByRole('combobox', { name: label, exact: true }).selectOption(value);
}

export async function setNumber(page, label, value) {
  const field = page.getByRole('spinbutton', { name: label, exact: true });
  await field.fill(String(value));
  await field.press('Tab');
}

/** Turns the document's Close Button switch on (a Floating Element ships without one). */
export async function enableCloseButton(page) {
  const input = page.locator('input[data-setting="elemacy_close_button"]');
  if (!(await input.isChecked())) await page.locator('.elementor-control-elemacy_close_button .elementor-switch').click();
  await expect(input).toBeChecked();
}

/** Gives a new popup some content and publishes it. */
export async function authorAndPublish(page, text) {
  await addHeading(page, text);
  await openPopupSettings(page);
}

export async function publish(page) {
  await saveEditor(page);
}

/** A signed-out visitor in a fresh browser context; records the names of the CSS animations that start. */
export async function visitor(browser, { viewport = { width: 1440, height: 1000 } } = {}) {
  const context = await browser.newContext({ viewport });
  const page = await context.newPage();
  await page.addInitScript(() => {
    window.__animations = [];
    document.addEventListener('animationstart', event => window.__animations.push(event.animationName), true);
  });
  return { context, page };
}

export const popupRoot = (page, id) => page.locator(`[data-elemacy-popup-id="${id}"]`);

/** The popup box's rectangle in the viewport, and the viewport itself. */
export async function geometry(page, id) {
  // Once its entrance has finished: a sliding popup is somewhere else on the way in.
  await expect(popupRoot(page, id)).toHaveClass(/is-open/);
  await expect(popupRoot(page, id)).not.toHaveClass(/is-animating/);
  const box = await popupRoot(page, id).locator('.elemacy-popup__box').boundingBox();
  expect(box, `popup ${id} must be on screen`).not.toBeNull();
  const viewport = page.viewportSize();
  return { ...box, right: box.x + box.width, bottom: box.y + box.height, viewport };
}

export const near = (actual, expected, tolerance = 2) => Math.abs(actual - expected) <= tolerance;

/** Opens a saved popup's settings dialog from the list, as a person would, on the tab given. */
export async function editSettings(page, title, tab) {
  await page.goto(popupsUrl);
  await templateCard(page, title).getByRole('button', { name: 'Actions', exact: true }).click();
  await page.getByRole('menuitem', { name: 'Edit', exact: true }).click();
  const dialog = page.getByRole('dialog');
  await dialog.getByRole('tab', { name: tab }).click();
  return dialog;
}

/** Waits for a popup to be hidden and stay hidden: a dismissed popup does not come back on its own. */
export async function staysClosed(page, id, ms = 1500) {
  await expect(popupRoot(page, id)).toBeHidden();
  await page.waitForTimeout(ms);
  await expect(popupRoot(page, id)).toBeHidden();
}

export const isOpen = (page, id) => popupRoot(page, id).evaluate(root => root.classList.contains('is-open'));

/** A browser context for a signed-in fixture user (see the `seed-user` fixture command). */
export async function signedIn(browser, cookies, options = {}) {
  const { environment } = await import('./environment.mjs');
  const context = await browser.newContext({ viewport: { width: 1440, height: 1000 }, ...options });
  await context.addCookies(cookies.map(cookie => ({ ...cookie, url: environment().url, httpOnly: true })));
  return context;
}

/**
 * Replaces a saved popup's rules through the admin app's own endpoint, for the one input the dialog makes
 * impractical to drive by hand (calendar pickers). Everything else is authored through the dialog.
 */
export async function setRules(page, id, rules) {
  await page.goto(popupsUrl);
  const status = await page.evaluate(async ({ id, rules }) => {
    const headers = { 'X-WP-Nonce': window.elemacy.nonce, 'Content-Type': 'application/json' };
    const current = (await (await fetch(`${window.elemacy.api_base.replace(/\/$/, '')}/popups/${id}`, { headers })).json()).data;
    const body = { id, title: current.title, type: current.type, status: current.status, conditions: current.conditions ?? [], triggers: current.triggers ?? [], rules };
    return (await fetch(`${window.elemacy.api_base.replace(/\/$/, '')}/popups/${id}`, { method: 'PUT', headers, body: JSON.stringify(body) })).status;
  }, { id: Number(id), rules });
  expect(status, 'The admin endpoint accepts the rules').toBe(200);
}
