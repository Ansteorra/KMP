// Global setup for Playwright tests
const { chromium } = require('@playwright/test');

async function globalSetup() {
  console.log('🚀 Starting global setup for UI tests...');
  
  // Start browser for authentication and other global setup
  const browser = await chromium.launch();
  const context = await browser.newContext();
  const page = await context.newPage();
  
  try {
    // Wait for the server to be ready
    console.log('⏳ Waiting for server to be ready...');
    await page.goto('http://localhost:8080', { waitUntil: 'networkidle' });
    console.log('✅ Server is ready');
    
    // Add any global authentication or setup here
    // Example: Login as admin user and save authentication state
    // await page.goto('/login');
    // await page.fill('#email', 'admin@example.com');
    // await page.fill('#password', 'admin123');
    // await page.click('button[type="submit"]');
    // await page.waitForURL('/dashboard');
    // 
    // // Save signed-in state to 'storageState.json'.
    // await context.storageState({ path: 'tests/ui/auth/adminStorageState.json' });
    
  } catch (error) {
    console.error('❌ Global setup failed:', error);
    throw error;
  } finally {
    await browser.close();
  }
  
  console.log('✅ Global setup completed');
}

module.exports = globalSetup;
