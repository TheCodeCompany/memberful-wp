const { defineConfig } = require('@playwright/test');

module.exports = defineConfig({
  testDir: '.',
  testMatch: '*.spec.cjs',
  workers: 1,
  retries: 0,
  timeout: 60000,
  reporter: 'line',
  outputDir: './results',
  use: {
    headless: true,
    launchOptions: { executablePath: process.env.QA_BROWSER_EXECUTABLE },
    screenshot: 'only-on-failure',
    trace: 'off',
  },
});
