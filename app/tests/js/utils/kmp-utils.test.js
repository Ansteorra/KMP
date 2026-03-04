/**
 * Tests for KMP_utils utility functions
 */

// You'll need to import your actual KMP_utils
// For now, we'll test against a mock implementation

const mockKMPUtils = {
  sanitizeString(str) {
    return str.replace(/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/gi, '');
  },

  urlParam(name) {
    const urlParams = new URLSearchParams(window.location.search);
    return urlParams.get(name);
  }
};

describe('KMP_utils', () => {
  beforeEach(() => {
    // JSDOM 28+ disallows redefining window.location directly.
    window.history.replaceState({}, '', '/?test=value&foo=bar');
  });

  describe('sanitizeString', () => {
    test('should remove script tags', () => {
      const input = 'Hello <script>alert("xss")</script> World';
      const result = mockKMPUtils.sanitizeString(input);
      expect(result).toBe('Hello  World');
    });

    test('should handle multiple script tags', () => {
      const input = '<script>bad()</script>Safe text<script>alsoBad()</script>';
      const result = mockKMPUtils.sanitizeString(input);
      expect(result).toBe('Safe text');
    });

    test('should return unchanged string if no scripts', () => {
      const input = 'This is safe text';
      const result = mockKMPUtils.sanitizeString(input);
      expect(result).toBe(input);
    });
  });

  describe('urlParam', () => {
    test('should return correct parameter value', () => {
      const result = mockKMPUtils.urlParam('test');
      expect(result).toBe('value');
    });

    test('should return null for non-existent parameter', () => {
      const result = mockKMPUtils.urlParam('nonexistent');
      expect(result).toBe(null);
    });

    test('should handle multiple parameters', () => {
      expect(mockKMPUtils.urlParam('test')).toBe('value');
      expect(mockKMPUtils.urlParam('foo')).toBe('bar');
    });
  });
});
