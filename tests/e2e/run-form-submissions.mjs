import assert from 'node:assert/strict';
import { chromium } from 'playwright-core';
import { spawn, execFileSync } from 'node:child_process';
import { cpSync, writeFileSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { setTimeout as delay } from 'node:timers/promises';

const integration = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../integration');
const php = process.env.ELEMACY_TEST_PHP || 'php';
const phpRun = (...args) => execFileSync(php, args, { encoding: 'utf8', env: process.env, timeout: 60_000 });
const environment = JSON.parse(phpRun('-r',
  'require $argv[1]; echo json_encode(elemacy_test_environment((string) getenv("ELEMACY_TEST_ENV")));',
  path.join(integration, 'environment.php')));
const fixtureScript = path.join(integration, 'form-submissions-browser-fixture.php');
const base = new URL(environment.url);
let fixture;
let server;
let browser;
const consoleErrors = [];
const responses = [];
const results = [];
const report = path.join(path.dirname(environment.root), 'form-submissions-report.json');
const check = (name, ok) => { results.push(`${ok ? 'PASS' : 'FAIL'}: ${name}`); if (!ok) process.exitCode = 1; };

try {
  // Refresh only our fixtures inside the already validated disposable root.
  cpSync(path.join(integration, 'fixtures/mu-plugins'), path.join(environment.root, 'wp-content/mu-plugins'), { recursive: true });

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
  const context = await browser.newContext({ viewport: { width: 1280, height: 800 } });
  // Record the runtime's DOM events as a site script would, from the document.
  await context.addInitScript(() => {
    window.__formEvents = [];
    for (const type of ['elemacy-form:submit', 'elemacy-form:success', 'elemacy-form:error']) {
      document.addEventListener(type, event => {
        const element = event.detail && event.detail.form;
        window.__formEvents.push({
          type,
          form: element && element.querySelector('[name="form_id"]') ? element.querySelector('[name="form_id"]').value : null,
          owner: element && element.querySelector('[name="form_owner"]') ? element.querySelector('[name="form_owner"]').value : null,
          sameForm: element === event.target,
          response: event.detail ? event.detail.response : undefined,
        });
      });
    }
  });
  const page = await context.newPage();
  page.on('console', message => {
    if (message.type() === 'error') consoleErrors.push(message.text());
  });
  page.on('response', response => responses.push({ url: response.url(), status: response.status() }));
  let ajaxRequests = 0;
  page.on('request', request => { if (request.url().includes('admin-ajax.php')) ajaxRequests++; });
  const events = () => page.evaluate(() => window.__formEvents.splice(0));
  /** Clicks submit and reports whether a request left the page within a short wait. */
  const sendsRequest = async (form) => {
    const before = ajaxRequests;
    await form.locator('button[type="submit"]').click();
    await delay(700);
    return ajaxRequests !== before;
  };

  const response = await page.goto(fixture.url, { waitUntil: 'networkidle' });
  assert.equal(response.status(), 200);

  // Guest markup carries nothing that expires or identifies the visitor.
  const html = await page.content();
  check('guest markup has no nonce or placement token', !html.includes('_wpnonce') && !html.includes('elemacy_form_context'));
  const config = await page.evaluate(() => window.elemacyFormsConfig);
  check('guest runtime config has no nonce and translated strings', Boolean(config && config.ajaxUrl && config.i18n && config.i18n.network) && !('nonce' in config));

  const popup = page.locator(`[data-elemacy-popup-id="${fixture.popup_id}"]`);
  await popup.waitFor();
  const hostForm = id => page.locator(`.elementor-${fixture.host_id} .elementor-element-${id} form.elemacy-form`);
  const roots = [
    ['popup', popup.locator('form.elemacy-form')],
    ['ordinary page', hostForm('form123')],
    ['Theme Builder header', page.locator(`.elementor-${fixture.header_id} form.elemacy-form`)],
  ];

  for (const [name, form] of roots) {
    await form.waitFor();
    const input = form.locator('[name="form_fields[name]"]');
    const email = form.locator('[name="form_fields[email]"]');
    await input.fill('Ada');
    await email.fill('invalid-email');
    await events();
    // Bypass native validation to exercise the server's field-error response.
    await form.evaluate(element => { element.noValidate = true; });
    let pending = page.waitForResponse(response => response.url().includes('admin-ajax.php'));
    await form.locator('button[type="submit"]').click();
    let result = await (await pending).json();
    check(`${name}: invalid email rejected by the server, keyed by field ID`, result.success === false && Boolean(result.data.errors.email));
    const error = form.locator('[data-field-id="email"] .elemacy-form-error');
    await error.waitFor({ state: 'visible' });
    const describedBy = await email.getAttribute('aria-describedby');
    check(`${name}: field error is marked, announced and linked by aria-describedby`,
      await email.getAttribute('aria-invalid') === 'true'
      && describedBy === await error.getAttribute('id')
      && await error.getAttribute('role') === 'alert'
      && (await error.textContent()).trim() === result.data.errors.email);
    let seen = await events();
    check(`${name}: submit then error events reach the document with the response`,
      seen.map(event => event.type).join() === 'elemacy-form:submit,elemacy-form:error'
      && seen.every(event => event.sameForm && event.form === 'form123')
      && Boolean(seen[1].response && seen[1].response.errors && seen[1].response.errors.email));

    await email.fill('ada@example.invalid');
    pending = page.waitForResponse(response => response.url().includes('admin-ajax.php'));
    await form.locator('button[type="submit"]').click();
    result = await (await pending).json();
    check(`${name}: valid submission accepted over AJAX`, result.success === true);
    await form.locator('.elemacy-form-message-success').waitFor();
    check(`${name}: success clears prior errors and resets fields`,
      await input.inputValue() === '' && await form.locator('.elemacy-form-field-invalid').count() === 0
      && await email.getAttribute('aria-invalid') === null);
    seen = await events();
    check(`${name}: success event carries the response`,
      seen.map(event => event.type).join() === 'elemacy-form:submit,elemacy-form:success'
      && seen[1].response && typeof seen[1].response.message === 'string');
    if (name === 'popup') await popup.locator('.elemacy-popup__close').click();
  }

  // A listener can cancel a submission.
  const main = hostForm('form123');
  await main.locator('[name="form_fields[name]"]').fill('Ada');
  await main.locator('[name="form_fields[email]"]').fill('ada@example.invalid');
  await page.evaluate(() => document.addEventListener('elemacy-form:submit', event => event.preventDefault(), { once: true }));
  check('preventDefault() on elemacy-form:submit sends nothing', !(await sendsRequest(main)));
  await events();

  // Two forms with the same field IDs, one with hidden labels.
  const ids = await page.evaluate(() => [...document.querySelectorAll('[id^="elemacy-form-"]')].map(element => element.id));
  check('no DOM ID repeats across the page, popup and header forms', ids.length > 10 && new Set(ids).size === ids.length);
  const second = hostForm('form456');
  for (const [name, form] of [['first form', main], ['second form (labels hidden)', second]]) {
    const label = form.locator('label', { hasText: 'Email' });
    // A visually hidden label cannot take a pointer click; activate it as assistive technology would.
    if (form === main) await label.click(); else await label.evaluate(element => element.click());
    const focused = await page.evaluate(() => document.activeElement && document.activeElement.id);
    check(`${name}: its Email label focuses its own field`, focused === await form.locator('[name="form_fields[email]"]').getAttribute('id'));
  }
  const hiddenLabel = second.locator('label', { hasText: 'Name' });
  const box = await hiddenLabel.boundingBox();
  check('hidden labels are visually hidden but still name each control',
    (!box || box.width <= 1) && await main.locator('label', { hasText: 'Name' }).isVisible()
    && await second.getByRole('textbox', { name: 'Name', exact: true }).count() === 1
    && await second.getByRole('textbox', { name: 'Email', exact: true }).count() === 1);

  // Choice and constraint rules, enforced in the browser before anything is sent.
  const rules = hostForm('form789');
  const quantity = rules.locator('[name="form_fields[quantity]"]');
  const day = rules.locator('[name="form_fields[day]"]');
  const plan = rules.locator('[name="form_fields[plan]"]');
  await quantity.fill('5');
  await day.fill('2030-06-15');
  await plan.selectOption('pro');
  check('a required checkbox group with no box ticked is blocked in the browser', !(await sendsRequest(rules)));
  const groupError = rules.locator('[data-field-id="topics"] .elemacy-form-error');
  check('the empty required group shows its error', await groupError.isVisible() && (await groupError.textContent()).trim() === config.i18n.required);
  await rules.locator('input[type="checkbox"][value="offers"]').check();
  await plan.selectOption('');
  check('a required select left on its placeholder blocks submission',
    await plan.evaluate(element => element.validity.valueMissing && element.options[0].value === '' && element.options[0].textContent === 'Choose a plan')
    && !(await sendsRequest(rules)));
  await plan.selectOption('pro');
  await quantity.fill('11');
  check('a number above its maximum blocks submission',
    await quantity.evaluate(element => element.validity.rangeOverflow && element.min === '1' && element.max === '10') && !(await sendsRequest(rules)));
  await quantity.fill('5');
  await day.fill('2029-12-31');
  check('a date before its minimum blocks submission',
    await day.evaluate(element => element.validity.rangeUnderflow && element.min === '2030-01-01' && element.max === '2030-12-31') && !(await sendsRequest(rules)));
  await day.fill('2030-06-15');
  let pending = page.waitForResponse(response => response.url().includes('admin-ajax.php'));
  await rules.locator('button[type="submit"]').click();
  let result = await (await pending).json();
  check('one ticked box of a required group, a chosen plan and in-range values submit', result.success === true);
  await rules.locator('.elemacy-form-message-success').waitFor();

  // Layout: half-width columns share a row, the stretched button fills its column.
  const [quantityBox, dayBox, rulesBox, buttonBox] = await Promise.all([
    rules.locator('[data-field-id="quantity"]').boundingBox(),
    rules.locator('[data-field-id="day"]').boundingBox(),
    rules.boundingBox(),
    rules.locator('.elemacy-form-button').boundingBox(),
  ]);
  check('layout: 50% columns sit side by side and the stretched button spans the form',
    Math.abs(quantityBox.y - dayBox.y) < 2 && dayBox.x > quantityBox.x
    && Math.abs(quantityBox.width - rulesBox.width / 2) < rulesBox.width * 0.05
    && buttonBox.width > rulesBox.width * 0.9);

  // A form added to the page after load works without initialization.
  await main.evaluate(element => new Promise(resolve => {
    const parent = element.parentElement;
    const markup = element.outerHTML;
    element.remove();
    setTimeout(() => { parent.insertAdjacentHTML('beforeend', markup); resolve(); }, 50);
  }));
  const inserted = hostForm('form123');
  await inserted.locator('[name="form_fields[name]"]').fill('Ada');
  await inserted.locator('[name="form_fields[email]"]').fill('ada@example.invalid');
  pending = page.waitForResponse(response => response.url().includes('admin-ajax.php'));
  await inserted.locator('button[type="submit"]').click();
  result = await (await pending).json();
  check('a form inserted after load submits through the delegated listener', result.success === true);
  await inserted.locator('.elemacy-form-message-success').waitFor();

  // Chromium logs every non-2xx fetch; the three deliberate 422 rejections above are the only ones allowed.
  const expected = 'Failed to load resource: the server responded with a status of 422 (Unprocessable Entity)';
  check('no console errors beyond the three deliberate 422 rejections',
    consoleErrors.filter(message => message !== expected).length === 0 && consoleErrors.length === roots.length);
  writeFileSync(report, JSON.stringify({ results, consoleErrors, responses }, null, 2) + '\n', { mode: 0o600 });
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
  writeFileSync(report, JSON.stringify({ results, consoleErrors, responses }, null, 2) + '\n', { mode: 0o600 });
  console.log(results.join('\n'));
  console.log(`Report: ${report}`);
}
