import { defineConfig } from '@playwright/test';
import { chromiumPath } from './browser.mjs';

export default defineConfig({
  testDir: './tests',
  timeout: 90_000,
  expect: { timeout: 10_000 },
  fullyParallel: false,
  workers: 1,
  reporter: [['list'], ['json', { outputFile: '../docs/qa/resultados-testes.json' }]],
  use: {
    baseURL: process.env.CDF_URL || 'http://casadasfardas.localhost:8092',
    launchOptions: { executablePath: chromiumPath() },
    viewport: { width: 1440, height: 900 },
    locale: 'pt-PT',
    trace: 'retain-on-failure',
  },
  outputDir: '../docs/qa/artefactos',
});
