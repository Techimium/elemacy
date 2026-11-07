import { test as base, expect } from '@playwright/test';
import { appendFileSync, mkdirSync } from 'node:fs';
import path from 'node:path';
import { environment, fixture } from './environment.mjs';
import { captureOptions } from './options.mjs';
import { isEditorNavigationCancellation } from './diagnostics.mjs';

export const test = base.extend({
  author: async ({ context }, use, info) => {
    environment(); // Fail before native fixture creation for an unowned target.
    const owned = fixture('create');
    const capture = captureOptions();
    mkdirSync(capture.output, { recursive: true, mode: 0o700 });
    const secretPath = path.join(capture.output, '.capture-secrets.jsonl');
    const remember = value => {
      if (value) appendFileSync(secretPath, JSON.stringify(value) + '\n', { mode: 0o600 });
    };
    for (const cookie of owned.cookies) remember(cookie.value);
    context.on('request', request => {
      const headers = request.headers();
      remember(headers['x-wp-nonce']);
    });
    try {
      // Decorative external services are isolated; native application requests
      // and submission responses are never intercepted or replaced.
      if (process.env.ELEMACY_TEST_DECORATIVE_SERVICES !== 'live') {
        await context.route('https://fonts.googleapis.com/**', route => route.fulfill({ contentType: 'text/css', body: '' }));
        await context.route('https://secure.gravatar.com/avatar/**', route => route.fulfill({
          contentType: 'image/svg+xml',
          body: '<svg xmlns="http://www.w3.org/2000/svg" width="96" height="96"><rect width="96" height="96" fill="#ddd"/></svg>',
        }));
      }
      await context.addCookies(owned.cookies.map(cookie => ({ ...cookie, url: environment().url, httpOnly: true })));
      await use(owned);
    } finally {
      const result = fixture('cleanup', owned.manifest);
      expect(result.cleaned, 'Owned journey fixtures must be removed').toBe(true);
      await info.attach('cleanup', { body: JSON.stringify(result), contentType: 'application/json' });
    }
  },
  checkpoint: async ({ page }, use, info) => {
    await use(async (name, target = page) => {
      if (captureOptions().screenshots !== 'all') return;
      const filename = info.outputPath(name.replace(/[^a-z0-9-]/gi, '-') + '.png');
      await target.screenshot({ path: filename, animations: 'disabled', timeout: 15000 });
      await info.attach(name, { path: filename, contentType: 'image/png' });
    });
  },
  diagnostics: [async ({ page, browser }, use, info) => {
    await info.attach('browser-version', { body: browser.version(), contentType: 'text/plain' });
    const errors = [];
    const navigations = [];
    let previousUrl = page.url();
    page.on('framenavigated', frame => {
      if (frame !== page.mainFrame()) return;
      navigations.push({ from: previousUrl, to: frame.url(), time: Date.now() });
      previousUrl = frame.url();
    });
    page.on('pageerror', error => errors.push({ type: 'javascript', name: error.name, message: error.message, stack: error.stack, time: Date.now() }));
    page.on('console', message => {
      if (message.type() === 'error') errors.push({ type: 'console', message: message.text() });
    });
    page.on('response', response => {
      if (response.status() >= 400) errors.push({ type: 'http', status: response.status(), url: response.url() });
    });
    page.on('requestfailed', request => {
      if (request.failure()?.errorText !== 'net::ERR_ABORTED') {
        errors.push({ type: 'network', message: request.failure()?.errorText, url: request.url() });
      }
    });
    await use(errors);
    await info.attach('diagnostics', { body: JSON.stringify(errors), contentType: 'application/json' });
    const cancellations = errors.filter(error => isEditorNavigationCancellation(error, navigations));
    await info.attach('navigation-cancellations', { body: JSON.stringify(cancellations), contentType: 'application/json' });
    expect(errors.filter(error => !cancellations.includes(error)), 'Unexpected browser failures').toEqual([]);
  }, { auto: true }],
});

export { expect };
