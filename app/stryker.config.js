// @ts-check
/** @type {import('@stryker-mutator/api/core').PartialStrykerOptions} */
const config = {
  packageManager: 'npm',
  testRunner: 'jest',
  jest: {
    projectType: 'custom',
    configFile: 'jest.config.js',
    enableFindRelatedTests: true
  },
  reporters: ['clear-text', 'progress', 'html'],
  htmlReporter: {
    fileName: 'tests/mutation-reports/stryker-report.html'
  },
  mutate: [
    'assets/js/controllers/mobile-pin-gate-controller.js',
    'assets/js/controllers/face-photo-validator-controller.js',
    'assets/js/controllers/login-device-auth-controller.js',
    'assets/js/controllers/member-mobile-card-pwa-controller.js',
    'assets/js/controllers/member-mobile-card-profile-controller.js',
  ],
  ignorePatterns: ['vendor', 'webroot', 'tmp', 'logs', 'node_modules'],
  coverageAnalysis: 'perTest',
  timeoutMS: 30000,
  concurrency: 2,
  tempDirName: 'tmp/stryker',
  thresholds: { high: 80, low: 60, break: null }
};
module.exports = config;
