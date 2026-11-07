/**
 * Real Elementor editor: a Theme Builder header and footer authored with atomic
 * elements keep their styles while the edited page gains atomic elements.
 *
 * Elementor's editor canvas renders the edited document's atomic styles in the
 * browser and, once it has any, removes every server-rendered
 * `local-<id>-…-css` stylesheet from the preview frame — including those of
 * other documents that only render on the page, such as a header or footer.
 * TemplateAssetsRegistrar::keep_in_editor_canvas() keeps a registered
 * template's stylesheet out of that match. This drives the real editor and
 * observes the header and footer's computed styles, not merely whether a
 * stylesheet id was rewritten.
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
const phpRun = (...args) => execFileSync(php, args, { encoding: 'utf8', env: process.env, timeout: 120_000 });
const environment = JSON.parse(phpRun('-r',
  'require $argv[1]; echo json_encode(elemacy_test_environment((string) getenv("ELEMACY_TEST_ENV")));',
  path.join(integration, 'environment.php')));
const fixtureScript = path.join(integration, 'editor-template-styles-browser-fixture.php');
const base = new URL(environment.url);
const HEADER_COLOR = 'rgb(133, 2, 2)';
const FOOTER_COLOR = 'rgb(2, 90, 133)';
const NEW_ELEMENT_COLOR = 'rgb(0, 255, 0)';
let fixture;
let server;
let browser;
const consoleErrors = [];
const results = [];
const report = path.join(path.dirname(environment.root), 'editor-template-styles-report.json');
const check = (name, ok) => { results.push(`${ok ? 'PASS' : 'FAIL'}: ${name}`); if (!ok) process.exitCode = 1; };

run: try {
  const created = JSON.parse(phpRun(fixtureScript, 'create'));
  if (created.skipped) {
    results.push(`SKIP: ${created.skipped}`);
    break run;
  }
  fixture = created;
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
  const page = await context.newPage();
  page.on('console', message => {
    if (message.type() === 'error') consoleErrors.push(message.text());
  });

  await page.goto(new URL('/wp-login.php', base).href);
  await page.fill('#user_login', 'elemacy_test_admin');
  await page.fill('#user_pass', `pw-${environment.token}`);
  await Promise.all([page.waitForNavigation(), page.click('#wp-submit')]);

  // A fresh install redirects the first admin request (Elementor's welcome
  // screen); spend it here so it cannot swallow the editor navigation.
  await page.goto(new URL('/wp-admin/', base).href, { waitUntil: 'load' });
  await page.goto(fixture.editor_url, { waitUntil: 'load' });
  await page.waitForSelector('#elementor-preview-iframe', { state: 'attached', timeout: 60_000 });
  const frame = await (await page.$('#elementor-preview-iframe')).contentFrame();
  await frame.waitForFunction(() => document.querySelector('.elementor[data-elementor-type="wp-page"]'), null, { timeout: 60_000 });
  await frame.waitForFunction(
    ([header, footer]) => document.querySelector(`[data-elementor-id="${header}"]`) && document.querySelector(`[data-elementor-id="${footer}"]`),
    [String(fixture.header_id), String(fixture.footer_id)],
    { timeout: 60_000 },
  );
  await page.waitForFunction(() => window.$e && window.elementor && elementor.getPreviewContainer, null, { timeout: 60_000 });

  // The computed background of a template's atomic container, in the preview.
  const backgroundOf = (documentId) => frame.evaluate((id) => {
    const element = document.querySelector(`[data-elementor-id="${id}"] [data-element_type="e-flexbox"]`);
    return element ? getComputedStyle(element).backgroundColor : null;
  }, String(documentId));

  check('the preview frame renders the header with its atomic styles', await backgroundOf(fixture.header_id) === HEADER_COLOR);
  check('the preview frame renders the footer with its atomic styles', await backgroundOf(fixture.footer_id) === FOOTER_COLOR);

  // Two styled atomic elements: the editor only discards pre-generated links on
  // a style pass after its own style cache has content, so the second addition
  // guarantees the removal has run by the time its style shows up.
  const styledElement = (styleId) => ({
    elType: 'e-div-block',
    settings: { classes: { $$type: 'classes', value: [styleId] } },
    styles: {
      [styleId]: {
        id: styleId,
        label: 'local',
        type: 'class',
        variants: [{
          meta: { breakpoint: 'desktop', state: null },
          props: { background: { $$type: 'background', value: { color: { $$type: 'color', value: '#00ff00' } } } },
          custom_css: null,
        }],
      },
    },
  });

  let addedStylesRendered = true;
  for (const styleId of ['e-ecsadded1', 'e-ecsadded2']) {
    const elementId = await page.evaluate((model) => {
      const created = $e.run('document/elements/create', {
        container: elementor.getPreviewContainer(), model, options: { edit: false },
      });
      return created.id;
    }, styledElement(styleId));
    addedStylesRendered = await frame.waitForFunction(
      ([id, color]) => {
        const element = document.querySelector(`[data-id="${id}"]`);
        return element && getComputedStyle(element).backgroundColor === color;
      },
      [elementId, NEW_ELEMENT_COLOR],
      { timeout: 15_000 },
    ).then(() => true, () => false) && addedStylesRendered;
  }
  check('the editor renders the added elements\' own styles itself', addedStylesRendered);

  check('the header keeps its atomic styles after atomic elements are added to the page', await backgroundOf(fixture.header_id) === HEADER_COLOR);
  check('the footer keeps its atomic styles after atomic elements are added to the page', await backgroundOf(fixture.footer_id) === FOOTER_COLOR);

  const templateLinkIds = await frame.evaluate(() => [...document.querySelectorAll('link[rel="stylesheet"]')]
    .map(link => link.id).filter(id => /local-\d+-/.test(id)));
  check('the templates\' atomic stylesheets are still in the frame, no longer under an id the editor discards',
    templateLinkIds.length >= 2 && templateLinkIds.every(id => id.startsWith('elemacy-local-')));
} catch (error) {
  process.exitCode = 1;
  results.push(`FAIL: ${error && error.stack ? error.stack.split('\n').slice(0, 3).join(' ') : error}`);
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
      results.push('PASS: browser fixture documents removed and the theme restored');
    } catch (error) {
      process.exitCode = 1;
      results.push(`FAIL: fixture cleanup: ${error.message}`);
    }
  }
  writeFileSync(report, JSON.stringify({ results, consoleErrors }, null, 2) + '\n', { mode: 0o600 });
  console.log(results.join('\n'));
  console.log(`Report: ${report}`);
}
