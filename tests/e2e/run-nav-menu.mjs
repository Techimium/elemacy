/**
 * Real browser behaviour of the Nav Menu widget (openspec widgets/nav-menu):
 * hover, touch and keyboard submenus, viewport repositioning, the mobile
 * dropdown and its toggle, full width, sticky headers, anchor tracking,
 * breakpoints, RTL, two widgets on one page, and live editing.
 *
 * Every scenario is a named function below, so a failure reads as the
 * behaviour that broke. Parity with Elementor Pro is run-nav-menu-parity.mjs.
 */
import assert from 'node:assert/strict';
import { chromium } from 'playwright-core';
import { writeFileSync } from 'node:fs';
import { base, phpRun, reportPath, signIn, startServer } from './shared/native-site.mjs';

const FIXTURE = 'nav-menu-browser-fixture.php';
const report = reportPath('nav-menu-report.json');
const results = [];
const errors = [];
let fixture;
let server;
let browser;

const DESKTOP = { width: 1280, height: 800 };
const TABLET = { width: 1024, height: 800 };
const MOBILE = { width: 390, height: 800 };

/** The widget with the given element ID, and its parts. */
function widget(page, id) {
  const root = page.locator(`.elementor-element-${id}`);

  return {
    root,
    main: root.locator('nav.elemacy-nav-menu__main'),
    toggle: root.locator('.elemacy-nav-menu__toggle'),
    dropdown: root.locator('nav.elemacy-nav-menu__dropdown'),
    mainLink: name => root.locator('nav.elemacy-nav-menu__main').getByRole('link', { name, exact: true, includeHidden: true }),
    dropdownLink: name => root.locator('nav.elemacy-nav-menu__dropdown').getByRole('link', { name, exact: true, includeHidden: true }),
  };
}

/** The submenu a parent link controls. */
async function submenuOf(link) {
  const id = await link.getAttribute('aria-controls');
  assert.ok(id, 'A parent link names the submenu it controls.');

  return link.page().locator(`[id="${id}"]`);
}

const isShown = locator => locator.evaluate(element => getComputedStyle(element).display !== 'none');
const box = locator => locator.evaluate(element => element.getBoundingClientRect().toJSON());

async function open(context, url, viewport = DESKTOP) {
  const page = await context.newPage();
  await page.setViewportSize(viewport);
  page.on('pageerror', error => errors.push({ kind: 'pageerror', message: error.message, url }));
  page.on('console', message => { if (message.type() === 'error') errors.push({ kind: 'console', message: message.text(), url }); });
  await page.goto(url, { waitUntil: 'networkidle' });

  return page;
}

/** Moves the mouse over an element's centre, so menus see a real mouse. */
async function hover(page, locator) {
  const rect = await box(locator);
  await page.mouse.move(rect.x + rect.width / 2, rect.y + rect.height / 2, { steps: 4 });
}

const awayFromMenus = page => page.mouse.move(640, 700, { steps: 4 });

const scenarios = {
  async 'Hover opens a submenu after 250 ms, keeps it while inside, and fades it out 500 ms after leaving'(context) {
    const page = await open(context, fixture.urls.horizontal);
    const nav = widget(page, 'nmw0001');
    const services = nav.mainLink('Services');
    const submenu = await submenuOf(services);

    await page.mouse.move(10, 10, { steps: 3 });
    await hover(page, services);
    await page.waitForTimeout(120);
    assert.equal(await isShown(submenu), false, 'Not open before the hover delay.');
    await page.waitForTimeout(330);
    assert.equal(await isShown(submenu), true, 'Open after the hover delay.');
    assert.ok((await services.getAttribute('class')).includes('elemacy-nav-menu__link--open'), 'The parent stays highlighted.');
    assert.equal(await services.getAttribute('aria-expanded'), 'true');
    assert.equal(await submenu.getAttribute('aria-hidden'), 'false');

    await hover(page, nav.mainLink('Design'));
    await page.waitForTimeout(600);
    assert.equal(await isShown(submenu), true, 'Moving into the submenu keeps it open.');

    await awayFromMenus(page);
    await page.waitForTimeout(300);
    assert.equal(await isShown(submenu), true, 'Still open shortly after leaving.');
    await page.waitForTimeout(260);
    assert.ok(await submenu.evaluate(element => element.getAnimations().length > 0 || getComputedStyle(element).display === 'none'), 'It fades out.');
    await page.waitForTimeout(400);
    assert.equal(await isShown(submenu), false, 'Closed after the delay and fade.');
    assert.equal(await services.getAttribute('aria-expanded'), 'false');
    await page.close();
  },

  async 'A cursor that lands straight on a parent opens its submenu'(context) {
    const page = await open(context, fixture.urls.horizontal);
    const services = widget(page, 'nmw0001').mainLink('Services');

    // One jump, no earlier movement: the browser fires mouseenter before the first pointermove.
    await services.hover();
    await page.waitForTimeout(450);
    assert.equal(await isShown(await submenuOf(services)), true);
    await page.close();
  },

  async 'Passing over a parent for less than 250 ms does not open it'(context) {
    const page = await open(context, fixture.urls.horizontal);
    const services = widget(page, 'nmw0001').mainLink('Services');
    const submenu = await submenuOf(services);

    await page.mouse.move(10, 10, { steps: 3 });
    await hover(page, services);
    await page.waitForTimeout(100);
    await awayFromMenus(page);
    await page.waitForTimeout(400);
    assert.equal(await isShown(submenu), false);
    await page.close();
  },

  async 'Nested submenus open beside their parent and flip at the viewport edge'(context) {
    const page = await open(context, fixture.urls.horizontal);
    const nav = widget(page, 'nmw0001');

    await page.mouse.move(10, 10, { steps: 3 });
    await hover(page, nav.mainLink('Services'));
    await page.waitForTimeout(400);
    await hover(page, nav.mainLink('Design'));
    await page.waitForTimeout(400);
    const design = await box(nav.mainLink('Design'));
    const branding = await box(await submenuOf(nav.mainLink('Design')));
    assert.ok(Math.abs(branding.left - design.right) < 2, 'Opens to the right of its parent when it fits.');
    assert.ok(Math.abs(branding.top - design.top) < 2, 'Aligned with its parent item.');

    await hover(page, nav.mainLink('Edge'));
    await page.waitForTimeout(400);
    const edgeMenu = await submenuOf(nav.mainLink('Edge'));
    const edgeMenuBox = await box(edgeMenu);
    assert.ok(edgeMenuBox.right <= DESKTOP.width + 0.5, 'A top-level dropdown shifts to stay on screen.');

    await hover(page, nav.mainLink('Edge child'));
    await page.waitForTimeout(400);
    const child = await box(nav.mainLink('Edge child'));
    const grandchild = await box(await submenuOf(nav.mainLink('Edge child')));
    assert.ok(grandchild.right <= DESKTOP.width + 0.5, 'The nested submenu stays on screen.');
    assert.ok(Math.abs(grandchild.right - child.left) < 2, 'It flips to the left of its parent.');
    await page.close();
  },

  async 'The vertical layout opens submenus beside the item'(context) {
    const page = await open(context, fixture.urls.vertical);
    const nav = widget(page, 'nmw0002');
    const services = nav.mainLink('Services');

    await page.mouse.move(600, 10, { steps: 3 });
    await hover(page, services);
    await page.waitForTimeout(400);
    const item = await box(services);
    const submenu = await box(await submenuOf(services));
    assert.ok(Math.abs(submenu.left - item.right) < 2 && Math.abs(submenu.top - item.top) < 2);
    await page.close();
  },

  async 'Submenus are at least 10em wide'(context) {
    const page = await open(context, fixture.urls.horizontal);
    const services = widget(page, 'nmw0001').mainLink('Services');
    await page.mouse.move(10, 10, { steps: 3 });
    await hover(page, services);
    await page.waitForTimeout(400);
    const submenu = await submenuOf(services);
    const [width, em] = await submenu.evaluate(element => [element.getBoundingClientRect().width, parseFloat(getComputedStyle(element).fontSize)]);
    assert.ok(width >= 10 * em - 0.5, `Width ${width} >= 10em.`);
    await page.close();
  },

  async 'On touch, the first tap opens a submenu and the second follows its link'(context) {
    const page = await open(context, fixture.urls.horizontal);
    const services = widget(page, 'nmw0001').mainLink('Services');
    const submenu = await submenuOf(services);

    await services.tap();
    await page.waitForTimeout(100);
    assert.equal(await isShown(submenu), true, 'The first tap opens it.');
    assert.equal(page.url(), fixture.urls.horizontal, 'The first tap does not navigate.');

    await Promise.all([page.waitForURL(url => url.pathname === '/services/'), services.tap()]);
    await page.close();
  },

  async 'A tap outside the menu closes it'(context) {
    const page = await open(context, fixture.urls.horizontal);
    const services = widget(page, 'nmw0001').mainLink('Services');
    const submenu = await submenuOf(services);

    await services.tap();
    assert.equal(await isShown(submenu), true);
    await page.touchscreen.tap(640, 700);
    await page.waitForTimeout(600);
    assert.equal(await isShown(submenu), false);
    await page.close();
  },

  async 'Space opens a submenu, focus moves into it, and Escape closes all and returns focus'(context) {
    const page = await open(context, fixture.urls.horizontal);
    const nav = widget(page, 'nmw0001');
    const services = nav.mainLink('Services');
    const submenu = await submenuOf(services);

    await services.focus();
    await page.keyboard.press('Space');
    assert.equal(await isShown(submenu), true, 'Space opens it.');
    assert.equal(await page.evaluate(() => window.scrollY), 0, 'Space does not scroll the page.');

    await page.keyboard.press('Tab');
    assert.equal(await page.evaluate(() => document.activeElement.textContent.trim()), 'Design', 'Tab moves into the submenu.');
    await page.waitForTimeout(100);
    assert.equal(await isShown(await submenuOf(nav.mainLink('Design'))), true, 'Focus opens the focused item\'s submenu once one was opened.');

    await page.keyboard.press('Escape');
    await page.waitForTimeout(400);
    assert.equal(await isShown(submenu), false, 'Escape closes every submenu.');
    assert.equal(await page.evaluate(() => document.activeElement.textContent.trim()), 'Services', 'Focus returns to the top-level item.');
    await page.close();
  },

  async 'A click outside the menu closes open submenus'(context) {
    const page = await open(context, fixture.urls.horizontal);
    const services = widget(page, 'nmw0001').mainLink('Services');
    const submenu = await submenuOf(services);

    await services.focus();
    await page.keyboard.press('Space');
    await page.mouse.click(640, 700);
    await page.waitForTimeout(400);
    assert.equal(await isShown(submenu), false);
    await page.close();
  },

  async 'The main menu and the toggle switch exactly at the tablet breakpoint'(context) {
    const page = await open(context, fixture.urls.horizontal, { width: 1025, height: 800 });
    const nav = widget(page, 'nmw0001');
    assert.equal(await nav.main.isVisible(), true, 'Above the breakpoint the main menu shows.');
    assert.equal(await nav.toggle.isVisible(), false);

    await page.setViewportSize(TABLET);
    assert.equal(await nav.main.isVisible(), false, 'At the breakpoint the main menu hides.');
    assert.equal(await nav.toggle.isVisible(), true);
    await page.close();
  },

  async 'The dropdown layout shows only the toggle at desktop width'(context) {
    const page = await open(context, fixture.urls.dropdown);
    const nav = widget(page, 'nmw0003');
    assert.equal(await nav.main.count(), 0);
    assert.equal(await nav.toggle.isVisible(), true);
    await nav.toggle.click();
    await page.waitForTimeout(400);
    assert.ok((await box(nav.dropdown)).height > 0, 'The toggle opens the whole menu as a dropdown.');
    await page.close();
  },

  async 'The toggle opens and closes the mobile dropdown by keyboard, with ARIA, tab order and icons'(context) {
    const page = await open(context, fixture.urls.horizontal, MOBILE);
    const nav = widget(page, 'nmw0001');
    const home = nav.dropdownLink('Home');
    assert.equal(await nav.dropdown.getAttribute('aria-hidden'), 'true');
    assert.equal(await home.getAttribute('tabindex'), '-1');
    assert.equal((await box(nav.dropdown)).height, 0, 'Closed.');

    await nav.toggle.focus();
    await page.keyboard.press('Enter');
    await page.waitForTimeout(400);
    assert.equal(await nav.toggle.getAttribute('aria-expanded'), 'true');
    assert.equal(await nav.dropdown.getAttribute('aria-hidden'), 'false');
    assert.equal(await home.getAttribute('tabindex'), '0');
    assert.ok((await box(nav.dropdown)).height > 0, 'Open.');
    assert.equal(await nav.toggle.locator('.elemacy-nav-menu__toggle-icon--close').isVisible(), true, 'The close icon shows.');
    assert.equal(await nav.toggle.locator('.elemacy-nav-menu__toggle-icon--open').isVisible(), false);

    await page.keyboard.press('Space');
    await page.waitForTimeout(400);
    assert.equal(await nav.toggle.getAttribute('aria-expanded'), 'false', 'Space closes it.');
    assert.equal(await home.getAttribute('tabindex'), '-1');
    assert.equal(await page.evaluate(() => window.scrollY), 0, 'Space does not scroll the page.');
    await page.close();
  },

  async 'Submenus in the mobile dropdown slide open in place, indented, and close from the indicator'(context) {
    const page = await open(context, fixture.urls.horizontal, MOBILE);
    const nav = widget(page, 'nmw0001');
    await nav.toggle.click();
    await page.waitForTimeout(400);

    const services = nav.dropdownLink('Services');
    const submenu = await submenuOf(services);
    await services.click();
    await page.waitForTimeout(90);
    const midway = (await box(submenu)).height;
    await page.waitForTimeout(300);
    const full = (await box(submenu)).height;
    assert.ok(midway > 0 && midway < full, `It slides (${midway} of ${full}px midway).`);
    assert.equal(page.url(), fixture.urls.horizontal, 'The first tap does not navigate.');

    await nav.dropdownLink('Design').click();
    await page.waitForTimeout(400);
    const indent = name => nav.dropdownLink(name).evaluate(element => getComputedStyle(element).borderLeftWidth);
    assert.equal(await indent('Design'), '8px', 'Second level indent.');
    assert.equal(await indent('Branding'), '16px', 'Third level indent.');

    await services.locator('.elemacy-nav-menu__indicator').click();
    await page.waitForTimeout(400);
    assert.equal(await isShown(submenu), false, 'The indicator closes an open submenu.');

    await services.click();
    await page.waitForTimeout(400);
    await Promise.all([page.waitForURL(url => url.pathname === '/services/'), services.click()]);
    await page.close();
  },

  async 'A full width dropdown spans the viewport below the toggle, refits on resize, and closes after a final item'(context) {
    const page = await open(context, fixture.urls.full_width, MOBILE);
    const nav = widget(page, 'nmw0004');
    await nav.toggle.click();
    await page.waitForTimeout(400);

    const viewportWidth = () => page.evaluate(() => document.documentElement.clientWidth);
    const toggle = await box(nav.toggle);
    const distance = await nav.dropdown.evaluate(element => parseFloat(getComputedStyle(element).marginTop));
    let dropdown = await box(nav.dropdown);
    assert.ok(Math.abs(dropdown.left) < 1 && Math.abs(dropdown.width - await viewportWidth()) < 1, 'Spans the viewport.');
    assert.ok(Math.abs(dropdown.top - toggle.bottom - distance) < 1.5, 'Right below the toggle, at the Dropdown distance.');

    await page.setViewportSize({ width: 360, height: 800 });
    await page.waitForTimeout(300);
    dropdown = await box(nav.dropdown);
    assert.ok(Math.abs(dropdown.width - await viewportWidth()) < 1, 'Refits after a resize.');

    await nav.dropdownLink('Strategy').evaluate(element => element.addEventListener('click', event => event.preventDefault()));
    await nav.dropdownLink('Services').click();
    await page.waitForTimeout(400);
    await nav.dropdownLink('Strategy').click();
    assert.equal(await nav.toggle.getAttribute('aria-expanded'), 'false', 'Choosing a final item closes the dropdown.');
    await page.close();
  },

  async 'In a sticky header the open dropdown only uses the space below it'(context) {
    const page = await open(context, fixture.urls.sticky, { width: 390, height: 600 });
    const nav = widget(page, 'nmw0005');
    await page.evaluate(() => window.scrollTo(0, 800));
    await page.waitForTimeout(300);
    await nav.toggle.click();
    await page.waitForTimeout(400);

    const [height, top, maxHeight] = await nav.dropdown.evaluate(element => [
      element.style.getPropertyValue('--elemacy-nav-menu-height'), element.getBoundingClientRect().top, getComputedStyle(element).maxHeight,
    ]);
    assert.equal(height, `${600 - top}px`, 'The height is the viewport space below the dropdown.');
    assert.equal(maxHeight, height);
    await page.close();
  },

  async 'One-page anchor links are active while their section is in view'(context) {
    const page = await open(context, fixture.urls.one_page);
    const nav = widget(page, 'nmw0007');
    const about = nav.mainLink('About');
    const contact = nav.mainLink('Contact');
    const active = link => link.evaluate(element => element.classList.contains('elemacy-nav-menu__link--active'));

    assert.equal(await active(about), false, 'Not active before its section is reached.');
    await page.evaluate(() => document.getElementById('about').scrollIntoView());
    await page.waitForTimeout(300);
    assert.equal(await active(about), true);
    assert.equal(await about.getAttribute('aria-current'), 'location');
    assert.equal(await active(contact), false);

    await page.evaluate(() => document.getElementById('contact').scrollIntoView());
    await page.waitForTimeout(300);
    assert.equal(await active(about), false);
    assert.equal(await active(contact), true);
    assert.equal(await about.getAttribute('aria-current'), null);
    await page.close();
  },

  async 'Two widgets on one page open, toggle and style only themselves'(context) {
    const page = await open(context, fixture.urls.two);
    const first = widget(page, 'nmw0009');
    const second = widget(page, 'nmw0010');

    await page.mouse.move(10, 10, { steps: 3 });
    await hover(page, first.mainLink('Services'));
    await page.waitForTimeout(400);
    assert.equal(await isShown(await submenuOf(first.mainLink('Services'))), true);
    assert.equal(await isShown(await submenuOf(second.mainLink('Services'))), false);

    const ids = await page.locator('.elemacy-nav-menu__list').evaluateAll(lists => lists.map(list => list.id));
    assert.equal(new Set(ids).size, ids.length, 'Every menu tree has its own ID.');
    assert.notEqual(await first.main.getAttribute('class'), await second.main.getAttribute('class'), 'Each keeps its own pointer.');

    await page.setViewportSize(MOBILE);
    await first.toggle.click();
    await page.waitForTimeout(400);
    assert.equal(await first.toggle.getAttribute('aria-expanded'), 'true');
    assert.equal(await second.toggle.getAttribute('aria-expanded'), 'false');
    await page.close();
  },

  async 'A right-to-left page aligns to the left edge and turns submenu indicators the other way'(context) {
    const rtl = new URL(fixture.urls.horizontal);
    rtl.searchParams.set('elemacy_fixture_rtl', '1');
    const page = await open(context, rtl.href);
    const nav = widget(page, 'nmw0001');
    assert.equal(await page.evaluate(() => document.documentElement.dir), 'rtl');

    const list = await box(nav.main.locator('.elemacy-nav-menu__list'));
    assert.ok(list.left < DESKTOP.width / 2, 'End alignment is the left edge.');

    // A hidden box has no resolved transform, so open the submenu first.
    await page.mouse.move(10, 10, { steps: 3 });
    await hover(page, nav.mainLink('Services'));
    await page.waitForTimeout(450);
    const indicator = nav.mainLink('Design').locator('.elemacy-nav-menu__indicator > *');
    const matrix = await indicator.evaluate(element => getComputedStyle(element).transform);
    assert.match(matrix, /^matrix\((?:6\.12323e-17|0), 1, -1, (?:6\.12323e-17|0)/, `Rotated +90deg (${matrix}).`);
    await page.close();
  },

  async 'The live editor re-fits Full Width and never doubles the toggle after re-renders'(context) {
    const page = await context.newPage();
    await page.setViewportSize({ width: 1500, height: 900 });
    page.on('pageerror', error => errors.push({ kind: 'pageerror', message: error.message, url: 'editor' }));
    await signIn(page);
    await page.goto(fixture.editor_url, { waitUntil: 'load' });
    await page.waitForFunction(() => window.$e && window.elementor && elementor.getPreviewContainer && elementor.getPreviewContainer(), null, { timeout: 60_000 });
    const frame = await (await page.waitForSelector('#elementor-preview-iframe')).contentFrame();
    await frame.waitForSelector('.elementor-element-nmw0004 .elemacy-nav-menu__toggle', { state: 'attached', timeout: 60_000 });

    await page.evaluate(() => {
      window.navMenu = () => elementor.getPreviewContainer().findChildrenRecursive(c => c.model.get('widgetType') === 'elemacy_nav_menu');
      elementor.changeDeviceMode('mobile');
    });
    await page.waitForTimeout(1500);
    const settings = values => page.evaluate(next => $e.run('document/elements/settings', { container: window.navMenu(), settings: next }), values);

    // Two re-renders (render_type: template), then one click must still open it once.
    // Each change re-renders the widget on the server; wait for the new element each time.
    for (const animation of ['grow', 'shrink']) {
      await settings({ toggle_icon_hover_animation: animation });
      await frame.waitForSelector(`.elementor-element-nmw0004 .elemacy-nav-menu__toggle-icon--open.elementor-animation-${animation}`, { state: 'attached', timeout: 20_000 });
      await page.waitForTimeout(300);
    }
    // A handler left behind by a re-render would toggle a second time on the same click. Record
    // every change of the toggle's state for one click (dispatched: Elementor's edit handles
    // overlay the widget, and a pointer click there selects the widget instead).
    const toggle = frame.locator('.elementor-element-nmw0004 .elemacy-nav-menu__toggle');
    const changes = await toggle.evaluate(element => new Promise(resolve => {
      const seen = [];
      new MutationObserver(records => records.forEach(() => seen.push(element.getAttribute('aria-expanded'))))
        .observe(element, { attributes: true, attributeFilter: ['aria-expanded'] });
      element.dispatchEvent(new MouseEvent('click', { bubbles: true }));
      setTimeout(() => resolve(seen), 400);
    }));
    assert.deepEqual(changes, ['true'], 'One click opens it exactly once after re-renders.');

    const width = () => frame.locator('.elementor-element-nmw0004 nav.elemacy-nav-menu__dropdown').evaluate(element => element.style.width);
    assert.notEqual(await width(), '', 'Stretched while Full Width is on.');

    // Flip the switch in the panel, as a builder does: that is what tells widget handlers a setting changed.
    await page.evaluate(() => {
      const menu = window.navMenu();
      $e.run('panel/editor/open', { model: menu.model, view: menu.view });
    });
    const fullWidth = page.locator('.elementor-control-full_width .elementor-switch');
    await fullWidth.waitFor({ state: 'visible', timeout: 20_000 });
    await fullWidth.click();
    await page.waitForTimeout(600);
    assert.equal(await width(), '', 'Full Width off removes the stretch without a reload.');
    await fullWidth.click();
    await page.waitForTimeout(600);
    assert.notEqual(await width(), '', 'Full Width on stretches it again.');
    await page.close();
  },

  async 'A custom Tablet breakpoint moves the switch to its value'(context) {
    phpRun(FIXTURE, 'breakpoint', fixture.manifest, 900);
    const page = await open(context, fixture.urls.horizontal, { width: 901, height: 800 });
    const nav = widget(page, 'nmw0001');
    assert.equal(await nav.main.isVisible(), true, 'Above 900px the main menu shows.');

    await page.setViewportSize({ width: 900, height: 800 });
    assert.equal(await nav.main.isVisible(), false, 'At 900px the toggle takes over.');
    assert.equal(await nav.toggle.isVisible(), true);
    await page.close();
  },
};

try {
  fixture = JSON.parse(phpRun(FIXTURE, 'create'));
  server = await startServer();
  browser = await chromium.launch(process.env.ELEMACY_CHROMIUM ? { executablePath: process.env.ELEMACY_CHROMIUM } : {});

  for (const [name, scenario] of Object.entries(scenarios)) {
    const touch = /touch|tap/i.test(name);
    const context = await browser.newContext({ hasTouch: touch, baseURL: base.href });
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

  // Navigating to the fixture's placeholder URLs (/services/ ...) is a 404 page, not an error of the widget.
  const unexpected = errors.filter(error => !/Failed to load resource/.test(error.message));
  if (unexpected.length) {
    process.exitCode = 1;
    results.push(`FAIL: unexpected browser errors: ${JSON.stringify(unexpected.slice(0, 5))}`);
  } else {
    results.push('PASS: no unexpected browser JavaScript or console errors');
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
  writeFileSync(report, JSON.stringify({ results, errors }, null, 2) + '\n', { mode: 0o600 });
  console.log(results.join('\n'));
  console.log(`Report: ${report}`);
}
