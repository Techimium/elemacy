/**
 * Parity and coexistence of the Elemacy Nav Menu with the Elementor Pro Nav Menu
 * (openspec widgets/nav-menu: "Default appearance matches the reference" and
 * "Isolation from Elementor Pro, themes and other menus").
 *
 * For each settings variant the fixture renders Pro's widget and Elemacy's on
 * separate pages with identical settings and element IDs. Every part of both is
 * compared by computed style and geometry: at rest, at the end of a hover
 * transition, with a submenu open and with the mobile dropdown open, at desktop,
 * tablet and mobile widths, left-to-right and right-to-left. Open-submenu
 * screenshots are compared too. A page holding both widgets checks that neither
 * changes the other.
 *
 * Elementor Pro is a paid plugin: provision with --elementor-pro-plugin. Without
 * it this runner reports UNAVAILABLE and exits non-zero; it never passes.
 */
import assert from 'node:assert/strict';
import { chromium } from 'playwright-core';
import { writeFileSync } from 'node:fs';
import { phpRun, reportPath, startServer } from './shared/native-site.mjs';

const FIXTURE = 'nav-menu-browser-fixture.php';
const report = reportPath('nav-menu-parity-report.json');
const results = [];
const errors = [];
const differences = [];
let fixture;
let server;
let browser;

const WIDTHS = { desktop: 1280, tablet: 1024, mobile: 390 };
const ELEMENT = 'nmp0002';

/** The same part of each widget. */
const PARTS = {
  reference: {
    main: 'nav.elementor-nav-menu--main',
    list: 'nav.elementor-nav-menu--main > ul',
    item: 'nav.elementor-nav-menu--main .elementor-item',
    subItem: 'nav.elementor-nav-menu--main .elementor-sub-item',
    submenu: 'nav.elementor-nav-menu--main ul.sub-menu',
    indicator: 'nav.elementor-nav-menu--main .sub-arrow',
    toggle: '.elementor-menu-toggle',
    toggleIcon: '.elementor-menu-toggle > *',
    dropdown: 'nav.elementor-nav-menu__container.elementor-nav-menu--dropdown',
    dropdownItem: 'nav.elementor-nav-menu--dropdown a',
  },
  elemacy: {
    main: 'nav.elemacy-nav-menu__main',
    list: 'nav.elemacy-nav-menu__main > ul',
    item: 'nav.elemacy-nav-menu__main .elemacy-nav-menu__item',
    subItem: 'nav.elemacy-nav-menu__main .elemacy-nav-menu__sub-item',
    submenu: 'nav.elemacy-nav-menu__main ul.sub-menu',
    indicator: 'nav.elemacy-nav-menu__main .elemacy-nav-menu__indicator',
    toggle: '.elemacy-nav-menu__toggle',
    toggleIcon: '.elemacy-nav-menu__toggle > *',
    dropdown: 'nav.elemacy-nav-menu__container.elemacy-nav-menu__dropdown',
    dropdownItem: 'nav.elemacy-nav-menu__dropdown a',
  },
};

const PROPERTIES = [
  'display', 'position', 'visibility', 'opacity', 'transform', 'z-index', 'box-sizing',
  'margin-top', 'margin-right', 'margin-bottom', 'margin-left',
  'padding-top', 'padding-right', 'padding-bottom', 'padding-left',
  'border-top-width', 'border-right-width', 'border-bottom-width', 'border-left-width',
  'border-top-style', 'border-left-style', 'border-top-color', 'border-left-color', 'border-radius',
  'color', 'background-color', 'fill', 'box-shadow', 'text-shadow',
  'font-family', 'font-size', 'font-weight', 'font-style', 'line-height', 'letter-spacing', 'text-transform', 'text-decoration-line',
  'white-space', 'justify-content', 'align-items', 'flex-grow', 'flex-wrap', 'overflow-x', 'overflow-y', 'max-height', 'cursor',
];
const PSEUDO_PROPERTIES = ['content', 'display', 'position', 'opacity', 'transform', 'width', 'height', 'top', 'right', 'bottom', 'left',
  'background-color', 'border-top-width', 'border-right-width', 'border-bottom-width', 'border-left-width', 'border-top-color', 'border-radius', 'z-index'];

/** Computed styles and widget-relative geometry of every element of every part. */
function describe(page, parts, element = ELEMENT) {
  return page.evaluate(({ parts, element, properties, pseudoProperties }) => {
    const widget = document.querySelector(`.elementor-element-${element}`);
    const origin = widget.getBoundingClientRect();
    const pick = (style, names) => Object.fromEntries(names.map(name => [name, style.getPropertyValue(name)]));
    const round = value => Math.round(value * 2) / 2;

    return Object.fromEntries(Object.entries(parts).map(([part, selector]) => [part, [...widget.querySelectorAll(selector)].map(node => {
      const rect = node.getBoundingClientRect();
      const entry = {
        rect: [round(rect.left - origin.left), round(rect.top - origin.top), round(rect.width), round(rect.height)],
        style: pick(getComputedStyle(node), properties),
      };
      if (node.tagName === 'A') {
        entry.before = pick(getComputedStyle(node, '::before'), pseudoProperties);
        entry.after = pick(getComputedStyle(node, '::after'), pseudoProperties);
      }

      return entry;
    })]));
  }, { parts, element, properties: PROPERTIES, pseudoProperties: PSEUDO_PROPERTIES });
}

function compare(label, reference, elemacy) {
  for (const part of Object.keys(reference)) {
    if (reference[part].length !== elemacy[part].length) {
      differences.push({ label, part, issue: `count ${reference[part].length} vs ${elemacy[part].length}` });
      continue;
    }
    reference[part].forEach((expected, index) => {
      for (const [key, value] of Object.entries(expected)) {
        const actual = elemacy[part][index][key];
        if (JSON.stringify(value) !== JSON.stringify(actual)) {
          const detail = typeof value === 'object' && !Array.isArray(value)
            ? Object.fromEntries(Object.keys(value).filter(name => value[name] !== actual[name]).map(name => [name, [value[name], actual[name]]]))
            : [value, actual];
          differences.push({ label, part, index, key, detail });
        }
      }
    });
  }
}

/** Fraction of pixels that differ by more than anti-aliasing between two PNG screenshots. */
async function pixelDifference(page, first, second) {
  return page.evaluate(async ([a, b]) => {
    const load = src => new Promise((resolve, reject) => {
      const image = new Image();
      image.onload = () => resolve(image);
      image.onerror = reject;
      image.src = 'data:image/png;base64,' + src;
    });
    const [one, two] = await Promise.all([load(a), load(b)]);
    if (one.width !== two.width || one.height !== two.height) return 1;
    const read = image => {
      const canvas = document.createElement('canvas');
      canvas.width = image.width;
      canvas.height = image.height;
      const context = canvas.getContext('2d');
      context.drawImage(image, 0, 0);

      return context.getImageData(0, 0, image.width, image.height).data;
    };
    const [x, y] = [read(one), read(two)];
    let different = 0;
    for (let i = 0; i < x.length; i += 4) {
      if (Math.abs(x[i] - y[i]) + Math.abs(x[i + 1] - y[i + 1]) + Math.abs(x[i + 2] - y[i + 2]) > 48) different++;
    }

    return different / (x.length / 4);
  }, [first.toString('base64'), second.toString('base64')]);
}

async function open(context, url, width, rtl) {
  const page = await context.newPage();
  await page.setViewportSize({ width, height: 900 });
  page.on('pageerror', error => errors.push({ kind: 'pageerror', message: error.message, url }));
  page.on('console', message => { if (message.type() === 'error') errors.push({ kind: 'console', message: message.text(), url }); });
  const target = new URL(url);
  if (rtl) target.searchParams.set('elemacy_fixture_rtl', '1');
  await page.goto(target.href, { waitUntil: 'networkidle' });
  await page.mouse.move(2, 2, { steps: 3 });

  return page;
}

/** Puts a page into a state: rest, hover end-state, open submenu, or open mobile dropdown. */
async function enter(page, side, state) {
  const parts = PARTS[side];
  const widget = `.elementor-element-${ELEMENT}`;

  if (state === 'hover' || state === 'submenu') {
    const item = page.locator(`${widget} ${parts.item}`).nth(1); // Services.
    if (!await item.isVisible()) return false;
    const rect = await item.boundingBox();
    await page.mouse.move(rect.x + rect.width / 2, rect.y + rect.height / 2, { steps: 4 });
    await page.waitForTimeout(state === 'submenu' ? 1000 : 700);
  }

  if (state === 'dropdown') {
    const toggle = page.locator(`${widget} ${parts.toggle}`);
    if (!await toggle.isVisible()) return false;
    await toggle.click();
    await page.mouse.move(2, 2, { steps: 3 });
    await page.waitForTimeout(700);
  }

  return true;
}

async function compareVariant(context, name, urls) {
  for (const [device, width] of Object.entries(WIDTHS)) {
    for (const rtl of [false, true]) {
      for (const state of ['rest', 'hover', 'submenu', 'dropdown']) {
        const label = `${name} ${device}${rtl ? ' rtl' : ''} ${state}`;
        const reference = await open(context, urls.reference, width, rtl);
        const elemacy = await open(context, urls.elemacy, width, rtl);
        try {
          const shownReference = await enter(reference, 'reference', state);
          const shownElemacy = await enter(elemacy, 'elemacy', state);
          assert.equal(shownElemacy, shownReference, `${label}: the same parts are reachable.`);
          if (!shownReference) continue;

          compare(label, await describe(reference, PARTS.reference), await describe(elemacy, PARTS.elemacy));

          if (state === 'submenu') {
            const shot = page => page.locator(`.elementor-element-${ELEMENT}`).screenshot({ animations: 'disabled' });
            const difference = await pixelDifference(reference, await shot(reference), await shot(elemacy));
            if (difference > 0.002) differences.push({ label, part: 'screenshot', detail: `${(difference * 100).toFixed(2)}% of pixels differ` });
          }
        } finally {
          await reference.close();
          await elemacy.close();
        }
      }
    }
  }
}

/** On one page with both widgets, each must look exactly as on its own page. */
async function compareTogether(context) {
  const together = fixture.parity.together;

  for (const side of ['reference', 'elemacy']) {
    const { element, variant } = together[side];
    const solo = await open(context, fixture.parity.variants[variant][side], WIDTHS.desktop, false);
    const shared = await open(context, together.url, WIDTHS.desktop, false);
    try {
      const strip = description => Object.fromEntries(Object.entries(description).map(([part, nodes]) => [part, nodes.map(({ style, before, after }) => ({ style, before, after }))]));
      // Positions differ between the pages; every style must not.
      compare(`together ${side} (${variant})`, strip(await describe(solo, PARTS[side])), strip(await describe(shared, PARTS[side], element)));
    } finally {
      await solo.close();
      await shared.close();
    }
  }

  const page = await open(context, together.url, WIDTHS.desktop, false);
  try {
    const leaks = await page.evaluate(() => ({
      elemacyInReference: document.querySelectorAll('.elementor-widget-nav-menu [class*="elemacy-nav-menu"]').length,
      referenceInElemacy: document.querySelectorAll('.elementor-widget-elemacy_nav_menu [class*="elementor-nav-menu"], .elementor-widget-elemacy_nav_menu .elementor-item, .elementor-widget-elemacy_nav_menu .sub-arrow').length,
    }));
    assert.deepEqual(leaks, { elemacyInReference: 0, referenceInElemacy: 0 }, 'Neither widget carries the other\'s classes.');

    for (const [id, item] of [['nmp0004', '.elementor-item'], ['nmp0005', '.elemacy-nav-menu__item']]) {
      const services = page.locator(`.elementor-element-${id} ${item}`).nth(1);
      const rect = await services.boundingBox();
      await page.mouse.move(rect.x + rect.width / 2, rect.y + rect.height / 2, { steps: 4 });
      await page.waitForTimeout(700);
      const open = await page.evaluate(() => [...document.querySelectorAll('ul.sub-menu')]
        .filter(list => !list.parentElement.closest('ul.sub-menu') && getComputedStyle(list).display !== 'none')
        .map(list => list.closest('.elementor-element[data-id]').dataset.id));
      assert.deepEqual([...new Set(open)], [id], `Hovering ${id} opens only its own submenu.`);
      await page.mouse.move(2, 2, { steps: 4 });
      await page.waitForTimeout(1200);
    }
  } finally {
    await page.close();
  }
}

try {
  fixture = JSON.parse(phpRun(FIXTURE, 'create'));

  if (!fixture.parity) {
    process.exitCode = 2;
    results.push('UNAVAILABLE: Elementor Pro is not active on this site; provision with --elementor-pro-plugin. Parity is not established.');
  } else {
    results.push(`INFO: reference Elementor Pro ${fixture.parity.elementor_pro}`);
    server = await startServer();
    browser = await chromium.launch(process.env.ELEMACY_CHROMIUM ? { executablePath: process.env.ELEMACY_CHROMIUM } : {});
    const context = await browser.newContext();

    for (const [name, urls] of Object.entries(fixture.parity.variants)) {
      const before = differences.length;
      try {
        await compareVariant(context, name, urls);
        const found = differences.length - before;
        if (found) process.exitCode = 1;
        results.push(`${found ? 'FAIL' : 'PASS'}: ${name} matches the reference at every width, direction and state${found ? ` (${found} differences)` : ''}`);
      } catch (error) {
        process.exitCode = 1;
        results.push(`FAIL: ${name}: ${error.stack || error.message}`);
      }
    }

    try {
      const before = differences.length;
      await compareTogether(context);
      if (differences.length > before) throw new Error(`${differences.length - before} differences on the shared page`);
      results.push('PASS: both widgets on one page keep their own look and behaviour');
    } catch (error) {
      process.exitCode = 1;
      results.push(`FAIL: coexistence: ${error.stack || error.message}`);
    }

    const unexpected = errors.filter(error => !/Failed to load resource/.test(error.message));
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
  if (server) await server.stop();
  if (fixture) {
    try {
      phpRun(FIXTURE, 'cleanup', fixture.manifest);
      results.push('PASS: browser fixture removed');
    } catch (error) {
      process.exitCode = 1;
      results.push(`FAIL: fixture cleanup: ${error.message}`);
    }
  }
  writeFileSync(report, JSON.stringify({ results, differences, errors }, null, 2) + '\n', { mode: 0o600 });
  console.log(results.join('\n'));
  if (differences.length) console.log(`First differences: ${JSON.stringify(differences.slice(0, 8), null, 1)}`);
  console.log(`Report: ${report}`);
}
