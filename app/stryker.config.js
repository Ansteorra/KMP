// @ts-check
/** @type {import('@stryker-mutator/api/core').PartialStrykerOptions} */
const scope = process.env.STRYKER_MUTATE_SCOPE ?? 'security';
const securityCriticalMutate = [
  'assets/js/controllers/mobile-pin-gate-controller.js',
  'assets/js/controllers/face-photo-validator-controller.js',
  'assets/js/controllers/login-device-auth-controller.js',
  'assets/js/controllers/member-mobile-card-pwa-controller.js',
  'assets/js/controllers/member-mobile-card-profile-controller.js',
];
const allMutate = [
  'assets/js/**/*.js',
  'plugins/**/assets/js/**/*.js',
  '!assets/js/index.js',
  '!**/node_modules/**',
];

const config = {
  packageManager: 'npm',
  testRunner: 'jest',
  jest: {
    projectType: 'custom',
    configFile: 'jest.config.cjs',
    enableFindRelatedTests: true
  },
  reporters: ['clear-text', 'progress', 'html'],
  htmlReporter: {
    fileName: `tests/mutation-reports/stryker-${scope}-report.html`
  },
  mutate: scope === 'all' ? allMutate : securityCriticalMutate,
  ignorePatterns: [
    'vendor',
    'webroot',
    'tmp',
    'logs',
    'node_modules',
    'tests/coverage',
    'tests/mutation-reports',
    'tests/ui-reports',
    'tests/ui-results',
  ],
  coverageAnalysis: 'perTest',
  timeoutMS: 30000,
  concurrency: Number(process.env.STRYKER_CONCURRENCY ?? 2),
  tempDirName: `tmp/stryker-${scope}`,
  thresholds: { high: 80, low: 60, break: null }
};
export default config;
