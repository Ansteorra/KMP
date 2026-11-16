<?php
declare(strict_types=1);

namespace Awards\Test\TestCase\Controller;

use App\KMP\PermissionsLoader;
use App\Model\Entity\ActionItem;
use App\Model\Entity\Permission;
use App\Policy\GatheringActivityPolicy;
use App\Policy\GatheringPolicy;
use App\Services\ActionItems\ActionItemAssigneeResolver;
use App\Services\ActionItems\ActionItemService;
use App\Services\GatheringActivityDelegationRegistry;
use App\Services\GatheringActivityService;
use App\Services\GatheringScheduleService;
use App\Test\TestCase\Support\HttpIntegrationTestCase;
use Awards\Model\Entity\Bestowal;
use Awards\Model\Entity\BestowalDelegation;
use Awards\Model\Entity\BestowalTodoTemplateItem;
use Awards\Policy\BestowalDelegationPolicy;
use Awards\Policy\BestowalPolicy;
use Awards\Policy\BestowalsTablePolicy;
use Awards\Services\AdHocBestowalService;
use Awards\Services\BestowalCancellationService;
use Awards\Services\BestowalDelegationService;
use Awards\Services\BestowalFinalizationService;
use Awards\Services\BestowalGatheringActivityDelegationProvider;
use Awards\Services\BestowalTodoAssigneeResolver;
use Awards\Services\BestowalTodoDelegationProvider;
use Awards\Services\BestowalUpdateService;
use Awards\Services\CourtAgendaService;
use Cake\Cache\Cache;
use Cake\Datasource\FactoryLocator;
use Cake\Http\Exception\ForbiddenException;
use Cake\ORM\Locator\TableLocator;

/** Live event delegation, grant/revoke HTTP boundaries, assignment, and audit coverage. */
class BestowalDelegationsControllerTest extends HttpIntegrationTestCase
{
    private object $gathering;
    private object $otherGathering;
    private object $award;
    private object $otherAward;
    private object $permission;
    private object $role;

    protected function setUp(): void
    {
        parent::setUp();
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->enableRetainFlashMessages();
        $this->getTableLocator()->get('MemberRoles')->updateAll(['expires_on' => '2000-01-01'], [
            'member_id IN' => [self::TEST_MEMBER_AGATHA_ID, self::TEST_MEMBER_BRYCE_ID, self::TEST_MEMBER_DEVON_ID],
        ]);
        Cache::clearGroup('security');
        BestowalTodoAssigneeResolver::clearMemberScopeCache();
        $gatherings = $this->getTableLocator()->get('Gatherings');
        $source = $gatherings->find()->firstOrFail();
        foreach (['gathering', 'otherGathering'] as $property) {
            $this->{$property} = $gatherings->saveOrFail($gatherings->newEntity([
                'name' => 'Delegation test ' . $property, 'branch_id' => $source->branch_id,
                'gathering_type_id' => $source->gathering_type_id, 'start_date' => $source->start_date,
                'end_date' => $source->end_date, 'location' => 'Test hall', 'created_by' => self::ADMIN_MEMBER_ID,
            ]));
        }
        $awards = $this->getTableLocator()->get('Awards.Awards');
        $this->award = $awards->find()->where(['branch_id' => self::KINGDOM_BRANCH_ID])->firstOrFail();
        $this->otherAward = $awards->find()->where(['branch_id !=' => self::KINGDOM_BRANCH_ID])->firstOrFail();
        $this->grantNativeAuthority();
        GatheringActivityDelegationRegistry::register('Awards', new BestowalGatheringActivityDelegationProvider());
    }

    public function testGrantAndRevokeKeepAuditHistoryAndRejectFurtherDelegation(): void
    {
        $bestowal = $this->bestowal();
        $delegate = $this->getTableLocator()->get('Members')->get(self::TEST_MEMBER_BRYCE_ID);
        $this->authenticateAsMember(self::TEST_MEMBER_AGATHA_ID);
        $this->get('/awards/bestowal-delegations/gathering/' . $this->gathering->id);
        $this->assertResponseOk();
        $this->assertResponseContains('Person receiving access');
        $this->assertResponseContains('Awarding group');
        $this->post('/awards/bestowal-delegations/grant/' . $this->gathering->id, [
            'branch_id' => self::KINGDOM_BRANCH_ID, 'delegate_public_id' => $delegate->public_id,
            'grantor_id' => self::ADMIN_MEMBER_ID,
        ]);
        $this->assertRedirectContains('/awards/bestowal-delegations/gathering/' . $this->gathering->id);
        $table = $this->getTableLocator()->get('Awards.BestowalDelegations');
        $grant = $table->find()->where(['delegate_id' => $delegate->id])->firstOrFail();
        $this->assertSame(self::TEST_MEMBER_AGATHA_ID, (int)$grant->grantor_id);
        $this->assertSame(self::TEST_MEMBER_AGATHA_ID, (int)$grant->created_by);
        $this->assertSame((int)$grant->id, (int)$this->delegate()->id);
        $this->assertSame(1, $table->find()->where(['delegate_id' => $delegate->id])->count());

        $this->authenticateAsMember(self::TEST_MEMBER_BRYCE_ID);
        $this->get('/awards/bestowals/view/' . $bestowal->id);
        $this->assertResponseOk();
        $this->assertResponseContains('Delegated Crown secret');
        $this->get('/awards/bestowal-delegations/gathering/' . $this->gathering->id);
        $this->assertResponseOk();
        $this->assertResponseNotContains('Grant event access');
        $this->post('/awards/bestowal-delegations/grant/' . $this->gathering->id, [
            'branch_id' => self::KINGDOM_BRANCH_ID,
            'delegate_public_id' => $this->getTableLocator()->get('Members')->get(self::TEST_MEMBER_DEVON_ID)->public_id,
        ]);
        $this->assertRedirectContains('/pages/unauthorized');
        $this->post('/awards/bestowal-delegations/revoke/' . $grant->id);
        $this->assertRedirectContains('/pages/unauthorized');
        $this->assertNull($table->get($grant->id)->revoked_at);

        $this->authenticateAsMember(self::TEST_MEMBER_AGATHA_ID);
        $this->post('/awards/bestowal-delegations/revoke/' . $grant->id);
        $this->assertRedirectContains('/awards/bestowal-delegations/gathering/');
        $revoked = $table->get($grant->id);
        $this->assertNotNull($revoked->revoked_at);
        $this->assertSame(self::TEST_MEMBER_AGATHA_ID, (int)$revoked->revoked_by);
        $this->authenticateAsMember(self::TEST_MEMBER_BRYCE_ID);
        $this->get('/awards/bestowals/view/' . $bestowal->id);
        $this->assertRedirectContains('/pages/unauthorized');
        $replacement = $this->delegate();
        $this->assertNotSame((int)$grant->id, (int)$replacement->id);
        $this->assertSame(2, $table->find()->where(['delegate_id' => $delegate->id])->count());
        $this->assertSame([], PermissionsLoader::getRoles(self::TEST_MEMBER_BRYCE_ID));
    }

    public function testQueriesCourtAndPrintStayWithinTheEventAndAwardingGroup(): void
    {
        $owned = $this->bestowal(['roaming_court' => true]);
        $backlog = $this->bestowal(['member_sca_name' => 'Visible event backlog']);
        $otherEvent = $this->bestowal(['gathering_id' => $this->otherGathering->id]);
        $blocked = $this->bestowal([
            'award_id' => $this->otherAward->id, 'member_sca_name' => 'Restricted other group', 'roaming_court' => true,
        ]);
        $this->bestowal(['award_id' => $this->otherAward->id, 'member_sca_name' => 'Restricted backlog']);
        $this->delegate();
        $user = $this->getTableLocator()->get('Members')->get(self::TEST_MEMBER_BRYCE_ID);
        $table = $this->getTableLocator()->get('Awards.Bestowals');
        $ids = (new BestowalsTablePolicy())->scopeIndex($user, $table->find())->all()->extract('id')->toList();
        $this->assertEqualsCanonicalizing([(int)$owned->id, (int)$backlog->id], array_map('intval', $ids));
        $this->authenticateAsMember(self::TEST_MEMBER_BRYCE_ID);
        $this->get('/awards/bestowals/grid-data');
        $this->assertResponseOk();
        $this->assertResponseNotContains('Restricted');
        $this->get('/awards/bestowals/view/' . $otherEvent->id);
        $this->assertRedirectContains('/pages/unauthorized');
        $this->get('/awards/bestowals/view/' . $blocked->id);
        $this->assertRedirectContains('/pages/unauthorized');
        $this->get('/awards/court-agendas/gathering/' . $this->gathering->id);
        $this->assertResponseOk();
        $this->assertResponseContains('Delegated event recipient');
        $this->assertResponseContains('Court Item  (details blocked by security)');
        $this->assertResponseContains('Visible event backlog');
        $this->assertResponseNotContains('Restricted');
        $agenda = $this->viewVariable('agenda');
        $this->get('/awards/court-agendas/print-agenda/' . $agenda->id);
        $this->assertResponseOk();
        $this->assertResponseContains('Delegated Crown secret');
        $this->assertResponseContains('Court Item  (details blocked by security)');
        $this->assertResponseNotContains('Restricted');
        $this->get('/awards/court-agendas/gathering/' . $this->otherGathering->id);
        $this->assertRedirectContains('/pages/unauthorized');
    }

    public function testGrantorPermissionLossTakesEffectAndDoesNotPreventRevocation(): void
    {
        $bestowal = $this->bestowal();
        $grant = $this->delegate();
        $this->getTableLocator()->get('PermissionPolicies')->deleteAll([
            'permission_id' => $this->permission->id, 'policy_method' => 'canAccessCrownFields',
        ]);
        Cache::clearGroup('security');
        $grantor = $this->getTableLocator()->get('Members')->get(self::TEST_MEMBER_AGATHA_ID);
        $this->assertFalse((new BestowalPolicy())->canUpdateWithinScope(
            $grantor,
            $bestowal,
            ['noble_notes' => 'Permission removed while work was queued'],
        ));
        $this->authenticateAsMember(self::TEST_MEMBER_BRYCE_ID);
        $this->get('/awards/bestowals/view/' . $bestowal->id);
        $this->assertResponseOk();
        $this->assertResponseNotContains('Delegated Crown secret');
        $this->getTableLocator()->get('MemberRoles')->updateAll(
            ['expires_on' => '2000-01-01'],
            ['member_id' => self::TEST_MEMBER_AGATHA_ID],
        );
        Cache::clearGroup('security');
        $this->get('/awards/bestowals/view/' . $bestowal->id);
        $this->assertRedirectContains('/pages/unauthorized');
        $this->authenticateAsMember(self::TEST_MEMBER_AGATHA_ID);
        $this->post('/awards/bestowal-delegations/revoke/' . $grant->id);
        $this->assertRedirectContains('/awards/bestowal-delegations/gathering/');
    }

    public function testTodosUseNativeGrantorEligibilityForEveryAssignmentAndAuditTheDelegate(): void
    {
        $bestowal = $this->bestowal();
        $this->delegate();
        $items = [];
        $configs = [
            [ActionItem::ASSIGNEE_TYPE_MEMBER, ['member_id' => self::TEST_MEMBER_AGATHA_ID]],
            [ActionItem::ASSIGNEE_TYPE_PERMISSION, ['permission_id' => $this->permission->id]],
            [ActionItem::ASSIGNEE_TYPE_ROLE, ['role_id' => $this->role->id]],
            [ActionItem::ASSIGNEE_TYPE_DYNAMIC, [
                'service' => BestowalTodoAssigneeResolver::class, 'kind' => 'permission',
                'source_id' => $this->permission->id,
            ]],
        ];
        foreach ($configs as [$type, $config]) {
            $items[] = $this->todo($bestowal, ['assignee_type' => $type, 'assignee_config' => $config]);
        }
        $unrelated = $this->todo($this->bestowal(['gathering_id' => $this->otherGathering->id]));
        $wrongGroup = $this->todo($this->bestowal(['award_id' => $this->otherAward->id]));
        $resolver = new ActionItemAssigneeResolver();
        $service = new ActionItemService();
        foreach ($items as $item) {
            $this->assertFalse($resolver->isMemberDirectlyEligible($item, self::TEST_MEMBER_BRYCE_ID));
            $this->assertTrue($resolver->isMemberEligible($item, self::TEST_MEMBER_BRYCE_ID));
            $this->assertContains(self::TEST_MEMBER_BRYCE_ID, $resolver->getEligibleMemberIds($item));
        }
        $this->assertFalse($resolver->isMemberEligible($unrelated, self::TEST_MEMBER_BRYCE_ID));
        $this->assertFalse($resolver->isMemberEligible($wrongGroup, self::TEST_MEMBER_BRYCE_ID));
        $ids = $service->getActionableItemIdsForMember(self::TEST_MEMBER_BRYCE_ID);
        foreach ($items as $item) {
            $this->assertContains((int)$item->id, $ids);
        }
        $this->assertNotContains((int)$unrelated->id, $ids);
        $this->assertNotContains((int)$wrongGroup->id, $ids);
        $this->assertTrue($service->complete((int)$items[0]->id, self::TEST_MEMBER_BRYCE_ID, 'Acting for grantor')->success);
        $saved = $this->getTableLocator()->get('ActionItems')->get($items[0]->id);
        $this->assertSame(self::TEST_MEMBER_BRYCE_ID, (int)$saved->completed_by);
        $log = $this->getTableLocator()->get('ActionItemLogs')->find()
            ->where(['action_item_id' => $saved->id])->firstOrFail();
        $this->assertSame(self::TEST_MEMBER_BRYCE_ID, (int)$log->created_by);
        $this->assertTrue($service->reopen((int)$items[0]->id, self::TEST_MEMBER_BRYCE_ID)->success);
        $grant = $this->getTableLocator()->get('Awards.BestowalDelegations')->find()->firstOrFail();
        (new BestowalDelegationService())->revoke(
            $this->getTableLocator()->get('Members')->get(self::TEST_MEMBER_AGATHA_ID),
            (int)$grant->id,
        );
        $this->assertFalse($resolver->isMemberEligible($items[0], self::TEST_MEMBER_BRYCE_ID));
        $this->assertFalse($service->complete((int)$items[0]->id, self::TEST_MEMBER_BRYCE_ID)->success);
        $this->assertNotContains((int)$items[0]->id, $service->getActionableItemIdsForMember(self::TEST_MEMBER_BRYCE_ID));
    }

    public function testDelegatedEditsCannotChangeEventOrAwardOwnershipAndAreRecheckedAfterRevocation(): void
    {
        $bestowal = $this->bestowal();
        $grant = $this->delegate();
        $user = $this->getTableLocator()->get('Members')->get(self::TEST_MEMBER_BRYCE_ID);
        $policy = new BestowalPolicy();
        $this->assertTrue($policy->canUpdateWithinScope($user, $bestowal, ['specialty' => 'Updated']));
        $this->assertFalse($policy->canUpdateWithinScope($user, $bestowal, ['gathering_id' => $this->otherGathering->id]));
        $this->assertFalse($policy->canUpdateWithinScope($user, $bestowal, ['award_id' => $this->otherAward->id]));
        $this->assertFalse($policy->canUpdateWithinScope($user, $bestowal, ['gathering_id' => '']));
        $service = new BestowalUpdateService();
        $table = $this->getTableLocator()->get('Awards.Bestowals');
        $result = $service->update($table, (int)$bestowal->id, [
            'award_id' => $this->award->id, 'specialty' => 'Delegate update',
        ], self::TEST_MEMBER_BRYCE_ID);
        $this->assertTrue($result['success'], $result['error'] ?? '');
        $this->assertSame(self::TEST_MEMBER_BRYCE_ID, (int)$table->get($bestowal->id)->modified_by);
        (new BestowalDelegationService())->revoke(
            $this->getTableLocator()->get('Members')->get(self::TEST_MEMBER_AGATHA_ID),
            (int)$grant->id,
        );
        $result = $service->update($table, (int)$bestowal->id, [
            'award_id' => $this->award->id, 'specialty' => 'Revoked update',
        ], self::TEST_MEMBER_BRYCE_ID);
        $this->assertFalse($result['success']);
        $this->assertSame('Delegate update', $table->get($bestowal->id)->specialty);
    }

    public function testNativeLocalAuthorityCanBeDelegatedWithoutPassingOnInheritedKingdomAuthority(): void
    {
        $kingdom = $this->bestowal();
        $local = $this->bestowal(['award_id' => $this->otherAward->id]);
        $this->delegate();
        $this->getTableLocator()->get('Roles')->getConnection()->execute('INSERT INTO member_roles
            (member_id, role_id, branch_id, start_on, expires_on, approver_id, entity_type, created, modified,
             created_by, modified_by) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), ?, ?)', [
            self::TEST_MEMBER_BRYCE_ID, $this->role->id, $this->otherAward->branch_id, '2020-01-01', '2100-01-01',
            self::ADMIN_MEMBER_ID, 'Direct Grant', self::ADMIN_MEMBER_ID, self::ADMIN_MEMBER_ID,
        ]);
        Cache::clearGroup('security');
        $grantor = $this->getTableLocator()->get('Members')->get(self::TEST_MEMBER_BRYCE_ID);
        (new BestowalDelegationService())->grant(
            $grantor,
            (int)$this->gathering->id,
            (int)$this->otherAward->branch_id,
            self::TEST_MEMBER_DEVON_ID,
        );
        $third = $this->getTableLocator()->get('Members')->get(self::TEST_MEMBER_DEVON_ID);
        $policy = new BestowalPolicy();
        $this->assertTrue($policy->canView($third, $local));
        $this->assertFalse($policy->canView($third, $kingdom));
        $activity = $this->courtActivity();
        $this->assertTrue((new GatheringPolicy())->canManageActivity($grantor, $this->gathering, $activity));
        $this->assertFalse((new GatheringPolicy())->canManageActivity($third, $this->gathering, $activity));
        $this->assertFalse((new ActionItemAssigneeResolver())->isMemberEligible(
            $this->todo($kingdom),
            self::TEST_MEMBER_DEVON_ID,
        ));
        $grant = new BestowalDelegation([
            'gathering_id' => $this->gathering->id, 'branch_id' => self::KINGDOM_BRANCH_ID,
        ]);
        $this->assertFalse((new BestowalDelegationPolicy())->canGrant($grantor, $grant));
    }

    public function testDelegatedTerminalTodoFinalizesWithTheActualDelegateAsActor(): void
    {
        $bestowal = $this->bestowal();
        $this->delegate();
        $terminal = $this->todo($bestowal, [
            'is_terminal' => true,
            'completion_config' => ['terminal_outcomes' => ['given' => 'Given']],
        ]);
        $sibling = $this->todo($bestowal);
        $result = (new ActionItemService())->complete(
            (int)$terminal->id,
            self::TEST_MEMBER_BRYCE_ID,
            'Presented on behalf of the Crown',
            true,
            ['terminal_status' => 'given'],
            $this->getTableLocator()->get('Members')->get(self::TEST_MEMBER_BRYCE_ID),
        );
        $this->assertTrue($result->success, (string)$result->reason);
        $saved = $this->getTableLocator()->get('Awards.Bestowals')->get($bestowal->id);
        $this->assertSame(Bestowal::LIFECYCLE_GIVEN, $saved->lifecycle_status);
        $this->assertSame(self::TEST_MEMBER_BRYCE_ID, (int)$saved->modified_by);
        $items = $this->getTableLocator()->get('ActionItems');
        $this->assertSame(self::TEST_MEMBER_BRYCE_ID, (int)$items->get($terminal->id)->completed_by);
        $this->assertSame(ActionItem::STATUS_CANCELLED, $items->get($sibling->id)->status);
    }

    public function testAdHocBestowalsAreLimitedToGrantedEventAndGroup(): void
    {
        $this->delegate();
        $service = new AdHocBestowalService();
        $data = ['member_sca_name' => 'Delegated on the spot', 'gathering_id' => $this->gathering->id,
            'award_id' => $this->award->id];
        $result = $service->record($data, self::TEST_MEMBER_BRYCE_ID);
        $this->assertTrue($result['success'], $result['error'] ?? '');
        $wrongEvent = $data;
        $wrongEvent['gathering_id'] = $this->otherGathering->id;
        $this->assertFalse($service->record($wrongEvent, self::TEST_MEMBER_BRYCE_ID)['success']);
        $wrongGroup = $data;
        $wrongGroup['award_id'] = $this->otherAward->id;
        $this->assertFalse($service->record($wrongGroup, self::TEST_MEMBER_BRYCE_ID)['success']);
        $this->authenticateAsMember(self::TEST_MEMBER_BRYCE_ID);
        $this->get('/awards/bestowals/index');
        $this->assertResponseOk();
        $this->assertResponseContains('Record Ad-Hoc Bestowal');
        $this->assertResponseContains('id="editBestowalModal"');
        $this->get('/awards/bestowals/gatherings-for-bestowal-auto-complete?include_past=1&q=Delegation');
        $this->assertResponseOk();
        $this->assertResponseContains('Delegation test gathering');
        $this->assertResponseNotContains('Delegation test otherGathering');
    }

    public function testReadOnlyDelegationNeverSuppliesEligibleBestowals(): void
    {
        $this->bestowal(['roaming_court' => true]);
        $this->bestowal(['member_sca_name' => 'Read only backlog']);
        $this->delegate();
        $this->getTableLocator()->get('PermissionPolicies')->deleteAll([
            'permission_id' => $this->permission->id,
            'policy_method IN' => ['canEdit', 'canManageCourtSchedule'],
        ]);
        Cache::clearGroup('security');
        $this->authenticateAsMember(self::TEST_MEMBER_BRYCE_ID);
        $this->get('/awards/court-agendas/gathering/' . $this->gathering->id);
        $this->assertResponseOk();
        $this->assertSame([], $this->viewVariable('unscheduledBestowals'));
        foreach ($this->viewVariable('segments') as $segment) {
            $this->assertSame([], $segment['eligibleBestowals']);
        }
        $this->assertResponseNotContains('Read only backlog');
    }

    public function testRetainedTodoProviderResolvesTheActiveTenantLocatorOnEveryCall(): void
    {
        $item = $this->todo($this->bestowal());
        $this->delegate();
        $provider = new BestowalTodoDelegationProvider();
        $resolver = new ActionItemAssigneeResolver();
        $this->assertTrue($provider->isMemberEligible($item, self::TEST_MEMBER_BRYCE_ID, $resolver));
        $connection = $this->getTableLocator()->get('Awards.BestowalDelegations')->getConnection();
        $connection->execute('CREATE TEMP TABLE isolated_bestowal_delegations (LIKE awards_bestowal_delegations)');
        $original = FactoryLocator::get('Table');
        $isolated = new TableLocator();
        FactoryLocator::add('Table', $isolated);
        try {
            $isolated->get('Awards.BestowalDelegations', ['connection' => $connection])
                ->setTable('isolated_bestowal_delegations')
                ->setSchema($this->getTableLocator()->get('Awards.BestowalDelegations')->getSchema());
            $this->assertFalse($provider->isMemberEligible($item, self::TEST_MEMBER_BRYCE_ID, $resolver));
            $this->assertSame([], $provider->candidateConditions(self::TEST_MEMBER_BRYCE_ID));
        } finally {
            FactoryLocator::add('Table', $original);
        }
        $this->assertTrue($provider->isMemberEligible($item, self::TEST_MEMBER_BRYCE_ID, $resolver));
    }

    private function delegate(): object
    {
        return (new BestowalDelegationService())->grant(
            $this->getTableLocator()->get('Members')->get(self::TEST_MEMBER_AGATHA_ID),
            (int)$this->gathering->id,
            self::KINGDOM_BRANCH_ID,
            self::TEST_MEMBER_BRYCE_ID,
        );
    }

    public function testQueuedCancellationCannotRunAfterRevocation(): void
    {
        $bestowal = $this->bestowal();
        $todo = $this->todo($bestowal);
        $grant = $this->delegate();
        (new BestowalDelegationService())->revoke(
            $this->getTableLocator()->get('Members')->get(self::TEST_MEMBER_AGATHA_ID),
            (int)$grant->id,
        );
        $result = (new BestowalCancellationService())->cancel(
            (int)$bestowal->id,
            self::TEST_MEMBER_BRYCE_ID,
            'Queued before revocation',
        );
        $this->assertFalse($result['success']);
        $this->assertSame(
            Bestowal::LIFECYCLE_OPEN,
            $this->getTableLocator()->get('Awards.Bestowals')->get($bestowal->id)->lifecycle_status,
        );
        $this->assertSame(ActionItem::STATUS_OPEN, $this->getTableLocator()->get('ActionItems')->get($todo->id)->status);
    }

    public function testGatheringEligibilityExcludesRetainedSelectionsAndRequiresCurrentEventAuthority(): void
    {
        $activity = $this->courtActivity();
        $links = $this->getTableLocator()->get('GatheringsGatheringActivities');
        $links->deleteAll(['gathering_id' => $this->gathering->id]);
        $links->saveOrFail($links->newEntity([
            'gathering_id' => $this->gathering->id, 'gathering_activity_id' => $activity->id,
        ]));
        $replacement = $this->getTableLocator()->get('Awards.Awards')->find()->where([
            'branch_id' => self::KINGDOM_BRANCH_ID, 'id !=' => $this->award->id,
        ])->firstOrFail();
        $bestowal = $this->bestowal();
        $grant = $this->delegate();
        $this->authenticateAsMember(self::TEST_MEMBER_BRYCE_ID);
        $url = '/awards/bestowals/gathering-eligibility/' . $bestowal->id;
        $query = '?include_past=1&gathering_id=' . $this->gathering->id . '&award_id=';
        $this->get($url . $query . $this->award->id);
        $this->assertResponseOk();
        $this->assertResponseContains('"eligible":true');
        $this->get($url . $query . $replacement->id);
        $this->assertResponseOk();
        $this->assertResponseContains('"eligible":false');
        $this->get('/awards/bestowals/gatherings-for-bestowal-auto-complete/' . $bestowal->id
            . '?include_past=1&selected_id=' . $this->gathering->id . '&award_id=' . $replacement->id);
        $this->assertResponseContains('data-ac-value="' . $this->gathering->id . '"');
        $this->get($url . '?include_past=1&gathering_id=' . $this->otherGathering->id
            . '&award_id=' . $this->award->id);
        $this->assertResponseCode(403);
        $this->get($url . $query . $this->otherAward->id);
        $this->assertResponseCode(403);
        (new BestowalDelegationService())->revoke(
            $this->getTableLocator()->get('Members')->get(self::TEST_MEMBER_AGATHA_ID),
            (int)$grant->id,
        );
        $this->get($url . $query . $this->award->id);
        $this->assertRedirectContains('/pages/unauthorized');
        $saved = $this->getTableLocator()->get('Awards.Bestowals')->get($bestowal->id);
        $this->assertSame((int)$this->gathering->id, (int)$saved->gathering_id);
        $this->assertSame((int)$this->award->id, (int)$saved->award_id);
    }

    public function testCourtEligibilityUsesTheNewAwardWhileRetainingTheGathering(): void
    {
        $activity = $this->courtActivity();
        $replacementActivity = $this->courtActivity(false);
        $tables = $this->getTableLocator();
        $replacement = $tables->get('Awards.Awards')->find()->where([
            'branch_id' => self::KINGDOM_BRANCH_ID, 'id !=' => $this->award->id,
        ])->firstOrFail();
        $awardLinks = $tables->get('Awards.AwardGatheringActivities');
        $awardLinks->saveOrFail($awardLinks->newEntity([
            'award_id' => $replacement->id, 'gathering_activity_id' => $replacementActivity->id,
        ]));
        $links = $tables->get('GatheringsGatheringActivities');
        foreach ([$activity, $replacementActivity] as $type) {
            $links->saveOrFail($links->newEntity([
                'gathering_id' => $this->gathering->id, 'gathering_activity_id' => $type->id,
            ]));
        }
        $sessions = $tables->get('GatheringScheduledActivities');
        $session = $sessions->saveOrFail($sessions->newEntity([
            'gathering_id' => $this->gathering->id, 'gathering_activity_id' => $activity->id,
            'start_datetime' => $this->gathering->start_date, 'display_title' => 'Original award court',
        ]));
        $foreign = $sessions->saveOrFail($sessions->newEntity([
            'gathering_id' => $this->otherGathering->id, 'gathering_activity_id' => $activity->id,
            'start_datetime' => $this->otherGathering->start_date, 'display_title' => 'Other event court secret',
        ]));
        $bestowal = $this->bestowal(['gathering_scheduled_activity_id' => $session->id]);
        $this->delegate();
        $this->authenticateAsMember(self::TEST_MEMBER_BRYCE_ID);
        $url = '/awards/bestowals/gathering-eligibility/' . $bestowal->id
            . '?include_past=1&gathering_id=' . $this->gathering->id;
        $this->get($url . '&award_id=' . $this->award->id . '&court_session=' . $session->id);
        $this->assertResponseOk();
        $this->assertResponseContains('"eligible":true,"courtEligible":true');
        $this->get($url . '&award_id=' . $replacement->id . '&court_session=' . $session->id);
        $this->assertResponseOk();
        $this->assertResponseContains('"eligible":true,"courtEligible":false');
        $this->get($url . '&award_id=' . $replacement->id . '&court_session=roaming');
        $this->assertResponseContains('"courtEligible":true');
        $this->get($url . '&award_id=' . $replacement->id);
        $this->assertResponseContains('"courtEligible":null');
        $this->get($url . '&award_id=' . $this->award->id . '&court_session=' . $foreign->id);
        $this->assertResponseContains('"courtEligible":false');
        $this->assertResponseNotContains('Other event court secret');
        $saved = $tables->get('Awards.Bestowals')->get($bestowal->id);
        $this->assertSame((int)$this->gathering->id, (int)$saved->gathering_id);
        $this->assertSame((int)$session->id, (int)$saved->gathering_scheduled_activity_id);
        $this->assertSame((int)$this->award->id, (int)$saved->award_id);
    }

    public function testBulkSchedulingCannotMoveDelegatedWorkToAnotherEvent(): void
    {
        $bestowal = $this->bestowal();
        $grant = $this->delegate();
        $table = $this->getTableLocator()->get('Awards.Bestowals');
        $service = new BestowalUpdateService();
        $result = $service->assignGathering(
            $table,
            (int)$bestowal->id,
            (int)$this->otherGathering->id,
            self::TEST_MEMBER_BRYCE_ID,
            false,
        );
        $this->assertFalse($result['success']);
        $this->assertSame((int)$this->gathering->id, (int)$table->get($bestowal->id)->gathering_id);
        // Native local scheduling opens the bulk endpoint but must not widen the inherited kingdom grant.
        $table->getConnection()->execute('INSERT INTO member_roles
            (member_id, role_id, branch_id, start_on, expires_on, approver_id, entity_type, created, modified,
             created_by, modified_by) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), ?, ?)', [
            self::TEST_MEMBER_BRYCE_ID, $this->role->id, $this->otherAward->branch_id, '2020-01-01', '2100-01-01',
            self::ADMIN_MEMBER_ID, 'Direct Grant', self::ADMIN_MEMBER_ID, self::ADMIN_MEMBER_ID,
        ]);
        Cache::clearGroup('security');
        $this->authenticateAsMember(self::TEST_MEMBER_BRYCE_ID);
        $this->post('/awards/bestowals/bulk-assign-gathering', [
            'bestowal_ids' => (string)$bestowal->id, 'bestowal_gathering_id' => $this->otherGathering->id,
            'include_past' => 1,
        ]);
        $this->assertResponseCode(302);
        $this->assertFlashMessage('No selected bestowals could be assigned to that gathering.');
        $this->assertSame((int)$this->gathering->id, (int)$table->get($bestowal->id)->gathering_id);
        (new BestowalDelegationService())->revoke(
            $this->getTableLocator()->get('Members')->get(self::TEST_MEMBER_AGATHA_ID),
            (int)$grant->id,
        );
        $result = $service->assignGathering(
            $table,
            (int)$bestowal->id,
            (int)$this->gathering->id,
            self::TEST_MEMBER_BRYCE_ID,
            false,
        );
        $this->assertFalse($result['success']);
    }

    public function testNativeSchedulingTasksRetainAuthorityOnlyForTheirActualBestowal(): void
    {
        $bestowal = $this->bestowal();
        $other = $this->bestowal();
        $this->delegate();
        $task = $this->todo($other, [
            'assignee_config' => ['member_id' => self::TEST_MEMBER_BRYCE_ID],
            'completion_config' => ['required_fields' => [[
                'provider' => BestowalTodoTemplateItem::COMPLETION_PROVIDER_BESTOWAL_GATHERING,
                'field' => BestowalTodoTemplateItem::REQUIRED_FIELD_GATHERING,
            ]]],
        ]);
        $table = $this->getTableLocator()->get('Awards.Bestowals');
        $service = new BestowalUpdateService();
        $result = $service->assignGathering(
            $table,
            (int)$bestowal->id,
            (int)$this->otherGathering->id,
            self::TEST_MEMBER_BRYCE_ID,
            false,
            false,
            (int)$task->id,
        );
        $this->assertFalse($result['success']);
        $this->assertSame((int)$this->gathering->id, (int)$table->get($bestowal->id)->gathering_id);
        $result = $service->assignGathering(
            $table,
            (int)$other->id,
            (int)$this->otherGathering->id,
            self::TEST_MEMBER_BRYCE_ID,
            false,
            false,
            (int)$task->id,
        );
        $this->assertTrue($result['success'], $result['error'] ?? '');
        $this->assertSame((int)$this->otherGathering->id, (int)$table->get($other->id)->gathering_id);
    }

    public function testLegacyFinalizationRequiresAuthorityForTheActualEvent(): void
    {
        $this->delegate();
        $templates = $this->getTableLocator()->get('Awards.BestowalTodoTemplates');
        $template = $templates->saveOrFail($templates->newEntity(['name' => 'Empty delegation test process']));
        $awards = $this->getTableLocator()->get('Awards.Awards');
        $award = $awards->get($this->award->id);
        $award->bestowal_todo_template_id = $template->id;
        $awards->saveOrFail($award);
        $foreign = $this->bestowal(['gathering_id' => $this->otherGathering->id]);
        $result = (new BestowalFinalizationService())->markGiven((int)$foreign->id, self::TEST_MEMBER_BRYCE_ID);
        $this->assertFalse($result->success);
        $this->assertSame(
            Bestowal::LIFECYCLE_OPEN,
            $this->getTableLocator()->get('Awards.Bestowals')->get($foreign->id)->lifecycle_status,
        );
    }

    public function testRecommendationContextCannotWidenDelegatedGatheringLookup(): void
    {
        $recommendations = $this->getTableLocator()->get('Awards.Recommendations');
        $recommendation = $recommendations->find()->where(['award_id' => $this->award->id])->firstOrFail();
        $this->delegate();
        $this->authenticateAsMember(self::TEST_MEMBER_BRYCE_ID);
        $this->get('/awards/bestowals/gatherings-for-bestowal-auto-complete?include_past=1&q=Delegation'
            . '&recommendation_id=' . $recommendation->id);
        $this->assertRedirectContains('/pages/unauthorized');
    }

    public function testCourtServiceRechecksAuthorityAfterRevocation(): void
    {
        $bestowal = $this->bestowal(['roaming_court' => true]);
        $grant = $this->delegate();
        $item = $this->getTableLocator()->get('Awards.CourtAgendaItems')->find()
            ->where(['bestowal_id' => $bestowal->id])->firstOrFail();
        (new BestowalDelegationService())->revoke(
            $this->getTableLocator()->get('Members')->get(self::TEST_MEMBER_AGATHA_ID),
            (int)$grant->id,
        );
        $this->expectException(ForbiddenException::class);
        (new CourtAgendaService())->updateItem(
            (int)$item->id,
            ['presentation_notes' => 'Submitted before revocation'],
            self::TEST_MEMBER_BRYCE_ID,
        );
    }

    public function testOtherEventsCourtSessionCannotBeAttachedToAnOwnedBestowal(): void
    {
        $activity = $this->getTableLocator()->get('GatheringActivities')->find()->firstOrFail();
        $sessions = $this->getTableLocator()->get('GatheringScheduledActivities');
        $session = $sessions->saveOrFail($sessions->newEntity([
            'gathering_id' => $this->otherGathering->id, 'gathering_activity_id' => $activity->id,
            'start_datetime' => $this->otherGathering->start_date, 'display_title' => 'Other event court secret',
            'created_by' => self::ADMIN_MEMBER_ID,
        ]));
        $bestowal = $this->bestowal();
        $this->delegate();
        $result = (new BestowalUpdateService())->update(
            $this->getTableLocator()->get('Awards.Bestowals'),
            (int)$bestowal->id,
            ['award_id' => $this->award->id, 'gathering_scheduled_activity_id' => $session->id],
            self::TEST_MEMBER_BRYCE_ID,
        );
        $this->assertFalse($result['success']);
        $this->assertNull($this->getTableLocator()->get('Awards.Bestowals')->get($bestowal->id)
            ->gathering_scheduled_activity_id);
        $result = (new AdHocBestowalService())->record([
            'award_id' => $this->award->id, 'gathering_id' => $this->gathering->id,
            'gathering_scheduled_activity_id' => $session->id, 'member_sca_name' => 'Forged session recipient',
        ], self::TEST_MEMBER_BRYCE_ID);
        $this->assertFalse($result['success']);
    }

    public function testForgedAgendaAndItemIdsCannotMoveWorkBetweenEvents(): void
    {
        $own = $this->bestowal(['roaming_court' => true]);
        $foreign = $this->bestowal(['gathering_id' => $this->otherGathering->id, 'roaming_court' => true]);
        $items = $this->getTableLocator()->get('Awards.CourtAgendaItems');
        $ownItem = $items->find()->where(['bestowal_id' => $own->id])->contain(['CourtAgendaSegments'])->firstOrFail();
        $foreignItem = $items->find()->where(['bestowal_id' => $foreign->id])->firstOrFail();
        $this->delegate();
        $this->authenticateAsMember(self::TEST_MEMBER_BRYCE_ID);
        $this->post('/awards/court-agendas/move-item', [
            'item_id' => $ownItem->id, 'court_agenda_segment_id' => $foreignItem->court_agenda_segment_id,
        ]);
        $this->assertResponseCode(403);
        $this->post('/awards/court-agendas/move-to-roaming', [
            'court_agenda_id' => $ownItem->court_agenda_segment->court_agenda_id,
            'item_id' => $foreignItem->id,
        ]);
        $this->assertResponseCode(403);
        $this->assertSame((int)$ownItem->court_agenda_segment_id, (int)$items->get($ownItem->id)->court_agenda_segment_id);
        $this->assertSame(
            (int)$foreignItem->court_agenda_segment_id,
            (int)$items->get($foreignItem->id)->court_agenda_segment_id,
        );
        $this->assertSame(
            (int)$this->otherGathering->id,
            (int)$this->getTableLocator()->get('Awards.Bestowals')->get($foreign->id)->gathering_id,
        );
    }

    public function testStaleRequestsAndAllReadSurfacesAreDeniedAfterRevocation(): void
    {
        $bestowal = $this->bestowal(['roaming_court' => true]);
        $todo = $this->todo($bestowal);
        $item = $this->getTableLocator()->get('Awards.CourtAgendaItems')->find()
            ->where(['bestowal_id' => $bestowal->id])->contain(['CourtAgendaSegments'])->firstOrFail();
        $grant = $this->delegate();
        (new BestowalDelegationService())->revoke(
            $this->getTableLocator()->get('Members')->get(self::TEST_MEMBER_AGATHA_ID),
            (int)$grant->id,
        );
        $this->authenticateAsMember(self::TEST_MEMBER_BRYCE_ID);
        $urls = [
            '/awards/bestowals/view/' . $bestowal->id,
            '/awards/bestowals/grid-data?export=csv',
            '/awards/bestowals/gathering-bestowals-grid-data/' . $this->gathering->id,
            '/awards/court-agendas/gathering/' . $this->gathering->id,
            '/awards/court-agendas/print-agenda/' . $item->court_agenda_segment->court_agenda_id,
        ];
        foreach ($urls as $url) {
            $this->get($url);
            $this->assertRedirectContains('/pages/unauthorized');
            $this->assertResponseNotContains('Delegated Crown secret');
        }
        $requests = [
            '/awards/bestowals/edit/' . $bestowal->id => ['award_id' => $this->award->id, 'specialty' => 'Stale write'],
            '/awards/bestowals/cancel/' . $bestowal->id => ['close_reason' => 'Stale cancellation'],
            '/awards/bestowals/mark-given' => ['id' => $bestowal->id],
            '/awards/court-agendas/update-item/' . $item->id => ['presentation_notes' => 'Stale notes'],
            '/awards/court-agendas/remove-item' => ['item_id' => $item->id],
            '/action-items/complete/' . $todo->id => [],
        ];
        foreach ($requests as $url => $data) {
            $this->post($url, $data);
            $this->assertRedirectContains('/pages/unauthorized');
        }
        $this->assertSame(
            Bestowal::LIFECYCLE_OPEN,
            $this->getTableLocator()->get('Awards.Bestowals')->get($bestowal->id)->lifecycle_status,
        );
        $this->assertTrue($this->getTableLocator()->get('Awards.CourtAgendaItems')->exists(['id' => $item->id]));
        $this->assertSame(ActionItem::STATUS_OPEN, $this->getTableLocator()->get('ActionItems')->get($todo->id)->status);
    }

    public function testEventDelegateCanAttachScheduleAndUseTheGrantingGroupsCourtAtALocalEvent(): void
    {
        $gatherings = $this->getTableLocator()->get('Gatherings');
        $this->gathering->branch_id = $this->otherAward->branch_id;
        $gatherings->saveOrFail($this->gathering);
        $activity = $this->courtActivity();
        $bestowal = $this->bestowal();
        $this->privateEventStaff();
        $this->delegate();
        $delegate = $this->getTableLocator()->get('Members')->get(self::TEST_MEMBER_BRYCE_ID);
        $grantor = $this->getTableLocator()->get('Members')->get(self::TEST_MEMBER_AGATHA_ID);
        $this->assertFalse((new GatheringActivityPolicy())->canAssignNatively($grantor, $activity, $this->gathering));
        $this->assertFalse((new GatheringPolicy())->canEdit($delegate, $this->gathering));
        $this->assertTrue((new GatheringPolicy())->canManageActivity($delegate, $this->gathering, $activity));
        $this->authenticateAsMember(self::TEST_MEMBER_BRYCE_ID);
        $this->get('/gatherings/view/' . $this->gathering->public_id);
        $this->assertResponseOk();
        $this->assertResponseContains('Add Activity');
        $this->assertPreparationTabsOnly();
        $this->post('/gatherings/add-activity/' . $this->gathering->id, ['activity_id' => $activity->id]);
        $this->assertRedirectContains('/gatherings/view/' . $this->gathering->public_id);
        $this->assertTrue($this->getTableLocator()->get('GatheringsGatheringActivities')->exists([
            'gathering_id' => $this->gathering->id, 'gathering_activity_id' => $activity->id,
        ]));
        $this->post('/gatherings/add-scheduled-activity/' . $this->gathering->public_id, [
            'gathering_activity_id' => $activity->id, 'is_other' => '0',
            'start_datetime' => $this->gathering->start_date->format('Y-m-d\TH:i'),
            'duration_minutes' => '30', 'display_title' => 'Delegated kingdom court',
        ]);
        $this->assertResponseOk();
        $scheduled = $this->getTableLocator()->get('GatheringScheduledActivities')->find()->where([
            'gathering_id' => $this->gathering->id, 'gathering_activity_id' => $activity->id,
        ])->firstOrFail();
        $this->assertSame(self::TEST_MEMBER_BRYCE_ID, (int)$scheduled->created_by);
        $this->post('/gatherings/edit-scheduled-activity/' . $this->gathering->public_id . '/' . $scheduled->id, [
            'display_title' => 'Prepared kingdom court', 'duration_minutes' => 'existing',
        ]);
        $this->assertResponseOk();
        $this->assertSame('Prepared kingdom court', $this->getTableLocator()->get('GatheringScheduledActivities')
            ->get($scheduled->id)->display_title);
        $service = new CourtAgendaService();
        $agenda = $service->getOrCreateDefaultAgenda((int)$this->gathering->id, self::TEST_MEMBER_BRYCE_ID);
        $service->ensureEligibleCourtSegments((int)$agenda->id, self::TEST_MEMBER_BRYCE_ID);
        $segment = $this->getTableLocator()->get('Awards.CourtAgendaSegments')->find()->where([
            'court_agenda_id' => $agenda->id, 'gathering_scheduled_activity_id' => $scheduled->id,
        ])->firstOrFail();
        $placed = $service->addBestowalToSegment(
            (int)$agenda->id,
            (int)$bestowal->id,
            (int)$segment->id,
            self::TEST_MEMBER_BRYCE_ID,
        );
        $this->assertSame((int)$bestowal->id, (int)$placed->bestowal_id);
        $this->assertSame((int)$scheduled->id, (int)$this->getTableLocator()->get('Awards.Bestowals')
            ->get($bestowal->id)->gathering_scheduled_activity_id);
    }

    public function testNativeAwardStaffCanPrepareLocalCourtsWithoutPrivateGatheringAccess(): void
    {
        $gatherings = $this->getTableLocator()->get('Gatherings');
        foreach ([$this->gathering, $this->otherGathering] as $gathering) {
            $gathering->branch_id = $this->otherAward->branch_id;
            $gatherings->saveOrFail($gathering);
        }
        $activity = $this->courtActivity();
        $this->bestowal();
        $this->privateEventStaff();
        $grantor = $this->getTableLocator()->get('Members')->get(self::TEST_MEMBER_AGATHA_ID);
        $policy = new GatheringPolicy();
        $this->assertFalse($policy->canViewManagement($grantor, $this->gathering));
        $this->assertFalse((new GatheringActivityPolicy())->canAssignNatively($grantor, $activity, $this->gathering));
        $this->assertTrue($policy->canManageActivity($grantor, $this->gathering, $activity));
        $this->assertFalse($policy->canManageActivity($grantor, $this->otherGathering, $activity));
        $this->assertSame(0, $this->getTableLocator()->get('Awards.BestowalDelegations')->find()->count());
        $this->authenticateAsMember(self::TEST_MEMBER_AGATHA_ID);
        $this->get('/gatherings/view/' . $this->gathering->public_id);
        $this->assertResponseOk();
        $this->assertPreparationTabsOnly();
        $this->post('/gatherings/add-activity/' . $this->otherGathering->id, ['activity_id' => $activity->id]);
        $this->assertResponseCode(403);
        $this->post('/gatherings/add-activity/' . $this->gathering->id, ['activity_id' => $activity->id]);
        $this->assertRedirectContains('/gatherings/view/' . $this->gathering->public_id);
        $this->post('/gatherings/add-scheduled-activity/' . $this->gathering->public_id, [
            'gathering_activity_id' => $activity->id, 'is_other' => '0',
            'start_datetime' => $this->gathering->start_date->format('Y-m-d\TH:i'),
            'duration_minutes' => '30', 'display_title' => 'Court prepared by the native Crown',
        ]);
        $this->assertResponseOk();
        $this->assertTrue($this->getTableLocator()->get('GatheringScheduledActivities')->exists([
            'gathering_id' => $this->gathering->id, 'gathering_activity_id' => $activity->id,
            'created_by' => self::TEST_MEMBER_AGATHA_ID,
        ]));
    }

    private function privateEventStaff(): void
    {
        $staff = $this->getTableLocator()->get('GatheringStaff');
        $staff->saveOrFail($staff->newEntity([
            'gathering_id' => $this->gathering->id, 'sca_name' => 'Private court staffing fixture',
            'role' => 'Private staffing fixture', 'show_on_public_page' => false,
            'email' => 'private-court-staff@example.test', 'created_by' => self::ADMIN_MEMBER_ID,
        ]));
    }

    private function assertPreparationTabsOnly(): void
    {
        foreach (['nav-activities-tab', 'nav-schedule-tab'] as $id) {
            $this->assertResponseContains('id="' . $id . '"');
        }
        foreach (['nav-staff', 'nav-location', 'nav-details', 'nav-attendance'] as $id) {
            $this->assertResponseNotContains('id="' . $id . '-tab"');
            $this->assertResponseNotContains('id="' . $id . '"');
        }
        $this->assertResponseNotContains('Private court staffing fixture');
        $this->assertResponseNotContains('private-court-staff@example.test');
    }

    public function testDelegatedCourtSchedulingCannotCrossEventsUseOtherActivitiesOrSurviveRevocation(): void
    {
        $activity = $this->courtActivity();
        $unrelated = $this->courtActivity(false);
        $grant = $this->delegate();
        $delegate = $this->getTableLocator()->get('Members')->get(self::TEST_MEMBER_BRYCE_ID);
        $policy = new GatheringPolicy();
        $this->assertFalse($policy->canManageActivity($delegate, $this->otherGathering, $activity));
        $this->assertFalse($policy->canManageActivity($delegate, $this->gathering, $unrelated));
        $this->authenticateAsMember(self::TEST_MEMBER_BRYCE_ID);
        $this->post('/gatherings/add-activity/' . $this->otherGathering->id, ['activity_id' => $activity->id]);
        $this->assertResponseCode(403);
        $this->post('/gatherings/add-activity/' . $this->gathering->id, ['activity_id' => $unrelated->id]);
        $this->assertResponseCode(403);
        $this->post('/gatherings/add-scheduled-activity/' . $this->gathering->public_id, [
            'is_other' => '1', 'display_title' => 'Forged other activity',
        ]);
        $this->assertResponseCode(403);
        $activityService = new GatheringActivityService();
        $activityService->addActivity(
            (int)$this->gathering->id,
            (int)$activity->id,
            [],
            null,
            self::TEST_MEMBER_BRYCE_ID,
        );
        $schedule = new GatheringScheduleService();
        $data = ['gathering_activity_id' => $activity->id, 'is_other' => false,
            'start_datetime' => $this->gathering->start_date->format('Y-m-d\TH:i'),
            'duration_minutes' => '30', 'display_title' => 'Delegated scheduled court'];
        $result = $schedule->add($data, $this->gathering, $delegate);
        $this->assertTrue($result['success'], $result['message']);
        $scheduledId = (int)$result['data']->id;
        $this->post('/gatherings/edit-scheduled-activity/' . $this->otherGathering->public_id . '/' . $scheduledId, [
            'display_title' => 'Forged owner',
        ]);
        $this->assertResponseCode(403);
        $this->post('/gatherings/edit-scheduled-activity/' . $this->gathering->public_id . '/' . $scheduledId, [
            'gathering_activity_id' => $unrelated->id,
        ]);
        $this->assertResponseCode(403);
        $agendaService = new CourtAgendaService();
        $agenda = $agendaService->getOrCreateDefaultAgenda((int)$this->gathering->id, self::TEST_MEMBER_BRYCE_ID);
        (new BestowalDelegationService())->revoke(
            $this->getTableLocator()->get('Members')->get(self::TEST_MEMBER_AGATHA_ID),
            (int)$grant->id,
        );
        $this->assertFalse($policy->canManageActivity($delegate, $this->gathering, $activity));
        $operations = [
            fn() => $activityService->addActivity(
                (int)$this->gathering->id,
                (int)$activity->id,
                [],
                null,
                self::TEST_MEMBER_BRYCE_ID,
            ),
            fn() => $activityService->editDescription(
                (int)$this->gathering->id,
                (int)$activity->id,
                'Stale description',
                self::TEST_MEMBER_BRYCE_ID,
            ),
            fn() => $schedule->add($data, $this->gathering, $delegate),
            fn() => $schedule->edit($scheduledId, ['display_title' => 'Stale edit'], $this->gathering, $delegate),
            fn() => $schedule->delete($scheduledId, $this->gathering, $delegate),
            fn() => (new CourtAgendaService())->getOrCreateDefaultAgenda(
                (int)$this->gathering->id,
                self::TEST_MEMBER_BRYCE_ID,
            ),
            fn() => $agendaService->ensureEligibleCourtSegments((int)$agenda->id, self::TEST_MEMBER_BRYCE_ID),
        ];
        foreach ($operations as $operation) {
            try {
                $operation();
                $this->fail('A stale event operation was authorized.');
            } catch (ForbiddenException $exception) {
                $this->assertSame(403, $exception->getCode());
            }
        }
        $this->assertSame('Delegated scheduled court', $this->getTableLocator()->get('GatheringScheduledActivities')
            ->get($scheduledId)->display_title);
    }

    public function testCourtSchedulingStopsWhenTheGrantorLosesTheActivityPermission(): void
    {
        $activity = $this->courtActivity();
        $bestowal = $this->bestowal();
        $this->delegate();
        $delegate = $this->getTableLocator()->get('Members')->get(self::TEST_MEMBER_BRYCE_ID);
        $policy = new GatheringPolicy();
        $this->assertTrue($policy->canManageActivity($delegate, $this->gathering, $activity));
        $permissions = $this->getTableLocator()->get('Permissions');
        $permission = $permissions->saveOrFail($permissions->newEntity([
            'name' => 'Different activity assignment ' . uniqid(),
            'scoping_rule' => Permission::SCOPE_BRANCH_ONLY,
        ]));
        $this->getTableLocator()->get('GatheringActivityAssignmentPermissions')->updateAll(
            ['permission_id' => $permission->id],
            ['gathering_activity_id' => $activity->id],
        );
        $this->assertTrue((new BestowalPolicy())->canView($delegate, $bestowal));
        $this->assertFalse($policy->canManageActivity($delegate, $this->gathering, $activity));
    }

    public function testBatchActivityAccessRetainsEventScopeAndRechecksRevocation(): void
    {
        $activity = $this->courtActivity();
        $unrelated = $this->courtActivity(false);
        $grant = $this->delegate();
        $delegate = $this->getTableLocator()->get('Members')->get(self::TEST_MEMBER_BRYCE_ID);
        $provider = new BestowalGatheringActivityDelegationProvider();
        $this->assertTrue($provider->canAssignAny($delegate, [$unrelated, $activity], $this->gathering));
        $this->assertFalse($provider->canAssignAny($delegate, [$unrelated], $this->gathering));
        $this->assertFalse($provider->canAssignAny($delegate, [$activity], $this->otherGathering));
        $this->assertTrue((new GatheringPolicy())->canView($delegate, $this->gathering));
        $service = new BestowalDelegationService();
        $service->revoke(
            $this->getTableLocator()->get('Members')->get(self::TEST_MEMBER_AGATHA_ID),
            (int)$grant->id,
        );
        $this->assertFalse($provider->canAssignAny($delegate, [$unrelated, $activity], $this->gathering));
        $this->assertFalse((new GatheringPolicy())->canView($delegate, $this->gathering));
    }

    private function courtActivity(bool $eligible = true): object
    {
        $tables = $this->getTableLocator();
        $activities = $tables->get('GatheringActivities');
        $activity = $activities->saveOrFail($activities->newEntity([
            'name' => 'Delegated activity ' . uniqid(), 'activity_type' => 'Court',
        ]));
        $rules = $tables->get('GatheringActivityAssignmentPermissions');
        $rules->saveOrFail($rules->newEntity([
            'gathering_activity_id' => $activity->id, 'permission_id' => $this->permission->id,
        ]));
        if ($eligible) {
            $links = $tables->get('Awards.AwardGatheringActivities');
            $links->saveOrFail($links->newEntity([
                'award_id' => $this->award->id, 'gathering_activity_id' => $activity->id,
            ]));
        }

        return $activity;
    }

    private function bestowal(array $overrides = []): Bestowal
    {
        $table = $this->getTableLocator()->get('Awards.Bestowals');

        return $table->saveOrFail($table->newEntity($overrides + [
            'member_sca_name' => 'Delegated event recipient', 'award_id' => $this->award->id,
            'gathering_id' => $this->gathering->id, 'lifecycle_status' => Bestowal::LIFECYCLE_OPEN,
            'source' => Bestowal::SOURCE_AD_HOC, 'noble_notes' => 'Delegated Crown secret',
        ]));
    }

    private function todo(Bestowal $bestowal, array $overrides = []): ActionItem
    {
        $table = $this->getTableLocator()->get('ActionItems');

        return $table->saveOrFail($table->newEntity($overrides + [
            'entity_type' => Bestowal::ACTION_ITEM_ENTITY_TYPE, 'entity_id' => $bestowal->id,
            'title' => 'Delegate test task', 'status' => ActionItem::STATUS_OPEN, 'is_gating' => true,
            'assignee_type' => ActionItem::ASSIGNEE_TYPE_MEMBER,
            'assignee_config' => ['member_id' => self::TEST_MEMBER_AGATHA_ID],
            'branch_id' => self::KINGDOM_BRANCH_ID, 'sort_order' => 10,
        ]));
    }

    private function grantNativeAuthority(): void
    {
        $tables = $this->getTableLocator();
        $permissions = $tables->get('Permissions');
        $this->permission = $permissions->saveOrFail($permissions->newEntity([
            'name' => 'Event delegation test ' . uniqid(), 'require_active_membership' => false,
            'require_active_background_check' => false, 'require_min_age' => 0,
            'is_system' => false, 'is_super_user' => false, 'requires_warrant' => false,
            'scoping_rule' => Permission::SCOPE_BRANCH_ONLY,
        ]));
        $policies = [
            BestowalPolicy::class => ['canIndex', 'canView', 'canEdit', 'canAccessCrownFields',
                'canAccessHeraldNotes', 'canManageCourtSchedule', 'canViewGatheringBestowals'],
            BestowalsTablePolicy::class => ['canIndex'],
            'Awards\\Policy\\CourtAgendaPolicy' => ['canGathering', 'canEdit', 'canPrintAgenda'],
        ];
        $permissionPolicies = $tables->get('PermissionPolicies');
        foreach ($policies as $class => $methods) {
            foreach ($methods as $method) {
                $permissionPolicies->saveOrFail($permissionPolicies->newEntity([
                    'permission_id' => $this->permission->id, 'policy_class' => $class, 'policy_method' => $method,
                ]));
            }
        }
        $roles = $tables->get('Roles');
        $this->role = $roles->saveOrFail($roles->newEntity(['name' => 'Delegation test role ' . uniqid()]));
        $connection = $roles->getConnection();
        $connection->execute('INSERT INTO roles_permissions (role_id, permission_id, created, created_by)
            VALUES (?, ?, NOW(), ?)', [$this->role->id, $this->permission->id, self::ADMIN_MEMBER_ID]);
        $connection->execute('INSERT INTO member_roles
            (member_id, role_id, branch_id, start_on, expires_on, approver_id, entity_type, created, modified,
             created_by, modified_by) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), ?, ?)', [
            self::TEST_MEMBER_AGATHA_ID, $this->role->id, self::KINGDOM_BRANCH_ID, '2020-01-01', '2100-01-01',
            self::ADMIN_MEMBER_ID, 'Direct Grant', self::ADMIN_MEMBER_ID, self::ADMIN_MEMBER_ID,
        ]);
        Cache::clearGroup('security');
    }
}
