/** Local acceptance: synthetic records and Mailpit only; never resets the database. */
const assert = require('node:assert/strict');
const fs = require('node:fs');
const { chromium, request } = require('playwright');
const { expect } = require('@playwright/test');
const { runPhpJson } = require('./ui-helpers.cjs');
const { getMailpitApiUrl } = require('./test-environment.cjs');
const source = fs.readFileSync(require.resolve('./offline-app-browser-check.cjs'), 'utf8');
const memberFixture = source.match(/const tenantFixture = String.raw`([\s\S]*?)`;/)[1];
const scoped = String.raw`
require 'vendor/autoload.php'; require 'config/bootstrap.php';
$input = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
$app = new \App\Application(CONFIG); $app->bootstrap(); $app->pluginBootstrap();
$app->routes(\Cake\Routing\Router::createRouteBuilder('/'));
$app->pluginRoutes(\Cake\Routing\Router::createRouteBuilder('/'));
$tenant = (new \App\Services\Platform\TenantHostResolver())->resolve('kmp.localhost');
if (!$tenant || $tenant->slug !== 'kmp' || !in_array($tenant->dbServer, ['db','localhost','postgres','127.0.0.1'], true)) throw new \RuntimeException('Local tenant required');
$manager = new \App\Services\TenantConnectionManager(\App\Services\Secrets\SecretStoreFactory::fromConfig());
$result = $manager->withTenant($tenant, function () use ($input, $app) {
    $tables = \Cake\ORM\TableRegistry::getTableLocator();
    $member = $tables->get('Members')->get($input['memberId']);
    if ($member->email_address !== $input['email'] || !str_ends_with($member->email_address, '@example.test')) throw new \RuntimeException('Synthetic fixture required');
    if ($input['mode'] === 'setup') {
        $admin = $tables->get('Members')->find()->where(['email_address' => 'admin@amp.ansteorra.org'])->firstOrFail();
        foreach ($tables->get('MemberRoles')->find()->where(['member_id' => $admin->id, 'expires_on IS' => null]) as $role) {
            $data = $role->toArray(); unset($data['id']); $data['member_id'] = $member->id;
            $tables->get('MemberRoles')->saveOrFail($tables->get('MemberRoles')->newEntity($data));
        }
        $item = $tables->get('ActionItems')->newEntity([
            'entity_type' => 'Gatherings', 'entity_id' => $input['id'], 'title' => 'Synthetic subscription task ' . $member->id,
            'assignee_type' => 'member', 'assignee_config' => ['member_id' => $member->id],
            'assignee_lookup_type' => 'member', 'assignee_lookup_id' => $member->id,
            'branch_id' => $member->branch_id, 'status' => 'open', 'is_gating' => false, 'sort_order' => 0,
        ]);
        $tables->get('ActionItems')->saveOrFail($item);
        $templates = $tables->get('EmailTemplates');
        $default = $templates->find()->where(['slug' => 'grid-email-summary'])->firstOrFail();
        $custom = $templates->newEntity($default->toArray());
        $custom->slug = 'synthetic-grid-summary-' . $member->id;
        $custom->name = 'Synthetic kingdom grid summary ' . $member->id;
        $custom->html_template .= "\n\nSynthetic kingdom template customization";
        $custom->text_template .= "\n\nSynthetic kingdom template customization";
        $templates->saveOrFail($custom);
        $previousTemplate = \App\KMP\StaticHelpers::getAppSetting('Email.GridSubscriptionTemplate');
        \App\KMP\StaticHelpers::setAppSetting('Email.GridSubscriptionTemplate', $custom->slug, 'string', true);
        return ['itemId' => $item->id, 'title' => $item->title, 'templateId' => $custom->id,
            'templateSlug' => $custom->slug, 'previousTemplate' => $previousTemplate];
    }
    if ($input['mode'] === 'subscription-count') {
        return ['count' => $tables->get('GridSubscriptions')->find()->where(['member_id' => $member->id])->count()];
    }
    if ($input['mode'] === 'revoke') {
        foreach ($tables->get('MemberRoles')->find()->where(['member_id' => $member->id]) as $role) {
            $tables->get('MemberRoles')->deleteOrFail($role);
        }
        return ['revoked' => true];
    }
    if ($input['mode'] === 'deliver') {
        $subscription = $tables->get('GridSubscriptions')->find()->where(['member_id' => $member->id])->firstOrFail();
        $subscription->next_run_at = \Cake\I18n\DateTime::now()->subDays(1);
        $tables->get('GridSubscriptions')->saveOrFail($subscription);
        $service = $app->getContainer()->get(\App\Services\GridSubscriptionService::class);
        $service->enqueueDue();
        $jobs = $tables->get('Queue.QueuedJobs')->find()->where(['job_task' => 'GridSubscription', 'completed IS' => null]);
        foreach ($jobs as $job) {
            $data = is_string($job->data) ? unserialize($job->data) : $job->data;
            if (($data['subscriptionId'] ?? null) != $subscription->id) continue;
            $task = new \App\Queue\Task\GridSubscriptionTask(); $task->setContainer($app->getContainer());
            $task->run($data, $job->id);
            $job->completed = \Cake\I18n\DateTime::now(); $tables->get('Queue.QueuedJobs')->saveOrFail($job);
        }
        $subscription = $tables->get('GridSubscriptions')->get($subscription->id);
        return ['sent' => $subscription->last_sent_at !== null, 'status' => $subscription->status];
    }
    if ($input['mode'] === 'cleanup') {
        if (!empty($input['templateId'])) {
            if (\App\KMP\StaticHelpers::getAppSetting('Email.GridSubscriptionTemplate') === $input['templateSlug']) {
                \App\KMP\StaticHelpers::setAppSetting('Email.GridSubscriptionTemplate', $input['previousTemplate'], 'string', true);
            }
            $tables->get('EmailTemplates')->deleteAll(['id' => $input['templateId'], 'slug' => $input['templateSlug']]);
        }
        $tables->get('GridSubscriptions')->deleteAll(['member_id' => $member->id]);
        $tables->get('MemberRoles')->deleteAll(['member_id' => $member->id]);
        if (!empty($input['itemId'])) {
            $tables->get('ActionItemLogs')->deleteAll(['action_item_id' => $input['itemId']]);
            $tables->get('ActionItems')->deleteAll(['id' => $input['itemId']]);
        }
        return ['cleaned' => true];
    }
}); echo json_encode($result, JSON_THROW_ON_ERROR);
`;
(async () => {
    let fixture, extra;
    const browser = await chromium.launch({ headless: true, args: ['--no-sandbox'] });
    const errors = [];
    try {
        fixture = runPhpJson(memberFixture);
        extra = runPhpJson(scoped, { ...fixture, mode: 'setup' });
        const context = await browser.newContext({ baseURL: 'http://kmp.localhost:8080', viewport: { width: 1000, height: 480 } });
        const page = await context.newPage();
        page.on('pageerror', error => errors.push(error.message));
        await page.goto('/members/login?method=password');
        await page.getByRole('textbox', { name: 'Email Address', exact: true }).fill(fixture.email);
        await page.getByLabel('Password', { exact: true }).fill(fixture.password);
        await page.getByRole('button', { name: 'Sign in', exact: true }).click();
        await page.waitForURL(url => /\/members\/view\//.test(url.pathname));
        await page.getByRole('button', { name: 'Security', exact: true }).click();
        const modal = page.locator('#securityModal');
        await expect(modal).toBeVisible();
        await page.waitForTimeout(300);
        await modal.evaluate(element => {
            const body = element.querySelector('.modal-body');
            const content = document.createElement('p'); content.textContent = 'Long modal content '.repeat(350);
            body.appendChild(content);
        });
        for (const viewport of [{ width: 1000, height: 480 }, { width: 375, height: 667 }]) {
            await page.setViewportSize(viewport);
            const result = await modal.evaluate(element => {
                const body = element.querySelector('.modal-body'); const content = element.querySelector('.modal-content');
                const scrolls = [body, content, element].some(el => el.scrollHeight > el.clientHeight && ['auto', 'scroll'].includes(getComputedStyle(el).overflowY));
                body.scrollTop = body.scrollHeight;
                return { scrolls, contentHeight: content.getBoundingClientRect().height, viewport: innerHeight, horizontal: content.scrollWidth > content.clientWidth + 1 || content.getBoundingClientRect().right > innerWidth + 1 };
            });
            assert(result.scrolls, 'Long modal must scroll');
            assert(result.contentHeight <= result.viewport + 1, 'Modal content must fit viewport');

            assert(!result.horizontal, 'No horizontal modal overflow');
        }
        await page.keyboard.press('Escape');
        await expect(modal).not.toBeVisible();
        await expect(page.getByRole('button', { name: 'Security', exact: true })).toBeFocused();
        await page.setViewportSize({ width: 1000, height: 600 });
        await page.goto('/waivers/gathering-waivers/dashboard');
        const toggles = page.locator('.waiver-dashboard button[data-bs-toggle=collapse]');
        assert(await toggles.count() >= 4);
        for (const toggle of await toggles.all()) await expect(toggle).toHaveAttribute('aria-expanded', 'false');
        await toggles.first().focus(); await page.keyboard.press('Enter');
        await expect(toggles.first()).toHaveAttribute('aria-expanded', 'true');
        await page.screenshot({ path: '/tmp/kmp-waiver-dashboard.png', fullPage: true });
        await page.goto('/action-items/my-tasks');
        await page.getByText('Email this view', { exact: true }).click();
        await page.getByLabel('Subscription name', { exact: true }).fill('Synthetic subscription browser check');
        await page.getByLabel('Frequency', { exact: true }).selectOption('3');
        const sampleButton = page.getByRole('button', { name: 'Send me a sample', exact: true });
        await sampleButton.focus();
        await page.keyboard.press('Enter');
        await expect(page.locator('[data-subscription-status]')).toContainText('Sample sent to your account email.');
        await expect(sampleButton).toBeFocused();
        assert.equal(runPhpJson(scoped, { ...fixture, mode: 'subscription-count' }).count, 0);
        const sampleApi = await request.newContext();
        const sampleResponse = await sampleApi.get(getMailpitApiUrl('api/v1/search') + '?query=' + encodeURIComponent('to:' + fixture.email));
        const sampleMessages = (await sampleResponse.json()).messages || [];
        const sample = sampleMessages.find(message => message.Subject.includes('Sample: Synthetic subscription browser check'));
        assert(sample, 'Immediate sample must reach local Mailpit before subscribing');
        const sampleBody = await (await sampleApi.get(getMailpitApiUrl('api/v1/message/' + sample.ID))).json();
        assert(sampleBody.Text.includes(extra.title));
        assert(sampleBody.Text.includes('Synthetic kingdom template customization'));
        assert(sampleBody.HTML.includes('Synthetic kingdom template customization'));
        assert(sampleBody.Text.includes('one-time sample'));
        assert(sampleBody.HTML.includes('one-time sample'));
        assert(!sampleBody.HTML.includes('You subscribed to this summary'));
        await page.goto('/action-items/my-tasks?view_id=sys-todos-open&search=synthetic-no-match-' + fixture.memberId);
        await page.getByText('Email this view', { exact: true }).click();
        await page.getByLabel('Subscription name', { exact: true }).fill('Synthetic empty sample');
        await page.getByRole('button', { name: 'Send me a sample', exact: true }).click();
        await expect(page.locator('[data-subscription-status]')).toContainText('Sample sent to your account email.');
        const emptyResponse = await sampleApi.get(getMailpitApiUrl('api/v1/search') + '?query=' + encodeURIComponent('to:' + fixture.email));
        const emptySample = ((await emptyResponse.json()).messages || []).find(message => message.Subject.includes('Sample: Synthetic empty sample'));
        assert(emptySample, 'Empty sample must still be sent');
        const emptyBody = await (await sampleApi.get(getMailpitApiUrl('api/v1/message/' + emptySample.ID))).json();
        assert(emptyBody.Text.includes('No rows match this view right now.'));
        assert(emptyBody.HTML.includes('No rows match this view right now.'));
        assert(!emptyBody.Text.includes(extra.title));
        assert.equal(runPhpJson(scoped, { ...fixture, mode: 'subscription-count' }).count, 0);
        await sampleApi.dispose();
        await page.goto('/action-items/my-tasks');
        await page.getByText('Email this view', { exact: true }).click();
        await page.getByLabel('Subscription name', { exact: true }).fill('Synthetic subscription browser check');
        await page.getByLabel('Frequency', { exact: true }).selectOption('3');

        await page.getByRole('button', { name: 'Subscribe', exact: true }).click();
        await expect(page.locator('[data-subscription-status]')).toContainText('Subscribed.');
        await page.screenshot({ path: '/tmp/kmp-grid-subscription.png', fullPage: true });
        const delivery = runPhpJson(scoped, { ...fixture, ...extra, mode: 'deliver' });
        assert(delivery.sent, 'Due subscription should send its current authorized rows');
        const api = await request.newContext();
        const response = await api.get(getMailpitApiUrl('api/v1/search') + '?query=' + encodeURIComponent('to:' + fixture.email));
        const messages = (await response.json()).messages || [];
        const summary = messages.find(message => message.Subject.includes('Synthetic subscription browser check') && !message.Subject.includes('Sample:'));
        assert(summary, 'Summary must reach local Mailpit');
        const message = await (await api.get(getMailpitApiUrl('api/v1/message/' + summary.ID))).json();
        assert(message.Text.includes(extra.title));
        assert(message.Text.includes('Synthetic kingdom template customization'));
        assert(message.HTML.includes('Synthetic kingdom template customization'));
        assert(message.Text.includes('http://kmp.localhost:8080/action-items/my-tasks'));
        await api.dispose();
        // Existing email management links must land on the profile and open its dialog.
        await page.goto('/grid-subscriptions');
        await page.waitForURL(url => /\/members\/view\//.test(url.pathname));
        const profileUrl = page.url();
        const subscriptionsModal = page.getByRole('dialog', { name: 'Email subscriptions', exact: true });
        const subscriptionsButton = page.getByRole('button', { name: 'Email subscriptions', exact: true });
        await expect(subscriptionsModal).toBeVisible();
        await expect(subscriptionsModal.getByText('Synthetic subscription browser check', { exact: true })).toBeVisible();
        await page.keyboard.press('Escape');
        await expect(subscriptionsModal).not.toBeVisible();
        await expect(subscriptionsButton).toBeFocused();
        await page.keyboard.press('Enter');
        await expect(subscriptionsModal).toBeVisible();
        await expect(subscriptionsModal.getByRole('heading', { name: 'Email subscriptions' })).toBeFocused();
        const cancelSubscription = subscriptionsModal.getByRole('button', { name: 'Cancel subscription Synthetic subscription browser check', exact: true });
        await cancelSubscription.focus();
        await page.keyboard.press('Enter');
        await expect(subscriptionsModal.getByRole('button', { name: 'Keep subscription', exact: true })).toBeFocused();
        await page.keyboard.press('Enter');
        await expect(cancelSubscription).toBeFocused();
        assert.equal(runPhpJson(scoped, { ...fixture, mode: 'subscription-count' }).count, 1);
        await page.keyboard.press('Enter');
        await subscriptionsModal.getByRole('button', { name: 'Confirm cancellation of Synthetic subscription browser check', exact: true }).click();
        await expect(subscriptionsModal.getByText('You have no email subscriptions.', { exact: false })).toBeVisible();
        await expect(subscriptionsModal.locator('[data-subscription-feedback]')).toBeFocused();
        assert.equal(page.url(), profileUrl, 'Cancellation must stay on the profile');
        assert.equal(runPhpJson(scoped, { ...fixture, mode: 'subscription-count' }).count, 0);
        await page.setViewportSize({ width: 375, height: 480 });
        await expect(subscriptionsModal.locator('.modal-footer').getByRole('button', { name: 'Close', exact: true })).toBeInViewport();
        await subscriptionsModal.locator('.modal-footer').getByRole('button', { name: 'Close', exact: true }).click();
        await expect(subscriptionsButton).toBeFocused();
        await subscriptionsButton.click();
        await expect(subscriptionsModal.getByText('You have no email subscriptions.', { exact: false })).toBeVisible();
        await page.keyboard.press('Escape');
        await expect(subscriptionsButton).toBeFocused();
        await page.setViewportSize({ width: 1000, height: 600 });
        await page.goto('/warrant-rosters');
        await page.getByText('Email this view', { exact: true }).click();
        await page.getByLabel('Subscription name', { exact: true }).fill('Synthetic role loss check');
        await page.getByRole('button', { name: 'Subscribe', exact: true }).click();
        await expect(page.locator('[data-subscription-status]')).toContainText('Subscribed.');
        runPhpJson(scoped, { ...fixture, mode: 'revoke' });
        const stopped = runPhpJson(scoped, { ...fixture, ...extra, mode: 'deliver' });
        assert.equal(stopped.status, 'stopped', 'Revoking roles must stop an existing grid subscription');
        assert.equal(stopped.sent, false, 'No email after permission loss');
        assert.deepEqual(errors, []);
        console.log('PASS: collapsed keyboard-accessible waiver sections; desktop/mobile modal scrolling and focus; subscribe → scheduler → authorized queue email → Mailpit → profile modal cancellation; management links, keyboard reopening/close, status focus, and mobile close controls; immediate populated/empty samples using unsaved filters without subscribing; kingdom-selected editable template used for samples and scheduled delivery; revoked roles stop delivery');
    } finally {
        await browser.close();
        if (fixture) {
            runPhpJson(scoped, { ...fixture, ...(extra || {}), mode: 'cleanup' });
            runPhpJson(memberFixture, { cleanup: fixture.id, name: fixture.name, memberId: fixture.memberId, email: fixture.email });
        }
    }
})().catch(error => { console.error(error.message); process.exitCode = 1; });
