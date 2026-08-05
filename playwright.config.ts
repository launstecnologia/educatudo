import { defineConfig } from '@playwright/test';

const colagUrl = process.env.COLAG_URL ?? 'http://colag.localhost';
const masterUrl = process.env.MASTER_URL ?? 'http://master.localhost';

export default defineConfig({
  testDir: './tests',
  timeout: 300_000,
  expect: { timeout: 20_000 },
  fullyParallel: false,
  workers: 1,
  retries: process.env.CI ? 1 : 0,
  reporter: [['list'], ['html', { open: 'never' }]],
  use: {
    baseURL: colagUrl,
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
    video: 'retain-on-failure',
    actionTimeout: 45_000,
    navigationTimeout: 30_000,
  },
  projects: [{ name: 'chromium', use: { browserName: 'chromium' } }],
  metadata: { colagUrl, masterUrl },
});
