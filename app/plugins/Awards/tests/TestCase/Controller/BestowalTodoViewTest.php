<?php
declare(strict_types=1);

namespace Awards\Test\TestCase\Controller;

use App\Model\Entity\ActionItem;
use App\Test\TestCase\Support\HttpIntegrationTestCase;
use Awards\Model\Entity\Bestowal;
use Awards\Model\Entity\BestowalTodoTemplateItem;
use Cake\I18n\DateTime;
use Cake\ORM\TableRegistry;

/**
 * Integration tests for the Bestowal view To-Dos checklist tab.
 */
class BestowalTodoViewTest extends HttpIntegrationTestCase
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
     * Persist a bestowal with the fields required by current validation.
     *
     * @return \Awards\Model\Entity\Bestowal
     */
    private function makeBestowal(): Bestowal
    {
        $award = TableRegistry::getTableLocator()->get('Awards.Awards')
            ->find()->select(['id'])->firstOrFail();
        $bestowals = TableRegistry::getTableLocator()->get('Awards.Bestowals');
        $bestowal = $bestowals->newEntity([
            'member_id' => self::ADMIN_MEMBER_ID,
            'member_sca_name' => 'Test Recipient',
            'award_id' => $award->id,
            'lifecycle_status' => Bestowal::LIFECYCLE_OPEN,
            'source' => Bestowal::SOURCE_RECOMMENDATION,
            'stack_rank' => 0,
        ]);

        return $bestowals->saveOrFail($bestowal);
    }

    /**
     * Persist an action item owned by the given bestowal.
     *
     * @param int $bestowalId Owning bestowal id
     * @param array<string, mixed> $overrides Field overrides
     * @return \App\Model\Entity\ActionItem
     */
    private function makeTodo(int $bestowalId, array $overrides = []): ActionItem
    {
        $table = TableRegistry::getTableLocator()->get('ActionItems');
        $data = array_merge([
            'entity_type' => Bestowal::ACTION_ITEM_ENTITY_TYPE,
            'entity_id' => $bestowalId,
            'title' => 'Scroll finished',
            'description' => 'Calligraphy complete',
            'assignee_type' => ActionItem::ASSIGNEE_TYPE_MEMBER,
            'assignee_config' => ['member_id' => self::ADMIN_MEMBER_ID],
            'branch_id' => self::KINGDOM_BRANCH_ID,
            'status' => ActionItem::STATUS_OPEN,
            'is_gating' => true,
            'sort_order' => 1,
        ], $overrides);

        return $table->saveOrFail($table->newEntity($data));
    }

    /**
     * The checklist renders open gating items with a Complete control and the
     * Mark Given action stays disabled while a required check is open.
     *
     * @return void
     */
    public function testViewRendersOpenTodoChecklist(): void
    {
        $bestowal = $this->makeBestowal();
        $this->makeTodo((int)$bestowal->id);

        $this->get('/awards/bestowals/view/' . $bestowal->id);

        $this->assertResponseOk();
        $this->assertResponseContains('To-Dos');
        $this->assertResponseContains('Scroll finished');
        $this->assertResponseContains('Required');
        $this->assertResponseContains('Complete all required checks');
        $this->assertResponseContains('bi-hourglass-split');
        $this->assertResponseContains('Open task:');
        $this->assertResponseNotContains('bi bi-circle me-1');
    }

    public function testViewRendersGatheringRequirementForUnbackfilledEventScheduledTodo(): void
    {
        $bestowal = $this->makeBestowal();
        $this->makeTodo((int)$bestowal->id, [
            'title' => 'Event Scheduled',
            'source_ref' => BestowalTodoTemplateItem::ITEM_KEY_EVENT_SCHEDULED,
        ]);

        $this->get('/awards/bestowals/view/' . $bestowal->id);

        $this->assertResponseOk();
        $this->assertResponseContains('Gathering required');
        $this->assertResponseContains('Assign Gathering and Complete');
    }

    public function testViewBlocksAgendaTodoUntilEventScheduledIsComplete(): void
    {
        $bestowal = $this->makeBestowal();
        $this->makeTodo((int)$bestowal->id, [
            'title' => 'Event Scheduled',
            'source_ref' => BestowalTodoTemplateItem::ITEM_KEY_EVENT_SCHEDULED,
            'sort_order' => 10,
        ]);
        $this->makeTodo((int)$bestowal->id, [
            'title' => 'Added to Agenda',
            'source_ref' => BestowalTodoTemplateItem::ITEM_KEY_ADDED_TO_AGENDA,
            'sort_order' => 20,
        ]);

        $this->get('/awards/bestowals/view/' . $bestowal->id);

        $this->assertResponseOk();
        $this->assertResponseContains('Waiting on Event Scheduled');
        $this->assertResponseContains('Complete Event Scheduled before Added to Agenda can be completed.');
        $this->assertResponseNotContains('aria-label="Mark complete: Added to Agenda"');
    }

    public function testViewRendersCourtRequirementForAddedToAgendaTodo(): void
    {
        $bestowal = $this->makeBestowal();
        $schedule = $this->makeScheduledCourtForAward((int)$bestowal->award_id);
        $bestowal->gathering_id = $schedule['gathering']->id;
        TableRegistry::getTableLocator()->get('Awards.Bestowals')->saveOrFail($bestowal);
        $this->makeTodo((int)$bestowal->id, [
            'title' => 'Event Scheduled',
            'source_ref' => BestowalTodoTemplateItem::ITEM_KEY_EVENT_SCHEDULED,
            'status' => ActionItem::STATUS_COMPLETED,
            'sort_order' => 10,
        ]);
        $this->makeTodo((int)$bestowal->id, [
            'title' => 'Added to Agenda',
            'source_ref' => BestowalTodoTemplateItem::ITEM_KEY_ADDED_TO_AGENDA,
            'sort_order' => 20,
        ]);

        $this->get('/awards/bestowals/view/' . $bestowal->id);

        $this->assertResponseOk();
        $this->assertResponseContains('Court assignment required');
        $this->assertResponseContains('Assign Court and Complete');
        $this->assertResponseContains('Roaming Court');
        $this->assertResponseContains('Evening Court');
    }

    public function testCompletingAddedToAgendaTodoCanAssignRoamingCourt(): void
    {
        $bestowal = $this->makeBestowal();
        $schedule = $this->makeScheduledCourtForAward((int)$bestowal->award_id);
        $bestowal->gathering_id = $schedule['gathering']->id;
        TableRegistry::getTableLocator()->get('Awards.Bestowals')->saveOrFail($bestowal);
        $this->makeTodo((int)$bestowal->id, [
            'title' => 'Event Scheduled',
            'source_ref' => BestowalTodoTemplateItem::ITEM_KEY_EVENT_SCHEDULED,
            'status' => ActionItem::STATUS_COMPLETED,
            'sort_order' => 10,
        ]);
        $agendaTodo = $this->makeTodo((int)$bestowal->id, [
            'title' => 'Added to Agenda',
            'source_ref' => BestowalTodoTemplateItem::ITEM_KEY_ADDED_TO_AGENDA,
            'sort_order' => 20,
        ]);

        $this->post('/action-items/complete/' . $agendaTodo->id, [
            'id' => (string)$agendaTodo->id,
            'gathering_scheduled_activity_id' => 'roaming',
        ]);

        $this->assertResponseCode(302);
        $reloadedBestowal = TableRegistry::getTableLocator()->get('Awards.Bestowals')->get($bestowal->id);
        $this->assertTrue((bool)$reloadedBestowal->roaming_court);
        $this->assertNull($reloadedBestowal->gathering_scheduled_activity_id);
        $reloadedTodo = TableRegistry::getTableLocator()->get('ActionItems')->get($agendaTodo->id);
        $this->assertTrue($reloadedTodo->isCompleted());
    }

    /**
     * When every gating item is complete, the Mark Given action is offered.
     *
     * @return void
     */
    public function testViewOffersMarkGivenWhenGatingComplete(): void
    {
        $bestowal = $this->makeBestowal();
        $this->makeTodo((int)$bestowal->id, [
            'status' => ActionItem::STATUS_COMPLETED,
        ]);

        $this->get('/awards/bestowals/view/' . $bestowal->id);

        $this->assertResponseOk();
        $this->assertResponseContains('Mark Given');
        $this->assertResponseContains('All required checks are complete.');
        $this->assertResponseContains('bi-check-square-fill');
        $this->assertResponseContains('Completed task:');
    }

    /**
     * @param int $awardId Award primary key.
     * @return array{gathering: object, scheduledActivity: object}
     */
    private function makeScheduledCourtForAward(int $awardId): array
    {
        $suffix = uniqid('', true);
        $gatheringActivities = TableRegistry::getTableLocator()->get('GatheringActivities');
        $awardGatheringActivities = TableRegistry::getTableLocator()->get('Awards.AwardGatheringActivities');
        $gatherings = TableRegistry::getTableLocator()->get('Gatherings');
        $scheduledActivities = TableRegistry::getTableLocator()->get('GatheringScheduledActivities');
        $gatheringActivityLinks = TableRegistry::getTableLocator()->get('GatheringsGatheringActivities');

        $activity = $gatheringActivities->saveOrFail($gatheringActivities->newEntity([
            'name' => 'Checklist Court Activity ' . $suffix,
        ]));
        $awardGatheringActivities->saveOrFail($awardGatheringActivities->newEntity([
            'award_id' => $awardId,
            'gathering_activity_id' => $activity->id,
        ]));

        $gatheringType = TableRegistry::getTableLocator()->get('GatheringTypes')
            ->find()
            ->select(['id'])
            ->firstOrFail();
        $gathering = $gatherings->saveOrFail($gatherings->newEntity([
            'branch_id' => self::KINGDOM_BRANCH_ID,
            'gathering_type_id' => $gatheringType->id,
            'name' => 'Checklist Court Gathering ' . $suffix,
            'start_date' => DateTime::now()->addDays(30),
            'end_date' => DateTime::now()->addDays(31),
            'created_by' => self::ADMIN_MEMBER_ID,
        ]));
        $gatheringActivityLinks->saveOrFail($gatheringActivityLinks->newEntity([
            'gathering_id' => $gathering->id,
            'gathering_activity_id' => $activity->id,
            'sort_order' => 1,
            'not_removable' => false,
        ]));
        $scheduledActivity = $scheduledActivities->saveOrFail($scheduledActivities->newEntity([
            'gathering_id' => $gathering->id,
            'gathering_activity_id' => $activity->id,
            'start_datetime' => DateTime::now()->addDays(30)->addHours(2),
            'has_end_time' => false,
            'display_title' => 'Evening Court',
            'description' => 'Checklist court session.',
            'pre_register' => false,
            'is_other' => false,
            'created_by' => self::ADMIN_MEMBER_ID,
        ]));

        return compact('gathering', 'scheduledActivity');
    }
}
