import { execFileSync } from 'node:child_process';
import { test, expect } from '../shared/fixtures.mjs';
import { fixture, environment } from '../shared/environment.mjs';
import { publishedPopup, editSettings, visitor, popupRoot, staysClosed, isOpen, signedIn, setRules } from '../shared/popups.mjs';

const home = page => new URL('/', page.url()).href;
const present = (page, id) => popupRoot(page, id).count();
const opens = async (page, id) => { await page.waitForTimeout(700); return isOpen(page, id).catch(() => false); };
const floating = (title, rules) => ({ title, type: 'Floating Element', rules });
const pad = n => String(n).padStart(2, '0');
const wall = date => `${date.getUTCFullYear()}-${pad(date.getUTCMonth() + 1)}-${pad(date.getUTCDate())}T${pad(date.getUTCHours())}:${pad(date.getUTCMinutes())}`;
const day = 24 * 3600 * 1000;

test('elemacy:journey:rule:frequency_cap @popups', async ({ page, author, browser }) => {
  const title = `Cap rule ${author.user}`;
  const id = await publishedPopup(page, floating(title, [{ label: 'Show up to X times', fields: [{ label: 'Maximum displays', value: 2 }] }]));
  const dialog = await editSettings(page, title, 'Advanced Rules');
  await expect(dialog.getByText('Maximum displays', { exact: true }).locator('xpath=following::input[1]')).toHaveValue('2');

  const guest = await visitor(browser);
  try {
    await guest.page.goto(home(page));
    expect(await opens(guest.page, id), 'First view').toBe(true);
    await guest.page.reload();
    expect(await opens(guest.page, id), 'Second view').toBe(true);
    await guest.page.reload();
    expect(await present(guest.page, id), 'It is in the page').toBe(1);
    expect(await opens(guest.page, id), 'Third view: the cap is reached').toBe(false);
  } finally { await guest.context.close(); }
});

test('elemacy:journey:rule:logged_in @popups', async ({ page, author, browser }) => {
  const title = `Guests only ${author.user}`;
  const id = await publishedPopup(page, floating(title, [{ label: 'Hide for logged-in users' }]));
  await editSettings(page, title, 'Advanced Rules').then(dialog => expect(dialog.getByRole('combobox').filter({ hasText: 'Hide for logged-in users' })).toBeVisible());

  const guest = await visitor(browser);
  try {
    await guest.page.goto(home(page));
    expect(await opens(guest.page, id), 'A guest sees it').toBe(true);
  } finally { await guest.context.close(); }
  await page.goto(home(page));
  expect(await present(page, id), 'A signed-in visitor gets no popup at all').toBe(0);
});

test('elemacy-pro:journey:rule:once_per @popups @pro', async ({ page, author, browser }) => {
  const session = await publishedPopup(page, floating(`Once per session ${author.user}`, [{ label: 'Show once per', selects: [{ label: 'period', option: 'Session' }] }]));
  const daily = await publishedPopup(page, floating(`Once per day ${author.user}`, [{ label: 'Show once per', selects: [{ label: 'period', option: 'Day' }] }]));

  const guest = await visitor(browser);
  try {
    await guest.page.clock.install({ time: new Date('2031-03-01T10:00:00Z') });
    await guest.page.goto(home(page));
    expect(await opens(guest.page, session) && await isOpen(guest.page, daily), 'Both open on the first visit').toBe(true);
    await guest.page.reload();
    expect(await opens(guest.page, session), 'Same session: not again').toBe(false);
    expect(await isOpen(guest.page, daily), 'Same day: not again').toBe(false);

    const second = await guest.context.newPage(); // a new tab is a new session
    await second.clock.install({ time: new Date('2031-03-01T11:00:00Z') });
    await second.goto(home(page));
    expect(await opens(second, session), 'A new session shows the per-session popup again').toBe(true);
    expect(await isOpen(second, daily), 'An hour later is still the same day').toBe(false);

    const third = await guest.context.newPage();
    await third.clock.install({ time: new Date('2031-03-03T11:00:00Z') });
    await third.goto(home(page));
    expect(await opens(third, daily), 'Two days later it shows again').toBe(true);
  } finally { await guest.context.close(); }
});

test('elemacy-pro:journey:rule:devices @popups @pro', async ({ page, author, browser }) => {
  const id = await publishedPopup(page, floating(`Phones only ${author.user}`, [{ label: 'Show on devices', uncheck: ['Desktop', 'Tablet'] }]));
  const desktop = await visitor(browser);
  const phone = await visitor(browser, { viewport: { width: 390, height: 800 } });
  try {
    await desktop.page.goto(home(page));
    expect(await opens(desktop.page, id), 'Not on a desktop viewport').toBe(false);
    await phone.page.goto(home(page));
    expect(await opens(phone.page, id), 'On a phone viewport').toBe(true);
  } finally { await desktop.context.close(); await phone.context.close(); }
});

test('elemacy-pro:journey:rule:browser @popups @pro', async ({ page, author, browser }) => {
  const firefox = await publishedPopup(page, floating(`Firefox only ${author.user}`, [{ label: 'By browser', check: ['Firefox'] }]));
  const chrome = await publishedPopup(page, floating(`Chrome only ${author.user}`, [{ label: 'By browser', check: ['Chrome'] }]));
  const usual = await visitor(browser);
  const gecko = await browser.newContext({ userAgent: 'Mozilla/5.0 (X11; Linux x86_64; rv:130.0) Gecko/20100101 Firefox/130.0' });
  try {
    await usual.page.goto(home(page));
    expect(await opens(usual.page, chrome), 'Chrome user agent matches the Chrome rule').toBe(true);
    expect(await isOpen(usual.page, firefox), 'And not the Firefox one').toBe(false);
    const other = await gecko.newPage();
    await other.goto(home(page));
    expect(await opens(other, firefox), 'A Firefox user agent matches the Firefox rule').toBe(true);
    expect(await isOpen(other, chrome)).toBe(false);
  } finally { await usual.context.close(); await gecko.close(); }
});

test('elemacy-pro:journey:rule:referrer @popups @pro', async ({ page, author, browser }) => {
  const { page: host } = fixture('seed-host-page', author.manifest);
  const id = await publishedPopup(page, floating(`From the host ${author.user}`, [{ label: 'Arriving from URL', fields: [{ label: 'Referrer URL', value: host.url }] }]));
  const guest = await visitor(browser);
  try {
    await guest.page.goto(home(page));
    expect(await opens(guest.page, id), 'Typed in directly: no referrer').toBe(false);
    await guest.page.goto(host.url);
    await guest.page.locator('a.journey-home').click();
    await guest.page.waitForURL(url => url.pathname === '/' && !url.searchParams.has('page_id'));
    expect(await opens(guest.page, id), 'Arrived by following a link from the host page').toBe(true);
  } finally { await guest.context.close(); }
});

test('elemacy-pro:journey:rule:page_views @popups @pro', async ({ page, author, browser }) => {
  const id = await publishedPopup(page, floating(`Third view ${author.user}`, [{ label: 'After X page views', fields: [{ label: 'Page views', value: 3 }] }]));
  const { page: host } = fixture('seed-host-page', author.manifest);
  const guest = await visitor(browser);
  try {
    await guest.page.goto(home(page));
    expect(await opens(guest.page, id), 'View 1').toBe(false);
    await guest.page.goto(host.url);
    expect(await opens(guest.page, id), 'View 2').toBe(false);
    await guest.page.goto(home(page));
    expect(await opens(guest.page, id), 'View 3').toBe(true);
  } finally { await guest.context.close(); }
});

test('elemacy-pro:journey:rule:sessions @popups @pro', async ({ page, author, browser }) => {
  const id = await publishedPopup(page, floating(`Second session ${author.user}`, [{ label: 'After X sessions', fields: [{ label: 'Sessions', value: 2 }] }]));
  const guest = await visitor(browser);
  try {
    await guest.page.goto(home(page));
    await guest.page.reload();
    expect(await opens(guest.page, id), 'A reload is still the first session').toBe(false);
    const second = await guest.context.newPage();
    await second.goto(home(page));
    expect(await opens(second, id), 'A new tab with the same storage is the second session').toBe(true);
  } finally { await guest.context.close(); }
});

test('elemacy-pro:journey:rule:schedule @popups @pro', async ({ page, author, browser }) => {
  const ended = await publishedPopup(page, floating(`Ended campaign ${author.user}`, []));
  const running = await publishedPopup(page, floating(`Running campaign ${author.user}`, []));
  const future = await publishedPopup(page, floating(`Future campaign ${author.user}`, []));
  const now = Date.now();
  const rule = (start, end) => [{ id: 'rule_schedule', type: 'schedule', params: { start: start ? wall(new Date(now + start * day)) : '', end: end ? wall(new Date(now + end * day)) : '' } }];
  await setRules(page, ended, rule(-7, -2));
  await setRules(page, running, rule(-1, 1));
  await setRules(page, future, rule(2, 4));

  const dialog = await editSettings(page, `Future campaign ${author.user}`, 'Advanced Rules');
  await expect(dialog.getByRole('combobox').filter({ hasText: 'Between dates' }), 'The saved schedule shows in the editor').toBeVisible();

  const guest = await visitor(browser);
  try {
    const response = await guest.page.goto(home(page));
    const source = await response.text();
    expect(source, 'An ended campaign is not in the page source').not.toContain(`data-elemacy-popup-id="${ended}"`);
    expect(source, 'A campaign yet to start is, for the browser to hold back').toContain(`data-elemacy-popup-id="${future}"`);
    expect(await opens(guest.page, running), 'A running campaign shows').toBe(true);
    expect(await isOpen(guest.page, future), 'The future one waits').toBe(false);
  } finally { await guest.context.close(); }

  // Three days on (a controlled clock), the future campaign has started.
  const later = await visitor(browser);
  try {
    await later.page.clock.install({ time: new Date(now + 3 * day) });
    await later.page.goto(home(page));
    expect(await opens(later.page, future), 'Once its start has passed it shows').toBe(true);
  } finally { await later.context.close(); }
});

test('elemacy-pro:journey:rule:roles @popups @pro', async ({ page, author, browser }) => {
  const id = await publishedPopup(page, floating(`Subscribers ${author.user}`, [{ label: 'By user role', check: ['Subscriber'] }]));
  const subscriber = fixture('seed-user', author.manifest, 'subscriber');
  const editor = fixture('seed-user', author.manifest, 'editor');
  const guest = await visitor(browser);
  const asSubscriber = await signedIn(browser, subscriber.cookies);
  const asEditor = await signedIn(browser, editor.cookies);
  try {
    await guest.page.goto(home(page));
    expect(await present(guest.page, id), 'A guest is not a subscriber').toBe(0);
    const s = await asSubscriber.newPage();
    await s.goto(home(page));
    expect(await opens(s, id), 'A subscriber sees it').toBe(true);
    const e = await asEditor.newPage();
    await e.goto(home(page));
    expect(await present(e, id), 'An editor does not').toBe(0);
  } finally { await guest.context.close(); await asSubscriber.close(); await asEditor.close(); }
});

test('elemacy:journey:rule:pro-deactivated @popups @pro', async ({ page, author, browser }) => {
  const title = `Pro rule ${author.user}`;
  const id = await publishedPopup(page, floating(title, [{ label: 'Show on devices', uncheck: ['Tablet'] }]));
  const wp = (...args) => execFileSync('wp', [`--path=${environment().root}`, '--skip-themes', ...args], { env: process.env, encoding: 'utf8', timeout: 60000 });
  const guest = await visitor(browser);
  try {
    await guest.page.goto(home(page));
    expect(await opens(guest.page, id), 'With Pro active it is delivered').toBe(true);
    wp('plugin', 'deactivate', 'elemacy-pro');
    await guest.page.goto(home(page));
    expect(await present(guest.page, id), 'Without Pro its rule cannot run, so it is not delivered at all').toBe(0);
    const dialog = await editSettings(page, title, 'Advanced Rules');
    await expect(dialog.getByRole('combobox').filter({ hasText: 'Show on devices' }), 'The rule is still in the editor').toBeVisible();
    await expect(dialog.getByRole('tabpanel').getByRole('checkbox', { name: 'Tablet', exact: true }), 'With its choices').not.toBeChecked();
    await dialog.getByRole('button', { name: 'Close', exact: true }).click();
  } finally {
    wp('plugin', 'activate', 'elemacy-pro');
    await guest.context.close();
  }
  const again = await visitor(browser);
  try {
    await again.page.goto(home(page));
    expect(await opens(again.page, id), 'With Pro back, it works as before').toBe(true);
  } finally { await again.context.close(); }
});
