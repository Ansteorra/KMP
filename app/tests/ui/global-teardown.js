// Global teardown for Playwright tests
async function globalTeardown() {
  console.log('🧹 Running global teardown...');
  
  // Add any cleanup logic here
  // Example: Clear test database, remove uploaded files, etc.
  
  console.log('✅ Global teardown completed');
}

module.exports = globalTeardown;
