// Global setup for Playwright tests
const { chromium, expect } = require('@playwright/test');

async function globalSetup() {
  console.log('🚀 Starting global setup for UI tests...');

  // Start browser for authentication and other global setup
  const browser = await chromium.launch();
  const context = await browser.newContext({ ignoreHTTPSErrors: true });
  const page = await context.newPage();

  try {
    // Wait for the server to be ready
    console.log('⏳ Waiting for server to be ready...');
    await page.goto('https://127.0.0.1:8080', { waitUntil: 'networkidle' });
    console.log('✅ Server is ready');
  } catch (error) {
    console.error('❌ Global setup failed:', error);
    throw error;
  } finally {
    await browser.close();
  }

  console.log('✅ Global setup completed');
}

module.exports = globalSetup;
