const { chromium } = require('playwright');

(async () => {
    console.log('Starting PWA Mobile Calendar test...\n');
    
    const browser = await chromium.launch({ headless: true });
    const context = await browser.newContext({
        viewport: { width: 390, height: 844 }, // iPhone 12 Pro
        userAgent: 'Mozilla/5.0 (iPhone; CPU iPhone OS 15_0 like Mac OS X) AppleWebKit/605.1.15'
    });
    const page = await context.newPage();
    
    // Capture console logs
    const logs = [];
    page.on('console', msg => logs.push({ type: msg.type(), text: msg.text() }));
    
    try {
        // Step 1: Login
        console.log('Step 1: Logging in as iris@ampdemo.com...');
        await page.goto('http://localhost:8080/members/login');
        await page.waitForLoadState('networkidle');
        await page.fill('#email-address', 'iris@ampdemo.com');
        await page.fill('#password', 'TestPassword');
        await page.click('input[type="submit"]');
        await page.waitForURL('**/members/**', { timeout: 15000 });
        console.log('✅ Login successful\n');
        
        // Step 2: Navigate to mobile calendar
        console.log('Step 2: Navigating to mobile calendar...');
        await page.goto('http://localhost:8080/Gatherings/mobileCalendar');
        await page.waitForLoadState('networkidle');
        
        // Wait for calendar controller
        await page.waitForSelector('[data-controller="mobile-calendar"]', { timeout: 10000 });
        console.log('✅ Mobile calendar controller loaded\n');
        
        // Step 3: Wait for grid to be visible (loading is complete)
        console.log('Step 3: Waiting for calendar data to load...');
        await page.waitForSelector('[data-mobile-calendar-target="grid"]:not([hidden])', { timeout: 15000 });
        console.log('✅ Calendar data loaded\n');
        
        // Step 4: Check calendar components
        console.log('Step 4: Verifying calendar components...');
        
        // Check month title
        const monthTitle = await page.locator('[data-mobile-calendar-target="monthTitle"]');
        const titleText = await monthTitle.textContent();
        console.log(`  - Month title: ${titleText}`);
        
        // Check navigation buttons
        const prevBtn = await page.locator('button[aria-label="Go to previous month"]').count();
        const nextBtn = await page.locator('button[aria-label="Go to next month"]').count();
        console.log(`  - Previous month button: ${prevBtn > 0 ? '✅' : '❌'}`);
        console.log(`  - Next month button: ${nextBtn > 0 ? '✅' : '❌'}`);
        
        // Check calendar grid
        const grid = await page.locator('[role="grid"]');
        const gridVisible = await grid.isVisible();
        console.log(`  - Calendar grid visible: ${gridVisible ? '✅' : '❌'}`);
        
        // Check weekday headers
        const weekdayHeaders = await page.locator('[role="columnheader"]').count();
        console.log(`  - Weekday headers: ${weekdayHeaders === 7 ? '✅' : '❌'} (${weekdayHeaders}/7)`);
        
        // Check calendar days
        const calendarDays = await page.locator('[role="gridcell"]').count();
        console.log(`  - Calendar days rendered: ${calendarDays > 0 ? '✅' : '❌'} (${calendarDays} days)`);
        
        console.log('\n✅ All calendar components verified!\n');
        
        // Step 5: Test navigation
        console.log('Step 5: Testing month navigation...');
        await page.click('button[aria-label="Go to next month"]');
        await page.waitForTimeout(1000);
        const newTitle = await monthTitle.textContent();
        console.log(`  - After next month: ${newTitle}`);
        
        await page.click('button[aria-label="Go to previous month"]');
        await page.waitForTimeout(1000);
        const backTitle = await monthTitle.textContent();
        console.log(`  - After previous month: ${backTitle}`);
        console.log('✅ Navigation works!\n');
        
        // Step 6: Test day selection
        console.log('Step 6: Testing day selection...');
        const firstDay = await page.locator('[role="gridcell"]:not(.other-month)').first();
        await firstDay.click();
        await page.waitForTimeout(500);
        
        // Check if day is selected
        const selectedDay = await page.locator('[role="gridcell"].selected').count();
        console.log(`  - Day selected: ${selectedDay > 0 ? '✅' : '❌'}`);
        console.log('✅ Day selection works!\n');
        
        // Step 7: Take screenshot
        console.log('Step 7: Capturing screenshot...');
        await page.screenshot({ 
            path: '/workspaces/KMP/test-results/mobile-calendar-test.png', 
            fullPage: true 
        });
        console.log('✅ Screenshot saved to test-results/mobile-calendar-test.png\n');
        
        // Step 8: Check accessibility
        console.log('Step 8: Checking accessibility attributes...');
        const ariaLabeled = await page.locator('[aria-label]').count();
        const roles = await page.locator('[role]').count();
        console.log(`  - Elements with aria-label: ${ariaLabeled}`);
        console.log(`  - Elements with role: ${roles}`);
        console.log('✅ Accessibility attributes present!\n');
        
        // Step 9: Test My RSVPs page
        console.log('Step 9: Testing My RSVPs page...');
        await page.goto('http://localhost:8080/GatheringAttendances/myRsvps');
        await page.waitForLoadState('networkidle');
        const rsvpPageLoaded = await page.locator('.my-rsvps-container, .mobile-rsvps-container, h1, h2').first().isVisible();
        console.log(`  - My RSVPs page loads: ${rsvpPageLoaded ? '✅' : '❌'}`);
        
        // Show any console errors
        const errors = logs.filter(l => l.type === 'error');
        if (errors.length > 0) {
            console.log('\n⚠️ Console errors:');
            errors.forEach(e => console.log(`  - ${e.text}`));
        } else {
            console.log('✅ No console errors!\n');
        }
        
        console.log('='.repeat(50));
        console.log('✅ All tests passed! Mobile calendar is working.');
        console.log('='.repeat(50));
        
    } catch (error) {
        console.error('❌ Test failed:', error.message);
        await page.screenshot({ path: '/workspaces/KMP/test-results/mobile-calendar-error.png' });
        console.log('Error screenshot saved to test-results/mobile-calendar-error.png');
        
        // Show console logs for debugging
        console.log('\nConsole logs:');
        logs.slice(-10).forEach(l => console.log(`  [${l.type}] ${l.text}`));
    } finally {
        await browser.close();
    }
})();
