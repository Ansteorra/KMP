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
        $this->assertResponseContains('hourglass');
        $this->assertResponseContains('Open task:');
        $this->assertResponseNotContains('<kmp-icon name="circle" class="me-1"');
        $this->assertResponseNotContains('<kmp-icon name="check" class="me-1"');
    }

    public function testRecordDetailsShowTerminalOutcomeAndRecordedCloseDate(): void
    {
        $bestowal = $this->makeBestowal();
        $table = $this->getTableLocator()->get('Awards.Bestowals');
        foreach ([Bestowal::LIFECYCLE_GIVEN, 'Given Not Received', 'Filed with Herald'] as $status) {
            $bestowal->lifecycle_status = $status;
            $bestowal->bestowed_at = new DateTime('2026-10-20 18:00:00');
            $table->saveOrFail($bestowal);

            $this->get('/awards/bestowals/view/' . $bestowal->id);

            $this->assertResponseOk();
            $this->assertResponseContains('<th scope="row">Terminal state</th>');
            $this->assertResponseContains('<td>' . h(ucfirst($status)) . '</td>');
            $this->assertResponseContains('<th scope="row">Close date</th>');
            $this->assertResponseContains('datetime="2026-10-20T18:00:00+00:00"');
            $this->assertResponseNotContains('<th scope="row">Lifecycle Status</th>');
        }

        $bestowal->bestowed_at = null;
        $table->saveOrFail($bestowal);
        $this->get('/awards/bestowals/view/' . $bestowal->id);
        $this->assertResponseContains('Not recorded');

        foreach ([Bestowal::LIFECYCLE_OPEN, Bestowal::LIFECYCLE_CANCELLED] as $status) {
            $bestowal->lifecycle_status = $status;
            $table->saveOrFail($bestowal);
            $this->get('/awards/bestowals/view/' . $bestowal->id);
            $this->assertResponseOk();
            $this->assertResponseContains('<th scope="row">Lifecycle Status</th>');
            $this->assertResponseNotContains('<th scope="row">Terminal state</th>');
            $this->assertResponseNotContains('<th scope="row">Close date</th>');
        }
    }

    /** @return array{\Awards\Model\Entity\Bestowal, \App\Model\Entity\ActionItem} */
    private function completedOutcomeFixture(): array
    {
        $bestowal = $this->makeBestowal();
        $bestowal->lifecycle_status = 'Given Not Received';
        $bestowal->bestowed_at = new DateTime('2026-10-20 18:00:00');
        $this->getTableLocator()->get('Awards.Bestowals')->saveOrFail($bestowal);
        $terminal = $this->makeTodo((int)$bestowal->id, [
            'title' => 'Given', 'is_terminal' => true, 'status' => ActionItem::STATUS_COMPLETED,
            'completed_at' => $bestowal->bestowed_at,
            'completion_config' => [
                'terminal_outcomes' => ['given' => 'Given', 'Given Not Received' => 'Given Not Received',
                    'Filed with Herald' => 'Filed with Herald'],
                'selected_terminal_status' => 'Given Not Received',
            ],
        ]);

        return [$bestowal, $terminal];
    }

    public function testRecipientDeliveryChangesOutcomeWithoutReopeningOrRewritingHistory(): void
    {
        [$bestowal, $terminal] = $this->completedOutcomeFixture();
        $this->makeTodo((int)$bestowal->id, ['status' => ActionItem::STATUS_CANCELLED]);
        $items = $this->getTableLocator()->get('ActionItems');
        $before = $items->find()->where(['entity_type' => Bestowal::ACTION_ITEM_ENTITY_TYPE,
            'entity_id' => $bestowal->id])->orderBy(['id' => 'ASC'])->all()->toArray();
        $logs = $this->getTableLocator()->get('ActionItemLogs');
        $originalLog = $logs->saveOrFail($logs->newEntity([
            'action_item_id' => $terminal->id, 'from_status' => 'open', 'to_status' => 'completed',
            'note' => 'Recipient was absent.', 'created_by' => self::ADMIN_MEMBER_ID,
        ]));
        $originalLog = $logs->get($originalLog->id);
        $url = '/awards/bestowals/change-terminal-state/' . $bestowal->id;
        $data = ['expected_status' => 'Given Not Received', 'terminal_status' => 'given',
            'note' => 'Delivered to the recipient. <script>unsafe</script>'];
        $this->post($url, $data);
        $this->assertRedirect('/awards/bestowals/view/' . $bestowal->id);
        $saved = $this->getTableLocator()->get('Awards.Bestowals')->get($bestowal->id);
        $this->assertSame('given', $saved->lifecycle_status);
        $this->assertEquals($bestowal->bestowed_at, $saved->bestowed_at);
        $this->assertFalse($saved->allowsActionItemMutations());
        $this->assertEquals($before, $items->find()->where(['entity_type' => Bestowal::ACTION_ITEM_ENTITY_TYPE,
            'entity_id' => $bestowal->id])->orderBy(['id' => 'ASC'])->all()->toArray());
        $this->assertEquals($originalLog, $logs->get($originalLog->id));
        $audit = $logs->find()->where(['action_item_id' => $terminal->id, 'id !=' => $originalLog->id])->firstOrFail();
        $this->assertStringContainsString('Terminal state changed from "Given Not Received" to "Given".', $audit->note);
        $this->assertStringContainsString($data['note'], $audit->note);
        $this->assertSame(self::ADMIN_MEMBER_ID, $audit->created_by);
        $this->assertNotNull($audit->created);
        $this->get('/awards/bestowals/view/' . $bestowal->id);
        $this->assertResponseContains('Change terminal state');
        $this->assertResponseContains(h($data['note']));
        $this->assertResponseNotContains('<script>unsafe</script>');
        foreach (['view', 'bestowal-todos'] as $action) {
            $this->get('/awards/bestowals/' . $action . '/' . $bestowal->id);
            $this->assertResponseOk();
            $this->assertResponseContains('Terminal state: Given</span>');
            $this->assertResponseNotContains('Terminal — marks Given Not Received');
            $this->assertResponseContains('Recipient was absent.');
            $this->assertResponseContains(nl2br(h($audit->note)));
        }

        // An older open form cannot overwrite a newer state or add a duplicate audit entry.
        $this->post($url, $data);
        $this->assertResponseCode(302);
        $this->assertSame(2, $logs->find()->where(['action_item_id' => $terminal->id])->count());
    }

    public function testOutcomeChangesRejectInvalidStatesAndAllowOtherSnapshottedOutcomes(): void
    {
        [$bestowal, $terminal] = $this->completedOutcomeFixture();
        $url = '/awards/bestowals/change-terminal-state/' . $bestowal->id;
        foreach (['open', 'cancelled', 'An outcome from another template'] as $status) {
            $this->post($url, ['expected_status' => 'Given Not Received', 'terminal_status' => $status]);
            $this->assertResponseCode(302);
            $this->assertSame('Given Not Received', $this->getTableLocator()
                ->get('Awards.Bestowals')->get($bestowal->id)->lifecycle_status);
        }
        $this->assertSame(0, $this->getTableLocator()->get('ActionItemLogs')->find()
            ->where(['action_item_id' => $terminal->id])->count());
        $this->post($url, ['expected_status' => 'Given Not Received', 'terminal_status' => ['given']]);
        $this->assertResponseCode(400);
        $this->post($url, ['expected_status' => 'Given Not Received', 'terminal_status' => 'Filed with Herald']);
        $this->assertResponseCode(302);
        $this->assertSame('Filed with Herald', $this->getTableLocator()
            ->get('Awards.Bestowals')->get($bestowal->id)->lifecycle_status);
        $this->get('/awards/bestowals/view/' . $bestowal->id);
        $this->assertResponseContains('Terminal state: Filed with Herald</span>');
    }

    public function testHistoricalTerminalTasksKeepTheirRecordedOutcomeBadge(): void
    {
        [$bestowal] = $this->completedOutcomeFixture();
        $this->makeTodo((int)$bestowal->id, [
            'title' => 'Superseded terminal', 'is_terminal' => true, 'status' => ActionItem::STATUS_COMPLETED,
            'completion_config' => ['terminal_status' => 'Earlier outcome'],
        ]);
        foreach (['view', 'bestowal-todos'] as $action) {
            $this->get('/awards/bestowals/' . $action . '/' . $bestowal->id);
            $this->assertResponseOk();
            $this->assertResponseContains('Terminal state: Given Not Received</span>');
            $this->assertResponseContains('Terminal — marks Earlier outcome</span>');
        }
    }

    public function testOutcomeChangeRequiresPostAndAnAuthorizedEditorOfACompletedBestowal(): void
    {
        [$bestowal] = $this->completedOutcomeFixture();
        $url = '/awards/bestowals/change-terminal-state/' . $bestowal->id;
        $data = ['expected_status' => 'Given Not Received', 'terminal_status' => 'given'];
        $this->get($url);
        $this->assertResponseCode(405);
        $this->authenticateAsMember(self::TEST_MEMBER_AGATHA_ID);
        $this->post($url, $data);
        $this->assertRedirectContains('/pages/unauthorized');
        $this->assertSame('Given Not Received', $this->getTableLocator()
            ->get('Awards.Bestowals')->get($bestowal->id)->lifecycle_status);
        $this->authenticateAsSuperUser();
        $table = $this->getTableLocator()->get('Awards.Bestowals');
        foreach (['open', 'cancelled'] as $status) {
            $bestowal->lifecycle_status = $status;
            $table->saveOrFail($bestowal);
            $this->post($url, ['expected_status' => $status, 'terminal_status' => 'given']);
            $this->assertResponseCode(302);
            $this->assertSame($status, $table->get($bestowal->id)->lifecycle_status);
        }
    }

    public function testOutcomeChangeRollsBackIfAuditCannotBeSaved(): void
    {
        [$bestowal] = $this->completedOutcomeFixture();
        $logs = $this->getTableLocator()->get('ActionItemLogs');
        $rejectAudit = static fn($event) => $event->stopPropagation();
        $logs->getEventManager()->on('Model.beforeSave', $rejectAudit);
        try {
            $this->post('/awards/bestowals/change-terminal-state/' . $bestowal->id, [
                'expected_status' => 'Given Not Received', 'terminal_status' => 'given',
            ]);
            $this->assertResponseCode(302);
            $this->assertSame('Given Not Received', $this->getTableLocator()
                ->get('Awards.Bestowals')->get($bestowal->id)->lifecycle_status);
        } finally {
            $logs->getEventManager()->off('Model.beforeSave', $rejectAudit);
        }
    }

    public function testCompletionNotesRemainVisibleOnFinalizedBestowalsAndQuickChecklists(): void
    {
        $bestowal = $this->makeBestowal();
        $todo = $this->makeTodo((int)$bestowal->id, ['title' => 'Given', 'is_terminal' => true]);
        $other = $this->makeBestowal();
        $otherTodo = $this->makeTodo((int)$other->id);
        $logs = $this->getTableLocator()->get('ActionItemLogs');
        $logs->saveOrFail($logs->newEntity([
            'action_item_id' => $otherTodo->id, 'from_status' => 'open', 'to_status' => 'completed',
            'note' => 'Another bestowal note must stay separate.', 'created_by' => self::ADMIN_MEMBER_ID,
        ]));
        $note = "Recipient was absent.\n<script>alert('unsafe')</script>";
        $this->post('/action-items/complete/' . $todo->id, ['note' => $note]);
        $this->assertResponseCode(302);
        $this->assertSame(Bestowal::LIFECYCLE_GIVEN, $this->getTableLocator()
            ->get('Awards.Bestowals')->get($bestowal->id)->lifecycle_status);
        $log = $logs->find()->where(['action_item_id' => $todo->id, 'note' => $note])->firstOrFail();
        $author = $this->getTableLocator()->get('Members')->get(self::ADMIN_MEMBER_ID);

        foreach (['view', 'bestowal-todos'] as $action) {
            $this->get('/awards/bestowals/' . $action . '/' . $bestowal->id);
            $this->assertResponseOk();
            $this->assertResponseContains('To-Do notes');
            $this->assertResponseContains('aria-label="Notes for Given"');
            $this->assertResponseContains(nl2br(h($note)));
            $this->assertResponseNotContains("<script>alert('unsafe')</script>");
            $this->assertResponseContains(h($author->sca_name));
            $this->assertResponseContains('datetime="' . $log->created->format(DATE_ATOM) . '"');
            $this->assertResponseNotContains('Another bestowal note must stay separate.');
        }
    }

    public function testReopenedTodoKeepsEarlierNotesInOrderAndHidesBlankEntries(): void
    {
        $bestowal = $this->makeBestowal();
        $this->makeTodo((int)$bestowal->id, ['title' => 'Given', 'is_terminal' => true]);
        $todo = $this->makeTodo((int)$bestowal->id, ['title' => 'Scroll ready']);
        $logs = $this->getTableLocator()->get('ActionItemLogs');
        $logs->saveOrFail($logs->newEntity([
            'action_item_id' => $todo->id, 'from_status' => 'open', 'to_status' => 'completed',
            'note' => " \n ", 'created_by' => self::ADMIN_MEMBER_ID,
        ]));
        $this->get('/awards/bestowals/view/' . $bestowal->id);
        $this->assertResponseNotContains('data-bestowal-todo-notes');
        $this->post('/action-items/complete/' . $todo->id, ['note' => 'First completion note.']);
        $this->assertResponseCode(302);
        $this->post('/action-items/reopen/' . $todo->id, ['note' => 'Later reopening note.']);
        $this->assertResponseCode(302);
        $this->get('/awards/bestowals/view/' . $bestowal->id);
        $this->assertResponseOk();
        $this->assertResponseRegExp('~First completion note\..*Reopened.*Later reopening note\.~s');
        $this->assertResponseContains('aria-label="Notes for Scroll ready"');
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
        $this->assertResponseContains('Choose a gathering to complete this task.');
        $this->assertResponseNotContains('Not assigned to you');
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

    public function testAgendaSubmissionCannotBypassSchedulingOrChangeCourtPlacement(): void
    {
        $this->enableRetainFlashMessages();
        foreach ([false, true] as $eventComplete) {
            $bestowal = $this->makeBestowal();
            $this->makeTodo((int)$bestowal->id, [
                'title' => 'Event Scheduled',
                'source_ref' => BestowalTodoTemplateItem::ITEM_KEY_EVENT_SCHEDULED,
                'status' => $eventComplete ? ActionItem::STATUS_COMPLETED : ActionItem::STATUS_OPEN,
            ]);
            $agenda = $this->makeTodo((int)$bestowal->id, [
                'title' => 'Added to Agenda',
                'source_ref' => BestowalTodoTemplateItem::ITEM_KEY_ADDED_TO_AGENDA,
            ]);

            $this->post('/action-items/complete/' . $agenda->id, [
                'gathering_scheduled_activity_id' => 'roaming',
            ]);

            $this->assertResponseCode(302);
            $this->assertFlashMessage($eventComplete
                ? 'Assign a gathering before Added to Agenda can be completed.'
                : 'Complete Event Scheduled before Added to Agenda can be completed.');
            $this->assertTrue($this->getTableLocator()->get('ActionItems')->get($agenda->id)->isOpen());
            $saved = $this->getTableLocator()->get('Awards.Bestowals')->get($bestowal->id);
            $this->assertNull($saved->gathering_id);
            $this->assertNull($saved->gathering_scheduled_activity_id);
            $this->assertFalse((bool)$saved->roaming_court);
            $this->assertFalse($this->getTableLocator()->get('Awards.CourtAgendaItems')
                ->exists(['bestowal_id' => $bestowal->id]));
        }
    }

    public function testScheduleThenAgendaAssignsTheSelectedCourtAndCreatesAgendaPlacement(): void
    {
        $bestowal = $this->makeBestowal();
        $schedule = $this->makeScheduledCourtForAward((int)$bestowal->award_id);
        $event = $this->makeTodo((int)$bestowal->id, [
            'title' => 'Event Scheduled',
            'source_ref' => BestowalTodoTemplateItem::ITEM_KEY_EVENT_SCHEDULED,
        ]);
        $agenda = $this->makeTodo((int)$bestowal->id, [
            'title' => 'Added to Agenda',
            'source_ref' => BestowalTodoTemplateItem::ITEM_KEY_ADDED_TO_AGENDA,
        ]);
        $this->makeTodo((int)$bestowal->id, ['title' => 'Given', 'is_terminal' => true]);

        $this->post('/action-items/complete/' . $event->id, [
            'bestowal_gathering_id' => (string)$schedule['gathering']->id,
        ]);
        $this->assertResponseCode(302);
        $this->assertTrue($this->getTableLocator()->get('ActionItems')->get($event->id)->isCompleted());

        $this->post('/action-items/complete/' . $agenda->id);
        $this->assertResponseCode(302);
        $this->assertTrue($this->getTableLocator()->get('ActionItems')->get($agenda->id)->isOpen());

        $this->post('/action-items/complete/' . $agenda->id, [
            'gathering_scheduled_activity_id' => (string)$schedule['scheduledActivity']->id,
        ]);
        $this->assertResponseCode(302);
        $this->assertTrue($this->getTableLocator()->get('ActionItems')->get($agenda->id)->isCompleted());
        $saved = $this->getTableLocator()->get('Awards.Bestowals')->get($bestowal->id);
        $this->assertSame((int)$schedule['gathering']->id, $saved->gathering_id);
        $this->assertSame((int)$schedule['scheduledActivity']->id, $saved->gathering_scheduled_activity_id);
        $this->assertTrue($this->getTableLocator()->get('Awards.CourtAgendaItems')
            ->exists(['bestowal_id' => $bestowal->id]));
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

    public function testMarkGivenRejectsMalformedDatesWithoutChangingTheChecklist(): void
    {
        $bestowal = $this->makeBestowal();
        $todo = $this->makeTodo((int)$bestowal->id, ['status' => ActionItem::STATUS_COMPLETED]);
        $this->enableRetainFlashMessages();
        foreach (['definitely-not-a-date', ['unexpected' => 'array']] as $date) {
            $this->post('/awards/bestowals/mark-given', ['bestowalId' => $bestowal->id, 'bestowed_at' => $date]);
            $this->assertResponseCode(302);
            $this->assertFlashMessage('Enter a valid bestowed date.');
            $saved = $this->getTableLocator()->get('Awards.Bestowals')->get($bestowal->id);
            $this->assertSame(Bestowal::LIFECYCLE_OPEN, $saved->lifecycle_status);
            $this->assertNull($saved->bestowed_at);
            $this->assertSame(ActionItem::STATUS_COMPLETED, $this->getTableLocator()
                ->get('ActionItems')->get($todo->id)->status);
        }
        $this->post('/awards/bestowals/mark-given', [
            'bestowalId' => $bestowal->id, 'bestowed_at' => '2026-09-15 12:30:00',
        ]);
        $this->assertResponseCode(302);
        $this->assertSame(Bestowal::LIFECYCLE_GIVEN, $this->getTableLocator()
            ->get('Awards.Bestowals')->get($bestowal->id)->lifecycle_status);
    }

    public function testViewOffersExplicitTerminalOutcomesAndPostsTheChosenStatus(): void
    {
        $bestowal = $this->makeBestowal();
        $given = $this->makeTodo((int)$bestowal->id, [
            'title' => 'Given', 'is_terminal' => true,
            'completion_config' => ['terminal_status' => 'given',
                'terminal_outcomes' => ['given' => 'Given', 'Given Not Received' => 'Given Not Received']],
        ]);
        $preparation = $this->makeTodo((int)$bestowal->id);
        $this->get('/awards/bestowals/view/' . $bestowal->id);
        $this->assertResponseOk();
        $this->assertResponseContains('Completion outcome');
        $this->assertResponseContains('value="Given Not Received"');
        $this->assertResponseContains('Complete Given');
        $this->assertResponseNotContains('Mark Given Not Received');

        $this->post('/awards/bestowals/mark-given', [
            'bestowalId' => $bestowal->id, 'terminalItemId' => $given->id, 'terminal_status' => 'Given Not Received',
            'bestowed_at' => '2026-11-04 12:00:00',
        ]);
        $this->assertResponseCode(302);
        $this->assertSame('Given Not Received', $this->getTableLocator()
            ->get('Awards.Bestowals')->get($bestowal->id)->lifecycle_status);
        $this->assertSame(ActionItem::STATUS_CANCELLED, $this->getTableLocator()
            ->get('ActionItems')->get($preparation->id)->status);
        $this->get('/awards/bestowals/view/' . $bestowal->id);
        $this->assertResponseContains('This bestowal has been marked Given Not Received.');
        $this->assertResponseNotContains('aria-label="Mark complete: Given"');
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
        $this->assertResponseContains('checkbox');
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
