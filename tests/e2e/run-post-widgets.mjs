/**
 * Real browser journey for Post Grid and Post Carousel on a custom post type:
 * authoring the card in the Elementor editor, save and reopen, and what visitors
 * get from each image position, Load More, Masonry and the slider.
 */
import assert from 'node:assert/strict';
import { chromium } from 'playwright-core';
import { spawn, execFileSync } from 'node:child_process';
import { writeFileSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { setTimeout as delay } from 'node:timers/promises';

const integration = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../integration');
const php = process.env.ELEMACY_TEST_PHP || 'php';
const phpRun = (...args) => execFileSync(php, args, { encoding: 'utf8', env: process.env, timeout: 90_000 });
const environment = JSON.parse(phpRun('-r',
  'require $argv[1]; echo json_encode(elemacy_test_environment((string) getenv("ELEMACY_TEST_ENV")));',
  path.join(integration, 'environment.php')));
const fixtureScript = path.join(integration, 'post-widgets-browser-fixture.php');
const base = new URL(environment.url);
const report = path.join(path.dirname(environment.root), 'post-widgets-report.json');
const results = [];
const consoleErrors = [];
let fixture;
let server;
let browser;
let page;
const check = (message, condition) => {
  results.push(`${condition ? 'PASS' : 'FAIL'}: ${message}`);
  if (!condition) process.exitCode = 1;
};
const near = (a, b, tolerance = 2) => Math.abs(a - b) <= tolerance;

const cardBoxes = (target, id) => target.evaluate(widgetId => {
  const rect = element => {
    const box = element.getBoundingClientRect();
    return { left: box.left, right: box.right, top: box.top, bottom: box.bottom };
  };
  return [...document.querySelectorAll(`[data-id="${widgetId}"] .elemacy-post-card`)].map(card => ({
    card: rect(card),
    media: rect(card.querySelector('.elemacy-post-card__media')),
    content: rect(card.querySelector('.elemacy-post-card__content')),
  }));
}, id);

const visit = async (target, url) => {
  await target.goto(url, { waitUntil: 'load' });
  await target.waitForTimeout(400);
};

async function imagePositions() {
  await page.setViewportSize({ width: 1400, height: 900 });
  await visit(page, fixture.urls.positions);

  let [box] = await cardBoxes(page, 'fxtopp1');
  check('Top: the image sits above the content', box.media.bottom <= box.content.top + 2);
  [box] = await cardBoxes(page, 'fxlefp1');
  check('Left: the image sits beside and before the content', box.media.right <= box.content.left + 2 && box.media.left < box.content.left);
  [box] = await cardBoxes(page, 'fxrigp1');
  check('Right: the image sits beside and after the content', box.content.right <= box.media.left + 2);
  [box] = await cardBoxes(page, 'fxbacp1');
  check('Background: the image fills the card behind its content',
    near(box.media.left, box.card.left) && near(box.media.right, box.card.right) && near(box.media.top, box.card.top) && near(box.media.bottom, box.card.bottom));
  const background = await page.evaluate(() => {
    const card = document.querySelector('[data-id="fxbacp1"] .elemacy-post-card');
    const bottom = selector => card.querySelector(selector).getBoundingClientRect().bottom;
    return { badge: bottom('.elemacy-post-card__zone--media-top'), titleTop: card.querySelector('.elemacy-post-card__title').getBoundingClientRect().top, button: bottom('.elemacy-post-card__button'), card: card.getBoundingClientRect().bottom };
  });
  check('Background: the content sits at the bottom, clear of an overlay badge', background.badge <= background.titleTop && background.card - background.button < 60);

  await page.setViewportSize({ width: 390, height: 800 });
  await visit(page, fixture.urls.positions);
  [box] = await cardBoxes(page, 'fxlefp1');
  check('A side image with a mobile override stacks on mobile', box.media.bottom <= box.content.top + 2);
  await page.setViewportSize({ width: 1400, height: 900 });
}

async function loadMoreAndAssets() {
  await visit(page, fixture.urls.grid);
  const items = () => page.locator('[data-id="fxgrid1"] [data-elemacy-item]');
  const before = await items().count();
  await page.locator('[data-id="fxgrid1"] a.elemacy-load-more').click();
  await page.waitForFunction(count => document.querySelectorAll('[data-id="fxgrid1"] [data-elemacy-item]').length > count, before, { timeout: 15_000 });
  const ids = await items().evaluateAll(nodes => nodes.map(node => node.getAttribute('data-elemacy-item')));
  check('Load More appends more custom post type cards', ids.length === 4 && await page.locator('[data-id="fxgrid1"] .elemacy-post-card--type-elemacy_fx_book').count() === 4);
  check('Load More repeats no item', new Set(ids).size === ids.length);
  check('The cards keep their custom post type image and terms overlay',
    await page.locator('[data-id="fxgrid1"] .elemacy-post-card__zone--media-top .elemacy-post-card__term').count() === 4);

  const sources = await page.evaluate(() => [...document.querySelectorAll('script[src], link[rel="stylesheet"]')].map(node => node.src || node.href).join(' '));
  check('A grid-only page loads no carousel script or stylesheet', !/loop-carousel|post-carousel/.test(sources));
}

async function masonry() {
  await visit(page, fixture.urls.masonry);
  await page.waitForSelector('.elemacy-post-grid--masonry-ready .elemacy-post-card[style*="span"]');
  const cards = await cardBoxes(page, 'fxmas01');
  check('Masonry keeps the first items in the first row', near(cards[0].card.top, cards[1].card.top) && near(cards[1].card.top, cards[2].card.top));
  check('Masonry closes the vertical gap under a short card', cards[3].card.top < cards[1].card.bottom);
  check('Masonry keeps reading order across a row', cards[0].card.left < cards[1].card.left && cards[1].card.left < cards[2].card.left);

  await visit(page, fixture.urls.masonry_more);
  await page.waitForSelector('.elemacy-post-grid--masonry-ready .elemacy-post-card[style*="span"]');
  await page.locator('[data-id="fxmas02"] a.elemacy-load-more').click();
  await page.waitForFunction(() => document.querySelectorAll('[data-id="fxmas02"] .elemacy-post-card').length === 6, null, { timeout: 15_000 });
  await page.waitForFunction(() => [...document.querySelectorAll('[data-id="fxmas02"] .elemacy-post-card')].every(card => /span/.test(card.style.gridRowEnd)), null, { timeout: 10_000 });
  check('Cards appended to Masonry are placed in the layout', true);

  const noScript = await browser.newContext({ javaScriptEnabled: false, viewport: { width: 1400, height: 900 } });
  const plain = await noScript.newPage();
  await plain.goto(fixture.urls.masonry, { waitUntil: 'load' });
  check('Without its script Masonry shows every card', await plain.locator('[data-id="fxmas01"] .elemacy-post-card').count() === 6);
  check('Without its script Masonry stays a regular grid', await plain.locator('.elemacy-post-grid--masonry-ready').count() === 0);
  await noScript.close();
}

async function carousel() {
  await visit(page, fixture.urls.carousel);
  await page.waitForSelector('[data-id="fxcar01"] .elemacy-loop-carousel.swiper-initialized', { timeout: 15_000 });
  check('The carousel renders every card as a slide', await page.locator('[data-id="fxcar01"] .swiper-slide .elemacy-post-card').count() >= 6);
  const dots = await page.evaluate(() => {
    const root = document.querySelector('[data-id="fxcar01"]');
    return { dotsTop: root.querySelector('.swiper-pagination').getBoundingClientRect().top, cardBottom: root.querySelector('.elemacy-post-card').getBoundingClientRect().bottom };
  });
  check('The carousel dots sit below the cards', dots.dotsTop >= dots.cardBottom - 1);
  const offset = () => page.locator('[data-id="fxcar01"] .swiper-wrapper').evaluate(wrapper => new DOMMatrix(getComputedStyle(wrapper).transform).m41);
  const before = await offset();
  await page.locator('[data-id="fxcar01"] .elemacy-swiper-button-next').click();
  await page.waitForTimeout(800);
  check('The next arrow moves the carousel', await offset() !== before);
  const sources = await page.evaluate(() => [...document.querySelectorAll('script[src]')].map(node => node.src).join(' '));
  check('A carousel page does not load the grid pagination scripts', !/post-grid|loop-incremental/.test(sources));
}

async function links() {
  await visit(page, fixture.urls.linked);
  const card = page.locator('[data-id="fxlnk01"] .elemacy-post-card');
  check('A whole-card link has no nested anchors', await page.locator('[data-id="fxlnk01"] a a').count() === 0);
  const content = await card.locator('.elemacy-post-card__content').boundingBox();
  await Promise.all([page.waitForNavigation(), page.mouse.click(content.x + content.width - 8, content.y + content.height - 8)]);
  check('Clicking the empty area of a linked card opens the post', page.url().includes('elemacy_fx_book'));

  await visit(page, fixture.urls.linked);
  await Promise.all([page.waitForNavigation(), page.locator('[data-id="fxlnk01"] a.elemacy-post-card__term').click()]);
  check('A term link inside a linked card opens the term archive', page.url().includes('elemacy_fx_genre'));

  await visit(page, fixture.urls.linked);
  await Promise.all([page.waitForNavigation(), page.locator('[data-id="fxlnk02"] a.elemacy-post-card__term').click()]);
  check('A term link over a background image opens the term archive', page.url().includes('elemacy_fx_genre'));

  await visit(page, fixture.urls.linked);
  await Promise.all([page.waitForNavigation(), page.locator('[data-id="fxlnk02"] a.elemacy-post-card__button').click()]);
  check('The button of a background card is usable', page.url().includes('elemacy_fx_book'));
}

/** The page size sits in Layout under Columns, and Current Query swaps it for a note. */
async function pageSize(frame) {
  const visibleControls = () => page.evaluate(() => [...document.querySelectorAll('#elementor-panel .elementor-control')]
    .filter(control => control.offsetParent)
    .map(control => (control.className.match(/elementor-control-([a-z0-9_]+)/) || [])[1]));
  const cards = count => frame.waitForFunction(
    expected => document.querySelectorAll('[data-widget_type="elemacy-post-grid.default"] .elemacy-post-card').length === expected,
    count, { timeout: 20_000 });
  const set = settings => page.evaluate(values => $e.run('document/elements/settings', { container: window.postGrid(), settings: values }), settings);

  await page.evaluate(() => {
    if (!document.querySelector('.elementor-control-posts_per_page')?.offsetParent) {
      document.querySelector('.elementor-control-section_layout .elementor-panel-heading').click();
    }
  });
  await page.waitForSelector('.elementor-control-posts_per_page', { state: 'visible', timeout: 15_000 });
  let controls = await visibleControls();
  check('The page size sits in the Layout section directly under Columns',
    controls.includes('section_layout') && controls.indexOf('posts_per_page') === controls.indexOf('columns') + 1);
  check('The page size is labelled Posts Per Page on Post Grid',
    (await page.locator('.elementor-control-posts_per_page .elementor-control-title').textContent()).trim() === 'Posts Per Page');

  await set({ data_source: 'current_query' });
  await page.waitForSelector('.elementor-control-page_size_notice', { state: 'visible', timeout: 15_000 });
  controls = await visibleControls();
  check('Current Query shows a note in place of the page size', !controls.includes('posts_per_page') && controls.includes('page_size_notice'));

  await set({ data_source: 'posts' });
  await page.waitForSelector('.elementor-control-posts_per_page', { state: 'visible', timeout: 15_000 });
  await cards(2);
  await set({ posts_per_page: 3 });
  await cards(3);
  check('Changing the page size in Layout changes the number of cards', true);

  await page.locator('.elementor-control-section_query .elementor-panel-heading').click();
  await page.waitForSelector('.elementor-control-data_source', { state: 'visible', timeout: 15_000 });
  check('The Query section holds no page size', !(await visibleControls()).some(name => /per_page$/.test(name)));
}

async function authoring() {
  const openEditor = async () => {
    await page.goto(fixture.editor_url, { waitUntil: 'load' });
    await page.waitForSelector('#elementor-preview-iframe', { state: 'attached', timeout: 60_000 });
    await page.waitForFunction(() => window.$e && window.elementor && elementor.getPreviewContainer && elementor.getPreviewContainer(), null, { timeout: 60_000 });
    const frame = await (await page.$('#elementor-preview-iframe')).contentFrame();
    await frame.waitForSelector('[data-widget_type="elemacy-post-grid.default"] .elemacy-post-card', { timeout: 60_000 });
    await page.evaluate(() => {
      window.postGrid = () => elementor.getPreviewContainer().findChildrenRecursive(c => c.model.get('widgetType') === 'elemacy-post-grid');
    });
    return frame;
  };

  let frame = await openEditor();
  await page.evaluate(() => {
    const grid = window.postGrid();
    $e.run('panel/editor/open', { model: grid.model, view: grid.view });
  });
  await page.waitForSelector('.elementor-control-section_card', { timeout: 20_000 });
  await pageSize(frame);
  await page.evaluate(() => {
    if (!document.querySelector('.elementor-control-card_parts .elementor-repeater-add')?.offsetParent) {
      document.querySelector('.elementor-control-section_card .elementor-panel-heading').click();
    }
  });
  await page.waitForSelector('.elementor-control-card_parts .elementor-repeater-add', { state: 'visible', timeout: 15_000 });
  const rowsBefore = await page.locator('.elementor-control-card_parts .elementor-repeater-fields').count();
  check('A new widget starts with the default card parts', rowsBefore === 6);

  await page.locator('.elementor-control-card_parts .elementor-repeater-add').click();
  const row = page.locator('.elementor-control-card_parts .elementor-repeater-fields').last();
  await row.locator('select[data-setting="part"]').selectOption('custom_field');
  const before = row.locator('input[data-setting="custom_field_before"]');
  await before.waitFor({ state: 'visible', timeout: 10_000 });
  check('A part row offers no dynamic tags on its text', await row.locator('.elementor-control-custom_field_before .elementor-control-dynamic-switcher').count() === 0);
  await before.fill('Ref: ');
  const field = row.locator('input[data-setting="custom_field_key"]');
  await field.fill('fx_code');
  await field.press('Tab');
  await frame.waitForFunction(() => document.body.textContent.includes('Ref: Code 1'), null, { timeout: 15_000 });
  check('A new Custom Field part previews each post\'s own value', true);

  await page.evaluate(() => {
    const grid = window.postGrid();
    $e.run('document/repeater/move', { container: grid, name: 'card_parts', sourceIndex: 2, targetIndex: 1 });
    $e.run('document/elements/settings', { container: grid, settings: { card_image_position: 'left', pagination_type: 'load_more', card_transition: { unit: 's', size: 0.5, sizes: [] } } });
  });
  await page.waitForTimeout(1500);
  await page.evaluate(() => $e.run('document/save/default'));
  await page.waitForFunction(() => elementor.documents.getCurrent().editor.isSaving === false && !elementor.documents.getCurrent().editor.isChanged, null, { timeout: 30_000 });

  frame = await openEditor();
  const saved = await page.evaluate(() => {
    const settings = window.postGrid().settings;
    return { position: settings.get('card_image_position'), size: settings.get('posts_per_page'), parts: settings.get('card_parts').models.map(model => model.get('part')) };
  });
  check('The page size persists after save and reopen', Number(saved.size) === 3);
  check('The part order and new part persist after save and reopen',
    saved.parts.join() === 'image,meta,title,meta,excerpt,button,custom_field');
  check('The image position persists after save and reopen', saved.position === 'left');

  await visit(page, fixture.urls.editor);
  const body = page.locator('[data-id="fxedt01"] .elemacy-post-card__zone--body').first();
  const order = await body.evaluate(zone => [...zone.children].map(child => child.className).join(' | '));
  check('Visitors get the saved part order', order.indexOf('group--meta') < order.indexOf('part--title'));
  check('Visitors get the Custom Field value of each post', (await page.locator('[data-id="fxedt01"]').textContent()).includes('Ref: Code 1'));
  check('Visitors get the saved page size', await page.locator('[data-id="fxedt01"] .elemacy-post-card').count() === 3);
  const direction = await page.locator('[data-id="fxedt01"] .elemacy-post-card').first().evaluate(card => getComputedStyle(card).flexDirection);
  check('Visitors get the saved image position', direction === 'row');
  const transition = await page.locator('[data-id="fxedt01"] .elemacy-post-card').first().evaluate(card => getComputedStyle(card).getPropertyValue('--elemacy-card-transition').trim());
  check('Card > Transition Duration outputs a value in seconds in the generated CSS', transition === '0.5s');

  // The Title row was never touched, so the editor saved it without its part: a later page must still read it as a Title row.
  const cards = page.locator('[data-id="fxedt01"] .elemacy-post-card');
  const partsOf = locator => locator.evaluateAll(nodes => nodes.map(node => [...node.querySelectorAll('.elemacy-post-card__part')]
    .map(part => (part.className.match(/elemacy-post-card__part--([a-z_]+)/) || [])[1]).join()));
  const firstPage = await partsOf(cards);
  await page.locator('[data-id="fxedt01"] a.elemacy-load-more').click();
  await page.waitForFunction(() => document.querySelectorAll('[data-id="fxedt01"] .elemacy-post-card').length === 6, null, { timeout: 15_000 });
  const everyPage = await partsOf(cards);
  check('A saved card shows a Title on its first page', firstPage.length === 3 && firstPage.every(parts => parts.split(',').includes('title')));
  check('Cards loaded on page 2 after an editor save have the same parts as page 1',
    everyPage.length === 6 && everyPage.slice(3).every(parts => parts === firstPage[0]));
  const titles = await page.locator('[data-id="fxedt01"] .elemacy-post-card__title').allTextContents();
  check('Cards loaded on page 2 show each post\'s title', titles.length === 6 && titles.every(text => /Fixture book/.test(text)));
}

try {
  fixture = JSON.parse(phpRun(fixtureScript, 'create'));
  server = spawn(php, ['-S', base.host, '-t', environment.root, path.join(integration, 'router.php')], {
    env: process.env, stdio: ['ignore', 'pipe', 'pipe'],
  });
  let serverOutput = '';
  let serverError;
  server.on('error', error => { serverError = error; });
  server.stdout.on('data', data => { serverOutput += data; });
  server.stderr.on('data', data => { serverOutput += data; });
  let ready = false;
  for (let attempt = 0; attempt < 100; attempt++) {
    if (serverError) throw serverError;
    if (server.exitCode !== null) throw new Error(`PHP server exited: ${serverOutput}`);
    try {
      const response = await fetch(new URL('/__elemacy_integration_health', base), { signal: AbortSignal.timeout(500) });
      const health = await response.json();
      assert.equal(health.token, environment.token, 'Port belongs to a different server.');
      ready = true;
      break;
    } catch (error) {
      if (error instanceof assert.AssertionError) throw error;
      await delay(100);
    }
  }
  assert.ok(ready, `PHP server did not become ready: ${serverOutput}`);

  browser = await chromium.launch(process.env.ELEMACY_CHROMIUM ? { executablePath: process.env.ELEMACY_CHROMIUM } : {});
  const context = await browser.newContext({ viewport: { width: 1400, height: 900 } });
  page = await context.newPage();
  page.on('console', message => { if (message.type() === 'error') consoleErrors.push(message.text()); });
  page.on('pageerror', error => consoleErrors.push(String(error)));

  await imagePositions();
  await loadMoreAndAssets();
  await masonry();
  await carousel();
  await links();

  await page.goto(new URL('/wp-login.php', base).href);
  await page.fill('#user_login', 'elemacy_test_admin');
  await page.fill('#user_pass', `pw-${environment.token}`);
  await Promise.all([page.waitForNavigation(), page.click('#wp-submit')]);
  await authoring();

  const unexpected = consoleErrors.filter(text => !/favicon|Failed to load resource.*(404|403)|DevTools|net::ERR_/i.test(text));
  check('no unexpected browser console errors', unexpected.length === 0);
  if (unexpected.length) results.push(`  ${unexpected.slice(0, 5).join(' | ')}`);
} catch (error) {
  if (process.env.ELEMACY_DEBUG_SHOT && page) await page.screenshot({ path: process.env.ELEMACY_DEBUG_SHOT }).catch(() => {});
  process.exitCode = 1;
  results.push(`FAIL: ${error && error.stack ? error.stack.split('\n').slice(0, 4).join(' ') : error}`);
} finally {
  if (browser) await browser.close();
  if (server && server.exitCode === null) {
    server.kill('SIGTERM');
    await Promise.race([new Promise(resolve => server.once('exit', resolve)), delay(3000)]);
    if (server.exitCode === null && server.signalCode === null) server.kill('SIGKILL');
  }
  if (fixture) {
    try {
      phpRun(fixtureScript, 'cleanup', fixture.manifest);
      results.push('PASS: browser fixture documents removed');
    } catch (error) {
      process.exitCode = 1;
      results.push(`FAIL: fixture cleanup: ${error.message}`);
    }
  }
  writeFileSync(report, JSON.stringify({ results, consoleErrors }, null, 2) + '\n', { mode: 0o600 });
  console.log(results.join('\n'));
  console.log(`Report: ${report}`);
}
