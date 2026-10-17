/**
 * Real Elementor editor journey for Loop Grid's Load More and Infinite Scroll
 * authoring: the Pagination section's choices and controls, editing the
 * texts, save and reopen, and the preview showing the button without loading
 * anything.
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
const phpRun = (...args) => execFileSync(php, args, { encoding: 'utf8', env: process.env, timeout: 60_000 });
const environment = JSON.parse(phpRun('-r',
  'require $argv[1]; echo json_encode(elemacy_test_environment((string) getenv("ELEMACY_TEST_ENV")));',
  path.join(integration, 'environment.php')));
const fixtureScript = path.join(integration, 'loop-load-more-browser-fixture.php');
const base = new URL(environment.url);
const report = path.join(path.dirname(environment.root), 'loop-load-more-editor-report.json');
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
  const context = await browser.newContext({ viewport: { width: 1500, height: 900 } });
  page = await context.newPage();
  page.on('console', message => { if (message.type() === 'error') consoleErrors.push(message.text()); });

  await page.goto(new URL('/wp-login.php', base).href);
  await page.fill('#user_login', 'elemacy_test_admin');
  await page.fill('#user_pass', `pw-${environment.token}`);
  await Promise.all([page.waitForNavigation(), page.click('#wp-submit')]);
  await page.goto(new URL('/wp-admin/', base).href, { waitUntil: 'load' });

  async function openEditor() {
    await page.goto(fixture.editor_url, { waitUntil: 'load' });
    await page.waitForSelector('#elementor-preview-iframe', { state: 'attached', timeout: 60_000 });
    await page.waitForFunction(() => window.$e && window.elementor && elementor.getPreviewContainer && elementor.getPreviewContainer(), null, { timeout: 60_000 });
    const frame = await (await page.$('#elementor-preview-iframe')).contentFrame();
    await frame.waitForSelector('[data-widget_type="elemacy-loop-grid.default"]', { timeout: 60_000 });
    // The page-side handle on the Loop Grid's editor container, used by the helpers below.
    await page.evaluate(() => {
      window.loopGrid = () => elementor.getPreviewContainer()
        .findChildrenRecursive(c => c.model.get('widgetType') === 'elemacy-loop-grid');
    });
    return frame;
  }

  // Opens the grid's panel on its Pagination section and reports its controls.
  async function paginationControls() {
    await page.evaluate(() => {
      const grid = window.loopGrid();
      $e.run('panel/editor/open', { model: grid.model, view: grid.view });
    });
    await page.waitForSelector('.elementor-control-section_pagination', { timeout: 20_000 });
    await page.evaluate(() => {
      const control = document.querySelector('.elementor-control-pagination_type');
      if (!control || control.offsetParent === null) {
        document.querySelector('.elementor-control-section_pagination .elementor-panel-heading')?.click();
      }
    });
    await page.waitForSelector('.elementor-control-pagination_type', { state: 'visible', timeout: 15_000 });
    return readControls();
  }

  const readControls = () => page.evaluate(() => {
    const shown = (name) => {
      const control = document.querySelector(`.elementor-control-${name}`);
      return !!control && !control.classList.contains('elementor-hidden-control') && control.offsetParent !== null;
    };
    return {
      types: [...document.querySelectorAll('.elementor-control-pagination_type select option')].map(o => o.value),
      shown: Object.fromEntries(['pagination_ajax', 'load_more_text', 'load_more_loading_text', 'load_more_end_text']
        .map(name => [name, shown(name)])),
    };
  });

  const setSettings = (settings) => page.evaluate((values) => {
    $e.run('document/elements/settings', { container: window.loopGrid(), settings: values });
  }, settings);

  const savedSettings = () => page.evaluate(() => window.loopGrid().settings.toJSON({ remove: ['default'] }));

  const save = async () => {
    await page.evaluate(() => $e.run('document/save/default'));
    await page.waitForFunction(() => elementor.documents.getCurrent().editor.isSaving === false && !elementor.documents.getCurrent().editor.isChanged, null, { timeout: 30_000 });
  };

  let frame = await openEditor();
  let controls = await paginationControls();
  check('the Pagination choices list Load More and Infinite Scroll after the link types', JSON.stringify(controls.types) === JSON.stringify(['', 'numbers', 'prev_next', 'numbers_and_prev_next', 'load_more', 'infinite_scroll']));
  check('with Load More selected the text controls show and the AJAX switch is hidden',
    controls.shown.load_more_text && controls.shown.load_more_loading_text && controls.shown.load_more_end_text && !controls.shown.pagination_ajax);

  await setSettings({ pagination_type: 'numbers' });
  await page.waitForTimeout(400);
  controls = await readControls();
  check('with Numbers selected the AJAX switch shows and the text controls are hidden',
    controls.shown.pagination_ajax && !controls.shown.load_more_text && !controls.shown.load_more_end_text);

  await setSettings({ pagination_type: 'infinite_scroll', load_more_text: 'Show more stories', load_more_loading_text: 'Fetching…', load_more_end_text: 'You are all caught up' });
  await page.waitForTimeout(400);
  controls = await readControls();
  check('with Infinite Scroll selected the text controls show and the AJAX switch is hidden',
    controls.shown.load_more_text && controls.shown.load_more_end_text && !controls.shown.pagination_ajax);

  await save();
  frame = await openEditor();
  let saved = await savedSettings();
  check('Infinite Scroll and its texts persist after save and reopen',
    saved.pagination_type === 'infinite_scroll' && saved.load_more_text === 'Show more stories' && saved.load_more_end_text === 'You are all caught up');

  await setSettings({ pagination_type: 'load_more' });
  await save();
  frame = await openEditor();
  saved = await savedSettings();
  check('a switch to Load More persists after save and reopen', saved.pagination_type === 'load_more');

  const button = frame.locator('a.elemacy-load-more').first();
  check('the editor preview shows the button with the authored text', await button.count() === 1 && (await button.textContent()) === 'Show more stories');
  const before = await frame.locator('[data-elemacy-item]').count();
  await button.click({ force: true }).catch(() => {});
  await page.waitForTimeout(800);
  check('activating the preview button loads nothing', await frame.locator('[data-elemacy-item]').count() === before);

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
      results.push('PASS: editor fixture documents removed');
    } catch (error) {
      process.exitCode = 1;
      results.push(`FAIL: fixture cleanup: ${error.message}`);
    }
  }
  writeFileSync(report, JSON.stringify({ results, consoleErrors }, null, 2) + '\n', { mode: 0o600 });
  console.log(results.join('\n'));
  console.log(`Report: ${report}`);
}
