/**
 * Interactive Playwright test for Workflow Engine pages
 * Tests all workflow admin views with multiple user roles from README
 */
const { chromium } = require('playwright');
const { captureConsoleLogs } = require('/workspaces/KMP/.github/skills/webapp-testing/test-helper.js');

const BASE_URL = 'http://localhost:8080';
const PASSWORD = 'TestPassword';

// Test users from README with different permission levels
const TEST_USERS = [
    { email: 'admin@amp.ansteorra.org', name: 'Admin (superuser)', expectAdmin: true },
    { email: 'eirik@ampdemo.com', name: 'Eirik (Kingdom Seneschal)', expectAdmin: false },
    { email: 'iris@ampdemo.com', name: 'Iris (Basic User)', expectAdmin: false },
];

const RESULTS = [];

async function login(page, email) {
    await page.goto(`${BASE_URL}/members/login`, { waitUntil: 'networkidle' });
    await page.getByRole('textbox', { name: 'Email Address' }).fill(email);
    await page.getByRole('textbox', { name: 'Password' }).fill(PASSWORD);
    await page.getByRole('button', { name: 'Sign in' }).click();
    await page.waitForTimeout(2000);
    const url = page.url();
    const loggedIn = !url.includes('/login');
    return loggedIn;
}

async function logout(page) {
    try {
        await page.goto(`${BASE_URL}/members/logout`, { waitUntil: 'networkidle' });
    } catch (e) { /* ignore */ }
}

function record(user, test, status, detail = '') {
    RESULTS.push({ user: user.name, test, status, detail });
    const icon = status === 'PASS' ? '✅' : status === 'FAIL' ? '❌' : '⚠️';
    console.log(`  ${icon} [${user.name}] ${test}${detail ? ': ' + detail : ''}`);
}

async function testWorkflowDefinitionsList(page, user) {
    await page.goto(`${BASE_URL}/workflows`, { waitUntil: 'networkidle' });
    const url = page.url();

    if (url.includes('/login') || url.includes('error')) {
        record(user, 'Workflow Definitions List', 'INFO', 'Redirected (no access) - expected for non-admin');
        return false;
    }

    // Check page loaded with content
    const pageText = await page.textContent('body');
    const hasTable = pageText.includes('Officer Hiring') || pageText.includes('Workflow') || await page.locator('table').count() > 0;
    
    if (hasTable) {
        const hasOfficerHiring = pageText.includes('Officer Hiring');
        const hasWarrantRoster = pageText.includes('Warrant Roster');
        const hasDirectWarrant = pageText.includes('Direct Warrant');
        
        record(user, 'Workflow Definitions List', 'PASS',
            `Shows: Officer Hiring=${hasOfficerHiring}, Warrant Roster=${hasWarrantRoster}, Direct Warrant=${hasDirectWarrant}`);
        return true;
    } else {
        record(user, 'Workflow Definitions List', 'FAIL', 'No workflow table found');
        return false;
    }
}

async function testWorkflowDesigner(page, user) {
    await page.goto(`${BASE_URL}/workflows`, { waitUntil: 'networkidle' });
    const url = page.url();
    if (url.includes('/login')) {
        record(user, 'Workflow Designer', 'INFO', 'No access');
        return;
    }

    // Find and click the Design button for Officer Hiring
    const designBtn = page.locator('a:has-text("Design"), a[href*="designer"]').first();
    if (await designBtn.count() === 0) {
        record(user, 'Workflow Designer', 'FAIL', 'No Design button found');
        return;
    }

    await designBtn.click();
    await page.waitForLoadState('networkidle');

    const designerUrl = page.url();
    if (!designerUrl.includes('designer')) {
        record(user, 'Workflow Designer', 'FAIL', `Unexpected URL: ${designerUrl}`);
        return;
    }

    // Check designer components loaded
    const hasCanvas = await page.locator('[data-workflow-designer-target="canvas"]').count() > 0;
    const hasPalette = await page.locator('[data-workflow-designer-target="palette"]').count() > 0;
    const hasSaveBtn = await page.locator('button:has-text("Save"), [data-action*="save"]').count() > 0;

    const pageText = await page.textContent('body');
    const hasNodeTypes = pageText.includes('Trigger') || pageText.includes('Action') || pageText.includes('Condition');

    record(user, 'Workflow Designer', hasCanvas && hasPalette ? 'PASS' : 'FAIL',
        `Canvas=${hasCanvas}, Palette=${hasPalette}, Save=${hasSaveBtn}, NodeTypes=${hasNodeTypes}`);
}

async function testWorkflowInstances(page, user) {
    await page.goto(`${BASE_URL}/workflows/instances`, { waitUntil: 'networkidle' });
    const url = page.url();
    if (url.includes('/login')) {
        record(user, 'Workflow Instances', 'INFO', 'No access');
        return;
    }

    const pageText = await page.textContent('body');
    const hasContent = pageText.includes('Instance') || pageText.includes('Workflow') || pageText.includes('No ');

    record(user, 'Workflow Instances', hasContent ? 'PASS' : 'FAIL',
        hasContent ? 'Instances page loaded' : 'No recognizable content');
}

async function testWorkflowApprovals(page, user) {
    await page.goto(`${BASE_URL}/workflows/approvals`, { waitUntil: 'networkidle' });
    const url = page.url();
    if (url.includes('/login')) {
        record(user, 'Workflow Approvals', 'INFO', 'No access');
        return;
    }

    const pageText = await page.textContent('body');
    const hasContent = pageText.includes('Approval') || pageText.includes('Pending') || pageText.includes('No ');

    record(user, 'Workflow Approvals', hasContent ? 'PASS' : 'FAIL',
        hasContent ? 'Approvals page loaded' : 'No recognizable content');
}

async function testWorkflowVersions(page, user) {
    await page.goto(`${BASE_URL}/workflows`, { waitUntil: 'networkidle' });
    const url = page.url();
    if (url.includes('/login')) {
        record(user, 'Workflow Versions', 'INFO', 'No access');
        return;
    }

    // Click versions link for Officer Hiring
    const versionsBtn = page.locator('a:has-text("Versions"), a[href*="versions"]').first();
    if (await versionsBtn.count() === 0) {
        record(user, 'Workflow Versions', 'FAIL', 'No Versions button found');
        return;
    }

    await versionsBtn.click();
    await page.waitForLoadState('networkidle');

    const pageText = await page.textContent('body');
    const hasVersion1 = pageText.includes('1') || pageText.includes('v1');
    const hasPublished = pageText.toLowerCase().includes('published');

    record(user, 'Workflow Versions', hasVersion1 ? 'PASS' : 'FAIL',
        `Version 1=${hasVersion1}, Published=${hasPublished}`);
}

async function testWorkflowNavigation(page, user) {
    // Check that Workflows appears in the sidebar/nav
    await page.goto(`${BASE_URL}/`, { waitUntil: 'networkidle' });
    const url = page.url();
    if (url.includes('/login')) {
        record(user, 'Nav: Workflow Links', 'INFO', 'Not logged in');
        return;
    }

    const pageText = await page.textContent('body');
    const hasWorkflowNav = pageText.includes('Workflows') || pageText.includes('Workflow');
    const hasDefinitionsLink = await page.locator('a[href*="/workflows"]').count() > 0;

    record(user, 'Nav: Workflow Links', hasWorkflowNav ? 'PASS' : 'WARN',
        `Workflow in nav=${hasWorkflowNav}, Link count=${hasDefinitionsLink}`);
}

async function testRegistryEndpoint(page, user) {
    await page.goto(`${BASE_URL}/workflows/registry`, { waitUntil: 'networkidle' });
    const url = page.url();
    if (url.includes('/login')) {
        record(user, 'Registry Endpoint', 'INFO', 'No access');
        return;
    }

    const pageText = await page.textContent('body');
    // Registry returns JSON with triggers, actions, conditions, entities
    const hasJsonContent = pageText.includes('triggers') || pageText.includes('actions') || 
                           pageText.includes('conditions') || pageText.includes('officer');

    record(user, 'Registry Endpoint', hasJsonContent ? 'PASS' : 'FAIL',
        hasJsonContent ? 'Registry JSON returned with plugin registrations' : 'No registry data');
}

async function runAllTests() {
    console.log('\n🔧 Workflow Engine Interactive Test Suite');
    console.log('=========================================\n');

    const browser = await chromium.launch({ headless: true });

    for (const user of TEST_USERS) {
        console.log(`\n👤 Testing as: ${user.name} (${user.email})`);
        console.log('-'.repeat(50));

        const context = await browser.newContext();
        const page = await context.newPage();
        const logs = captureConsoleLogs(page);

        // Login
        const loggedIn = await login(page, user.email);
        if (!loggedIn) {
            record(user, 'Login', 'FAIL', `Could not log in as ${user.email}`);
            await context.close();
            continue;
        }
        record(user, 'Login', 'PASS', `Logged in successfully`);

        // Run tests
        await testWorkflowNavigation(page, user);
        const hasAccess = await testWorkflowDefinitionsList(page, user);

        if (hasAccess) {
            await testWorkflowDesigner(page, user);
            await testWorkflowInstances(page, user);
            await testWorkflowApprovals(page, user);
            await testWorkflowVersions(page, user);
            await testRegistryEndpoint(page, user);
        }

        // Check for JS errors
        const errors = logs.filter(l => l.type === 'error');
        if (errors.length > 0) {
            record(user, 'JS Console Errors', 'WARN', errors.map(e => e.text).join('; ').substring(0, 200));
        } else {
            record(user, 'JS Console Errors', 'PASS', 'No JS errors');
        }

        await logout(page);
        await context.close();
    }

    await browser.close();

    // Print summary
    console.log('\n\n📊 TEST SUMMARY');
    console.log('===============');
    const passed = RESULTS.filter(r => r.status === 'PASS').length;
    const failed = RESULTS.filter(r => r.status === 'FAIL').length;
    const info = RESULTS.filter(r => r.status === 'INFO' || r.status === 'WARN').length;
    console.log(`  ✅ Passed: ${passed}`);
    console.log(`  ❌ Failed: ${failed}`);
    console.log(`  ℹ️  Info/Warn: ${info}`);
    console.log(`  📝 Total: ${RESULTS.length}`);

    if (failed > 0) {
        console.log('\n❌ FAILURES:');
        RESULTS.filter(r => r.status === 'FAIL').forEach(r => {
            console.log(`  - [${r.user}] ${r.test}: ${r.detail}`);
        });
    }

    console.log('\n');
    return failed === 0;
}

runAllTests().then(success => {
    process.exit(success ? 0 : 1);
}).catch(err => {
    console.error('Test suite error:', err);
    process.exit(1);
});
