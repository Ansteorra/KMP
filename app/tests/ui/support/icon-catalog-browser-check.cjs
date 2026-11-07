/** Local acceptance without resets. Creates/removes only a synthetic member and activity. */
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require('playwright');
const { expect } = require('@playwright/test');
const { loginAs, runPhpJson } = require('./ui-helpers.cjs');
const activityExamples = [
    ['kingdom courts', 'court-crown', 'Crowned thrones'],
    ['principality courts', 'court-coronet', 'Gemmed coronet and thrones'],
    ['baronial courts', 'court-pearls', 'Pearled coronet and thrones'],
    ['guild meeting', 'round-table', 'Round table gathering'],
    ['workshop', 'group-class', 'Group class'],
    ['communal feast', 'shared-feast', 'Shared feast'],
    ['group dance', 'dance-circle', 'Group dance'],
    ['war camp', 'encampment', 'Group encampment'],
    ['medieval trumpets', 'trumpet-straight', 'Straight herald trumpet'],
    ['banner fanfare', 'trumpet-banner', 'Bannered herald trumpet'],
    ['sounding horn', 'curved-horn', 'Curved herald horn'],
    ['herald trumpeter', 'herald-trumpeter', 'Herald sounding a trumpet'],
    ['town crier', 'herald-proclamation', 'Herald with proclamation'],
    ['minstrel lute', 'lute', 'Lute'],
    ['harpist', 'harp', 'Harp'],
    ['vocal singing', 'singing', 'Singing'],
    ['campfire storytelling', 'bardic-circle', 'Bardic circle'],
    ['chain collar', 'chain-necklace', 'Chain necklace'],
    ['order of defence', 'master-of-defense', 'Master of Defense'],
    ['pelican vulning itself', 'pelican-vulning', 'Pelican vulning itself'],
    ['Ansteorra', 'ansteorran-star', 'Ansteorran star'],
    ['golden lance', 'lance', 'Lance'],
    ['nesting weights', 'order-of-the-mark', 'Order of the Mark'],
    ['iris of merit', 'rainbow', 'Rainbow'],
    ['star of merit', 'ansteorran-star', 'Ansteorran star'],
    ['blades of merit', 'single-rapier', 'Single rapier'],
    ['centurion', 'phoenix', 'Rising phoenix'],
    ['arc de or', 'archery', 'Archery'],
];

const fixture = String.raw`
if (function_exists('posix_geteuid') && posix_geteuid() === 0) {
    posix_setgid(33);
    posix_setuid(33);
}
require 'vendor/autoload.php';
require 'config/bootstrap.php';
$input = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
$tenant = (new \App\Services\Platform\TenantHostResolver())->resolve('kmp.localhost');
if (!$tenant || $tenant->slug !== 'kmp' || $tenant->status !== 'active'
    || !in_array($tenant->dbServer, ['db', 'postgres', '127.0.0.1', 'localhost'], true)) {
    throw new \RuntimeException('This check requires the local seeded tenant.');
}
$manager = new \App\Services\TenantConnectionManager(\App\Services\Secrets\SecretStoreFactory::fromConfig());
$result = $manager->withTenant($tenant, function () use ($input) {
    $locator = \Cake\ORM\TableRegistry::getTableLocator();
    $members = $locator->get('Members');
    $activities = $locator->get('GatheringActivities');
    if (!empty($input['email'])) {
        $member = $members->find()->where(['email_address' => $input['email']])->firstOrFail();
        $activity = $activities->find()->where(['name' => $input['activityName']])->first();
        if (isset($input['legacy'])) {
            if (!$activity) throw new \RuntimeException('Synthetic activity not found');
            $activities->updateAll(['icon_name' => $input['legacy'], 'icon_source' => 'bootstrap'], ['id' => $activity->id]);
        }
        if (!empty($input['cleanup'])) {
            if ($activity) $activities->deleteOrFail($activity, ['purge' => true]);
            $locator->get('MemberRoles')->deleteAll(['member_id' => $member->id]);
            $members->deleteOrFail($member);
            return ['cleaned' => true];
        }
        if ($activity) $activity = $activities->get($activity->id);
        return ['id' => $activity?->id, 'icon' => $activity?->icon_name, 'source' => $activity?->icon_source];
    }
    $admin = $members->find()->where(['email_address' => 'admin@amp.ansteorra.org'])->firstOrFail();
    $password = 'Temporary!' . bin2hex(random_bytes(24));
    $name = 'Synthetic icon catalog ' . bin2hex(random_bytes(8));
    $member = $members->newEntity([
        'first_name' => 'Synthetic', 'last_name' => 'Icons', 'sca_name' => 'Synthetic Icon Tester',
        'email_address' => 'icon-' . bin2hex(random_bytes(8)) . '@example.test', 'password' => $password,
        'status' => $admin->status, 'branch_id' => $admin->branch_id, 'membership_number' => 'SYNTHETIC',
        'membership_expires_on' => '2099-01-01', 'birth_year' => 1990, 'birth_month' => 1,
        'created_by' => $admin->id, 'street_address' => '1 Synthetic Street', 'city' => 'Test',
        'state' => 'TX', 'zip' => '75001', 'phone_number' => '5555550100',
    ]);
    $members->saveOrFail($member);
    $role = $locator->get('Roles')->find()->innerJoinWith('Permissions', fn($q) => $q->where(['Permissions.is_super_user' => true]))->firstOrFail();
    $locator->get('MemberRoles')->saveOrFail($locator->get('MemberRoles')->newEntity([
        'member_id' => $member->id, 'role_id' => $role->id, 'branch_id' => $admin->branch_id,
        'start_on' => '2020-01-01', 'expires_on' => '2099-01-01', 'approver_id' => $admin->id, 'entity_type' => 'Direct Grant',
    ]));
    return ['email' => $member->email_address, 'password' => $password, 'activityName' => $name];
});
echo json_encode($result, JSON_THROW_ON_ERROR);
`;

(async () => {
    const data = runPhpJson(fixture);
    const browser = await chromium.launch({ headless: true, args: ['--no-sandbox'] });
    const output = process.env.KMP_ICON_EVIDENCE_DIR || '/tmp/kmp-icon-evidence';
    fs.mkdirSync(output, { recursive: true });
    try {
        const context = await browser.newContext({ baseURL: process.env.PLAYWRIGHT_BASE_URL || 'http://kmp.localhost:8080', viewport: { width: 1280, height: 1000 }, reducedMotion: 'reduce' });
        const page = await context.newPage();
        const errors = [];
        const requests = [];
        page.on('pageerror', error => errors.push(error.message));
        page.on('request', request => requests.push(new URL(request.url()).pathname));
        await loginAs(page, data.email, data.password);
        const onlineOnly = page.getByRole('button', { name: 'Use online only', exact: true });
        if (await onlineOnly.isVisible()) await onlineOnly.click();
        await page.goto('/gathering-activities/add');
        assert.equal(requests.some(url => /kmp-icons.*catalog\.json$/.test(url)), false);
        assert.equal(requests.some(url => /bootstrap-icons/.test(url)), false);
        const ordinarySvgRequests = new Set(requests.filter(url => /\/kmp-icons\/.*\.svg$/.test(url))).size;
        assert.equal(ordinarySvgRequests < 150, true);
        await page.getByLabel('Name', { exact: true }).fill(data.activityName);
        await page.getByRole('searchbox', { name: 'Search icons', exact: true }).fill('rapier');
        const blades = page.getByRole('button', { name: 'Fencing blades', exact: true });
        await blades.focus();
        await page.keyboard.press('Space');
        await expect(blades).toHaveAttribute('aria-pressed', 'true');
        await expect(page.getByLabel('Selected icon', { exact: true })).toHaveValue('fencing');
        assert.equal(await page.locator('[data-activity-appearance-target=icons] button').count() <= 48, true);
        await page.locator('[data-controller=activity-appearance]').screenshot({ path: path.join(output, 'picker.png') });
        await page.getByRole('button', { name: 'Submit', exact: true }).click();
        await page.waitForURL(url => /^\/gathering-activities\/?$/.test(url.pathname));
        let saved = runPhpJson(fixture, data);
        assert.equal(saved.icon, 'fencing');
        assert.equal(saved.source, 'catalog');
        await page.goto(`/gathering-activities/edit/${saved.id}`);
        await expect(page.getByLabel('Selected icon', { exact: true })).toHaveValue('fencing');
        runPhpJson(fixture, { ...data, legacy: 'bi-bullseye' });
        await page.reload();
        await expect(page.getByLabel('Selected icon', { exact: true })).toHaveValue('target');
        await page.getByRole('button', { name: 'Submit', exact: true }).click();
        await page.waitForURL(/\/gathering-activities\/view\//);
        saved = runPhpJson(fixture, data);
        assert.equal(saved.icon, 'target');
        runPhpJson(fixture, { ...data, legacy: 'future-unknown-icon' });
        await page.goto(`/gathering-activities/edit/${saved.id}`);
        await page.getByRole('button', {name:'Browse icon catalog',exact:true}).click();
        await page.locator('[data-activity-appearance-target=icons] button').first().waitFor({state:'visible'});
        await expect(page.getByLabel('Selected icon', {exact:true})).toHaveValue('future-unknown-icon');
        await page.getByRole('button', { name: 'Submit', exact: true }).click();
        await page.waitForURL(/\/gathering-activities\/view\//);
        assert.equal(runPhpJson(fixture, data).icon, 'future-unknown-icon');

        for (const [query, id, label] of activityExamples) {
            await page.goto(`/gathering-activities/edit/${saved.id}`);
            await page.getByRole('searchbox', { name: 'Search icons', exact: true }).fill(query);
            const choice = page.getByRole('button', { name: label, exact: true });
            await choice.focus();
            await page.keyboard.press('Space');
            await expect(choice).toHaveAttribute('aria-pressed', 'true');
            await expect(page.getByLabel('Selected icon', { exact: true })).toHaveValue(id);
            await page.getByRole('button', { name: 'Submit', exact: true }).click();
            await page.waitForURL(/\/gathering-activities\/view\//);
            assert.equal(runPhpJson(fixture, data).icon, id);
            await page.goto(`/gathering-activities/edit/${saved.id}`);
            await expect(page.getByLabel('Selected icon', { exact: true })).toHaveValue(id);
        }
        await page.getByRole('searchbox', { name: 'Search icons', exact: true }).fill('thrones');
        await expect(page.locator('[data-activity-appearance-target=icons] button')).toHaveCount(3);
        await page.locator('[data-controller=activity-appearance]').screenshot({ path: path.join(output, 'picker-courts.png') });
        await page.setViewportSize({ width: 390, height: 844 });
        await page.getByRole('searchbox', { name: 'Search icons', exact: true }).fill('group activities');
        await expect(page.locator('[data-activity-appearance-target=icons] button')).toHaveCount(9);
        await page.locator('[data-controller=activity-appearance]').screenshot({ path: path.join(output, 'picker-groups-mobile.png') });
        await page.getByRole('searchbox', { name: 'Search icons', exact: true }).fill('heralds');
        await expect(page.locator('[data-activity-appearance-target=icons] button')).toHaveCount(5);
        await page.locator('[data-controller=activity-appearance]').screenshot({ path: path.join(output, 'picker-heralds-mobile.png') });
        await page.setViewportSize({ width: 1280, height: 1000 });
        await page.locator('[data-controller=activity-appearance]').screenshot({ path: path.join(output, 'picker-heralds.png') });
        await page.getByRole('searchbox', { name: 'Search icons', exact: true }).fill('bardic');
        await expect(page.locator('[data-activity-appearance-target=icons] button')).toHaveCount(4);
        await page.locator('[data-controller=activity-appearance]').screenshot({ path: path.join(output, 'picker-bardic.png') });
        await page.setViewportSize({ width: 390, height: 844 });
        await page.locator('[data-controller=activity-appearance]').screenshot({ path: path.join(output, 'picker-bardic-mobile.png') });
        await page.setViewportSize({ width: 1280, height: 1000 });

        await page.goto('/branches/add');
        await page.getByRole('button', {name:'Link type: Link',exact:true}).click();
        await page.getByRole('button', {name:'Discord',exact:true}).click();
        await expect(page.getByRole('button', {name:'Link type: Discord',exact:true})).toBeVisible();

        await page.goto('/gatherings');
        await page.locator('turbo-frame table tbody tr').first().waitFor({state:'visible'});
        await page.screenshot({ path: path.join(output, 'desktop-grid.png') });
        await page.locator('[data-filter-button]').first().click();
        await expect(page.locator('[data-filter-button]').first()).toHaveAttribute('aria-expanded', 'true');
        await page.keyboard.press('Escape');
        for (const control of await page.locator('button:has(kmp-icon), a:has(kmp-icon)').all()) {
            if (await control.isVisible()) await expect(control).toHaveAccessibleName(/\S/);
        }
        await page.screenshot({ path: path.join(output, 'desktop-grid.png') });
        const countBefore = requests.filter(url => /catalog\.json$/.test(url)).length;
        await page.setViewportSize({ width: 390, height: 844 });
        await page.goto('/gatherings/mobile-calendar');
        await page.waitForSelector('.mobile-event-card, .mobile-events-empty');
        await page.screenshot({ path: path.join(output, 'mobile-calendar.png') });
        assert.equal(requests.filter(url => /catalog\.json$/.test(url)).length, countBefore);
        await page.evaluate(async () => {
            await navigator.serviceWorker.register('/sw.js', { updateViaCache: 'none' });
            const registration = await navigator.serviceWorker.ready;
            await new Promise((resolve, reject) => {
                const channel = new MessageChannel();
                const timer = setTimeout(() => reject(new Error('Offline icons preparation timed out')), 30000);
                channel.port1.onmessage = event => {
                    clearTimeout(timer); channel.port1.close();
                    event.data.ready ? resolve() : reject(new Error('Offline preparation failed'));
                };
                registration.active.postMessage({ type: 'PREPARE_OFFLINE' }, [channel.port2]);
            });
        });
        await page.waitForFunction(() => navigator.serviceWorker.controller);
        const uncommon = await page.evaluate(async () => {
            await window.KMP_icons.loadIcon('abacus');
            const icon = window.KMP_icons.createIcon('abacus');
            document.body.append(icon);
            const asset = icon.style.getPropertyValue('--kmp-icon').match(/url\("([^"]+)"\)/)[1];
            await fetch(asset);
            return asset;
        });
        const customAssets = await page.evaluate(async () => {
            const assets = [];
            for (const name of ['court-pearls', 'herald-proclamation', 'lute', 'bardic-circle']) {
                const icon = window.KMP_icons.createIcon(name);
                document.body.append(icon);
                const asset = icon.style.getPropertyValue('--kmp-icon').match(/url\("([^"]+)"\)/)[1];
                await fetch(asset);
                assets.push(asset);
            }
            return assets;
        });
        await context.setOffline(true);
        await page.goto('/offline?page=calendar', { waitUntil: 'domcontentloaded' });
        await expect(page.locator('kmp-icon[name="calendar-event"]').first()).toBeVisible();
        assert.equal(await page.evaluate(async () => (await window.KMP_icons.loadIcon('abacus')).id), 'abacus');
        assert.equal(await page.evaluate(async asset => (await fetch(asset)).ok, uncommon), true);
        for (const asset of customAssets) assert.equal(await page.evaluate(async url => (await fetch(url)).ok, asset), true);
        await page.screenshot({ path: path.join(output, 'offline-calendar.png') });
        await context.setOffline(false);
        await page.goto('/gatherings/mobile-calendar');
        const examples = ['search', 'edit', 'circle-check', 'crown', 'armored-combat', 'archery', 'equestrian', 'needlework', 'scribal', 'laurel-wreath', 'metalwork', 'fencing', ...activityExamples.map(([, id]) => id)];
        await page.setViewportSize({ width: 1100, height: 1200 });
        await page.evaluate(examples => {
            const panel = document.createElement('main');
            panel.id = 'icon-evidence';
            panel.className = 'p-4 bg-white text-dark';
            const heading = document.createElement('h1'); heading.textContent = 'KMP icons: 16 / 20 / 24px'; panel.append(heading);
            for (const name of examples) {
                const row = document.createElement('div'); row.className = 'd-flex gap-4 align-items-center mb-3';
                for (const size of [16, 20, 24]) row.append(window.KMP_icons.createIcon(name, {size}));
                row.append(document.createTextNode(name)); panel.append(row);
            }
            document.body.replaceChildren(panel);
        }, examples);
        await page.waitForTimeout(500);
        await page.screenshot({ path: path.join(output, 'mixed-sources-sizes.png'), fullPage: true });
        const sizes = await page.locator('#icon-evidence kmp-icon').evaluateAll(icons => icons.map(icon => icon.getBoundingClientRect().width));
        assert.deepEqual(sizes.slice(0, 3), [16, 20, 24]);
        await page.evaluate(ids => {
            const panel = document.querySelector('#icon-evidence');
            panel.replaceChildren();
            for (const theme of ['bg-white text-dark', 'bg-dark text-light']) {
                const section = document.createElement('section');
                section.className = `${theme} p-4 mb-3`;
                const heading = document.createElement('h2'); heading.textContent = 'Activity icons: 16 / 20 / 24px / danger / disabled'; section.append(heading);
                for (const id of ids) {
                    const row = document.createElement('div'); row.className = 'd-flex gap-4 align-items-center mb-3';
                    for (const size of [16, 20, 24]) row.append(window.KMP_icons.createIcon(id, {size}));
                    row.append(window.KMP_icons.createIcon(id, {size:24, class:'text-danger'}));
                    const button = document.createElement('button'); button.disabled = true; button.className = 'btn btn-secondary'; button.setAttribute('aria-label', id); button.append(window.KMP_icons.createIcon(id, {size:24})); row.append(button);
                    row.append(document.createTextNode(id)); section.append(row);
                }
                panel.append(section);
            }
        }, activityExamples.map(([, id]) => id));
        await page.screenshot({ path: path.join(output, 'custom-icons-themes.png'), fullPage: true });
        const paths = requests.filter(url => /\/kmp-icons\//.test(url));
        for (const url of [...new Set(paths.filter(url => url.endsWith('.svg')))].slice(0, 3)) {
            const response = await context.request.get(url);
            assert.equal(response.status(), 200);
            assert.match(response.headers()['cache-control'], /immutable/);
            assert.match(await response.text(), /stroke-width="1.5"/);
        }
        assert.deepEqual(errors, []);
        fs.writeFileSync(path.join(output, 'results.json'), JSON.stringify({ savedCanonical: true, legacyReopened: true, unknownPreserved: true, keyboardSelection: true, customActivitiesSavedAndReopened: activityExamples.map(([, id]) => id), controlsNamed: true, ordinarySvgRequests, noBootstrapRequests: true, staticAssets: true, offlineCommonAndDynamic: true, offlineCustom: true, sizes: [16,20,24], pageErrors: errors }, null, 2));
        console.log(`Icon browser checks passed. Evidence: ${output}`);
    } finally {
        await browser.close();
        runPhpJson(fixture, { ...data, cleanup: true });
    }
})().catch(error => { console.error(error); process.exitCode = 1; });
