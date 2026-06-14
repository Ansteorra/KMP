// @ts-check
const { defineConfig, devices } = require('@playwright/test');
// Playwright-BDD configuration  
const { defineBddConfig } = require('playwright-bdd');
const { getUiTestEnvironment } = require('./tests/ui/support/test-environment.cjs');

const { baseUrl: baseURL, webServerCommand, hostHeader } = getUiTestEnvironment();
const e2eHeaders = {
  ...(hostHeader ? { Host: hostHeader } : {}),
  'X-KMP-E2E': '1',
};

const testDir = defineBddConfig({
  featuresRoot: './tests/ui/bdd',
  outputDir: 'tests/ui/gen',
  steps: ['tests/ui/bdd/**/*.js'],
  /**
   * Enforce Gherkin keyword discipline: And/But inherit the preceding primary
   * keyword's phase (Given=CONTEXT, When=ACTION, Then=OUTCOME) and a step only
   * matches a definition registered under a compatible keyword. Convention:
   * never cross a phase boundary with And — use a primary When for actions and a
   * primary Then for the assertions that follow.
   */
  matchKeywords: true,
  /** Generate tests even when path-scoped steps look missing; runtime will surface gaps. */
  missingSteps: 'fail-on-run',
});

/**
 * @see https://playwright.dev/docs/test-configuration
 */
module.exports = defineConfig({
  testDir: testDir,

  /* Run tests in files in parallel */
  fullyParallel: false,

  /* Fail the build on CI if you accidentally left test.only in the source code. */
  forbidOnly: !!process.env.CI,

  /* Retry on CI only */
  retries: process.env.CI ? 2 : 0,

  /* Opt out of parallel tests on CI. */
  workers: 1,

  /* Destructive platform-control-plane tests are opt-in because they rebuild seeded tenants. */
  grepInvert: process.env.PLAYWRIGHT_INCLUDE_DESTRUCTIVE === '1' ? undefined : /@destructive/,

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
    baseURL,

    extraHTTPHeaders: e2eHeaders,

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

    //{
    //  name: 'firefox',
    //  use: { ...devices['Desktop Firefox'] },
    //},

    //{
    //  name: 'webkit',
    //  use: {
    //    ...devices['Desktop Safari'],
    //  },
    //},

    /* Test against mobile viewports. */
    //{
    //  name: 'Mobile Chrome',
    //  use: { ...devices['Pixel 5'] },
    //},
    //{
    //  name: 'Mobile Safari',
    //  use: { ...devices['iPhone 12'] },
    //},

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
  webServer: process.env.PLAYWRIGHT_SKIP_WEBSERVER === '1' ? undefined : {
    command: webServerCommand,
    url: baseURL,
    reuseExistingServer: true,
    ignoreHTTPSErrors: true,
  },

  /* Global setup and teardown */
  globalSetup: require.resolve('./tests/ui/global-setup.js'),
  globalTeardown: require.resolve('./tests/ui/global-teardown.js'),

  /* Test timeout */
  timeout: 600000,

  /* Expect timeout */
  expect: {
    timeout: 10000,
  },

  /* Directories */
  outputDir: 'tests/ui-results/',
});
