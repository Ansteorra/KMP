import { test, expect } from '@playwright/test';

test.describe('Multi-Select Dropdown Filters', () => {
    test.beforeEach(async ({ page }) => {
        // Login first
        await page.goto('/users/login');
        await page.fill('input[name="email_address"]', 'admin@example.com');
        await page.fill('input[name="password"]', 'password');
        await page.click('button[type="submit"]');
        await page.waitForURL('/');

        // Navigate to members dataverse
        await page.goto('/members/index-dv');
        await page.waitForLoadState('networkidle');
    });

    test('should display multi-select dropdowns with size="3"', async ({ page }) => {
        // Check Status dropdown
        const statusSelect = page.locator('select[data-grid-view-target="statusFilter"]');
        await expect(statusSelect).toBeVisible();
        await expect(statusSelect).toHaveAttribute('multiple', '');
        await expect(statusSelect).toHaveAttribute('size', '3');

        // Check Branch dropdown
        const branchSelect = page.locator('select[data-grid-view-target="branchFilter"]');
        await expect(branchSelect).toBeVisible();
        await expect(branchSelect).toHaveAttribute('multiple', '');

        // Check Minor dropdown
        const minorSelect = page.locator('select[data-grid-view-target="minorFilter"]');
        await expect(minorSelect).toBeVisible();
        await expect(minorSelect).toHaveAttribute('multiple', '');
    });

    test('should show selected count badge when options are selected', async ({ page }) => {
        // Select 2 status options using Ctrl+Click
        const statusSelect = page.locator('select[data-grid-view-target="statusFilter"]');

        // Select first option
        await statusSelect.selectOption({ index: 0 });

        // Hold Ctrl and select second option
        await page.keyboard.down('Control');
        await statusSelect.selectOption({ index: 1 });
        await page.keyboard.up('Control');

        // Check that badge shows count
        const statusLabel = page.locator('label:has-text("Status")');
        const badge = statusLabel.locator('.badge');
        await expect(badge).toBeVisible();
        await expect(badge).toHaveText('2');
    });

    test('should build URL with array parameters for multiple selections', async ({ page }) => {
        // Select multiple status options
        const statusSelect = page.locator('select[data-grid-view-target="statusFilter"]');

        // Get the options
        const options = await statusSelect.locator('option').allTextContents();
        console.log('Available status options:', options);

        // Select first two options
        await page.evaluate(() => {
            const select = document.querySelector('select[data-grid-view-target="statusFilter"]');
            select.options[0].selected = true;
            select.options[1].selected = true;
            // Trigger change event
            select.dispatchEvent(new Event('change', { bubbles: true }));
        });

        // Wait for navigation
        await page.waitForLoadState('networkidle');

        // Check URL contains filter array parameters
        const url = page.url();
        console.log('URL after filter:', url);
        expect(url).toContain('filter[status][]');
    });

    test('should filter results with multiple selections', async ({ page }) => {
        // Get initial row count
        const initialRows = await page.locator('tbody tr').count();
        console.log('Initial row count:', initialRows);

        // Select verified status only
        await page.evaluate(() => {
            const select = document.querySelector('select[data-grid-view-target="statusFilter"]');
            // Find and select only 'verified' option
            for (let i = 0; i < select.options.length; i++) {
                if (select.options[i].value === 'verified') {
                    select.options[i].selected = true;
                } else {
                    select.options[i].selected = false;
                }
            }
            select.dispatchEvent(new Event('change', { bubbles: true }));
        });

        await page.waitForLoadState('networkidle');
        const verifiedCount = await page.locator('tbody tr').count();
        console.log('Verified only count:', verifiedCount);

        // Now select both verified and active
        await page.evaluate(() => {
            const select = document.querySelector('select[data-grid-view-target="statusFilter"]');
            for (let i = 0; i < select.options.length; i++) {
                if (select.options[i].value === 'verified' || select.options[i].value === 'active') {
                    select.options[i].selected = true;
                }
            }
            select.dispatchEvent(new Event('change', { bubbles: true }));
        });

        await page.waitForLoadState('networkidle');
        const multiCount = await page.locator('tbody tr').count();
        console.log('Verified + Active count:', multiCount);

        // Multiple selections should show more results than single
        expect(multiCount).toBeGreaterThanOrEqual(verifiedCount);
    });

    test('should clear all filters including multi-select', async ({ page }) => {
        // Select some options
        await page.evaluate(() => {
            const select = document.querySelector('select[data-grid-view-target="statusFilter"]');
            select.options[0].selected = true;
            select.options[1].selected = true;
            select.dispatchEvent(new Event('change', { bubbles: true }));
        });

        await page.waitForLoadState('networkidle');

        // Verify URL has filter params
        let url = page.url();
        expect(url).toContain('filter[status][]');

        // Click clear filters button
        await page.click('button:has-text("Clear Filters")');
        await page.waitForLoadState('networkidle');

        // Verify URL no longer has filter params
        url = page.url();
        expect(url).not.toContain('filter[status][]');

        // Verify no badge is shown
        const badge = page.locator('label:has-text("Status") .badge');
        await expect(badge).not.toBeVisible();
    });

    test('should combine search with multi-select filters', async ({ page }) => {
        // Enter search text
        const searchInput = page.locator('input[data-grid-view-target="searchInput"]');
        await searchInput.fill('John');
        await searchInput.press('Enter');

        await page.waitForLoadState('networkidle');
        const searchCount = await page.locator('tbody tr').count();
        console.log('Search results count:', searchCount);

        // Now add status filter
        await page.evaluate(() => {
            const select = document.querySelector('select[data-grid-view-target="statusFilter"]');
            select.options[0].selected = true;
            select.dispatchEvent(new Event('change', { bubbles: true }));
        });

        await page.waitForLoadState('networkidle');
        const filteredCount = await page.locator('tbody tr').count();
        console.log('Search + Filter count:', filteredCount);

        // URL should have both search and filter params
        const url = page.url();
        expect(url).toContain('search=');
        expect(url).toContain('filter[status][]');

        // Filtered results should be less than or equal to search-only
        expect(filteredCount).toBeLessThanOrEqual(searchCount);
    });
});
