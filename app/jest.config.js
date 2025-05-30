module.exports = {
  testEnvironment: 'jsdom',
  setupFilesAfterEnv: ['<rootDir>/tests/js/setup.js'],
  testMatch: [
    '<rootDir>/tests/js/**/*.test.js'
  ],
  collectCoverageFrom: [
    'assets/js/**/*.js',
    'plugins/**/assets/js/**/*.js',
    '!assets/js/index.js',
    '!**/node_modules/**'
  ],
  coverageDirectory: 'tests/js-coverage',
  coverageReporters: ['text', 'lcov', 'html'],
  moduleNameMapper: {
    '^@/(.*)$': '<rootDir>/assets/js/$1',
    '^@hotwired/stimulus$': '<rootDir>/tests/js/__mocks__/stimulus.js'
  },
  transform: {
    '^.+\\.js$': 'babel-jest'
  },
  testTimeout: 10000,
  verbose: true
};
