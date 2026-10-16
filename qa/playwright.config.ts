import path from 'path';
import dotenv from 'dotenv';
import { defineConfig, devices } from '@playwright/test';

dotenv.config({ path: path.join(__dirname, '.env') });

const baseURL = process.env.QA_BASE_URL ?? 'http://colag.localhost';

export default defineConfig({
  testDir: './testes',
  globalSetup: './global-setup.ts',
  timeout: 120_000,
  expect: { timeout: 15_000 },
  fullyParallel: false,
  workers: 1,
  retries: 0,
  reporter: [
    ['list'],
    ['html', { open: 'never', outputFolder: 'relatorios/playwright' }],
    ['./src/relatorio/reporter.ts'],
  ],
  outputDir: 'resultados/test-results',
  use: {
    baseURL,
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
    video: 'retain-on-failure',
    actionTimeout: 20_000,
    navigationTimeout: 30_000,
    locale: 'pt-BR',
    serviceWorkers: 'block',
    ...devices['Desktop Chrome'],
    viewport: { width: 1920, height: 1080 },
  },
  projects: [
    { name: 'setup', testMatch: /autenticacao\.setup\.ts/ },
    {
      name: 'publico',
      testMatch: /autenticacao\.spec\.ts/,
      use: { storageState: { cookies: [], origins: [] } },
    },
    {
      name: 'chromium',
      dependencies: ['setup'],
      testIgnore: /autenticacao\.(setup|spec)\.ts|service-worker\.spec\.ts/,
      use: { storageState: '.auth/admin.json' },
    },
    {
      name: 'service-worker',
      dependencies: ['setup'],
      testMatch: /service-worker\.spec\.ts/,
      use: { storageState: { cookies: [], origins: [] }, serviceWorkers: 'allow' },
    },
  ],
});
