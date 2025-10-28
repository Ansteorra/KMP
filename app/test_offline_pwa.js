const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch({ headless: false });
  const context = await browser.newContext();
  const page = await context.newPage();

  console.log('1. Loading page ONLINE...');
  await page.goto('http://localhost:8080/members/view-mobile-card/9cf9fd5c389304f85d5ade102a9c9119');
  await page.waitForTimeout(3000);
  
  // Check if content loaded
  const onlineContent = await page.textContent('body');
  console.log('Online - Page loaded:', onlineContent.includes('Admin von Admin') ? '✅ YES' : '❌ NO');

  console.log('\n2. Going OFFLINE...');
  await context.setOffline(true);
  
  console.log('3. Reloading page while OFFLINE...');
  await page.reload();
  await page.waitForTimeout(3000);
  
  // Check if cached content is available
  const offlineContent = await page.textContent('body');
  console.log('Offline - Page loaded:', offlineContent.includes('Admin von Admin') ? '✅ YES (from cache!)' : '❌ NO (cache failed)');
  
  // Get console messages
  page.on('console', msg => console.log('PAGE LOG:', msg.text()));
  
  await page.waitForTimeout(2000);
  
  console.log('\n4. Going back ONLINE...');
  await context.setOffline(false);
  
  console.log('5. Reloading page while ONLINE again...');
  await page.reload();
  await page.waitForTimeout(3000);
  
  const onlineAgainContent = await page.textContent('body');
  console.log('Online again - Page loaded:', onlineAgainContent.includes('Admin von Admin') ? '✅ YES' : '❌ NO');

  await browser.close();
  console.log('\n✅ Test complete!');
})();
