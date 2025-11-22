import { test, expect } from '@playwright/test';

test.describe('Modern Filter Chips UI', () => {
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

    test('should display modern filter UI with dropdown and add button', async ({ page }) => {
        // Check filter type dropdown exists
        const filterTypeSelect = page.locator('select[data-grid-view-target="filterTypeSelect"]');
        await expect(filterTypeSelect).toBeVisible();
        await expect(filterTypeSelect).toContainText('Add Filter...');

        // Value select should be hidden initially
        const filterValueSelect = page.locator('select[data-grid-view-target="filterValueSelect"]');
        await expect(filterValueSelect).toHaveCSS('display', 'none');

        // Add button should be hidden initially
        const addFilterBtn = page.locator('button[data-grid-view-target="addFilterBtn"]');
        await expect(addFilterBtn).toHaveCSS('display', 'none');
    });

    test('should show value dropdown when filter type is selected', async ({ page }) => {
        // Select Status filter
        const filterTypeSelect = page.locator('select[data-grid-view-target="filterTypeSelect"]');
        await filterTypeSelect.selectOption({ label: 'Status' });

        // Value select should now be visible
        const filterValueSelect = page.locator('select[data-grid-view-target="filterValueSelect"]');
        await expect(filterValueSelect).toBeVisible();

        // Add button should be visible
        const addFilterBtn = page.locator('button[data-grid-view-target="addFilterBtn"]');
        await expect(addFilterBtn).toBeVisible();

        // Value dropdown should have options
        const options = await filterValueSelect.locator('option').allTextContents();
        expect(options.length).toBeGreaterThan(1); // Should have "Select value..." + actual values
    });

    test('should add filter chip when Add button is clicked', async ({ page }) => {
        // Select Status filter
        const filterTypeSelect = page.locator('select[data-grid-view-target="filterTypeSelect"]');
        await filterTypeSelect.selectOption({ label: 'Status' });

        // Select a value
        const filterValueSelect = page.locator('select[data-grid-view-target="filterValueSelect"]');
        await filterValueSelect.selectOption({ index: 1 }); // First real option

        // Click Add
        const addFilterBtn = page.locator('button[data-grid-view-target="addFilterBtn"]');
        await addFilterBtn.click();

        // Wait for page reload
        await page.waitForLoadState('networkidle');

        // Should have a filter chip
        const filterChips = page.locator('.badge.bg-primary.rounded-pill');
        await expect(filterChips).toHaveCount(1);

        // Chip should contain Status label
        await expect(filterChips.first()).toContainText('Status:');
    });

    test('should display multiple filter chips', async ({ page }) => {
        // Add first filter - Status
        await page.locator('select[data-grid-view-target="filterTypeSelect"]').selectOption({ label: 'Status' });
        await page.locator('select[data-grid-view-target="filterValueSelect"]').selectOption({ index: 1 });
        await page.locator('button[data-grid-view-target="addFilterBtn"]').click();
        await page.waitForLoadState('networkidle');

        // Add second filter - Branch
        await page.locator('select[data-grid-view-target="filterTypeSelect"]').selectOption({ label: 'Branch' });
        await page.locator('select[data-grid-view-target="filterValueSelect"]').selectOption({ index: 1 });
        await page.locator('button[data-grid-view-target="addFilterBtn"]').click();
        await page.waitForLoadState('networkidle');

        // Should have 2 filter chips
        const filterChips = page.locator('.badge.bg-primary.rounded-pill');
        await expect(filterChips).toHaveCount(2);

        // First chip should have Status
        await expect(filterChips.nth(0)).toContainText('Status:');
        // Second chip should have Branch
        await expect(filterChips.nth(1)).toContainText('Branch:');
    });

    test('should remove filter chip when X button is clicked', async ({ page }) => {
        // Add a filter
        await page.locator('select[data-grid-view-target="filterTypeSelect"]').selectOption({ label: 'Status' });
        await page.locator('select[data-grid-view-target="filterValueSelect"]').selectOption({ index: 1 });
        await page.locator('button[data-grid-view-target="addFilterBtn"]').click();
        await page.waitForLoadState('networkidle');

        // Verify chip exists
        const filterChips = page.locator('.badge.bg-primary.rounded-pill');
        await expect(filterChips).toHaveCount(1);

        // Click the close button on the chip
        const closeBtn = page.locator('.badge.bg-primary.rounded-pill .btn-close-white');
        await closeBtn.click();
        await page.waitForLoadState('networkidle');

        // Chip should be gone
        await expect(filterChips).toHaveCount(0);

        // URL should not have filter params
        expect(page.url()).not.toContain('filter[status]');
    });

    test('should add multiple values for same filter type', async ({ page }) => {
        // Add first Status filter
        await page.locator('select[data-grid-view-target="filterTypeSelect"]').selectOption({ label: 'Status' });
        const valueSelect = page.locator('select[data-grid-view-target="filterValueSelect"]');
        await valueSelect.selectOption({ index: 1 });
        await page.locator('button[data-grid-view-target="addFilterBtn"]').click();
        await page.waitForLoadState('networkidle');

        // Add second Status filter with different value
        await page.locator('select[data-grid-view-target="filterTypeSelect"]').selectOption({ label: 'Status' });
        await valueSelect.selectOption({ index: 2 });
        await page.locator('button[data-grid-view-target="addFilterBtn"]').click();
        await page.waitForLoadState('networkidle');

        // Should have 2 Status chips
        const statusChips = page.locator('.badge.bg-primary.rounded-pill:has-text("Status:")');
        await expect(statusChips).toHaveCount(2);

        // URL should have array notation
        const url = page.url();
        expect(url).toContain('filter[status][]');
    });

    test('should clear all filters with "Clear all" button', async ({ page }) => {
        // Add multiple filters
        await page.locator('select[data-grid-view-target="filterTypeSelect"]').selectOption({ label: 'Status' });
        await page.locator('select[data-grid-view-target="filterValueSelect"]').selectOption({ index: 1 });
        await page.locator('button[data-grid-view-target="addFilterBtn"]').click();
        await page.waitForLoadState('networkidle');

        await page.locator('select[data-grid-view-target="filterTypeSelect"]').selectOption({ label: 'Branch' });
        await page.locator('select[data-grid-view-target="filterValueSelect"]').selectOption({ index: 1 });
        await page.locator('button[data-grid-view-target="addFilterBtn"]').click();
        await page.waitForLoadState('networkidle');

        // Verify filters exist
        const filterChips = page.locator('.badge.bg-primary.rounded-pill');
        await expect(filterChips).toHaveCount(2);

        // Click "Clear all" button
        await page.click('button:has-text("Clear all")');
        await page.waitForLoadState('networkidle');

        // All chips should be gone
        await expect(filterChips).toHaveCount(0);

        // URL should not have filter params
        const url = page.url();
        expect(url).not.toContain('filter[');
    });

    test('should not add duplicate filter values', async ({ page }) => {
        // Add first Status filter
        await page.locator('select[data-grid-view-target="filterTypeSelect"]').selectOption({ label: 'Status' });
        const valueSelect = page.locator('select[data-grid-view-target="filterValueSelect"]');

        // Get the first value option
        const firstValue = await valueSelect.locator('option').nth(1).getAttribute('value');
        await valueSelect.selectOption({ index: 1 });
        await page.locator('button[data-grid-view-target="addFilterBtn"]').click();
        await page.waitForLoadState('networkidle');

        // Try to add same filter again
        await page.locator('select[data-grid-view-target="filterTypeSelect"]').selectOption({ label: 'Status' });
        await valueSelect.selectOption({ index: 1 }); // Same value
        await page.locator('button[data-grid-view-target="addFilterBtn"]').click();

        // Should still only have 1 chip (page may not reload since it's duplicate)
        await page.waitForTimeout(500); // Brief wait to ensure no navigation
        const statusChips = page.locator('.badge.bg-primary.rounded-pill:has-text("Status:")');
        await expect(statusChips).toHaveCount(1);
    });

    test('should work with search functionality', async ({ page }) => {
        // Add a filter
        await page.locator('select[data-grid-view-target="filterTypeSelect"]').selectOption({ label: 'Status' });
        await page.locator('select[data-grid-view-target="filterValueSelect"]').selectOption({ index: 1 });
        await page.locator('button[data-grid-view-target="addFilterBtn"]').click();
        await page.waitForLoadState('networkidle');

        // Add search term
        const searchInput = page.locator('input[data-grid-view-target="searchInput"]');
        await searchInput.fill('John');
        await searchInput.press('Enter');
        await page.waitForLoadState('networkidle');

        // URL should have both filter and search
        const url = page.url();
        expect(url).toContain('filter[status]');
        expect(url).toContain('search=');

        // Filter chip should still be visible
        const filterChips = page.locator('.badge.bg-primary.rounded-pill');
        await expect(filterChips).toHaveCount(1);
    });
});
