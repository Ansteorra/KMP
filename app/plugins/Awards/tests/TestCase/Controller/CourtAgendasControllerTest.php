<?php
declare(strict_types=1);

namespace Awards\Test\TestCase\Controller;

use App\KMP\TimezoneHelper;
use App\Model\Entity\GatheringScheduledActivity;
use App\Model\Entity\Permission;
use App\Test\TestCase\Support\HttpIntegrationTestCase;
use Awards\Model\Entity\Bestowal;
use Cake\Cache\Cache;
use Cake\I18n\DateTime;
use DateTimeZone;

/**
 * CourtAgendasControllerTest
 */
class CourtAgendasControllerTest extends HttpIntegrationTestCase
{
    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->authenticateAsSuperUser();
    }

    /**
     * @return void
     */
    public function testGatheringAgendaRendersPerCourtBuilder(): void
    {
        $gathering = $this->getTableLocator()->get('Gatherings')
            ->find()
            ->select(['id', 'public_id'])
            ->firstOrFail();
        $this->createBestowalForGathering((int)$gathering->id);
        $this->importBestowalsForGathering((int)$gathering->id);

        $this->get('/awards/court-agendas/gathering/' . $gathering->id);

        $this->assertResponseOk();
        $this->assertResponseContains('Projected Agenda Runtime');
        $this->assertResponseContains('Court Activities');
        $this->assertResponseContains('Build one court at a time');
        $this->assertResponseContains('data-controller="court-agenda-board"');
        $this->assertResponseContains('Back to Gathering');
        $this->assertResponseContains(
            '/gatherings/view/' . $gathering->public_id . '?tab=gathering-bestowals',
        );
        $this->assertResponseContains('Printer Ready');
        $this->assertResponseContains('Remove from Agenda');
        $this->assertResponseNotContains('No linked scheduled activity');
    }

    /**
     * @return void
     */
    public function testGatheringAgendaShowsCourtLanesFromScheduleWithoutImport(): void
    {
        $gathering = $this->getTableLocator()->get('Gatherings')
            ->find()
            ->select(['id', 'start_date'])
            ->firstOrFail();
        $award = $this->getTableLocator()->get('Awards.Awards')
            ->find()
            ->select(['id'])
            ->firstOrFail();
        $this->createScheduledActivityForAward(
            (int)$gathering->id,
            (int)$award->id,
            $gathering->start_date,
        );

        $this->get('/awards/court-agendas/gathering/' . $gathering->id);

        $this->assertResponseOk();
        $this->assertResponseContains('Court Session');
        $this->assertResponseNotContains('No court activities are available yet.');
    }

    public function testAgendaExplainsAttachedAwardActivityNeedsSchedulingAndClearsWarningAfterScheduling(): void
    {
        $gathering = $this->createGatheringWithoutActivities();
        $award = $this->getTableLocator()->get('Awards.Awards')->find()->firstOrFail();
        $activities = $this->getTableLocator()->get('GatheringActivities');
        $activity = $activities->saveOrFail($activities->newEntity(['name' => 'Unscheduled Presentation <Oak>']));
        $links = $this->getTableLocator()->get('Awards.AwardGatheringActivities');
        $links->saveOrFail($links->newEntity([
            'award_id' => $award->id, 'gathering_activity_id' => $activity->id,
        ]));
        $attached = $this->getTableLocator()->get('GatheringsGatheringActivities');
        $attached->saveOrFail($attached->newEntity([
            'gathering_id' => $gathering->id, 'gathering_activity_id' => $activity->id,
        ]));

        $this->get('/awards/court-agendas/gathering/' . $gathering->id);
        $this->assertResponseOk();
        $this->assertSame([(int)$activity->id => $activity->name], $this->viewVariable('unscheduledAwardActivities'));
        $this->assertResponseContains('Award-giving activities need scheduling');
        $this->assertResponseContains(h($activity->name));
        $this->assertResponseContains('Adding an activity under Activities does not schedule it.');
        $this->assertResponseContains('/gatherings/view/' . $gathering->public_id . '?tab=schedule');
        $this->assertResponseNotContains($activity->name);

        $this->createScheduledActivityForAward(
            (int)$gathering->id,
            (int)$award->id,
            $gathering->start_date,
            (int)$activity->id,
        );
        $this->get('/awards/court-agendas/gathering/' . $gathering->id);
        $this->assertResponseOk();
        $this->assertSame([], $this->viewVariable('unscheduledAwardActivities'));
        $this->assertResponseNotContains('Award-giving activities need scheduling');
        $this->assertResponseContains('Court Session');
    }

    public function testAgendaWithoutEligibleActivityExplainsAwardCatalogAndLinksToActivities(): void
    {
        $gathering = $this->createGatheringWithoutActivities();
        $this->get('/awards/court-agendas/gathering/' . $gathering->id);
        $this->assertResponseOk();
        $this->assertResponseContains('No court activities are available yet.');
        $this->assertResponseContains('check its award eligibility in the awards catalog');
        $this->assertResponseContains('/gatherings/view/' . $gathering->public_id . '?tab=activities');
    }

    /**
     * @return void
     */
    public function testScopedCourtManagerCanManageAgendaHostedByAnotherBranch(): void
    {
        $gathering = $this->getTableLocator()->get('Gatherings')
            ->find()
            ->where(['branch_id IS NOT' => null, 'branch_id !=' => self::KINGDOM_BRANCH_ID])
            ->firstOrFail();
        $award = $this->getTableLocator()->get('Awards.Awards')
            ->find()
            ->where(['branch_id' => self::KINGDOM_BRANCH_ID])
            ->firstOrFail();
        $this->createBestowalForGathering((int)$gathering->id, (int)$award->id);
        $this->grantCourtAgendaManagement(
            self::TEST_MEMBER_AGATHA_ID,
            self::KINGDOM_BRANCH_ID,
        );
        $this->authenticateAsMember(self::TEST_MEMBER_AGATHA_ID);

        $this->get('/awards/court-agendas/gathering/' . $gathering->id);

        $this->assertResponseOk();
        $this->assertResponseContains('draggable="true"');
        $this->assertResponseContains('Refresh Scheduled Bestowals');
        $this->assertResponseNotContains('Noble court secret.');
    }

    /** @return void */
    public function testScopedCourtManagerCanPrepareScheduledAgendaBeforeBestowalsExist(): void
    {
        $gatherings = $this->getTableLocator()->get('Gatherings');
        $source = $gatherings->find()->where(['branch_id !=' => self::KINGDOM_BRANCH_ID])->firstOrFail();
        $gathering = $gatherings->saveOrFail($gatherings->newEntity([
            'name' => 'Empty award-giving agenda',
            'created_by' => self::ADMIN_MEMBER_ID,
            'branch_id' => $source->branch_id,
            'gathering_type_id' => $source->gathering_type_id,
            'start_date' => $source->start_date,
            'end_date' => $source->end_date,
            'location' => 'Test Hall',
        ]));
        $award = $this->getTableLocator()->get('Awards.Awards')->find()
            ->where(['branch_id' => self::KINGDOM_BRANCH_ID])->firstOrFail();
        $activities = $this->getTableLocator()->get('GatheringActivities');
        $activity = $activities->saveOrFail($activities->newEntity(['name' => 'Agenda Access Test Presentation']));
        $links = $this->getTableLocator()->get('Awards.AwardGatheringActivities');
        $links->saveOrFail($links->newEntity([
            'award_id' => $award->id,
            'gathering_activity_id' => $activity->id,
        ]));
        $this->getTableLocator()->get('MemberRoles')->deleteAll(['member_id' => self::TEST_MEMBER_AGATHA_ID]);
        $this->grantCourtAgendaManagement(self::TEST_MEMBER_AGATHA_ID, self::KINGDOM_BRANCH_ID);
        $this->authenticateAsMember(self::TEST_MEMBER_AGATHA_ID);

        $testUser = $this->getTableLocator()->get('Members')->get(self::TEST_MEMBER_AGATHA_ID);
        $this->assertFalse($testUser->isSuperUser());
        $testPolicy = $testUser->getPolicies()['Awards\\Policy\\CourtAgendaPolicy']['canGathering'];
        $this->assertSame(Permission::SCOPE_BRANCH_ONLY, $testPolicy->scoping_rule);
        $this->assertSame([self::KINGDOM_BRANCH_ID], $testPolicy->branch_ids);
        $this->get('/awards/court-agendas/gathering/' . $gathering->id);
        $this->assertAuthenticatedAs(self::TEST_MEMBER_AGATHA_ID);
        $this->assertRedirectContains('/pages/unauthorized');
        $this->assertFalse($this->getTableLocator()->get('Awards.CourtAgendas')->exists([
            'gathering_id' => $gathering->id,
        ]));

        $this->createScheduledActivityForAward(
            (int)$gathering->id,
            (int)$award->id,
            $gathering->start_date,
            (int)$activity->id,
        );
        $this->get('/awards/court-agendas/gathering/' . $gathering->id);
        $this->assertResponseOk();
        $this->assertResponseContains('Refresh Scheduled Bestowals');
        $this->assertResponseContains('Court Session');
        $agenda = $this->getTableLocator()->get('Awards.CourtAgendas')->find()
            ->where(['gathering_id' => $gathering->id])->firstOrFail();
        $this->get('/awards/court-agendas/print-agenda/' . $agenda->id);
        $this->assertResponseOk();

        // Keeping a scheduled session must not grant access once it supports only out-of-scope awards.
        $links->deleteAll(['gathering_activity_id' => $activity->id]);
        $otherAward = $this->getTableLocator()->get('Awards.Awards')->find()
            ->where(['branch_id !=' => self::KINGDOM_BRANCH_ID])->firstOrFail();
        $links->saveOrFail($links->newEntity([
            'award_id' => $otherAward->id,
            'gathering_activity_id' => $activity->id,
        ]));
        $this->get('/awards/court-agendas/gathering/' . $gathering->id);
        $this->assertRedirectContains('/pages/unauthorized');
        $this->get('/awards/court-agendas/print-agenda/' . $agenda->id);
        $this->assertRedirectContains('/pages/unauthorized');
    }

    /**
     * @return void
     */
    public function testPrintAgendaRendersPrinterReadyFormat(): void
    {
        $gathering = $this->getTableLocator()->get('Gatherings')
            ->find()
            ->select(['id'])
            ->firstOrFail();
        $this->createBestowalForGathering((int)$gathering->id);
        $agenda = $this->importBestowalsForGathering((int)$gathering->id);

        $this->get('/awards/court-agendas/print-agenda/' . $agenda->id);

        $this->assertResponseOk();
        $this->assertResponseContains('<table>');
        $this->assertResponseContains('Projected court runtime');
        $this->assertResponseContains('Print Agenda');
        $this->assertResponseContains('Call in:');
        $this->assertResponseContains('With Notice');
        $this->assertResponseContains('Court availability:');
        $this->assertResponseContains('Evening');
        $this->assertResponseContains('Notify:');
        $this->assertResponseContains('Agenda Contact');
    }

    /** @return void */
    public function testPrintIncludesNewlyScheduledAwardGivingSessionsAndCurrentScheduleDetails(): void
    {
        $gathering = $this->getTableLocator()->get('Gatherings')->find()->firstOrFail();
        $award = $this->getTableLocator()->get('Awards.Awards')->find()->firstOrFail();
        $this->createBestowalForGathering((int)$gathering->id, (int)$award->id);
        $agenda = $this->importBestowalsForGathering((int)$gathering->id);
        $session = $this->createScheduledActivityForAward(
            (int)$gathering->id,
            (int)$award->id,
            $gathering->start_date,
        );
        $session->display_title = 'New Prize Presentation';
        $this->getTableLocator()->get('GatheringScheduledActivities')->saveOrFail($session);

        $this->get('/awards/court-agendas/print-agenda/' . $agenda->id);

        $this->assertResponseOk();
        $this->assertResponseContains('New Prize Presentation');
        $localStart = $session->start_datetime->setTimezone(new DateTimeZone(TimezoneHelper::getGatheringTimezone($gathering)));
        $this->assertResponseContains('New Prize Presentation - ' . $localStart->format('D g:i A'));

        $session->display_title = 'Updated Prize Presentation';
        $session->start_datetime = $session->start_datetime->addMinutes(15);
        $this->getTableLocator()->get('GatheringScheduledActivities')->saveOrFail($session);

        $this->get('/awards/court-agendas/print-agenda/' . $agenda->id);

        $this->assertResponseOk();
        $localStart = $session->start_datetime->setTimezone(new DateTimeZone(TimezoneHelper::getGatheringTimezone($gathering)));
        $this->assertResponseContains('Updated Prize Presentation - ' . $localStart->format('D g:i A'));
    }

    public function testCourtManagerPrintAgendaShowsHeraldNotesButHidesCrownFields(): void
    {
        $gathering = $this->getTableLocator()->get('Gatherings')
            ->find()
            ->select(['id'])
            ->firstOrFail();
        $award = $this->getTableLocator()->get('Awards.Awards')
            ->find()
            ->select(['id'])
            ->where(['branch_id' => self::KINGDOM_BRANCH_ID])
            ->firstOrFail();
        $this->createBestowalForGathering((int)$gathering->id, (int)$award->id);
        $agenda = $this->importBestowalsForGathering((int)$gathering->id);
        $this->grantCourtAgendaManagement(
            self::TEST_MEMBER_AGATHA_ID,
            self::KINGDOM_BRANCH_ID,
        );
        $this->authenticateAsMember(self::TEST_MEMBER_AGATHA_ID);

        $this->get('/awards/court-agendas/print-agenda/' . $agenda->id);

        $this->assertResponseOk();
        $this->assertResponseContains('Speak clearly.');
        $this->assertResponseNotContains('Noble court secret.');
    }

    /**
     * @return void
     */
    public function testRemoveItemEndpointClearsBestowalCourtAssignment(): void
    {
        $gathering = $this->getTableLocator()->get('Gatherings')
            ->find()
            ->select(['id'])
            ->firstOrFail();
        $this->createBestowalForGathering((int)$gathering->id);
        $agenda = $this->importBestowalsForGathering((int)$gathering->id);
        $item = $this->getTableLocator()->get('Awards.CourtAgendaItems')
            ->find()
            ->contain(['CourtAgendaSegments'])
            ->where(['CourtAgendaSegments.court_agenda_id' => $agenda->id])
            ->where(['bestowal_id IS NOT' => null])
            ->firstOrFail();

        $this->post('/awards/court-agendas/remove-item', [
            'item_id' => (int)$item->id,
            'return_segment_id' => (int)$item->court_agenda_segment_id,
        ]);

        $this->assertRedirectContains('/awards/court-agendas/gathering/' . $gathering->id);
        $this->assertFalse($this->getTableLocator()->get('Awards.CourtAgendaItems')->exists([
            'id' => (int)$item->id,
        ]));
        $bestowal = $this->getTableLocator()->get('Awards.Bestowals')->get((int)$item->bestowal_id);
        $this->assertNull($bestowal->gathering_scheduled_activity_id);
        $this->assertFalse((bool)$bestowal->roaming_court);
    }

    private function createGatheringWithoutActivities(): object
    {
        $gatherings = $this->getTableLocator()->get('Gatherings');
        $source = $gatherings->find()->firstOrFail();
        $gathering = $gatherings->saveOrFail($gatherings->newEntity([
            'name' => 'Agenda setup guidance fixture', 'created_by' => self::ADMIN_MEMBER_ID,
            'branch_id' => $source->branch_id, 'gathering_type_id' => $source->gathering_type_id,
            'start_date' => $source->start_date, 'end_date' => $source->end_date, 'location' => 'Test Hall',
        ]));
        $this->getTableLocator()->get('GatheringsGatheringActivities')->deleteAll(['gathering_id' => $gathering->id]);

        return $gathering;
    }

    /**
     * @param int $gatheringId Gathering ID.
     * @return \Awards\Model\Entity\CourtAgenda
     */
    private function importBestowalsForGathering(int $gatheringId)
    {
        $this->get('/awards/court-agendas/gathering/' . $gatheringId);
        $this->assertResponseOk();
        $agenda = $this->getTableLocator()->get('Awards.CourtAgendas')
            ->find()
            ->where(['gathering_id' => $gatheringId])
            ->firstOrFail();

        $this->post('/awards/court-agendas/import/' . $agenda->id);
        $this->assertRedirect(['controller' => 'CourtAgendas', 'action' => 'gathering', $gatheringId]);

        return $agenda;
    }

    /**
     * @param int $gatheringId Gathering ID.
     * @return void
     */
    private function createBestowalForGathering(int $gatheringId, ?int $awardId = null): void
    {
        $gathering = $this->getTableLocator()->get('Gatherings')
            ->find()
            ->select(['id', 'start_date'])
            ->where(['id' => $gatheringId])
            ->firstOrFail();
        $awardQuery = $this->getTableLocator()->get('Awards.Awards')
            ->find()
            ->select(['id']);
        if ($awardId !== null) {
            $awardQuery->where(['id' => $awardId]);
        }
        $award = $awardQuery->firstOrFail();
        $scheduledActivity = $this->createScheduledActivityForAward(
            $gatheringId,
            (int)$award->id,
            $gathering->start_date,
        );

        $bestowals = $this->getTableLocator()->get('Awards.Bestowals');
        $bestowals->saveOrFail($bestowals->newEntity([
            'member_id' => self::ADMIN_MEMBER_ID,
            'award_id' => $award->id,
            'gathering_id' => $gatheringId,
            'gathering_scheduled_activity_id' => $scheduledActivity->id,
            'state' => 'Court Scheduled',
            'status' => 'Scheduling',
            'source' => Bestowal::SOURCE_AD_HOC,
            'stack_rank' => 10,
            'herald_notes' => 'Speak clearly.',
            'noble_notes' => 'Noble court secret.',
            'call_into_court' => 'With Notice',
            'court_availability' => 'Evening',
            'person_to_notify' => 'Agenda Contact',
        ]));
    }

    /**
     * @param int $memberId Member ID.
     * @param int $branchId Permission assignment branch ID.
     * @return void
     */
    private function grantCourtAgendaManagement(int $memberId, int $branchId): void
    {
        $permissions = $this->getTableLocator()->get('Permissions');
        $permission = $permissions->saveOrFail($permissions->newEntity([
            'name' => 'Test Court Agenda Management ' . uniqid(),
            'require_active_membership' => false,
            'require_active_background_check' => false,
            'require_min_age' => 0,
            'is_system' => false,
            'is_super_user' => false,
            'requires_warrant' => false,
            'scoping_rule' => Permission::SCOPE_BRANCH_ONLY,
        ]));

        $permissionPolicies = $this->getTableLocator()->get('PermissionPolicies');
        foreach (['canGathering', 'canEdit', 'canPrintAgenda'] as $policyMethod) {
            $permissionPolicies->saveOrFail($permissionPolicies->newEntity([
                'permission_id' => (int)$permission->id,
                'policy_class' => 'Awards\\Policy\\CourtAgendaPolicy',
                'policy_method' => $policyMethod,
            ]));
        }
        $permissionPolicies->saveOrFail($permissionPolicies->newEntity([
            'permission_id' => (int)$permission->id,
            'policy_class' => 'Awards\\Policy\\BestowalPolicy',
            'policy_method' => 'canAccessHeraldNotes',
        ]));

        $roles = $this->getTableLocator()->get('Roles');
        $role = $roles->saveOrFail($roles->newEntity([
            'name' => 'Test Court Agenda Manager ' . uniqid(),
        ]));
        $connection = $roles->getConnection();
        $connection->execute(
            'INSERT INTO roles_permissions (role_id, permission_id, created, created_by)
             VALUES (?, ?, NOW(), ?)',
            [(int)$role->id, (int)$permission->id, self::ADMIN_MEMBER_ID],
        );
        $connection->execute(
            'INSERT INTO member_roles
             (member_id, role_id, branch_id, start_on, expires_on, approver_id, entity_type,
              created, modified, created_by, modified_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), ?, ?)',
            [
                $memberId,
                (int)$role->id,
                $branchId,
                '2020-01-01 00:00:00',
                '2100-01-01',
                self::ADMIN_MEMBER_ID,
                'Direct Grant',
                self::ADMIN_MEMBER_ID,
                self::ADMIN_MEMBER_ID,
            ],
        );
        Cache::clearGroup('security');
    }

    /**
     * @param int $gatheringId Gathering ID.
     * @param int $awardId Award ID.
     * @param \Cake\I18n\DateTime $startDate Gathering start date.
     * @param int|null $activityId Optional activity type.
     * @return \App\Model\Entity\GatheringScheduledActivity
     */
    private function createScheduledActivityForAward(
        int $gatheringId,
        int $awardId,
        DateTime $startDate,
        ?int $activityId = null,
    ): GatheringScheduledActivity {
        $activity = $this->getTableLocator()->get('GatheringActivities')
            ->find()
            ->where($activityId === null ? [] : ['id' => $activityId])
            ->firstOrFail();
        $awardActivities = $this->getTableLocator()->get('Awards.AwardGatheringActivities');
        if (!$awardActivities->exists(['award_id' => $awardId, 'gathering_activity_id' => $activity->id])) {
            $awardActivities->saveOrFail($awardActivities->newEntity([
                'award_id' => $awardId,
                'gathering_activity_id' => $activity->id,
            ]));
        }

        $scheduledActivities = $this->getTableLocator()->get('GatheringScheduledActivities');
        $scheduledActivity = $scheduledActivities->newEntity([
            'gathering_id' => $gatheringId,
            'gathering_activity_id' => $activity->id,
            'start_datetime' => (clone $startDate)->modify('+1 hour'),
            'end_datetime' => (clone $startDate)->modify('+2 hours'),
            'has_end_time' => true,
            'display_title' => 'Court Session',
            'description' => 'Court Session description.',
            'pre_register' => false,
            'is_other' => false,
            'created_by' => self::ADMIN_MEMBER_ID,
        ]);

        return $scheduledActivities->saveOrFail($scheduledActivity);
    }
}
