// Setup file for Jest tests
import '@testing-library/jest-dom';

// Global polyfills for jsdom environment
if (typeof global.TextEncoder === 'undefined') {
  const { TextEncoder, TextDecoder } = require('util');
  global.TextEncoder = TextEncoder;
  global.TextDecoder = TextDecoder;
}

// Mock Stimulus framework for testing
global.window = global.window || {};
global.window.Stimulus = {
  register: jest.fn(),
  start: jest.fn()
};

global.window.Controllers = {};

// Mock Bootstrap
global.window.bootstrap = {
  Tooltip: jest.fn()
};

// Mock KMP_utils if needed
global.window.KMP_utils = {
  sanitizeString: jest.fn(),
  urlParam: jest.fn()
};

// Mock console methods to reduce noise in tests
global.console = {
  ...console,
  log: jest.fn(),
  debug: jest.fn(),
  info: jest.fn(),
  warn: jest.fn(),
  error: jest.fn(),
};
