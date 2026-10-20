/**
 * A default Top Bar printed by the server on the Hello Elementor theme, seen by a real browser: styled at first
 * paint (classic Button and an atomic heading), its Elementor widgets alive, no shift of the page below it,
 * and it closes with its button. Run with ELEMACY_TEST_ENV set to a site provisioned with
 * --hello-elementor-theme. Writes a screenshot beside the environment manifest.
 */
import assert from 'node:assert/strict';
import { chromium } from 'playwright-core';
import path from 'node:path';
import { phpRun, startServer, reportPath, base } from './shared/native-site.mjs';

const fixture = JSON.parse(phpRun('topbar-prerender-browser-fixture.php', 'create'));
const server = await startServer();
let browser;
const results = [];
const check = (name, ok) => { results.push(`${ok ? 'PASS' : 'FAIL'}  ${name}`); if (!ok) process.exitCode = 1; };

try {
  browser = await chromium.launch(process.env.ELEMACY_CHROMIUM ? { executablePath: process.env.ELEMACY_CHROMIUM } : {});
  const page = await (await browser.newContext({ viewport: { width: 1280, height: 800 } })).newPage();
  const errors = [];
  page.on('pageerror', error => errors.push(String(error)));

  // The first frame the browser can paint is the parsed HTML, before scripts run.
  await page.route('**/*.js', route => route.abort());
  await page.goto(fixture.url, { waitUntil: 'domcontentloaded' });
  const root = page.locator(`[data-elemacy-popup-id="${fixture.popup}"]`);
  const html = await page.content();
  check('the bar is in the HTML, open, outside a template', html.includes('data-elemacy-prerendered') && !(await root.evaluate(el => el.hasAttribute('hidden'))));
  const styled = await page.evaluate(() => {
    const atomic = document.querySelector('.e-heading-base');
    const classic = document.querySelector('.tb-classic-button .elementor-button');
    return {
      atomicColor: atomic && getComputedStyle(atomic).color,
      classicBackground: classic && getComputedStyle(classic).backgroundColor,
      top: document.querySelector('.entry-content, main, #content, article')?.getBoundingClientRect().top,
    };
  });
  check('the atomic heading is styled before any script runs (#123401)', styled.atomicColor === 'rgb(18, 52, 1)');
  check('the classic button is styled before any script runs', !!styled.classicBackground && styled.classicBackground !== 'rgba(0, 0, 0, 0)');
  await page.unroute('**/*.js');

  // With scripts: the page does not move, Elementor's widgets work, and the bar closes.
  await page.goto(fixture.url, { waitUntil: 'load' });
  await page.waitForTimeout(1200);
  const after = await page.evaluate(() => document.querySelector('.entry-content, main, #content, article')?.getBoundingClientRect().top);
  check('nothing below the bar moved once the scripts ran', Math.abs((after ?? 0) - (styled.top ?? 0)) < 1);
  check('the bar is still open', await root.evaluate(el => el.classList.contains('is-open')));
  const widgets = await page.evaluate(() => ({
    classic: document.querySelectorAll('.tb-classic-button.elementor-widget-button').length === 1,
    initialised: !!document.querySelector('.elementor-widget-button.elementor-widget') && typeof window.elementorFrontend === 'object',
  }));
  check("Elementor's frontend is running and the bar's button is a live widget", widgets.classic && widgets.initialised);
  await page.screenshot({ path: reportPath('topbar-prerender-hello.png'), animations: 'disabled' });
  await root.getByRole('button', { name: 'Close', exact: true }).click();
  await page.waitForTimeout(600);
  check('the bar closes with its close button', await root.evaluate(el => el.hasAttribute('hidden')));
  check('no page errors', errors.length === 0);
  console.log(results.join('\n'));
  console.log('Screenshot:', reportPath('topbar-prerender-hello.png'));
  assert.equal(process.exitCode ?? 0, 0, 'topbar-prerender checks failed');
} finally {
  await browser?.close();
  await server.stop();
  phpRun('topbar-prerender-browser-fixture.php', 'cleanup', fixture.manifest);
}
