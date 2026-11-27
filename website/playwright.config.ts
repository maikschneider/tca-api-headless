import { defineConfig } from '@playwright/test';
import { prefix } from './src/lib/config';

export default defineConfig({
  testDir: './tests',
  testMatch: ['smoke.spec.ts', 'a11y.spec.ts'],
  workers: 1,
  use: {
    baseURL: 'http://127.0.0.1:4400',
    browserName: 'chromium',
    javaScriptEnabled: false,
    launchOptions: { executablePath: process.env.CHROMIUM_PATH },
  },
  webServer: {
    command: 'bun scripts/serve.ts',
    url: `http://127.0.0.1:4400${prefix}/`,
    reuseExistingServer: false,
  },
});
