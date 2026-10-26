import { test, expect } from '../shared/fixtures.mjs';

const modules = [
  ['theme-builder', 'Theme Builder'],
  ['dynamic-tags', 'Dynamic Tags'],
  ['widgets', 'Widgets'],
  ['custom-css', 'Custom CSS'],
  ['popups', 'Popups & Floating Elements'],
];

for (const [name, title] of modules) {
  test(`elemacy:journey:module:${name} @modules @smoke`, async ({ page, author, checkpoint }) => {
    expect(author.user).toBeGreaterThan(0);
    await page.goto('/wp-admin/admin.php?page=elemacy#/modules', { waitUntil: 'domcontentloaded' });
    const card = page.locator('[data-slot="card"]').filter({ has: page.getByText(title, { exact: true }) });
    const toggle = card.getByRole('switch', { name: 'Toggle Module' });
    await expect(toggle).toBeChecked();
    await checkpoint('enabled');
    const saved = page.waitForResponse(response => response.url().includes('/modules/') && response.request().method() !== 'GET');
    await toggle.click();
    expect((await saved).ok()).toBe(true);
    await page.reload();
    await expect(toggle).not.toBeChecked();
    await checkpoint('disabled-and-reopened');
    if (['theme-builder', 'widgets', 'popups'].includes(name)) {
      await page.goto(`/wp-admin/admin.php?page=elemacy#/${name}`, { waitUntil: 'domcontentloaded' });
      await expect(page.getByText('Module is inactive', { exact: true })).toBeVisible();
      await page.goto('/wp-admin/admin.php?page=elemacy#/modules', { waitUntil: 'domcontentloaded' });
    }
    const restored = page.waitForResponse(response => response.url().includes('/modules/') && response.request().method() !== 'GET');
    await toggle.click();
    expect((await restored).ok()).toBe(true);
    await page.reload();
    await expect(toggle).toBeChecked();
    await checkpoint('restored-and-reopened');
  });
}
