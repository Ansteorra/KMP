// Workflow test-specific config — uses HTTP for devcontainer testing
const base = require('./playwright.config.cjs');
const { defineBddConfig } = require('playwright-bdd');

const testDir = defineBddConfig({
  featuresRoot: './tests/ui/bdd/@workflows',
  outputDir: 'tests/ui/gen/@workflows-only',
});

module.exports = {
  ...base,
  testDir: testDir,
  webServer: undefined,
  globalSetup: undefined,
  globalTeardown: undefined,
  use: {
    ...base.use,
    baseURL: 'http://127.0.0.1:8080',
    ignoreHTTPSErrors: true,
  },
};
