/**
 * Real Elementor editor journey for the Current Query loop data source: open
 * an Archive template whose Loop Grid uses Current Query, check what the
 * Query section offers, switch sources and back, save, reopen, and confirm the
 * choice persisted and the preview lists posts.
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
const fixtureScript = path.join(integration, 'current-query-browser-fixture.php');
const base = new URL(environment.url);
let fixture;
let server;
let browser;
let page;
const consoleErrors = [];
const results = [];
const report = path.join(path.dirname(environment.root), 'current-query-editor-report.json');
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

  browser = await chromium.launch(process.env.ELEMACY_CHROMIUM
    ? { executablePath: process.env.ELEMACY_CHROMIUM } : {});
  const context = await browser.newContext({ viewport: { width: 1500, height: 900 } });
  page = await context.newPage();
  page.on('console', message => {
    if (message.type() === 'error') consoleErrors.push(message.text());
  });

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
    return frame;
  }

  // Selects the Loop Grid and opens its Query section, then reports which
  // Query controls the panel currently shows.
  async function queryControls(open = true) {
    if (open) {
      await page.evaluate(() => {
        const grid = elementor.getPreviewContainer().findChildrenRecursive(c => c.model.get('widgetType') === 'elemacy-loop-grid');
        $e.run('panel/editor/open', { model: grid.model, view: grid.view });
      });
    }
    await page.waitForSelector('.elementor-control-section_query', { timeout: 20_000 });
    // Open the Query section if its controls are not showing (a settings change can collapse it).
    await page.evaluate(() => {
      const control = document.querySelector('.elementor-control-data_source');
      if (!control || control.offsetParent === null) {
        document.querySelector('.elementor-control-section_query .elementor-panel-heading')?.click();
      }
    });
    await page.waitForSelector('.elementor-control-data_source', { state: 'visible', timeout: 15_000 });
    return page.evaluate(() => {
      const visible = (name) => {
        const control = document.querySelector(`.elementor-control-${name}`);
        return !!control && !control.classList.contains('elementor-hidden-control') && control.offsetParent !== null;
      };
      const postTypes = [...document.querySelectorAll('.elementor-control-post_type select option')].map(o => o.value);
      const sources = [...document.querySelectorAll('.elementor-control-data_source select option')].map(o => o.value);
      return {
        sources,
        postTypes,
        shown: Object.fromEntries(['post_type', 'posts_per_page', 'offset', 'orderby', 'order', 'exclude_current_post', 'current_query_notice']
          .map(name => [name, visible(name)])),
      };
    });
  }

  const setSource = (value) => page.evaluate((source) => {
    const grid = elementor.getPreviewContainer().findChildrenRecursive(c => c.model.get('widgetType') === 'elemacy-loop-grid');
    $e.run('document/elements/settings', { container: grid, settings: { data_source: source } });
  }, value);

  let frame = await openEditor();
  let controls = await queryControls();
  check('the Data Source choice lists Current Query as its own entry beside Posts', JSON.stringify(controls.sources.slice(0, 3)) === JSON.stringify(['posts', 'current_query', 'terms']));
  check('with Current Query selected no Posts control is shown', ['post_type', 'posts_per_page', 'offset', 'orderby', 'order', 'exclude_current_post'].every(name => controls.shown[name] === false));
  check('with Current Query selected its notice is shown', controls.shown.current_query_notice === true);
  check('the preview lists posts for Current Query', await frame.locator('.elemacy-loop-item').count() > 0);

  await setSource('posts');
  await page.waitForTimeout(500);
  controls = await queryControls(false);
  check('with Posts selected its controls are shown and the notice is hidden', controls.shown.post_type === true && controls.shown.posts_per_page === true && controls.shown.current_query_notice === false);
  check('the Post Type choice lists real post types only, never Current Query', controls.postTypes.length > 0 && !controls.postTypes.includes('current_query') && controls.postTypes.includes('post'));

  const savedSource = async () => {
    await page.evaluate(() => $e.run('document/save/default'));
    await page.waitForFunction(() => elementor.documents.getCurrent().editor.isSaving === false && !elementor.documents.getCurrent().editor.isChanged, null, { timeout: 30_000 });
    frame = await openEditor();
    return page.evaluate(() => elementor.getPreviewContainer()
      .findChildrenRecursive(c => c.model.get('widgetType') === 'elemacy-loop-grid').settings.get('data_source'));
  };

  // Posts is still selected: save and reopen proves the choice itself is stored, not just the fixture's.
  check('a switch to Posts persists after save and reopen', await savedSource() === 'posts');
  await setSource('current_query');
  await page.waitForTimeout(500);
  check('a switch back to Current Query persists after save and reopen', await savedSource() === 'current_query');
  check('the reopened preview lists posts for Current Query', await frame.locator('.elemacy-loop-item').count() > 0);

  const unexpected = consoleErrors.filter(text => !/favicon|Failed to load resource.*(404|403)|DevTools|net::ERR_/i.test(text));
  check('no unexpected browser console errors', unexpected.length === 0);
  if (unexpected.length) results.push(`  ${unexpected.slice(0, 5).join(' | ')}`);
} catch (error) {
  if (process.env.ELEMACY_DEBUG_SHOT && page) await page.screenshot({ path: process.env.ELEMACY_DEBUG_SHOT, fullPage: false }).catch(() => {});
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
