// Regression check against an isolated local Docker tenant; fixtures are removed in finally.
const path = require('node:path');
const axe = require('axe-core');
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
    if (isset($input['inspect'])) {
        return $l->get('ActionItemLogs')->find()->select(['action_item_id', 'note'])
            ->where(['action_item_id IN' => $input['inspect'], 'to_status' => 'completed'])
            ->enableHydration(false)->all()->toList();
    }
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
        $name = 'Synthetic feedback validation ' . uniqid();
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
        $prepareTasks = [];
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
                else $prepareTasks[$outcome] = $task->id;
            }
        }
        return ['name' => $name, 'awardId' => $award->id, 'templateId' => $template->id,
            'bestowals' => $ids, 'tasks' => $tasks, 'prepareTasks' => $prepareTasks];
    });
}), JSON_THROW_ON_ERROR);`;

(async () => {
    const fixture = runPhpJson(fixturePhp);
    const browser = await chromium.launch({ headless: true });
    const page = await browser.newPage({
        baseURL: process.env.PLAYWRIGHT_BASE_URL || 'http://kmp.localhost:8080',
        viewport: process.env.FEEDBACK_MOBILE ? { width: 390, height: 844 } : { width: 1440, height: 1000 },
    });
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    try {
        await loginAs(page, 'admin@amp.ansteorra.org');
        const [a, b, c] = ['Given', 'Given Not Received', 'Filed with Herald'];
        await page.goto('/action-items/my-tasks?search=Prepare');
        const taskButton = key => page.locator(`[data-todo-id="${fixture.prepareTasks[key]}"][data-todo-mode="complete"]`);

        await taskButton(a).click();
        const modal = page.locator('#todoCompleteModal');
        await expect(modal).toHaveClass(/show/);
        await modal.getByLabel('Note (optional)', { exact: true }).fill('Feedback check: first task only');
        await modal.getByRole('button', { name: 'Mark Complete', exact: true }).click();
        await expect(modal).not.toBeVisible();
        await taskButton(b).click();
        await expect(modal.getByLabel('Note (optional)', { exact: true })).toHaveValue('');
        await modal.getByRole('button', { name: 'Cancel', exact: true }).click();
        await expect(modal).not.toBeVisible();

        await page.goto(`/awards/bestowals/view/${fixture.bestowals[b]}?tab=nav-todos`);
        const complete = page.getByRole('button', { name: 'Mark complete: Prepare Scroll', exact: true });
        if (!await complete.isVisible()) await page.getByRole('tab', { name: /To-Dos/ }).click();
        await complete.focus();
        await page.keyboard.press('Enter');
        const note = page.getByLabel('Note (optional)', { exact: true }).filter({ visible: true });
        await expect(note).toBeFocused();
        await expect(note).toHaveValue('');
        await page.addScriptTag({ content: axe.source });
        const audit = await page.evaluate(async () => window.axe.run('.modal.show', {
            runOnly: { type: 'tag', values: ['wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa'] },
        }));
        expect(audit.violations).toEqual([]);
        await page.keyboard.press('Escape');
        await expect(complete).toBeFocused();
        await complete.click();
        await expect(note).toBeFocused();
        await Promise.all([
            page.waitForNavigation({ waitUntil: 'load' }),
            page.locator('[data-dialog-confirm]').click(),
        ]);
        await expect(page.getByRole('button', { name: 'Mark complete: Prepare Scroll', exact: true })).toHaveCount(0);

        await page.goto('/awards/bestowals?search=' + encodeURIComponent(fixture.name));
        const row = page.locator(`tr[data-id="${fixture.bestowals[c]}"]`);
        await row.locator('.todos-bestowal button, button.todos-bestowal, .todos-bestowal a').first().click();
        const quick = page.locator('#bestowalTodosModal');
        await expect(quick).toBeVisible();
        await quick.getByRole('button', { name: 'Mark complete: Prepare Scroll', exact: true }).click();
        await expect(note).toBeFocused();
        await expect(quick).not.toBeVisible();
        await note.fill('Feedback check: quick dialog\nSecond line');
        await Promise.all([
            page.waitForNavigation({ waitUntil: 'load' }),
            page.locator('[data-dialog-confirm]').click(),
        ]);
        const logs = runPhpJson(fixturePhp, { inspect: Object.values(fixture.prepareTasks) });
        const notes = Object.fromEntries(logs.map(log => [log.action_item_id, log.note]));
        expect(notes[fixture.prepareTasks[a]]).toBe('Feedback check: first task only');
        expect(notes[fixture.prepareTasks[b]] ?? '').toBe('');
        expect(notes[fixture.prepareTasks[c]]?.replace(/\r\n/g, '\n')).toBe('Feedback check: quick dialog\nSecond line');
        expect(errors).toEqual([]);
        console.log('PASS: note isolation, detail and quick-dialog completion, cancel/focus, blank and multiline audit notes.');
    } finally {
        await browser.close();
        runPhpJson(fixturePhp, { cleanup: fixture });
    }
})().catch(error => { console.error(error); process.exitCode = 1; });
