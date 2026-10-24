import { defineConfig } from '@playwright/test';
import path from 'node:path';
import { captureOptions, playwrightCapture } from './shared/options.mjs';
import { environment } from './shared/environment.mjs';
import { fileURLToPath } from 'node:url';

const capture = captureOptions();
const site = process.env.ELEMACY_TEST_ENV ? environment() : null;

export default defineConfig({
  testDir: './journeys',
  fullyParallel: false,
  workers: 1,
  retries: 0,
  timeout: 90000,
  expect: { timeout: 15000 },
  outputDir: path.join(capture.output, 'artifacts'),
  reporter: [['list'], ['html', { outputFolder: path.join(capture.output, 'html'), open: 'never' }],
    ['json', { outputFile: path.join(capture.output, 'results.json') }],
    ['junit', { outputFile: path.join(capture.output, 'results.xml') }]],
  metadata: { harnessVersion: 1, capture, decorativeServices: process.env.ELEMACY_TEST_DECORATIVE_SERVICES === 'live' ? 'live' : 'fixtures' },
  use: {
    baseURL: site?.url,
    actionTimeout: 15000,
    navigationTimeout: 30000,
    viewport: { width: 1440, height: 1000 },
    ...playwrightCapture(capture),
    launchOptions: process.env.ELEMACY_CHROMIUM ? { executablePath: process.env.ELEMACY_CHROMIUM } : {},
  },
  projects: [{ name: 'chromium', use: { browserName: 'chromium' } }],
  webServer: site && site.transport !== 'external' ? {
    command: [process.execPath, fileURLToPath(new URL('./shared/server.mjs', import.meta.url))]
      .map(value => "'" + value.replaceAll("'", "'\\''") + "'").join(' '),
    url: site.url + '/__elemacy_integration_health',
    reuseExistingServer: false,
    timeout: 30000,
    stdout: 'ignore', stderr: 'pipe',
  } : undefined,
});
