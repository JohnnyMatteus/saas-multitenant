import { defineConfig } from '@playwright/test';

export default defineConfig({
  testDir: './tests',
  timeout: 5 * 60_000,
  workers: 1,
  fullyParallel: false,
  retries: 0,
  outputDir: './test-results',
  reporter: 'list',
  use: {
    viewport: { width: 1280, height: 720 },
    video: { mode: 'on', size: { width: 1280, height: 720 } },
    launchOptions: { slowMo: 600 },
  },
});
