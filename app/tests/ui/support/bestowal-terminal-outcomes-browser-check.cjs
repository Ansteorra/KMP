// Local Docker check without resetting existing development data.
const path = require('node:path');
const root = path.resolve(__dirname, '../../..');
const { chromium, expect } = require(path.join(root, 'node_modules/@playwright/test'));
const { loginAs, runPhpJson } = require('./ui-helpers.cjs');

const fixturePhp = String.raw`require 'vendor/autoload.php';
require 'config/bootstrap.php';
$input = json_decode(stream_get_contents(STDIN), true);
$tenant = (new \App\Services\Platform\TenantHostResolver())->resolve('kmp.localhost');
if (!$tenant || $tenant->slug !== 'kmp'
    || !in_array($tenant->dbServer, ['db', 'postgres', 'localhost', '127.0.0.1'], true)) {
    throw new \RuntimeException('This check requires the local kmp tenant.');
}
$manager = new \App\Services\TenantConnectionManager(\App\Services\Secrets\SecretStoreFactory::fromConfig());
echo json_encode($manager->withTenant($tenant, function () use ($input) {
    $l = \Cake\ORM\TableRegistry::getTableLocator();
    $bestowals = $l->get('Awards.Bestowals');
    $awards = $l->get('Awards.Awards');
    $templates = $l->get('Awards.BestowalTodoTemplates');
    $definitions = $l->get('Awards.BestowalTodoTemplateItems');
    if (isset($input['cleanup'])) {
        $fixture = $input['cleanup'];
        $award = $awards->find()->where(['id' => $fixture['awardId'], 'name' => $fixture['name']])->firstOrFail();
        foreach ($bestowals->find()->where(['award_id' => $award->id]) as $bestowal) {
            $items = $l->get('ActionItems');
            $ids = $items->find()->select(['id'])->where(['entity_type' => 'Awards.Bestowals', 'entity_id' => $bestowal->id])
                ->all()->extract('id')->toList();
            if ($ids !== []) $l->get('ActionItemLogs')->deleteAll(['action_item_id IN' => $ids]);
            $items->deleteAll(['entity_type' => 'Awards.Bestowals', 'entity_id' => $bestowal->id]);
            $bestowals->deleteOrFail($bestowal);
        }
        $awards->deleteOrFail($award);
        $definitions->deleteAll(['template_id' => $fixture['templateId']]);
        $templates->deleteAll(['id' => $fixture['templateId'], 'name' => $fixture['name']]);
        return ['cleaned' => true];
    }
    return $bestowals->getConnection()->transactional(function () use ($l, $bestowals, $awards, $templates, $definitions) {
        $name = 'Synthetic terminal outcome validation ' . uniqid();
        $admin = $l->get('Members')->find()->where(['email_address' => 'admin@amp.ansteorra.org'])->firstOrFail();
        $template = $templates->saveOrFail($templates->newEntity(['name' => $name, 'is_active' => true]));
        foreach (['given' => 'Given', 'given_not_received' => 'Given Not Received', 'filed' => 'Filed with Herald',
            'prepare' => 'Prepare Scroll'] as $key => $status) {
            $definitions->saveOrFail($definitions->newEntity([
                'template_id' => $template->id, 'item_key' => $key, 'label' => $status,
                'terminal_status' => $key === 'given' ? 'given' : $status,
                'is_terminal' => $key !== 'prepare', 'is_gating' => true, 'sort_order' => 1,
                'assignee_type' => 'member', 'assignee_source_id' => $admin->id, 'branch_mode' => 'award_branch',
            ]));
        }
        $base = $awards->find()->where(['is_active' => true])->firstOrFail();
        $award = $awards->saveOrFail($awards->newEntity([
            'name' => $name, 'abbreviation' => 'TOV' . uniqid(), 'domain_id' => $base->domain_id,
            'level_id' => $base->level_id, 'branch_id' => $base->branch_id, 'is_active' => true,
            'bestowal_todo_template_id' => $template->id,
        ]));
        $ids = [];
        $tasks = [];
        foreach (['Given', 'Given Not Received', 'Filed with Herald'] as $outcome) {
            $bestowal = $bestowals->saveOrFail($bestowals->newEntity([
                'member_id' => null, 'member_sca_name' => $name, 'award_id' => $award->id,
                'lifecycle_status' => 'open', 'source' => 'ad_hoc', 'stack_rank' => 0,
            ]));
            $result = (new \Awards\Services\BestowalTodoMaterializationService())->materializeForBestowal($bestowal);
            if (!$result->success) throw new \RuntimeException($result->reason);
            $ids[$outcome] = $bestowal->id;
            foreach ($result->data as $task) {
                if ($task->is_terminal) $tasks[$outcome] = $task->id;
            }
        }
        return ['name' => $name, 'awardId' => $award->id, 'templateId' => $template->id,
            'bestowals' => $ids, 'tasks' => $tasks];
    });
}), JSON_THROW_ON_ERROR);`;

(async () => {
    const fixture = runPhpJson(fixturePhp);
    const browser = await chromium.launch({ headless: true, args: ['--no-sandbox'] });
    const page = await browser.newPage({ baseURL: process.env.PLAYWRIGHT_BASE_URL || 'http://kmp.localhost:8080' });
    const errors = [];
    page.on('pageerror', (error) => errors.push(error.message));
    try {
        await loginAs(page, 'admin@amp.ansteorra.org');
        await page.goto(`/awards/bestowal-todo-templates/view/${fixture.templateId}`);
        const completionStatus = page.getByLabel('Completion status', { exact: true }).first();
        await expect(completionStatus).toHaveAttribute('aria-describedby', /terminal-status-help$/);
        for (const [outcome, id] of Object.entries(fixture.bestowals)) {
            if (outcome === 'Given Not Received') {
                await page.goto('/action-items/my-tasks?search=Given');
                await page.locator(`[data-todo-id="${fixture.tasks[outcome]}"][data-todo-mode="complete"]`).click();
                const dialog = page.getByRole('dialog');
                await dialog.getByLabel('Completion outcome', { exact: true }).selectOption(outcome);
                await dialog.getByLabel('Note (optional)', { exact: true }).fill(
                    'Recipient was absent.\nArrange delivery of the award.',
                );
                await dialog.getByRole('button', { name: 'Mark Complete', exact: true }).click();
                await expect(dialog).toBeHidden();
                await page.goto(`/awards/bestowals/view/${id}`);
                await page.getByRole('tab', { name: /^To-Dos/ }).click();
                await expect(page.locator('body')).toContainText(`This bestowal has been marked ${outcome}.`);
                const notes = page.locator('[data-bestowal-todo-notes]').filter({ hasText: 'Recipient was absent.' });
                await expect(notes).toBeVisible();
                await expect(notes).toContainText('Arrange delivery of the award.');
                await expect(notes.locator('time')).toHaveAttribute('datetime', /\d{4}-\d{2}-\d{2}T/);
                await page.goto(`/awards/bestowals/bestowal-todos/${id}`);
                await expect(page.locator('[data-bestowal-todo-notes]').filter({ hasText: 'Recipient was absent.' })).toBeVisible();
                continue;
            }
            await page.goto(`/awards/bestowals/view/${id}`);
            await page.getByRole('tab', { name: /^To-Dos/ }).click();
            await expect(page.locator('li.list-group-item')).toHaveCount(2);
            await expect(page.getByLabel('Completion outcome', { exact: true })).toHaveCount(1);
            await page.getByLabel('Completion outcome', { exact: true }).selectOption(outcome === 'Given' ? 'given' : outcome);
            const choice = page.getByRole('button', { name: 'Complete Given', exact: true });
            await choice.focus();
            await page.keyboard.press('Enter');
            const dialog = page.getByRole('dialog');
            await expect(dialog).toContainText(`Completion outcome: ${outcome}.`);
            await expect(dialog).toContainText('Prepare Scroll');
            await expect(dialog.getByRole('button', { name: 'Confirm', exact: true })).toBeFocused();
            await page.keyboard.press('Escape');
            await expect(dialog).toHaveCount(0);
            await expect(choice).toBeFocused();
            await page.keyboard.press('Enter');
            await page.getByRole('dialog').getByRole('button', { name: 'Confirm', exact: true }).press('Enter');
            await expect(page.locator('[data-controller=awards-bestowal-todos]')).toContainText('its checklist is read-only');
            await expect(page.locator('[data-controller=awards-bestowal-todos]')).toContainText('Closed — not applicable');
            await expect(page.locator('[data-controller=awards-bestowal-todos]').getByRole('link')).toHaveCount(0);
            await expect(page.locator('body')).toContainText(`This bestowal has been marked ${outcome}.`);
        }
        const query = new URLSearchParams({ view_id: 'sys-bestowals-completed', search: fixture.name });
        for (const [outcome, id] of Object.entries(fixture.bestowals)) {
            await page.goto(`/awards/bestowals/view/${id}`);
            const state = page.getByRole('rowheader', { name: 'Terminal state', exact: true }).locator('..');
            await expect(state.getByRole('cell')).toHaveText(outcome);
            const closed = page.getByRole('rowheader', { name: 'Close date', exact: true }).locator('..');
            await expect(closed.locator('time')).toHaveAttribute('datetime', /\d{4}-\d{2}-\d{2}T/);
        }
        await page.goto(`/awards/bestowals?${query}`);
        await expect(page.getByRole('button', { name: 'Sort by Terminal state', exact: true })).toBeVisible();
        const dateHeader = page.locator('th[data-column-key="close_date"]');
        const dateIndex = await dateHeader.evaluate((header) => header.cellIndex);
        for (const [outcome, id] of Object.entries(fixture.bestowals)) {
            const row = page.locator(`tr[data-id="${id}"]`);
            await expect(row).toBeVisible();
            await expect(row.getByRole('cell', {
                name: outcome === 'Given' ? 'given' : outcome, exact: true,
            })).toBeVisible();
            await expect(row.getByRole('cell').nth(dateIndex)).toContainText(/\w+ \d+, \d{4}/);
        }
        await expect(page.getByRole('button', { name: 'Sort by Terminal state', exact: true })).toBeVisible();
        const dateSort = page.getByRole('button', { name: 'Sort by Close date', exact: true });
        await expect(page.locator('turbo-frame[aria-busy="true"]')).toHaveCount(0);
        const activateSort = async button => {
            await button.focus();
            // The header can render before frame-load updates the grid controller's state.
            await Promise.all([
                button.evaluate(element => new Promise(resolve => {
                    element.closest('turbo-frame').addEventListener('turbo:frame-load', () => resolve(true), { once: true });
                })),
                page.keyboard.press('Enter'),
            ]);
        };
        await activateSort(dateSort);
        await expect(page.locator('th[data-column-key="close_date"]')).toHaveAttribute('aria-sort', 'ascending');
        await expect(page.locator('turbo-frame[aria-busy="true"]')).toHaveCount(0);
        await activateSort(dateSort);
        await expect(page.locator('th[data-column-key="close_date"]')).toHaveAttribute('aria-sort', 'descending');
        await expect(page.locator('turbo-frame[aria-busy="true"]')).toHaveCount(0);
        await activateSort(page.getByRole('button', { name: 'Sort by Terminal state', exact: true }));
        await expect(page.locator('th[data-column-key="terminal_state"]')).toHaveAttribute('aria-sort', 'ascending');
        await expect(page.locator('turbo-frame[aria-busy="true"]')).toHaveCount(0);
        await expect(page.locator('body')).toContainText('Given Not Received');
        const filterButton = page.locator('[data-filter-button]');
        await filterButton.click();
        await page.locator('[data-filter-nav-item][data-filter-key="terminal_state"]').press('Enter');
        await page.getByRole('checkbox', { name: 'Given Not Received', exact: true }).check();
        await expect(page.locator(`tr[data-id="${fixture.bestowals.Given}"]`)).toHaveCount(0);
        await expect(page.locator(`tr[data-id="${fixture.bestowals['Given Not Received']}"]`)).toBeVisible();
        if (await filterButton.getAttribute('aria-expanded') !== 'true') await filterButton.click();
        await page.locator('[data-filter-nav-item][data-filter-key="close_date"]').click();
        await page.locator('input[data-filter-column="close_date_start"]').fill('2100-01-01');
        await page.locator('input[data-filter-column="close_date_start"]').press('Tab');
        await expect(page.locator(`tr[data-id="${fixture.bestowals['Given Not Received']}"]`)).toHaveCount(0);
        query.set('export', 'csv');
        const csv = await page.request.get(`/awards/bestowals/grid-data?${query}`);
        expect(csv.ok()).toBe(true);
        expect(await csv.text()).toContain('Given Not Received');
        expect(await csv.text()).toContain('Terminal state');
        expect(await csv.text()).toContain('Close date');
        query.delete('export');
        const deliveredId = fixture.bestowals['Given Not Received'];
        await page.goto(`/awards/bestowals/view/${deliveredId}`);
        const closeDate = page.getByRole('rowheader', { name: 'Close date', exact: true }).locator('..').locator('time');
        const originalCloseDate = await closeDate.getAttribute('datetime');
        const changeState = page.getByRole('button', { name: 'Change terminal state', exact: true });
        await changeState.focus();
        await page.keyboard.press('Enter');
        const outcomeDialog = page.getByRole('dialog', { name: 'Change terminal state', exact: true });
        await expect(outcomeDialog).toBeVisible();
        await expect(outcomeDialog).toBeFocused();
        await outcomeDialog.getByLabel('New terminal state', { exact: true }).focus();
        await page.keyboard.press('Escape');
        await expect(outcomeDialog).toBeHidden();
        await expect(changeState).toBeFocused();
        await page.keyboard.press('Enter');
        await expect(outcomeDialog).toBeFocused();
        await outcomeDialog.getByLabel('New terminal state', { exact: true }).selectOption('given');
        await outcomeDialog.getByLabel('Note (optional)', { exact: true }).fill('The recipient received the award today.');
        await outcomeDialog.getByRole('button', { name: 'Save terminal state', exact: true }).press('Enter');
        await expect(outcomeDialog).toBeHidden();
        await expect(page.getByRole('rowheader', { name: 'Terminal state', exact: true }).locator('..')
            .getByRole('cell')).toHaveText('Given');
        await expect(closeDate).toHaveAttribute('datetime', originalCloseDate);
        await page.getByRole('tab', { name: /^To-Dos/ }).click();
        const deliveryNotes = page.locator('[data-bestowal-todo-notes]').filter({ hasText: 'The recipient received' });
        await expect(deliveryNotes).toContainText('Terminal state changed from "Given Not Received" to "Given".');
        await expect(deliveryNotes).toContainText('Recipient was absent.');
        const terminalTask = deliveryNotes.locator('xpath=ancestor::li[contains(@class,"list-group-item")]');
        await expect(terminalTask.locator('.badge.bg-primary')).toHaveText('Terminal state: Given');
        await expect(page.locator('[data-controller=awards-bestowal-todos]')).toContainText('its checklist is read-only');
        await page.goto(`/awards/bestowals/bestowal-todos/${deliveredId}`);
        await expect(page.locator('.badge.bg-primary')).toHaveText('Terminal state: Given');
        await page.goto(`/awards/bestowals?${query}`);
        await expect(page.locator(`tr[data-id="${deliveredId}"]`).getByRole('cell', { name: 'given', exact: true })).toBeVisible();
        query.set('view_id', 'sys-bestowals-active');
        await page.goto(`/awards/bestowals/grid-data?${query}`);
        for (const id of Object.values(fixture.bestowals)) {
            await expect(page.locator(`tr[data-id="${id}"]`)).toHaveCount(0);
        }
        expect(errors).toEqual([]);
        console.log('PASS: three outcomes, delivery state change with audit history, keyboard modals/focus, Completed/Active views, CSV.');
    } catch (error) {
        console.error('Checklist state:', await page.locator('[data-controller=awards-bestowal-todos]').allTextContents());
        throw error;
    } finally {
        await browser.close();
        runPhpJson(fixturePhp, { cleanup: fixture });
    }
})().catch((error) => { console.error(error); process.exitCode = 1; });
