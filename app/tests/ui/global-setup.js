// Global setup for Playwright tests
const { chromium, expect } = require('@playwright/test');
const { execSync } = require('child_process');

async function globalSetup() {
  console.log('🚀 Starting global setup for UI tests...');
  const baseUrl = process.env.PLAYWRIGHT_BASE_URL || 'https://127.0.0.1:8080';

  // Start browser for authentication and other global setup
  const browser = await chromium.launch();
  const context = await browser.newContext({ ignoreHTTPSErrors: true });
  const page = await context.newPage();

  // empty the test mail server inbox
  console.log('🧹 Emptying test mail server inbox...')
  try {
    await page.goto('http://localhost:8025/');
    var btn = await page.getByRole('button', { name: ' Delete all' });
    //if the button is enabled, click it
    if (await btn.isDisabled()) {
      console.log('❗️ Delete all button is disabled, skipping emptying inbox');
    } else {
      await btn.click();
      await page.getByRole('button', { name: 'Delete', exact: true }).click();

    }
    console.log('✅ Test mail server inbox emptied');
  } catch (error) {
    console.error('❌ Failed to empty test mail server inbox:', error);
  }

  // Clean up authorization requests for test activities to avoid conflicts
  console.log('🧹 Cleaning up auth requests for test user...');
  try {
    execSync(`mysql -h 127.0.0.1 -u KMPSQLDEV -pP@ssw0rd KMP_DEV -e "DELETE aa FROM activities_authorization_approvals aa JOIN activities_authorizations az ON aa.authorization_id = az.id JOIN members m ON az.member_id = m.id JOIN activities_activities act ON az.activity_id = act.id WHERE m.email_address = 'iris@ampdemo.com' AND act.name = 'Armored'; DELETE az FROM activities_authorizations az JOIN members m ON az.member_id = m.id JOIN activities_activities act ON az.activity_id = act.id WHERE m.email_address = 'iris@ampdemo.com' AND act.name = 'Armored';"`, { stdio: 'pipe' });
    console.log('✅ Auth requests cleaned up');
  } catch (error) {
    console.log('⚠️ Could not clean up auths (non-fatal):', error.message?.substring(0, 100));
  }

  try {
    // Wait for the server to be ready
    console.log('⏳ Waiting for server to be ready...');
    await page.goto(baseUrl, { waitUntil: 'networkidle' });
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
