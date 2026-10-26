import { test, expect } from '../shared/fixtures.mjs';
import { fixture } from '../shared/environment.mjs';
import { publishedPopup, openCreateDialog, addEntry, editSettings, visitor, popupRoot, staysClosed, isOpen } from '../shared/popups.mjs';

const close = (page, id) => popupRoot(page, id).getByRole('button', { name: 'Close', exact: true }).click();

test('elemacy:journey:trigger:page_load @popups', async ({ page, author, browser }) => {
  const title = `Load trigger ${author.user}`;
  const id = await publishedPopup(page, { title, type: 'Floating Element', triggers: [{ label: 'On Page Load', fields: [{ label: 'Delay (seconds)', value: 2 }] }] });
  const dialog = await editSettings(page, title, 'Triggers');
  await expect(dialog.getByRole('combobox').filter({ hasText: 'On Page Load' })).toBeVisible();
  await expect(dialog.getByText('Delay (seconds)', { exact: true }).locator('xpath=following::input[1]')).toHaveValue('2');

  const guest = await visitor(browser);
  try {
    await guest.page.goto(new URL('/', page.url()).href);
    await guest.page.waitForTimeout(800);
    expect(await popupRoot(guest.page, id).count(), 'The popup is on the page').toBe(1);
    expect(await isOpen(guest.page, id), 'Not yet: the delay has not passed').toBe(false);
    await expect.poll(() => isOpen(guest.page, id), { timeout: 8000 }).toBe(true);
    await close(guest.page, id);
    await staysClosed(guest.page, id, 3000);
    await guest.page.reload();
    await expect.poll(() => isOpen(guest.page, id), { timeout: 8000 }).toBe(true); // the next page view opens it again
  } finally { await guest.context.close(); }
});

test('elemacy:journey:trigger:click @popups', async ({ page, author, browser }) => {
  const title = `Click trigger ${author.user}`;
  const id = await publishedPopup(page, { title, type: 'Floating Element', triggers: [{ label: 'On Click', fields: [{ label: 'CSS selector', value: '.journey-open' }] }] });
  const { page: host } = fixture('seed-host-page', author.manifest);

  const guest = await visitor(browser);
  try {
    await guest.page.goto(host.url);
    await guest.page.waitForTimeout(500);
    expect(await isOpen(guest.page, id), 'A click trigger does not open on load').toBe(false);
    // Scrolled down, then clicked without the test scrolling it into view: the only thing that could move the
    // page is the link's own default action.
    await guest.page.evaluate(() => window.scrollTo(0, 300));
    const before = { url: guest.page.url(), scroll: await guest.page.evaluate(() => window.scrollY) };
    await guest.page.locator('.journey-open a').evaluate(link => link.click());
    await expect.poll(() => isOpen(guest.page, id)).toBe(true);
    expect(guest.page.url(), 'A button linked "#" does not change the address').toBe(before.url);
    expect(await guest.page.evaluate(() => window.scrollY), 'Nor does it jump to the top').toBe(before.scroll);
    await close(guest.page, id);
    await staysClosed(guest.page, id);
    await guest.page.locator('.journey-open a').click();
    await expect.poll(() => isOpen(guest.page, id), { message: 'A click opens it again' }).toBe(true);
    // An unrelated link does not.
    await close(guest.page, id);
    await expect(popupRoot(guest.page, id)).toBeHidden();
  } finally { await guest.context.close(); }
});

test('elemacy:journey:trigger:open-link @popups', async ({ page, author, browser }) => {
  const title = `Open link ${author.user}`;
  // Its own trigger never matches, so only the link can open it.
  const id = await publishedPopup(page, { title, type: 'Floating Element', triggers: [{ label: 'On Click', fields: [{ label: 'CSS selector', value: '.journey-never' }] }] });
  const { page: host } = fixture('seed-host-page', author.manifest, id);

  const guest = await visitor(browser);
  try {
    await guest.page.goto(host.url);
    await guest.page.waitForTimeout(500);
    expect(await isOpen(guest.page, id)).toBe(false);
    const url = guest.page.url();
    await guest.page.locator('.journey-missing a').click();
    await guest.page.waitForTimeout(300);
    expect(await isOpen(guest.page, id), 'A link to a popup that is not on the page opens nothing').toBe(false);
    expect(guest.page.url()).toBe(url);
    await guest.page.locator('.journey-link a').click();
    await expect.poll(() => isOpen(guest.page, id)).toBe(true);
    expect(guest.page.url(), 'The popup link leaves the address alone').toBe(url);
    await close(guest.page, id);
    await staysClosed(guest.page, id);
  } finally { await guest.context.close(); }
});

test('elemacy:journey:trigger:rules-warning @popups @pro', async ({ page, author }) => {
  const title = `Contradictory rules ${author.user}`;
  const dialog = await openCreateDialog(page, { title, type: 'Popup' });
  const warning = dialog.getByTestId('popup-rules-never-shows');
  await addEntry(page, dialog, 'rule', { label: 'Hide for logged-in users' });
  await addEntry(page, dialog, 'rule', { label: 'By user role' });
  await expect(warning, 'A role rule with no role chosen restricts nothing').toHaveCount(0);
  await dialog.getByRole('tabpanel').getByRole('checkbox', { name: 'Subscriber', exact: true }).check();
  await expect(warning).toBeVisible();
  await expect(warning).toContainText('will never display');
  await dialog.getByRole('tabpanel').getByRole('checkbox', { name: 'Subscriber', exact: true }).uncheck();
  await expect(warning).toHaveCount(0);
  await dialog.getByRole('tabpanel').getByRole('checkbox', { name: 'Subscriber', exact: true }).check();
  await expect(warning).toBeVisible();

  // It is a warning, not a validation: the popup still saves.
  await dialog.getByRole('button', { name: 'Create Popup', exact: true }).click();
  await expect(dialog).not.toBeVisible();
  await expect(page.locator('[data-slot="card"]').filter({ has: page.getByText(title, { exact: true }) })).toBeVisible();
});

test('elemacy-pro:journey:trigger:scroll @popups @pro', async ({ page, author, browser }) => {
  const id = await publishedPopup(page, { title: `Scroll trigger ${author.user}`, type: 'Floating Element', triggers: [{ label: 'On Scroll', fields: [{ label: 'Scroll % (%)', value: 40 }] }] });
  const { page: host } = fixture('seed-host-page', author.manifest);
  const guest = await visitor(browser);
  try {
    await guest.page.goto(host.url);
    await guest.page.waitForTimeout(500);
    expect(await isOpen(guest.page, id)).toBe(false);
    await guest.page.evaluate(() => window.scrollTo(0, (document.documentElement.scrollHeight - innerHeight) * 0.2));
    await guest.page.waitForTimeout(500);
    expect(await isOpen(guest.page, id), 'Below 40% nothing happens').toBe(false);
    await guest.page.evaluate(() => window.scrollTo(0, (document.documentElement.scrollHeight - innerHeight) * 0.6));
    await expect.poll(() => isOpen(guest.page, id)).toBe(true);
    await close(guest.page, id);
    await guest.page.evaluate(() => window.scrollTo(0, 0));
    await guest.page.evaluate(() => window.scrollTo(0, document.documentElement.scrollHeight));
    await staysClosed(guest.page, id);
  } finally { await guest.context.close(); }
});

test('elemacy-pro:journey:trigger:scroll_to_element @popups @pro', async ({ page, author, browser }) => {
  const id = await publishedPopup(page, { title: `Element trigger ${author.user}`, type: 'Floating Element', triggers: [{ label: 'On Scroll To Element', fields: [{ label: 'CSS selector', value: '#journey-target' }] }] });
  const { page: host } = fixture('seed-host-page', author.manifest);
  const guest = await visitor(browser);
  try {
    await guest.page.goto(host.url);
    await guest.page.waitForTimeout(500);
    expect(await isOpen(guest.page, id), 'The element is far below the fold').toBe(false);
    await guest.page.locator('#journey-target').scrollIntoViewIfNeeded();
    await expect.poll(() => isOpen(guest.page, id)).toBe(true);
    await close(guest.page, id);
    await guest.page.evaluate(() => window.scrollTo(0, 0));
    await guest.page.locator('#journey-target').scrollIntoViewIfNeeded();
    await staysClosed(guest.page, id);
  } finally { await guest.context.close(); }
});

test('elemacy-pro:journey:trigger:exit_intent @popups @pro', async ({ page, author, browser }) => {
  const id = await publishedPopup(page, { title: `Exit trigger ${author.user}`, type: 'Floating Element', triggers: [{ label: 'On Exit Intent' }] });
  const guest = await visitor(browser);
  try {
    await guest.page.goto(new URL('/', page.url()).href);
    await guest.page.mouse.move(400, 400);
    await guest.page.mouse.move(400, 200);
    await guest.page.waitForTimeout(400);
    expect(await isOpen(guest.page, id), 'Moving about the page is not leaving it').toBe(false);
    // The pointer leaves through the top edge.
    await guest.page.mouse.move(400, 0);
    await guest.page.evaluate(() => document.dispatchEvent(new MouseEvent('mouseout', { clientY: -4, relatedTarget: null, bubbles: true })));
    await expect.poll(() => isOpen(guest.page, id)).toBe(true);
    await close(guest.page, id);
    await guest.page.evaluate(() => document.dispatchEvent(new MouseEvent('mouseout', { clientY: -4, relatedTarget: null, bubbles: true })));
    await staysClosed(guest.page, id);
  } finally { await guest.context.close(); }
});

test('elemacy-pro:journey:trigger:inactivity @popups @pro', async ({ page, author, browser }) => {
  const id = await publishedPopup(page, { title: `Inactivity trigger ${author.user}`, type: 'Floating Element', triggers: [{ label: 'After Inactivity', fields: [{ label: 'Inactivity (seconds)', value: 2 }] }] });
  const guest = await visitor(browser);
  try {
    await guest.page.goto(new URL('/', page.url()).href);
    for (let i = 0; i < 6; i++) { await guest.page.mouse.move(100 + i * 40, 200); await guest.page.waitForTimeout(500); }
    expect(await isOpen(guest.page, id), 'A visitor who keeps moving is not inactive').toBe(false);
    await expect.poll(() => isOpen(guest.page, id), { timeout: 6000 }).toBe(true);
    await close(guest.page, id);
    await staysClosed(guest.page, id, 3000);
  } finally { await guest.context.close(); }
});

test('elemacy-pro:journey:trigger:on_popup_close @popups @pro', async ({ page, author, browser }) => {
  const other = await publishedPopup(page, { title: `Other popup ${author.user}`, type: 'Floating Element', triggers: [{ label: 'On Page Load' }] });
  const source = await publishedPopup(page, { title: `Source popup ${author.user}`, type: 'Floating Element', triggers: [{ label: 'On Page Load' }] });
  const title = `Chained popup ${author.user}`;
  const chained = await publishedPopup(page, {
    title, type: 'Floating Element',
    triggers: [{ label: 'After Another Popup Closes', selects: [{ label: 'After this popup closes', option: `Source popup ${author.user} (#${source})` }] }],
  });
  const dialog = await editSettings(page, title, 'Triggers');
  await expect(dialog.getByText('After this popup closes', { exact: true }).locator('xpath=following::button[@role="combobox"][1]')).toContainText(`Source popup ${author.user}`);

  const guest = await visitor(browser);
  try {
    await guest.page.goto(new URL('/', page.url()).href);
    await expect.poll(async () => (await isOpen(guest.page, other)) && (await isOpen(guest.page, source))).toBe(true);
    expect(await isOpen(guest.page, chained), 'Waiting for its source').toBe(false);
    await close(guest.page, other);
    await guest.page.waitForTimeout(600);
    expect(await isOpen(guest.page, chained), 'Another popup closing is not its source').toBe(false);
    await close(guest.page, source);
    await expect.poll(() => isOpen(guest.page, chained)).toBe(true);
    await close(guest.page, chained);
    await staysClosed(guest.page, chained);
  } finally { await guest.context.close(); }
});
