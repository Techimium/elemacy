/**
 * Real browser journey for nested ACF repeater loops (sections > items > tags):
 * the editor shows a Parent Row loop's preview rows, re-renders it after a
 * setting change, saves and reopens; a visitor's Load More at the second and
 * third level appends only that parent's rows, styled, reading their ancestors.
 * Needs ACF Pro; without it the journey reports a profile skip.
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
const fixtureScript = path.join(integration, 'nested-repeater-browser-fixture.php');
const base = new URL(environment.url);
const report = path.join(path.dirname(environment.root), 'nested-repeater-report.json');
const results = [];
const errors = [];
let fixture;
let server;
let browser;

/** The paginated loop of $widget whose text includes $marker, innermost match. */
const loopOf = (page, widget, marker) => page
  .locator(`.elemacy-ajax-pagination[data-elemacy-loop-request*='"widget_id":"${widget}"']`)
  .filter({ hasText: marker });

const textOf = locator => locator.evaluate(el => el.textContent.replace(/\s+/g, ' '));

async function inOrder(locator, needles, message) {
  const text = await textOf(locator);
  let at = 0;
  for (const needle of needles) {
    const found = text.indexOf(needle, at);
    assert.ok(found >= 0, `${message}: expected ${needle} after ${at} in "${text}"`);
    at = found + needle.length;
  }
}

async function open(context, url) {
  const page = await context.newPage();
  page.on('pageerror', error => errors.push({ kind: 'pageerror', message: error.message }));
  page.on('console', message => { if (message.type() === 'error') errors.push({ kind: 'console', message: message.text() }); });
  await page.goto(url, { waitUntil: 'networkidle' });
  return page;
}

async function signIn(page) {
  await page.goto(new URL('/wp-login.php', base).href);
  await page.fill('#user_login', 'elemacy_test_admin');
  await page.fill('#user_pass', `pw-${environment.token}`);
  await Promise.all([page.waitForNavigation(), page.click('#wp-submit')]);
}

async function openEditor(page) {
  await page.goto(fixture.editor_url, { waitUntil: 'load' });
  await page.waitForSelector('#elementor-preview-iframe', { state: 'attached', timeout: 60_000 });
  await page.waitForFunction(() => window.$e && window.elementor && elementor.getPreviewContainer && elementor.getPreviewContainer(), null, { timeout: 60_000 });
  const frame = await (await page.$('#elementor-preview-iframe')).contentFrame();
  await frame.waitForSelector('[data-widget_type="elemacy-loop-grid.default"]', { timeout: 60_000 });
  await page.evaluate(() => {
    window.itemsLoop = () => elementor.getPreviewContainer()
      .findChildrenRecursive(c => c.model.get('widgetType') === 'elemacy-loop-grid');
  });
  return frame;
}

const scenarios = {
  async 'A Parent Row loop edited in its outer template shows, re-renders and keeps that preview row\'s items'(context) {
    const page = await context.newPage();
    page.on('console', message => { if (message.type() === 'error') errors.push({ kind: 'editor console', message: message.text() }); });
    await signIn(page);

    const save = async () => {
      await page.evaluate(() => $e.run('document/save/default'));
      await page.waitForFunction(() => elementor.documents.getCurrent().editor.isSaving === false && !elementor.documents.getCurrent().editor.isChanged, null, { timeout: 30_000 });
    };

    let frame = await openEditor(page);
    try {
      const preview = frame.locator('[data-widget_type="elemacy-loop-grid.default"]').first();
      await inOrder(preview, ['[I=Apple]', '[T=red]'], 'The preview section\'s first item and its first tag');
      assert.ok(!(await textOf(preview)).includes('[I=Carrot]'), 'Only the preview section\'s items.');

      await page.evaluate(() => $e.run('document/elements/settings', { container: window.itemsLoop(), settings: { rows_number: 0 } }));
      await frame.waitForFunction(() => document.body.textContent.includes('[I=Banana]'), null, { timeout: 20_000 });
      await inOrder(frame.locator('[data-widget_type="elemacy-loop-grid.default"]').first(), ['[I=Apple]', '[I=Banana]', '[T=yellow]'], 'Re-rendered alone, the loop still reads the preview row');

      await save();
      frame = await openEditor(page);
      await inOrder(frame.locator('[data-widget_type="elemacy-loop-grid.default"]').first(), ['[I=Apple]', '[I=Banana]'], 'After save and reopen');
      // 0 is the control's default, which Elementor does not store, so read the live value.
      const saved = await page.evaluate(() => ({ rows: window.itemsLoop().settings.get('rows_number'), source: window.itemsLoop().settings.get('source_type') }));
      assert.equal(String(saved.rows), '0', 'The setting persisted.');
      assert.equal(saved.source, 'parent_row');
    } finally {
      // Restore one row per page for the visitor scenarios.
      await page.evaluate(() => $e.run('document/elements/settings', { container: window.itemsLoop(), settings: { rows_number: 1 } })).catch(() => {});
      await save().catch(() => {});
      await page.close();
    }
  },

  async 'Load More on a third-level loop appends only that parent\'s rows, styled, reading their ancestors'(context) {
    const page = await open(context, fixture.page_url);
    const apple = loopOf(page, 'nrtags', '[T=red]');
    assert.equal(await apple.count(), 1, 'Apple\'s tags loop.');
    await inOrder(page.locator('body'), ['[S=Fruits]', '[I=Apple]', '[T=red]', '[TI=Apple]', '[TS=Fruits]', '[S=Vegetables]', '[I=Carrot]', '[T=orange]'], 'Page one');

    await apple.locator('a.elemacy-load-more').first().click();
    await page.waitForFunction(() => document.body.textContent.includes('[T=sweet]'), null, { timeout: 10_000 });
    await inOrder(apple, ['[T=red]', '[T=sweet]', '[TI=Apple]', '[TS=Fruits]'], 'The appended tag belongs to Apple and reads its item and section');
    assert.ok(!(await textOf(loopOf(page, 'nrtags', '[T=orange]'))).includes('[T=sweet]'), 'Carrot\'s loop is untouched.');

    const color = await apple.locator('[data-elemacy-item]').nth(1).locator('.elementor-heading-title').first()
      .evaluate(el => getComputedStyle(el).color);
    assert.equal(color, 'rgb(180, 0, 0)', 'The appended item is styled.');
    await page.close();
  },

  async 'An item appended by Load More paginates its own inner loop'(context) {
    const page = await open(context, fixture.page_url);
    // Only the Fruits section's items loop holds Apple; its own control is its direct child.
    const fruits = loopOf(page, 'nritems', '[I=Apple]');
    assert.equal(await fruits.count(), 1, 'Fruits\' items loop.');
    await fruits.locator(':scope > .elemacy-pagination a.elemacy-load-more').click();
    await page.waitForFunction(() => document.body.textContent.includes('[I=Banana]'), null, { timeout: 10_000 });

    const banana = loopOf(page, 'nrtags', '[T=yellow]');
    assert.equal(await banana.count(), 1, 'Banana arrived with its own tags loop.');
    await banana.locator('a.elemacy-load-more').first().click();
    await page.waitForFunction(() => document.body.textContent.includes('[T=long]'), null, { timeout: 10_000 });
    await inOrder(banana, ['[T=yellow]', '[T=long]', '[TI=Banana]', '[TS=Fruits]'], 'Banana\'s second tag');
    await page.close();
  },
};

try {
  fixture = JSON.parse(phpRun(fixtureScript, 'create'));
  if (fixture.skip) {
    results.push(`SKIP (profile): ${fixture.skip}`);
    fixture = undefined;
  } else {
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

    for (const [name, scenario] of Object.entries(scenarios)) {
      const context = await browser.newContext({ viewport: { width: 1400, height: 900 } });
      try {
        await scenario(context);
        results.push(`PASS: ${name}`);
      } catch (error) {
        process.exitCode = 1;
        results.push(`FAIL: ${name}: ${error.stack || error.message}`);
      } finally {
        await context.close();
      }
    }

    const unexpected = errors.filter(error => !/favicon|Failed to load resource.*(404|403)|DevTools|net::ERR_/i.test(error.message));
    if (unexpected.length) {
      process.exitCode = 1;
      results.push(`FAIL: unexpected browser errors: ${JSON.stringify(unexpected.slice(0, 5))}`);
    } else {
      results.push('PASS: no unexpected browser JavaScript or console errors');
    }
  }
} catch (error) {
  process.exitCode = 1;
  results.push(`FAIL: ${error.stack || error.message}`);
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
  writeFileSync(report, JSON.stringify({ results, errors }, null, 2) + '\n', { mode: 0o600 });
  console.log(results.join('\n'));
  console.log(`Report: ${report}`);
}
