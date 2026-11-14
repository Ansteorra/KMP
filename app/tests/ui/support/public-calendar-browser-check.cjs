/** Non-destructive acceptance of the anonymous calendar with many activity choices. */
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require('playwright');
const { expect } = require('@playwright/test');
const { runPhpJson } = require('./ui-helpers.cjs');

const fixture = String.raw`
if (function_exists('posix_geteuid') && posix_geteuid() === 0) { posix_setgid(33); posix_setuid(33); }
require 'vendor/autoload.php'; require 'config/bootstrap.php';
$input = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
$tenant = (new \App\Services\Platform\TenantHostResolver())->resolve('kmp.localhost');
if (!$tenant || $tenant->slug !== 'kmp' || $tenant->status !== 'active'
    || !in_array($tenant->dbServer, ['db','postgres','127.0.0.1','localhost'], true)) {
    throw new \RuntimeException('Calendar acceptance requires the local seeded tenant.');
}
$manager = new \App\Services\TenantConnectionManager(\App\Services\Secrets\SecretStoreFactory::fromConfig());
$result = $manager->withTenant($tenant, function () use ($input) {
    $tables = \Cake\ORM\TableRegistry::getTableLocator();
    $gatherings = $tables->get('Gatherings'); $activities = $tables->get('GatheringActivities');
    if (!empty($input['cleanup'])) {
        $event = $gatherings->find()->where(['public_id' => $input['publicId'], 'name LIKE' => 'Calendar Acceptance %'])->firstOrFail();
        $gatherings->deleteOrFail($event, ['purge' => true]);
        foreach ($activities->find()->where(['name LIKE' => $input['prefix'] . '%']) as $activity) {
            $activities->deleteOrFail($activity, ['purge' => true]);
        }
        return ['cleaned' => true];
    }
    return $gatherings->getConnection()->transactional(function () use ($tables,$gatherings,$activities) {
        $admin = $tables->get('Members')->find()->where(['email_address' => 'admin@amp.ansteorra.org'])->firstOrFail();
        $type = $tables->get('GatheringTypes')->find()->firstOrFail();
        $prefix = 'Calendar Acceptance ' . bin2hex(random_bytes(5));
        $start = (new \Cake\I18n\DateTime('first day of next month'))->endOfMonth()->startOfDay();
        while ($start->format('N') !== '5') { $start = $start->subDays(1); }
        $start = $start->setTime(18,0);
        $event = $gatherings->newEntity([
            'name'=>$prefix.' — A gathering with a long event name for narrow screens', 'branch_id'=>$admin->branch_id, 'gathering_type_id'=>$type->id,
            'start_date'=>$start, 'end_date'=>$start->addDays(9), 'timezone'=>'America/Chicago',
            'location'=>'100 Synthetic Lane, Testville, TX 75001', 'description'=>'Calendar acceptance with a long address link: https://example.test/'.str_repeat('event-information-',20),
            'public_page_enabled'=>true, 'created_by'=>$admin->id,
        ]);
        $event->set('published',true); $gatherings->saveOrFail($event);
        $ids=[];
        for ($i=0;$i<80;$i++) {
            $activity=$activities->saveOrFail($activities->newEntity([
                'name'=>$prefix.' '.sprintf('%03d',$i), 'display_order'=>79-$i,
                'icon_name'=>['archery','bardic-circle','armored-combat'][$i%3], 'activity_type'=>$i===1 ? 'Order Circle' : ($i===2 ? 'Royal Progress' : ['Martial','Arts & Sciences','General'][$i%3]),
            ]));
            $ids[]=$activity->id;
            $links=$tables->get('GatheringsGatheringActivities');
            $links->saveOrFail($links->newEntity(['gathering_id'=>$event->id,'gathering_activity_id'=>$activity->id,'sort_order'=>$i]));
        }
        $business = $activities->saveOrFail($activities->newEntity([
            'name'=>$prefix.' Administrative Gate', 'display_order'=>0, 'activity_type'=>'Business',
        ]));
        $tables->get('GatheringsGatheringActivities')->saveOrFail($tables->get('GatheringsGatheringActivities')->newEntity([
            'gathering_id'=>$event->id,'gathering_activity_id'=>$business->id,'sort_order'=>81,
        ]));
        return ['prefix'=>$prefix,'publicId'=>$event->public_id,'ids'=>$ids,'month'=>$start->format('Y-m'),'nextMonth'=>$start->addDays(9)->format('Y-m')];
    });
});
echo json_encode($result, JSON_THROW_ON_ERROR);
`;

(async () => {
    const data = runPhpJson(fixture);
    const browser = await chromium.launch({ headless: true });
    const errors = [];
    const page = await browser.newPage({ baseURL: 'http://kmp.localhost:8080', viewport: { width: 1280, height: 950 }, reducedMotion: 'reduce' });
    page.on('pageerror', error => errors.push(error.message));
    try {
        await page.goto('/events');
        await page.waitForFunction(() => window.Stimulus?.getControllerForElementAndIdentifier(
            document.querySelector('[data-controller~="public-calendar"]'), 'public-calendar',
        ));
        await expect(page.locator('.kc-empty-weekend').first()).toBeVisible();
        const link = page.locator(`.kc-event-name[href*="${data.publicId}"]`).first();
        const row = link.locator('xpath=ancestor::tr');
        await expect(row.locator('.kc-address')).toHaveText('100 Synthetic Lane, Testville, TX 75001');
        assert.equal(await row.locator('[data-activity-badge]').count(), 4);
        await expect(row.locator('.kc-icons > kmp-icon')).toHaveCount(0);
        await expect(page.locator('[data-activity-type="Business"]')).toHaveCount(0);
        await expect(page.getByText(data.prefix + ' Administrative Gate', { exact: true })).toHaveCount(0);
        assert.deepEqual(await row.locator('[data-activity-badge]').evaluateAll(nodes => nodes.map(n => Number(n.dataset.activityBadge))), [data.ids[2], data.ids[1], data.ids[79], data.ids[78]]);
        const disclosure = row.locator('details');
        const panel = page.locator(`#${await disclosure.getAttribute('data-panel')}`);
        await row.locator('.kc-group').click();
        await expect(panel).toBeVisible();
        assert.deepEqual(await panel.locator('[data-activity-badge]').evaluateAll(nodes => nodes.slice(0, 80).map(n => Number(n.dataset.activityBadge))), [data.ids[2], data.ids[1], ...data.ids.slice(3).reverse(), data.ids[0]]);
        await row.locator('.kc-group').click();
        await expect(panel).toBeHidden();
        await row.locator('.kc-overflow').click();
        await expect(panel).toBeVisible();
        await expect(disclosure.locator('summary')).toBeFocused();
        await page.keyboard.press('Enter');
        await expect(panel).toBeHidden();
        await expect(page.locator('.kc-filter-picker')).toHaveCount(1);
        const picker = page.locator('.kc-filter-picker');
        assert.equal(await picker.getAttribute('open'), null);
        await picker.locator(':scope > summary').click();
        await expect(picker.locator('.kc-picker-body')).toHaveCSS('width', '600px');
        const groups = picker.locator('[data-public-calendar-target="activityGroup"]');
        const royalColumns = await groups.nth(1).locator('label').evaluateAll(nodes => nodes.slice(0, 2).map(n => ({x:n.getBoundingClientRect().x,y:n.getBoundingClientRect().y})));
        assert.equal(royalColumns[0].y, royalColumns[1].y);
        assert.ok(royalColumns[1].x > royalColumns[0].x);
        const selectedNames = page.locator('[data-public-calendar-target="selectedLabels"]');
        const selectionStatus = page.locator('[data-public-calendar-target="selectionStatus"]');
        assert.deepEqual((await groups.evaluateAll(nodes => nodes.map(el => el.dataset.activityType))).slice(0, 2), ['Royal Progress', 'Order Circle']);
        assert.ok(await groups.evaluateAll(nodes => nodes.slice(0, 2).every(el => el.open) && nodes.slice(2).every(el => !el.open)));
        const martial = picker.locator('details[data-activity-type="Martial"]');
        await martial.locator(':scope > summary').focus();
        await page.keyboard.press('Enter');
        await expect(martial).toHaveAttribute('open', '');
        await page.keyboard.press('Space');
        await expect(martial).not.toHaveAttribute('open', '');
        const royalChoice = picker.getByRole('checkbox', { name: data.prefix + ' 002', exact: true });
        await royalChoice.focus();
        await page.keyboard.press('Space');
        await expect(royalChoice).toBeChecked();
        const royalSurface = royalChoice.locator('xpath=..').locator('.kc-choice-content');
        await expect(royalSurface).toHaveCSS('border-top-width', '2px');
        await expect(royalSurface.locator('.kc-choice-selected')).toBeVisible();
        await expect(royalSurface).toHaveCSS('outline-style', 'solid');
        await expect(royalChoice).toHaveAccessibleName(data.prefix + ' 002');
        const anyCircle = picker.getByRole('checkbox', { name: 'Any order circle', exact: true });
        await anyCircle.locator('xpath=..').click();
        await expect(picker.locator('[data-public-calendar-target="activityCount"]')).toHaveText('(2)');
        await expect(selectedNames).toContainText(data.prefix + ' 002');
        await expect(selectedNames).toContainText('Any order circle');
        await expect(selectionStatus).toHaveText('Selection changed. Apply filters to update events.');
        await anyCircle.locator('xpath=..').click();
        await picker.getByLabel('Find an activity').fill('Order Circle');
        await expect(picker.locator('[data-public-calendar-target="activityGroup"]:visible')).toHaveCount(1);
        const circleChoice = picker.locator(`input[name="activities[]"][value="${data.ids[1]}"]`);
        await expect(circleChoice.locator('xpath=..').locator('.kc-choice-name')).toHaveText(data.prefix + ' 001');
        await expect(circleChoice.locator('xpath=..').locator('.kc-choice-icon [data-activity-badge]')).toHaveAttribute('data-activity-badge', String(data.ids[1]));
        await expect(circleChoice.locator('xpath=..').locator('.kc-choice-icon kmp-icon')).toHaveAttribute('name', 'bardic-circle');
        await expect(picker.locator(`input[name="activities[]"][value="${data.ids[2]}"]:checked`)).toHaveCount(1);
        await picker.getByLabel('Find an activity').fill('');
        assert.ok(await groups.evaluateAll(nodes => nodes.slice(0, 2).every(el => el.open) && nodes.slice(2).every(el => !el.open)));
        const options = picker.locator('.kc-picker-options');
        assert.ok(await options.evaluate(el => el.clientHeight <= 240));
        await picker.getByLabel('Find an activity').fill(data.prefix + ' 079');
        await expect(picker.locator('[data-public-calendar-target="activityOption"]:visible')).toHaveCount(1);
        await expect(picker.locator('details[data-activity-type="Arts & Sciences"]')).toHaveAttribute('open', '');
        await picker.locator(`input[value="${data.ids.at(-1)}"]`).locator('xpath=..').click();
        await picker.getByLabel('Find an activity').fill('no matching choice');
        await expect(picker.locator('[role="status"]')).toHaveText('0 options shown');
        assert.ok(await picker.locator(`input[value="${data.ids.at(-1)}"]`).isChecked());
        await expect(selectedNames).toContainText(data.prefix + ' 079');
        await picker.locator(':scope > summary').click();
        await page.getByRole('button', { name: 'Apply filters', exact: true }).click();
        await expect(page.locator(`.kc-event-name[href*="${data.publicId}"]`)).toHaveCount(2);
        assert.ok(new URL(page.url()).searchParams.getAll('activities[]').includes(String(data.ids[2])));
        await expect(selectedNames).toContainText(data.prefix + ' 079');
        await expect(selectionStatus).toBeEmpty();
        await expect(page.locator('.kc-continuing')).toHaveCount(1);
        await page.selectOption('#calendar-month', data.nextMonth);
        await page.getByRole('button', { name: 'Apply filters', exact: true }).click();
        await expect(page.locator(`.kc-event-name[href*="${data.publicId}"]`)).toHaveCount(1);
        await expect(page.locator('.kc-continuing')).toHaveCount(1);
        await page.locator(`.kc-event-name[href*="${data.publicId}"]`).click();
        await expect(page).toHaveURL(new RegExp('/gatherings/public-landing/' + data.publicId));
        await page.goto('/events?q=' + encodeURIComponent(data.prefix));
        for (const width of [1280, 768, 375, 320]) {
            await page.setViewportSize({ width, height: 950 });
            await expect(page.locator('.kc-table')).toHaveCSS('min-width', width <= 700 ? '0px' : '810px');
            const geometry = await page.evaluate(() => ({ viewport: innerWidth, scroll: document.documentElement.scrollWidth,
                table: (()=>{const el=document.querySelector('.kc-table-scroll');return {width:el.clientWidth,scroll:el.scrollWidth,overflow:getComputedStyle(el).overflowX,rect:el.getBoundingClientRect().toJSON()};})(), outside: [...document.querySelectorAll('body *')].filter(el => el.getBoundingClientRect().right > innerWidth + 1).slice(0, 12).map(el => ({tag:el.tagName,cls:el.className,right:el.getBoundingClientRect().right})) }));
            assert.ok(geometry.scroll <= width + 1, `Overflow at ${width}: ${JSON.stringify(geometry)}`);
            const filters = page.locator('.kc-filter-bar');
            await page.evaluate(() => window.scrollTo({ top: 1200, behavior: 'instant' }));
            await expect.poll(async () => Math.abs((await filters.boundingBox()).y)).toBeLessThan(1);
            const tableScroll = page.locator('.kc-table-scroll');
            const vertical = await tableScroll.evaluate(el => ({
                visible: el.clientHeight, content: el.querySelector('table').getBoundingClientRect().height,
            }));
            assert.ok(vertical.visible >= vertical.content - 1, 'The table must grow to its full height instead of scrolling vertically');
            const documentScroll = await page.evaluate(() => window.scrollY);
            const pinned = await filters.boundingBox();
            await page.mouse.move(width / 2, pinned.y + pinned.height + 100);
            await page.mouse.wheel(0, 200);
            await expect.poll(() => page.evaluate(() => window.scrollY)).toBeGreaterThan(documentScroll);
            assert.equal(await tableScroll.evaluate(el => el.scrollTop), 0, 'Wheel scrolling must belong to the document');
            const before = await page.locator('.kc-table').boundingBox();
            await page.evaluate(() => window.scrollBy({ top: 150, behavior: 'instant' }));
            const after = await page.locator('.kc-table').boundingBox();
            assert.ok(after.y < before.y - 140, 'The document should scroll beneath the filters');
            assert.ok(Math.abs((await filters.boundingBox()).y) < 1, 'Filters should remain pinned');
            const filterDisclosure = page.locator('.kc-filter-settings');
            if (width <= 700) {
                assert.ok((await filters.boundingBox()).height < 100, 'Closed phone filters should leave room for events');
                await filterDisclosure.locator(':scope > summary').click();
            }
            await picker.locator(':scope > summary').click();
            await expect(picker.getByLabel('Find an activity')).toBeVisible();
            assert.ok(await picker.getByLabel('Find an activity').evaluate(el => {
                const rect = el.getBoundingClientRect();
                return document.elementFromPoint(rect.x + rect.width / 2, rect.y + rect.height / 2) === el;
            }), 'The activity picker must appear above scrolling rows');
            await picker.locator(':scope > summary').click();
            if (width <= 700) await filterDisclosure.locator(':scope > summary').click();
            const eventLink = page.locator(`.kc-event-name[href*="${data.publicId}"]`).first();
            await page.keyboard.press('Tab');
            await eventLink.evaluate(el => {
                const filters = document.querySelector('.kc-filter-bar').getBoundingClientRect();
                window.scrollTo({ top: window.scrollY + el.getBoundingClientRect().top - filters.height / 2, behavior: 'instant' });
                el.focus({ preventScroll: true });
            });
            await expect.poll(async () => {
                const header = await filters.boundingBox();
                const focused = await eventLink.boundingBox();
                return focused.y - header.y - header.height;
            }).toBeGreaterThanOrEqual(0);
            if (width > 700 && width < 858) {
                const scroll = page.locator('.kc-table-scroll');
                await scroll.focus(); await page.keyboard.press('ArrowRight'); await page.waitForTimeout(150);
                assert.ok(await scroll.evaluate(el => el.scrollLeft > 0));
            }
            if (width <= 700) {
                assert.ok(await tableScroll.evaluate(el => el.scrollWidth <= el.clientWidth + 1), 'Phone rows should fit without horizontal scrolling');
                assert.ok(await page.locator('.kc-date-range').evaluateAll(nodes => nodes.every(node => {
                    const range = document.createRange();
                    range.selectNodeContents(node);
                    const tops = [...range.getClientRects()].filter(rect => rect.width > 0).map(rect => rect.top);
                    return Math.max(...tops) - Math.min(...tops) < 1;
                })), 'Event and empty-weekend date ranges must stay on one line');
                const phoneRow = eventLink.locator('xpath=ancestor::tr');
                for (const selector of ['.kc-address', '.kc-group', '.kc-indicators']) {
                    const rect = await phoneRow.locator(selector).boundingBox();
                    assert.ok(rect.x >= 0 && rect.x + rect.width <= width + 1, selector + ' must fit on a phone');
                }
                const summary = phoneRow.locator('.kc-more summary');
                const target = await summary.boundingBox();
                assert.ok(target.width >= 44 && target.height >= 44, 'Phone disclosure targets should be at least 44px');
                await summary.click();
                const phonePanel = page.locator('#' + await phoneRow.locator('details').getAttribute('data-panel'));
                await expect(phonePanel).toBeVisible();
                const columns = await phonePanel.locator('.kc-detail-body').evaluate(el => getComputedStyle(el).gridTemplateColumns);
                assert.equal(columns.split(' ').length, 1, 'Expanded phone details should use one column');
                assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), 'Long expanded event information must wrap');
                await summary.click();
                await expect(phonePanel).toBeHidden();
                await expect(page.getByRole('columnheader')).toHaveCount(4);
            }
        }
        await page.setViewportSize({ width: 320, height: 400 });
        await page.evaluate(() => window.scrollTo({ top: 1200, behavior: 'instant' }));
        assert.ok((await page.locator('.kc-filter-bar').boundingBox()).height <= 241, 'Short viewports must leave room for events');
        const phoneContext = await browser.newContext({ baseURL: 'http://kmp.localhost:8080',
            viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true, reducedMotion: 'reduce' });
        const phone = await phoneContext.newPage();
        await phone.goto('/events?q=' + encodeURIComponent(data.prefix));
        const phoneFilters = phone.locator('.kc-filter-settings');
        await expect(phoneFilters).not.toHaveAttribute('open', '');
        await expect(phoneFilters.locator('.kc-filter-hint')).toContainText(data.prefix);
        await phoneFilters.locator(':scope > summary').tap();
        await phone.locator('.kc-filter-picker > summary').tap();
        await phone.locator(`input[name="activities[]"][value="${data.ids[2]}"]`).locator('xpath=..').tap();
        await phone.getByRole('button', { name: 'Apply filters', exact: true }).tap();
        await expect(phone.locator('[data-public-calendar-target="selectedLabels"]')).toContainText(data.prefix + ' 002');
        await expect(phoneFilters).not.toHaveAttribute('open', '');
        if (process.env.KMP_CALENDAR_EVIDENCE_DIR) {
            fs.mkdirSync(process.env.KMP_CALENDAR_EVIDENCE_DIR, { recursive: true });
            await phone.screenshot({ path: path.join(process.env.KMP_CALENDAR_EVIDENCE_DIR, 'public-calendar-mobile.png'), fullPage: false });
        }
        await phone.setViewportSize({ width: 844, height: 390 });
        await expect(phoneFilters).not.toHaveAttribute('open', '');
        await phoneFilters.locator(':scope > summary').tap();
        await expect(phone.getByLabel('Search events', { exact: true })).toBeVisible();
        assert.ok((await phone.locator('.kc-filter-bar').boundingBox()).height <= 235, 'Landscape filters must leave room for events');
        await phoneContext.close();
        const noScript = await browser.newContext({ baseURL: 'http://kmp.localhost:8080', javaScriptEnabled: false,
            viewport: { width: 320, height: 568 } });
        const fallback = await noScript.newPage();
        await fallback.goto('/events?q=' + encodeURIComponent(data.prefix));
        await expect(fallback.getByLabel('Search events', { exact: true })).toBeVisible();
        await expect(fallback.locator('.kc-detail-row').first()).toBeVisible();
        assert.ok(await fallback.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), 'Phone fallback details must wrap without JavaScript');
        await noScript.close();
        if (process.env.KMP_CALENDAR_EVIDENCE_DIR) {
            fs.mkdirSync(process.env.KMP_CALENDAR_EVIDENCE_DIR, { recursive: true });
            await page.setViewportSize({ width: 1280, height: 950 });
            await page.screenshot({ path: path.join(process.env.KMP_CALENDAR_EVIDENCE_DIR, 'public-calendar-desktop.png'), fullPage: true });
        }
        assert.deepEqual(errors, []);
        console.log('Public calendar passed: ordered overflow, grouped 80-activity picker, royal/circle choices, empty weekends, continuation, navigation, pinned filters, unobscured keyboard focus and mobile reflow.');
    } finally {
        await browser.close();
        runPhpJson(fixture, { ...data, cleanup: true });
    }
})().catch(error => { console.error(error); process.exitCode = 1; });
