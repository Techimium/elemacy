import { test, expect } from '../shared/fixtures.mjs';
import { fixture } from '../shared/environment.mjs';
import { readyEditor, addWidget, saveEditor } from '../shared/editor.mjs';
import { createTemplate } from '../shared/templates.mjs';

const WIDGET = '.elementor-widget-elemacy_nav_menu';

/** Opens the Nav Menu's panel on its Content > Layout section. */
async function openLayoutSection(page, frame) {
  await frame.locator(WIDGET).click();
  await expect(page.locator('select[data-setting="layout"]')).toBeVisible();
}

const select = (page, setting) => page.locator(`select[data-setting="${setting}"]`);

test('elemacy:journey:widget:elemacy_nav_menu @widgets', async ({ page, author, browser, checkpoint }) => {
  const { menu } = fixture('seed-menu', author.manifest);
  await createTemplate(page, { title: `Journey nav menu ${author.user}`, type: 'Header', edit: true });

  let frame = await addWidget(page, 'Elemacy Nav Menu', WIDGET);
  await openLayoutSection(page, frame);
  await select(page, 'menu').selectOption(menu.slug);
  await select(page, 'pointer').selectOption('overline');
  await select(page, 'animation_line').selectOption('grow');
  await select(page, 'dropdown').selectOption('mobile');
  await page.locator('.elementor-control-full_width .elementor-switch').click();

  const main = frame.locator(`${WIDGET} nav.elemacy-nav-menu__main`);
  await expect(main.getByRole('link', { name: 'Services', exact: true })).toBeVisible();
  await expect(main).toHaveClass(/elemacy-nav-menu__main--pointer-overline/);
  await expect(main).toHaveClass(/elemacy-nav-menu__main--animation-grow/);
  await expect(frame.locator(WIDGET)).toHaveClass(/elemacy-nav-menu--breakpoint-mobile/);
  await expect(frame.locator(WIDGET)).toHaveClass(/elemacy-nav-menu--stretch/);
  await checkpoint('nav-menu-authored');

  await saveEditor(page);
  await page.reload();
  frame = await readyEditor(page);
  await openLayoutSection(page, frame);
  await expect(select(page, 'menu')).toHaveValue(menu.slug);
  await expect(select(page, 'layout')).toHaveValue('horizontal');
  await expect(select(page, 'pointer')).toHaveValue('overline');
  await expect(select(page, 'animation_line')).toHaveValue('grow');
  await expect(select(page, 'dropdown')).toHaveValue('mobile');
  await expect(page.locator('.elementor-control-full_width input[type="checkbox"]')).toBeChecked();
  await checkpoint('nav-menu-saved-and-reopened');

  const guest = await browser.newContext();
  try {
    const visitor = await guest.newPage();
    await visitor.goto(new URL('/', page.url()).href);
    const nav = visitor.locator(`${WIDGET} nav.elemacy-nav-menu__main`);
    const services = nav.getByRole('link', { name: 'Services', exact: true });
    await expect(services).toBeVisible();
    await expect(visitor.locator(`${WIDGET} .elemacy-nav-menu__toggle`)).toBeHidden();

    await services.hover();
    await expect(nav.getByRole('link', { name: 'Design', exact: true })).toBeVisible();
    await expect(services).toHaveAttribute('aria-expanded', 'true');
    await checkpoint('visitor-desktop-submenu', visitor);

    await visitor.setViewportSize({ width: 390, height: 800 });
    const toggle = visitor.locator(`${WIDGET} .elemacy-nav-menu__toggle`);
    await expect(nav).toBeHidden();
    await expect(toggle).toBeVisible();
    await toggle.click();
    await expect(toggle).toHaveAttribute('aria-expanded', 'true');
    const dropdown = visitor.locator(`${WIDGET} nav.elemacy-nav-menu__dropdown`);
    await expect(dropdown.getByRole('link', { name: 'Home', exact: true })).toBeVisible();
    await checkpoint('visitor-mobile-dropdown-open', visitor);
    await toggle.click();
    await expect(toggle).toHaveAttribute('aria-expanded', 'false');
    await expect(dropdown).toHaveAttribute('aria-hidden', 'true');
  } finally {
    await guest.close();
  }
});
