<?php
declare(strict_types=1);

namespace Awards\Test\TestCase\Controller;

use App\Model\Entity\ActionItem;
use App\Model\Entity\Member;
use App\Test\TestCase\Support\HttpIntegrationTestCase;
use Awards\Model\Entity\Bestowal;
use Awards\Model\Entity\BestowalTodoTemplateItem;
use Cake\I18n\DateTime;
use Cake\ORM\TableRegistry;

/**
 * Integration tests for the bestowal grid quick To-Dos modal frame and the
 * bulk "Complete Check" action.
 */
class BestowalBulkTodoTest extends HttpIntegrationTestCase
{
    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->enableRetainFlashMessages();
        $this->authenticateAsSuperUser();
    }

    /**
     * @return \Awards\Model\Entity\Bestowal
     */
    private function makeBestowal(): Bestowal
    {
        $award = TableRegistry::getTableLocator()->get('Awards.Awards')
            ->find()->select(['id'])->firstOrFail();
        $bestowals = TableRegistry::getTableLocator()->get('Awards.Bestowals');
        $bestowal = $bestowals->newEntity([
            'member_id' => self::ADMIN_MEMBER_ID,
            'member_sca_name' => 'Bulk Recipient',
            'award_id' => $award->id,
            'lifecycle_status' => Bestowal::LIFECYCLE_OPEN,
            'source' => Bestowal::SOURCE_AD_HOC,
            'stack_rank' => 0,
        ]);

        return $bestowals->saveOrFail($bestowal);
    }

    /**
     * @param int $bestowalId Owning bestowal id
     * @param int $assigneeMemberId Member allowed to complete the check
     * @param string $sourceRef Template item key
     * @param array<string, mixed> $overrides Field overrides
     * @return \App\Model\Entity\ActionItem
     */
    private function makeTodo(
        int $bestowalId,
        int $assigneeMemberId,
        string $sourceRef,
        array $overrides = [],
    ): ActionItem {
        $table = TableRegistry::getTableLocator()->get('ActionItems');

        return $table->saveOrFail($table->newEntity(array_merge([
            'entity_type' => Bestowal::ACTION_ITEM_ENTITY_TYPE,
            'entity_id' => $bestowalId,
            'title' => 'Has scroll',
            'assignee_type' => ActionItem::ASSIGNEE_TYPE_MEMBER,
            'assignee_config' => ['member_id' => $assigneeMemberId],
            'branch_id' => self::KINGDOM_BRANCH_ID,
            'status' => ActionItem::STATUS_OPEN,
            'is_gating' => true,
            'sort_order' => 1,
            'source_ref' => $sourceRef,
        ], $overrides)));
    }

    /**
     * @param string $scaName Display name
     * @return \App\Model\Entity\Member
     */
    private function makeMember(string $scaName): Member
    {
        $members = TableRegistry::getTableLocator()->get('Members');

        return $members->saveOrFail($members->newEntity([
            'password' => 'VeryStrongPassword123!',
            'sca_name' => $scaName,
            'first_name' => 'Bulk',
            'last_name' => 'Doer',
            'street_address' => '',
            'city' => '',
            'state' => '',
            'zip' => '',
            'phone_number' => '',
            'email_address' => 'bulk-' . uniqid() . '@example.test',
            'birth_month' => 1,
            'birth_year' => 1990,
        ]));
    }

    /**
     * The quick To-Dos modal frame renders the bestowal's checklist.
     *
     * @return void
     */
    public function testBestowalTodosRendersChecklist(): void
    {
        $bestowal = $this->makeBestowal();
        $this->makeTodo((int)$bestowal->id, self::ADMIN_MEMBER_ID, 'has_scroll');

        $this->get('/awards/bestowals/bestowal-todos/' . $bestowal->id);

        $this->assertResponseOk();
        $this->assertResponseContains('Has scroll');
        $this->assertResponseContains('bestowalTodosQuick');
        $this->assertResponseContains('Complete');
    }

    /**
     * Bulk complete flips the eligible bestowal's check and leaves the
     * ineligible one open, reporting the outcome.
     *
     * @return void
     */
    public function testBulkCompleteCompletesEligibleAndSkipsIneligible(): void
    {
        $eligible = $this->makeBestowal();
        $ineligible = $this->makeBestowal();
        $otherMember = $this->makeMember('Bulk Other ' . uniqid());

        $eligibleTodo = $this->makeTodo((int)$eligible->id, self::ADMIN_MEMBER_ID, 'has_scroll');
        $ineligibleTodo = $this->makeTodo((int)$ineligible->id, (int)$otherMember->id, 'has_scroll');

        $this->post('/awards/bestowals/bulk-complete-todo', [
            'bestowal_ids' => $eligible->id . ',' . $ineligible->id,
            'check_key' => 'has_scroll',
        ]);

        $this->assertResponseCode(302);

        $table = TableRegistry::getTableLocator()->get('ActionItems');
        $this->assertTrue(
            $table->get($eligibleTodo->id)->isCompleted(),
            'Eligible bestowal check should be completed',
        );
        $this->assertTrue(
            $table->get($ineligibleTodo->id)->isOpen(),
            'Ineligible bestowal check should remain open',
        );
    }

    /**
     * Bulk complete with no matching open check reports nothing was completed.
     *
     * @return void
     */
    public function testBulkCompleteWithoutMatchingCheckReportsNone(): void
    {
        $bestowal = $this->makeBestowal();
        $this->makeTodo((int)$bestowal->id, self::ADMIN_MEMBER_ID, 'has_scroll');

        $this->post('/awards/bestowals/bulk-complete-todo', [
            'bestowal_ids' => (string)$bestowal->id,
            'check_key' => 'regalia_allotted',
        ]);

        $this->assertResponseCode(302);
        $this->assertFlashMessage('None of the selected bestowals have that check open.');
    }

    public function testBulkCompleteAssignsGatheringForMatchingRequiredTodo(): void
    {
        $bestowal = $this->makeBestowal();
        $gathering = $this->makeSelectableGatheringForAward((int)$bestowal->award_id);
        $todo = $this->makeTodo((int)$bestowal->id, self::ADMIN_MEMBER_ID, 'event_scheduled', [
            'title' => 'Event Scheduled',
            'completion_config' => [
                'required_fields' => [
                    [
                        'provider' => BestowalTodoTemplateItem::COMPLETION_PROVIDER_BESTOWAL_GATHERING,
                        'field' => BestowalTodoTemplateItem::REQUIRED_FIELD_GATHERING,
                        'conditional_complete_on_assign' => true,
                    ],
                ],
            ],
        ]);

        $this->post('/awards/bestowals/bulk-complete-todo', [
            'bestowal_ids' => (string)$bestowal->id,
            'check_key' => 'event_scheduled',
            'bestowal_gathering_id' => (string)$gathering->id,
        ]);

        $this->assertResponseCode(302);
        $reloadedBestowal = TableRegistry::getTableLocator()->get('Awards.Bestowals')->get($bestowal->id);
        $this->assertSame((int)$gathering->id, (int)$reloadedBestowal->gathering_id);
        $reloadedTodo = TableRegistry::getTableLocator()->get('ActionItems')->get($todo->id);
        $this->assertTrue($reloadedTodo->isCompleted());
    }

    public function testBulkCompleteAssignsGatheringForUnbackfilledEventScheduledTodo(): void
    {
        $bestowal = $this->makeBestowal();
        $gathering = $this->makeSelectableGatheringForAward((int)$bestowal->award_id);
        $todo = $this->makeTodo((int)$bestowal->id, self::ADMIN_MEMBER_ID, 'event_scheduled', [
            'title' => 'Event Scheduled',
        ]);

        $this->post('/awards/bestowals/bulk-complete-todo', [
            'bestowal_ids' => (string)$bestowal->id,
            'check_key' => 'event_scheduled',
            'bestowal_gathering_id' => (string)$gathering->id,
        ]);

        $this->assertResponseCode(302);
        $reloadedBestowal = TableRegistry::getTableLocator()->get('Awards.Bestowals')->get($bestowal->id);
        $this->assertSame((int)$gathering->id, (int)$reloadedBestowal->gathering_id);
        $reloadedTodo = TableRegistry::getTableLocator()->get('ActionItems')->get($todo->id);
        $this->assertTrue($reloadedTodo->isCompleted());
    }

    public function testAssignedEventScheduledTodoUserCanAssignGatheringWithoutSchedulePermission(): void
    {
        $bestowal = $this->makeBestowal();
        $assignee = $this->makeMember('Todo Assignee ' . uniqid());
        $gathering = $this->makeSelectableGatheringForAward((int)$bestowal->award_id);
        $todo = $this->makeTodo((int)$bestowal->id, (int)$assignee->id, 'event_scheduled', [
            'title' => 'Event Scheduled',
        ]);
        $this->authenticateAsMember((int)$assignee->id);

        $this->post('/action-items/complete/' . $todo->id, [
            'id' => (string)$todo->id,
            'bestowal_gathering_id' => (string)$gathering->id,
        ]);

        $this->assertResponseCode(302);
        $reloadedBestowal = TableRegistry::getTableLocator()->get('Awards.Bestowals')->get($bestowal->id);
        $this->assertSame((int)$gathering->id, (int)$reloadedBestowal->gathering_id);
        $reloadedTodo = TableRegistry::getTableLocator()->get('ActionItems')->get($todo->id);
        $this->assertTrue($reloadedTodo->isCompleted());
    }

    public function testBulkCompleteAssignsGatheringEvenWhenConditionalCompleteFlagIsFalse(): void
    {
        $bestowal = $this->makeBestowal();
        $gathering = $this->makeSelectableGatheringForAward((int)$bestowal->award_id);
        $todo = $this->makeTodo((int)$bestowal->id, self::ADMIN_MEMBER_ID, 'event_scheduled', [
            'title' => 'Event Scheduled',
            'completion_config' => [
                'required_fields' => [
                    [
                        'provider' => BestowalTodoTemplateItem::COMPLETION_PROVIDER_BESTOWAL_GATHERING,
                        'field' => BestowalTodoTemplateItem::REQUIRED_FIELD_GATHERING,
                        'conditional_complete_on_assign' => false,
                    ],
                ],
            ],
        ]);

        $this->post('/awards/bestowals/bulk-complete-todo', [
            'bestowal_ids' => (string)$bestowal->id,
            'check_key' => 'event_scheduled',
            'bestowal_gathering_id' => (string)$gathering->id,
        ]);

        $this->assertResponseCode(302);
        $reloadedBestowal = TableRegistry::getTableLocator()->get('Awards.Bestowals')->get($bestowal->id);
        $this->assertSame((int)$gathering->id, (int)$reloadedBestowal->gathering_id);
        $reloadedTodo = TableRegistry::getTableLocator()->get('ActionItems')->get($todo->id);
        $this->assertTrue($reloadedTodo->isCompleted());
    }

    private function makeSelectableGatheringForAward(int $awardId)
    {
        $suffix = uniqid('', true);
        $gatheringActivities = TableRegistry::getTableLocator()->get('GatheringActivities');
        $awardGatheringActivities = TableRegistry::getTableLocator()->get('Awards.AwardGatheringActivities');
        $gatherings = TableRegistry::getTableLocator()->get('Gatherings');
        $gatheringActivityLinks = TableRegistry::getTableLocator()->get('GatheringActivityLinks');

        $activity = $gatheringActivities->saveOrFail($gatheringActivities->newEntity([
            'name' => 'Bulk Gathering Activity ' . $suffix,
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
            'name' => 'Bulk Assign Gathering ' . $suffix,
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

        return $gathering;
    }
}
