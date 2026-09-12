// Run after the documented local seeded reset; exercises tenant-bound transactions and the real queue.
const root = require('node:path').resolve(__dirname, '../../..');
const {chromium}=require(root+'/node_modules/playwright');
const {expect}=require(root+'/node_modules/@playwright/test');
const {loginAs,runPhpJson,waitForQueueSettled}=require(root+'/tests/ui/support/ui-helpers.cjs');
const php=String.raw`require 'vendor/autoload.php';
require 'config/bootstrap.php';
$input = json_decode(stream_get_contents(STDIN), true);
$tenant = (new \App\Services\Platform\TenantHostResolver())->resolve('kmp.localhost');
if (!$tenant || $tenant->slug !== 'kmp' || !in_array($tenant->dbServer, ['db','postgres','localhost','127.0.0.1'], true)) { throw new \RuntimeException('Local only'); }
$manager = new \App\Services\TenantConnectionManager(\App\Services\Secrets\SecretStoreFactory::fromConfig());
$result = $manager->withTenant($tenant, function () use ($input) {
$l=\Cake\ORM\TableRegistry::getTableLocator();
if (!empty($input['inspect'])) {
    $b = $l->get('Awards.Bestowals')->get($input['inspect']);
    return ['gathering_id' => $b->gathering_id, 'roaming_court' => $b->roaming_court];
}
if (!empty($input['cleanup'])) {
    $b = $l->get('Awards.Bestowals')->find()->where(['id' => $input['cleanup'], 'member_sca_name' => 'Synthetic Release Validation'])->firstOrFail();
    $l->get('ActionItems')->deleteAll(['entity_type' => 'Awards.Bestowals', 'entity_id' => $b->id]);
    $l->get('Awards.Bestowals')->deleteOrFail($b);
    return ['cleaned' => true];
}
$admin=$l->get('Members')->find()->where(['email_address'=>'admin@amp.ansteorra.org'])->firstOrFail();
$award=$l->get('Awards.Awards')->find()->where(['is_active'=>true])->firstOrFail();
$g=$l->get('Gatherings')->find()->firstOrFail();
$b=$l->get('Awards.Bestowals');
$bestowal=$b->saveOrFail($b->newEntity(['member_id'=>$admin->id,'member_sca_name'=>'Synthetic Release Validation', 'award_id'=>$award->id, 'gathering_id'=>$g->id,'roaming_court'=>true,'lifecycle_status'=>'open','source'=>'ad_hoc','stack_rank'=>0]));
$items=$l->get('ActionItems'); $ids=[];
foreach ([['event_scheduled','Scheduling','completed',false],['added_to_agenda','Court Slot','completed',false],['given','Presented','open',true],['scroll','Prepare Scroll','open',false]] as [$key,$title,$status,$terminal]) {
$i=$items->saveOrFail($items->newEntity(['entity_type'=>'Awards.Bestowals','entity_id'=>$bestowal->id,'title'=>$title,'source_ref'=>$key,'assignee_type'=>'member','assignee_config'=>['member_id'=>$admin->id],'branch_id'=>$award->branch_id,'status'=>$status,'is_gating'=>true,'is_terminal'=>$terminal,'sort_order'=>count($ids)])); $ids[$key]=$i->id;
}
$p=$l->get('Awards.ApprovalProcesses')->find()->firstOrFail();
return ['bestowalId'=>$bestowal->id,'items'=>$ids,'processId'=>$p->id];
});
echo json_encode($result, JSON_THROW_ON_ERROR);
`;
(async()=>{
const fixture=runPhpJson(php);
const browser=await chromium.launch({headless:true,args:['--no-sandbox']});
const context=await browser.newContext({baseURL:process.env.PLAYWRIGHT_BASE_URL || 'http://kmp.localhost:8080'});
const page=await context.newPage(); const errors=[];page.on('pageerror',e=>errors.push(e.message));
try {
await loginAs(page,'admin@amp.ansteorra.org');
await page.goto('/awards/bestowals/view/'+fixture.bestowalId);


await page.screenshot({path:'/tmp/kmp-local-todos.png'});
const scheduling=page.locator('li.list-group-item').filter({hasText:'Scheduling'});
await scheduling.getByRole('link',{name:'Reopen: Scheduling',exact:true}).click();

await page.screenshot({path:'/tmp/kmp-local-reopen-confirm.png'});
await expect(page.getByRole('dialog')).toContainText('event and court');
await expect(page.getByRole('dialog').getByRole('button',{name:'Confirm',exact:true})).toBeFocused();
await page.keyboard.press('Escape');
await expect(page.getByRole('dialog')).toHaveCount(0);
await expect(scheduling.getByRole('link',{name:'Reopen: Scheduling',exact:true})).toBeFocused();
await scheduling.getByRole('link',{name:'Reopen: Scheduling',exact:true}).press('Enter');
await page.getByRole('dialog').getByRole('button',{name:'Confirm',exact:true}).click();
await expect(page.locator('[data-controller=awards-bestowal-todos]')).toContainText('Gathering required');
const state=runPhpJson(php,{inspect:fixture.bestowalId});
expect(state.gathering_id).toBe(null);expect(state.roaming_court).toBe(false);
const terminal=page.getByRole('link',{name:'Mark complete: Presented',exact:true});
await terminal.press('Enter');
await expect(page.getByRole('dialog')).toContainText('Prepare Scroll');
await expect(page.getByRole('dialog')).toContainText('Scheduling');
await page.screenshot({path:'/tmp/kmp-local-terminal-confirm.png'});
await page.getByRole('dialog').getByRole('button',{name:'Confirm',exact:true}).click();
await expect(page.locator('[data-controller=awards-bestowal-todos]')).toContainText('given; its checklist is read-only');
await expect(page.locator('[data-controller=awards-bestowal-todos]')).toContainText('Closed — not applicable');
await expect(page.locator('[data-controller=awards-bestowal-todos]').getByRole('link')).toHaveCount(0);
console.log('Scheduling reversal / terminal override / finalized read-only PASS');
await page.screenshot({path:'/tmp/kmp-local-finalized.png'});
await page.goto('/awards/approval-processes/view/'+fixture.processId);
await page.getByRole('button',{name:'Sync Outdated Recommendations',exact:true}).click();
await page.getByRole('dialog').getByRole('button',{name:'Sync Now',exact:true}).click();
await expect(page.locator('[data-awards-approval-sync-target=status]')).toContainText('Synchronization #');
console.log('QUEUED',await page.locator('[data-awards-approval-sync-target=status]').innerText());
await waitForQueueSettled({tenantSlug:'kmp',timeoutMs:60000});
await page.getByRole('button',{name:'Refresh progress',exact:true}).click();
await expect(page.locator('[data-awards-approval-sync-target=status]')).toContainText('completed');
console.log('Background queue / progress PASS');
await page.screenshot({path:'/tmp/kmp-local-sync.png'});
expect(errors).toEqual([]);
} finally {await browser.close();runPhpJson(php,{cleanup:fixture.bestowalId});}
})();
