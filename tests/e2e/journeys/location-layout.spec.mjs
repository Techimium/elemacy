import { test, expect } from '../shared/fixtures.mjs';
import { readyEditor, addHeading, saveEditor } from '../shared/editor.mjs';
import { createTemplate } from '../shared/templates.mjs';
import { environment, fixture } from '../shared/environment.mjs';

// Elementor's "Meet Angie" promotion can pop up over the editor panel and
// intercept clicks; close it when it is there rather than fail on it.
async function dismissPromotions(page) {
  const close = page.getByRole('button', { name: /close angie/i });
  if (await close.first().isVisible().catch(() => false)) await close.first().click();
}

async function openDocumentSettings(page) {
  await dismissPromotions(page);
  await page.getByRole('button', { name: /Settings$/ }).first().click();
  const layout = page.locator('select[data-setting="template"]');
  await expect(layout).toBeVisible();
  return layout;
}

test('elemacy:journey:location:canvas @theme-builder', async ({ page, author, checkpoint, browser }) => {
  const title = `Journey canvas single ${author.user}`;
  const text = `Human-authored canvas single ${author.user}`;

  await createTemplate(page, { title, type: 'Single', edit: true });
  await readyEditor(page);
  await addHeading(page, text);
  await saveEditor(page);

  // Changing the layout reloads the preview, so do it on its own after the first
  // save and let the editor settle before saving again.
  await (await openDocumentSettings(page)).selectOption('elementor_canvas');
  await checkpoint('page-layout-canvas');
  await readyEditor(page);
  await expect(page.getByRole('button', { name: /^(Publish|Update|Save Changes)$/ })).toBeEnabled();
  await saveEditor(page);

  await page.reload();
  await readyEditor(page);
  await expect(await openDocumentSettings(page), 'The Page Layout persists after reopening').toHaveValue('elementor_canvas');
  await checkpoint('layout-persisted');

  const guest = await browser.newContext();
  try {
    const visitor = await guest.newPage();
    await visitor.goto(new URL('/?p=1', page.url()).href);
    await expect(visitor.getByText(text, { exact: true })).toBeVisible();
    await expect(visitor.locator('body.elementor-template-canvas'), 'Served as an Elementor Canvas page').toHaveCount(1);
    await expect(visitor.locator('.site-header, #site-header, header#masthead'), 'No theme header on a Canvas page').toHaveCount(0);
    await expect(visitor.locator('.site-footer, #site-footer, footer#colophon'), 'No theme footer on a Canvas page').toHaveCount(0);
    await checkpoint('visitor-rendered', visitor);
  } finally {
    await guest.close();
  }
});

// A Single template on a block theme keeps the theme's own header and footer
// template parts when no Elemacy Header or Footer applies.
test('elemacy:journey:location:block-theme-chrome @theme-builder @block-theme', async ({ page, author, checkpoint, browser }, info) => {
  const switched = fixture('use-theme', author.manifest, 'twentytwentyfive');
  test.skip(!switched.installed, 'Twenty Twenty-Five is not installed in this environment.');

  const guest = await browser.newContext();
  try {
    const visitor = await guest.newPage();
    const post = new URL('/?p=1', environment().url).href;
    const chrome = async label => {
      await visitor.goto(post);
      const header = visitor.locator('header.wp-block-template-part');
      await expect(header, `${label}: the theme's header part is shown`).toHaveCount(1);
      await expect(visitor.locator('footer.wp-block-template-part'), `${label}: the theme's footer part is shown`).toHaveCount(1);
      await expect(header.locator('.wp-block-site-title')).toBeVisible();
      await expect(header.locator('nav.wp-block-navigation')).toBeAttached();
      await visitor.waitForLoadState('load');
      const shot = info.outputPath(`block-theme-header-${label}.png`);
      await header.screenshot({ path: shot, animations: 'disabled' });
      await info.attach(`block-theme-header-${label}`, { path: shot, contentType: 'image/png' });
      return {
        title: (await header.locator('.wp-block-site-title').innerText()).trim(),
        navigation: await header.locator('nav.wp-block-navigation').innerText(),
        box: await header.boundingBox(),
        navigationBox: await header.locator('nav.wp-block-navigation').boundingBox(),
        titleWeight: await header.locator('.wp-block-site-title').evaluate(element => getComputedStyle(element).fontWeight),
      };
    };

    const plain = await chrome('plain-post');

    const text = `Human-authored block-theme single ${author.user}`;
    await createTemplate(page, { title: `Journey block-theme single ${author.user}`, type: 'Single', edit: true });
    await readyEditor(page);
    await addHeading(page, text);
    await saveEditor(page);

    const templated = await chrome('single-template');
    await expect(visitor.getByText(text, { exact: true }), 'The Single template is served').toBeVisible();
    await expect(visitor.locator('.wp-site-blocks header.wp-block-template-part'), "The header sits in the theme's site wrapper").toHaveCount(1);
    expect(templated.title, 'Same site title').toBe(plain.title);
    expect(templated.navigation, 'Same navigation').toBe(plain.navigation);
    expect(Math.abs(templated.box.height - plain.box.height), 'Same header height').toBeLessThanOrEqual(2);
    expect(Math.abs(templated.box.x - plain.box.x), 'Same header position').toBeLessThanOrEqual(2);
    expect(Math.abs(templated.box.width - plain.box.width), 'Same header width').toBeLessThanOrEqual(2);
    // The theme's layout and block-support styles apply: the title keeps its
    // weight and the navigation its place on the right.
    expect(templated.titleWeight, 'Same site title weight').toBe(plain.titleWeight);
    expect(Math.abs(templated.navigationBox.x - plain.navigationBox.x), 'Navigation in the same place').toBeLessThanOrEqual(2);
    await checkpoint('block-theme-single', visitor);
  } finally {
    await guest.close();
  }
});
