// @ts-check
const { defineConfig, devices } = require('@playwright/test');
// Playwright-BDD configuration  
const { defineBddConfig } = require('playwright-bdd');

const testDir = defineBddConfig({
  featuresRoot: './tests/ui/bdd',
  outputDir: 'tests/ui/gen',
});

/**
 * @see https://playwright.dev/docs/test-configuration
 */
module.exports = defineConfig({
  testDir: testDir,

  /* Run tests in files in parallel */
  fullyParallel: true,

  /* Fail the build on CI if you accidentally left test.only in the source code. */
  forbidOnly: !!process.env.CI,

  /* Retry on CI only */
  retries: process.env.CI ? 2 : 0,

  /* Opt out of parallel tests on CI. */
  workers: process.env.CI ? 1 : undefined,

  /* Reporter to use. See https://playwright.dev/docs/test-reporters */
  reporter: [
    ['html', {
      outputFolder: 'tests/ui-reports/html', host: '0.0.0.0',
      port: 9324
    }],
    ['json', { outputFile: 'tests/ui-reports/results.json' }],
    ['junit', { outputFile: 'tests/ui-reports/results.xml' }]
  ],

  /* Shared settings for all the projects below. See https://playwright.dev/docs/api/class-testoptions. */
  use: {
    /* Base URL to use in actions like `await page.goto('/')`. */
    baseURL: 'https://127.0.0.1:8080',

    /* Collect trace when retrying the failed test. See https://playwright.dev/docs/trace-viewer */
    trace: 'on-first-retry',

    /* Capture screenshot after each test failure */
    screenshot: 'only-on-failure',

    /* Capture video on failure */
    video: 'retain-on-failure',

    /* Set global timeout for actions */
    actionTimeout: 30000,

    /* Set navigation timeout */
    navigationTimeout: 30000,

    /* Ignore SSL errors for self-signed certificates */
    ignoreHTTPSErrors: true,
  },

  /* Configure projects for major browsers */
  projects: [
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'] },
    },

    {
      name: 'firefox',
      use: { ...devices['Desktop Firefox'] },
    },

    {
      name: 'webkit',
      use: {
        ...devices['Desktop Safari'],
      },
    },

    /* Test against mobile viewports. */
    {
      name: 'Mobile Chrome',
      use: { ...devices['Pixel 5'] },
    },
    {
      name: 'Mobile Safari',
      use: { ...devices['iPhone 12'] },
    },

    /* Test against branded browsers. */
    // {
    //   name: 'Microsoft Edge',
    //   use: { ...devices['Desktop Edge'], channel: 'msedge' },
    // },
    // {
    //   name: 'Google Chrome',
    //   use: { ...devices['Desktop Chrome'], channel: 'chrome' },
    // },
  ],

  /* Run your local dev server before starting the tests */
  webServer: {
    command: '',
    url: 'https://127.0.0.1:8080',
    reuseExistingServer: true,
    ignoreHTTPSErrors: true,
  },

  /* Global setup and teardown */
  globalSetup: require.resolve('./tests/ui/global-setup.js'),
  globalTeardown: require.resolve('./tests/ui/global-teardown.js'),

  /* Test timeout */
  timeout: 60000,

  /* Expect timeout */
  expect: {
    timeout: 10000,
  },

  /* Directories */
  outputDir: 'tests/ui-results/',
});
