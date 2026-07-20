<?php
declare(strict_types=1);

namespace Awards\Test\TestCase\Controller;

use App\Model\Entity\GatheringScheduledActivity;
use App\Model\Entity\Permission;
use App\Test\TestCase\Support\HttpIntegrationTestCase;
use Awards\Model\Entity\Bestowal;
use Cake\Cache\Cache;
use Cake\I18n\DateTime;

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
    }

    public function testCourtManagerPrintAgendaShowsHeraldNotesButHidesCrownFields(): void
    {
        $gathering = $this->getTableLocator()->get('Gatherings')
            ->find()
            ->select(['id'])
            ->firstOrFail();
        $this->createBestowalForGathering((int)$gathering->id);
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
     * @return \App\Model\Entity\GatheringScheduledActivity
     */
    private function createScheduledActivityForAward(
        int $gatheringId,
        int $awardId,
        DateTime $startDate,
    ): GatheringScheduledActivity {
        $activity = $this->getTableLocator()->get('GatheringActivities')
            ->find()
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
