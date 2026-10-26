/** Real installed admin bridge checks. Uses only the guarded disposable site. */
import assert from 'node:assert/strict';
import { chromium } from 'playwright-core';
import { spawn, execFileSync } from 'node:child_process';
import { writeFileSync, readFileSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { setTimeout as delay } from 'node:timers/promises';

const integration = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../integration');
const php = process.env.ELEMACY_TEST_PHP || 'php';
const phpRun = code => execFileSync(php, ['-r', code, path.join(integration, 'bootstrap.php')],
  { encoding: 'utf8', env: process.env, timeout: 30_000 });
const fixture = JSON.parse(phpRun(`require $argv[1];
$user = get_user_by('login', 'elemacy_test_admin');
if (!$user || !class_exists('ElemacyPro\\Core\\ElemacyPro')) throw new RuntimeException('Pro and fixture admin are required.');
$onboarded = get_option('elementor_onboarded', false);
update_option('elementor_onboarded', true);
$token = WP_Session_Tokens::get_instance($user->ID)->create(time() + 600);
echo json_encode(['environment' => $elemacy_test_environment, 'user' => $user->ID, 'token' => $token, 'onboarded' => base64_encode(serialize($onboarded)),
'cookies' => [[ 'name' => AUTH_COOKIE, 'value' => wp_generate_auth_cookie($user->ID, time()+600, 'auth', $token)],
['name' => LOGGED_IN_COOKIE, 'value' => wp_generate_auth_cookie($user->ID, time()+600, 'logged_in', $token)]]]);`));
const environment = fixture.environment;
const base = new URL(environment.url);
const reportDir = path.dirname(environment.root);
const results = [];
let server, browser, activeFixture;
let serverOutput = '';
try {
  let existing = false;
  try {
    const health = await (await fetch(new URL('/__elemacy_integration_health', base))).json();
    assert.equal(health.token, environment.token, 'Port belongs to another environment');
    existing = true;
  } catch (error) { if (error instanceof assert.AssertionError) throw error; }
  if (!existing) {
  server = spawn(php, ['-S', base.host, '-t', environment.root, path.join(integration, 'router.php')],
    { env: process.env, stdio: ['ignore', 'pipe', 'pipe'] });
  server.stdout.on('data', data => { serverOutput += data; });
  server.stderr.on('data', data => { serverOutput += data; });
  }
  let ready = existing;
  for (let i = 0; !ready && i < 100; i++) {
    if (server.exitCode !== null) throw new Error(serverOutput);
    try {
      const response = await fetch(new URL('/__elemacy_integration_health', base));
      const health = await response.json();
      assert.equal(health.token, environment.token);
      ready = true;
      break;
    } catch (error) {
      if (error instanceof assert.AssertionError) throw error;
      await delay(100);
    }
  }
  assert.ok(ready, 'Fixture server did not start');
  browser = await chromium.launch(process.env.ELEMACY_CHROMIUM ? { executablePath: process.env.ELEMACY_CHROMIUM } : {});
  for (const scenario of ['ordinary', 'free-delayed', 'pro-delayed', 'unsupported']) {
    const context = await browser.newContext({ viewport: { width: 1440, height: 1000 } });
    await context.addCookies(fixture.cookies.map(cookie => ({ ...cookie, url: base.origin, httpOnly: true })));
    const page = await context.newPage();
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    page.on('console', message => { if (message.type() === 'error' && !message.text().startsWith('[Elemacy Pro]')) errors.push(message.text()); });
    page.on('requestfailed', request => errors.push(`${request.url()}: ${request.failure()?.errorText}`));
    page.on('response', response => { if (response.status() >= 400) errors.push(`${response.status()} ${response.url()}`); });
    await page.route('**/*', async route => {
      const url = route.request().url();
      if ((scenario === 'free-delayed' && url.includes('/scripts/admin.js')) ||
          (scenario === 'pro-delayed' && url.includes('/scripts/elemacy-pro.js'))) await delay(1500);
      if (scenario === 'unsupported' && route.request().isNavigationRequest()) {
        const response = await route.fetch();
        return route.fulfill({ response, body: (await response.text()).replace(/"contract_version":"1\.0\.0"/g, '"contract_version":"99.0.0"') });
      }
      return route.continue();
    });
    await page.goto(new URL('/wp-admin/admin.php?page=elemacy', base).href, { waitUntil: 'networkidle' });
    await page.waitForFunction(() => Boolean(window.ElemacyShared));
    const freeStyle = page.locator('#elemacy-admin-app-css');
    assert.equal(await freeStyle.count(), 1, 'Free stylesheet must remain loaded');
    assert.equal(await page.locator('#elemacy-pro-admin-css').count(), 1, 'Pro stylesheet must be additive');
    if (scenario === 'unsupported') {
      await page.locator('.elemacy-pro-compatibility-notice').waitFor();
      assert.equal(await page.locator('#elemacy-pro-app-js').getAttribute('type'), 'elemacy/deferred');
    } else {
      await page.getByText('Pro features unlocked', { exact: true }).waitFor();
      assert.equal(await page.locator('#elemacy-pro-app-js').getAttribute('type'), null);
      const count = await page.getByText('Pro features unlocked', { exact: true }).count();
      await page.evaluate(() => {
        window.dispatchEvent(new CustomEvent('elemacy:admin-ready'));
        window.dispatchEvent(new CustomEvent('elemacy:admin-ready'));
      });
      assert.equal(await page.getByText('Pro features unlocked', { exact: true }).count(), count);
      // Re-executing the loader must not execute the Pro application again.
      await page.addScriptTag({ url: new URL('/wp-content/plugins/elemacy-pro/assets/admin/scripts/elemacy-pro-loader.js', base).href });
      await page.goto(new URL('/wp-admin/admin.php?page=elemacy#/license', base).href);
      await page.getByText('Elemacy Pro License', { exact: true }).waitFor();
      const input = page.getByPlaceholder('Enter your license key');
      await input.fill('');
      await page.getByRole('button', { name: 'Activate', exact: true }).click();
      await page.getByText('Please enter your license key.', { exact: true }).waitFor();
      const style = await input.evaluate(el => ({ height: el.getBoundingClientRect().height, border: getComputedStyle(el).borderTopStyle }));
      assert.ok(style.height >= 30 && style.border !== 'none', 'Free component styles must remain present inside Pro');
      await page.screenshot({ path: path.join(reportDir, `admin-${scenario}-license.png`), fullPage: true });
    }
    for (const screen of ['library', 'popups', 'theme-builder']) {
      await page.goto(new URL(`/wp-admin/admin.php?page=elemacy#/${screen}`, base).href, { waitUntil: 'networkidle' });
      assert.ok((await page.locator('#elemacy_root').innerText()).length > 50, `${screen} must remain usable`);
      if (screen === 'theme-builder') {
        await page.getByRole('button', { name: 'Create New Template', exact: true }).click();
        await page.getByText('Display Conditions', { exact: true }).waitFor();
        await page.getByRole('button', { name: '+ Add Condition', exact: true }).click();
        assert.ok(await page.getByRole('dialog').getByRole('combobox').count() >= 2,
          'Condition operator/type controls must mount');
      }
      await page.screenshot({ path: path.join(reportDir, `admin-${scenario}-${screen}.png`), fullPage: true });
    }
    assert.deepEqual(errors, [], `${scenario} browser failures`);
    results.push(`PASS ${scenario}: Free screens, additive styles, bridge and Pro behavior`);
    await context.close();
  }
  // Exercise the analytics fill and the license screen against an active license record.
  // Only this disposable site's owned license record is changed, then restored in finally.
  activeFixture = JSON.parse(phpRun(`require $argv[1];
$key = 'elemacy_pro_license';
$old = get_option($key, null);
wp_set_current_user(${Number(fixture.user)});
$docs = new Elemacy\\Tests\\Integration\\Fixtures\\LibraryDocument();
try { $id = $docs->create('Admin contract analytics fixture', 'popup'); }
catch (Throwable $error) { $docs->cleanup(); throw $error; }
// checked_at = now keeps the admin fallback from verifying; the environment blocks outbound HTTP regardless.
update_option($key, ['format' => 1, 'key' => 'FIXTURE-KEY-0000', 'state' => 'active', 'reason' => '',
  'site' => \\ElemacyPro\\Core\\License\\Sdk\\Site::normalize(home_url()), 'verified_at' => time(), 'checked_at' => time(),
  'info' => ['license_status' => 'active', 'expires_at' => null, 'plan_name' => 'integration-fixture',
    'plan_type' => 'lifetime', 'seats_used' => 1, 'seats_limit' => 1]]);
echo json_encode(['id' => $id, 'old' => base64_encode(serialize($old))]);`));
  const activeContext = await browser.newContext();
  await activeContext.addCookies(fixture.cookies.map(cookie => ({ ...cookie, url: base.origin, httpOnly: true })));
  const activePage = await activeContext.newPage();
  const activeErrors = [];
  // Every Elemacy REST request the admin apps make goes to the versioned namespace and succeeds.
  const restCalls = [];
  activePage.on('response', response => {
    const url = new URL(response.url());
    const route = url.searchParams.get('rest_route') || (url.pathname.startsWith('/wp-json/') ? url.pathname.slice('/wp-json'.length) : '');
    if (route.startsWith('/elemacy')) restCalls.push({ route, status: response.status() });
  });
  activePage.on('pageerror', error => activeErrors.push(error.message));
  activePage.on('response', response => { if (response.status() >= 400) activeErrors.push(`${response.status()} ${response.url()}`); });
  await activePage.goto(new URL('/wp-admin/admin.php?page=elemacy#/popups', base).href, { waitUntil: 'networkidle' });
  await activePage.getByText('Admin contract analytics fixture', { exact: true }).waitFor();
  assert.ok(await activePage.getByText('Impressions', { exact: true }).count() > 0, 'Active analytics fill must render');
  assert.ok(await activePage.getByText('Conversions', { exact: true }).count() > 0, 'Active analytics fill must render');
  assert.deepEqual(activeErrors, []);
  await activePage.screenshot({ path: path.join(reportDir, 'admin-active-analytics.png'), fullPage: true });
  const licenseUrl = new URL('/wp-admin/admin.php?page=elemacy#/license', base).href;
  await activePage.goto(licenseUrl);
  await activePage.getByRole('button', { name: 'Deactivate license', exact: true }).waitFor();
  assert.ok(await activePage.getByText('Active', { exact: true }).count() > 0, 'Active license must show the Active badge');
  assert.ok(await activePage.getByText('••••••••••••0000', { exact: true }).count() > 0, 'Masked key must be shown');
  assert.ok(await activePage.getByRole('button', { name: 'Check now', exact: true }).count() > 0, 'Active license must offer Check now');
  assert.ok(!(await activePage.content()).includes('FIXTURE-KEY-0000'), 'The raw key must not reach the rendered page');
  assert.ok(!(await (await activeContext.request.get(licenseUrl)).text()).includes('FIXTURE-KEY-0000'), 'The raw key must not reach the page HTML');
  assert.deepEqual(activeErrors, []);
  await activePage.screenshot({ path: path.join(reportDir, 'admin-active-license.png'), fullPage: true });
  await activeContext.close();
  assert.ok(restCalls.some(call => call.route.startsWith('/elemacy/v1/popups')), 'The popups screen read its data over REST');
  assert.ok(restCalls.some(call => call.route.startsWith('/elemacy/v1/license')), 'The license screen read its data over REST: ' + JSON.stringify(restCalls));
  assert.deepEqual(restCalls.filter(call => !call.route.startsWith('/elemacy/v1/') || call.status >= 400), [], 'Every Elemacy REST call is under /elemacy/v1/ and succeeds');
  results.push(`PASS REST calls of the admin apps: ${restCalls.length}, all under /wp-json/elemacy/v1/ and successful`);
  results.push('PASS active license fixture: real popup analytics fill, REST response and license screen');
  const bundle = JSON.parse(readFileSync(path.resolve(integration, '../../../elemacy-pro/assets/admin/admin-bundle-report.json'), 'utf8'));
  assert.equal(bundle.reactModules, 0);
  results.push('PASS bundle analysis: Free is the sole React runtime owner');
} catch (error) {
  process.exitCode = 1;
  results.push(`FAIL ${error.stack || error}`);
} finally {
  if (browser) await browser.close();
  if (server && server.exitCode === null) server.kill('SIGTERM');
  if (activeFixture) phpRun(`require $argv[1];
wp_delete_post(${Number(activeFixture.id)}, true);
$old = unserialize(base64_decode('${activeFixture.old}'));
if ($old === null) delete_option('elemacy_pro_license');
else update_option('elemacy_pro_license', $old);`);
  // Revoke only this runner's short-lived login session.
  phpRun(`require $argv[1]; WP_Session_Tokens::get_instance(${Number(fixture.user)})->destroy(${JSON.stringify(fixture.token)});
$onboarded = unserialize(base64_decode('${fixture.onboarded}'));
if ($onboarded === false) delete_option('elementor_onboarded');
else update_option('elementor_onboarded', $onboarded);`);
  writeFileSync(path.join(reportDir, 'admin-contract-report.json'), JSON.stringify({ results, serverOutput }, null, 2), { mode: 0o600 });
  console.log(results.join('\n'));
}
