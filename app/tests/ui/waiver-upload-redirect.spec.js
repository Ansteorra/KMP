/**
 * Waiver Upload Redirect Test
 * 
 * Tests that waiver upload from mobile card view properly:
 * 1. Uploads the waiver
 * 2. Shows processing/success page
 * 3. Redirects back to mobile card view
 * 
 * Issue: Currently bouncing back to step 1 instead of showing processing page
 */

const { test, expect } = require('@playwright/test');

test.describe('Waiver Upload Redirect Flow', () => {
    test.beforeEach(async ({ page }) => {
        // Login first
        await page.goto('http://192.168.0.253:8080/users/login');
        
        // Fill login form
        await page.fill('input[name="username"]', 'test');
        await page.fill('input[name="password"]', 'test');
        await page.click('button[type="submit"]');
        
        // Wait for redirect after login
        await page.waitForURL(/\/(members|dashboard|home)/);
    });

    test('should upload waiver and redirect back to mobile card', async ({ page }) => {
        // Navigate to mobile card view
        await page.goto('http://192.168.0.253:8080/members/view-mobile-card/9cf9fd5c389304f85d5ade102a9c9119');
        
        // Wait for page to load
        await page.waitForLoadState('networkidle');
        
        // Look for a link to upload waivers (might be in a gathering section)
        // This is exploratory - we need to see what's on the page
        await page.screenshot({ path: 'tests/ui-reports/screenshots/mobile-card-view.png' });
        
        // Find and click on waiver upload link
        // The exact selector will depend on the page structure
        const uploadLink = page.locator('a[href*="gathering-waivers/upload"]').first();
        
        if (await uploadLink.count() > 0) {
            await uploadLink.click();
            
            // Wait for wizard to load
            await page.waitForSelector('[data-controller="waiver-upload-wizard"]');
            
            // Step 1: Select an activity
            const firstActivity = page.locator('input[data-waiver-upload-wizard-target="activityCheckbox"]').first();
            await firstActivity.check();
            
            // Click Next
            await page.locator('button', { hasText: 'Next' }).click();
            
            // Step 2: Select waiver type
            await page.waitForSelector('[data-step-number="2"]:not(.d-none)');
            const firstWaiverType = page.locator('input[type="radio"][name="waiver_type_id"]').first();
            await firstWaiverType.check();
            
            // Click Next
            await page.locator('button', { hasText: 'Next' }).click();
            
            // Step 3: Add waiver pages
            await page.waitForSelector('[data-step-number="3"]:not(.d-none)');
            
            // Create a test image file
            const buffer = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==', 'base64');
            
            // Upload file
            const fileInput = page.locator('input[type="file"]');
            await fileInput.setInputFiles({
                name: 'test-waiver.png',
                mimeType: 'image/png',
                buffer: buffer,
            });
            
            // Wait for preview to appear
            await page.waitForSelector('[data-waiver-upload-wizard-target="pagesPreview"] .col-6', { timeout: 5000 });
            
            // Click Next to review
            await page.locator('button', { hasText: 'Next' }).click();
            
            // Step 4: Review and submit
            await page.waitForSelector('[data-step-number="4"]:not(.d-none)');
            
            // Take screenshot before submit
            await page.screenshot({ path: 'tests/ui-reports/screenshots/before-submit.png' });
            
            // Listen for the AJAX request
            const responsePromise = page.waitForResponse(response => 
                response.url().includes('gathering-waivers/upload') && 
                response.request().method() === 'POST'
            );
            
            // Click submit button
            const submitButton = page.locator('button[data-waiver-upload-wizard-target="submitButton"]');
            await submitButton.click();
            
            // Wait for response
            const response = await responsePromise;
            
            // Check response
            console.log('Response status:', response.status());
            console.log('Response content-type:', response.headers()['content-type']);
            
            // The response should be JSON with redirectUrl
            const contentType = response.headers()['content-type'];
            if (contentType && contentType.includes('application/json')) {
                const data = await response.json();
                console.log('Response data:', data);
                
                // Should have redirectUrl
                expect(data).toHaveProperty('redirectUrl');
                expect(data.redirectUrl).toContain('view-mobile-card');
            } else {
                // This is the bug - receiving HTML instead of JSON
                console.log('ERROR: Received HTML response instead of JSON');
                const text = await response.text();
                console.log('Response text (first 500 chars):', text.substring(0, 500));
            }
            
            // Wait a bit to see what happens
            await page.waitForTimeout(2000);
            
            // Take screenshot after submit
            await page.screenshot({ path: 'tests/ui-reports/screenshots/after-submit.png' });
            
            // The URL should eventually be back at mobile card view
            // (either immediately or after showing success page)
            const currentUrl = page.url();
            console.log('Current URL after submit:', currentUrl);
            
            // It should either be:
            // 1. Still on upload page showing success (step 5)
            // 2. Redirected to mobile card view
            // But NOT back to step 1
            
            // Check if we're back at step 1 (the bug)
            const step1Visible = await page.locator('[data-step-number="1"]:not(.d-none)').count() > 0;
            if (step1Visible) {
                console.log('BUG CONFIRMED: Back at step 1 instead of showing success or redirecting');
            }
            
            // Should NOT be back at step 1
            expect(step1Visible).toBe(false);
            
        } else {
            console.log('No waiver upload link found on mobile card page');
            test.skip();
        }
    });
    
    test('should show processing page before redirect', async ({ page }) => {
        // This test checks the expected behavior:
        // After upload, should show success message/processing page
        // Then redirect after a delay
        
        // Navigate to mobile card
        await page.goto('http://192.168.0.253:8080/members/view-mobile-card/9cf9fd5c389304f85d5ade102a9c9119');
        await page.waitForLoadState('networkidle');
        
        // Find upload link
        const uploadLink = page.locator('a[href*="gathering-waivers/upload"]').first();
        
        if (await uploadLink.count() === 0) {
            test.skip();
            return;
        }
        
        await uploadLink.click();
        
        // Go through wizard steps (abbreviated version)
        await page.waitForSelector('[data-controller="waiver-upload-wizard"]');
        
        // Select activity
        await page.locator('input[data-waiver-upload-wizard-target="activityCheckbox"]').first().check();
        await page.locator('button', { hasText: 'Next' }).click();
        
        // Select waiver type
        await page.waitForSelector('[data-step-number="2"]:not(.d-none)');
        await page.locator('input[type="radio"][name="waiver_type_id"]').first().check();
        await page.locator('button', { hasText: 'Next' }).click();
        
        // Upload file
        await page.waitForSelector('[data-step-number="3"]:not(.d-none)');
        const buffer = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==', 'base64');
        await page.locator('input[type="file"]').setInputFiles({
            name: 'test.png',
            mimeType: 'image/png',
            buffer: buffer,
        });
        await page.waitForSelector('[data-waiver-upload-wizard-target="pagesPreview"] .col-6');
        await page.locator('button', { hasText: 'Next' }).click();
        
        // Review and submit
        await page.waitForSelector('[data-step-number="4"]:not(.d-none)');
        
        // Submit
        await page.locator('button[data-waiver-upload-wizard-target="submitButton"]').click();
        
        // Should show success message or processing indicator
        // Look for success icon or message
        const successIndicator = page.locator('.bi-check-circle-fill, text=/uploaded successfully/i');
        
        // Wait up to 5 seconds for success indicator
        try {
            await successIndicator.waitFor({ timeout: 5000 });
            console.log('Success indicator found');
        } catch (e) {
            console.log('No success indicator found - checking current state');
            await page.screenshot({ path: 'tests/ui-reports/screenshots/no-success-indicator.png' });
        }
        
        // Eventually should redirect to mobile card
        // Wait up to 5 seconds for redirect
        await page.waitForURL(/view-mobile-card/, { timeout: 5000 }).catch(() => {
            console.log('Did not redirect to mobile card within timeout');
        });
        
        const finalUrl = page.url();
        console.log('Final URL:', finalUrl);
        
        // Should end up at mobile card view
        expect(finalUrl).toContain('view-mobile-card');
    });
});
