import { defineConfig } from '@playwright/test';

export default defineConfig({
  testDir: './tests',
  testMatch: 'smoke.spec.ts',
  workers: 1,
  use: {
    baseURL: 'http://127.0.0.1:4400',
    browserName: 'chromium',
    javaScriptEnabled: false,
    launchOptions: { executablePath: process.env.CHROMIUM_PATH },
  },
  webServer: {
    command: 'bun scripts/serve.ts',
    url: `http://127.0.0.1:4400${process.env.WEBSITE_BASE ?? '/tca-api-headless/'}`,
    reuseExistingServer: false,
  },
});
