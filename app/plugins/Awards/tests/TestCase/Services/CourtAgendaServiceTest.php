<?php
declare(strict_types=1);

namespace Awards\Test\TestCase\Services;

use App\Model\Entity\ActionItem;
use App\Services\ActionItems\ActionItemCompletionFormRegistry;
use App\Test\TestCase\BaseTestCase;
use Awards\Model\Entity\Bestowal;
use Awards\Model\Entity\BestowalTodoTemplateItem;
use Awards\Services\BestowalTodoCompletionFormProvider;
use Awards\Services\CourtAgendaService;
use RuntimeException;

/**
 * CourtAgendaServiceTest
 */
class CourtAgendaServiceTest extends BaseTestCase
{
    private CourtAgendaService $service;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new CourtAgendaService();
        ActionItemCompletionFormRegistry::clear();
        ActionItemCompletionFormRegistry::register('Awards.BestowalTodo', new BestowalTodoCompletionFormProvider());
    }

    /**
     * @return void
     */
    protected function tearDown(): void
    {
        ActionItemCompletionFormRegistry::clear();
        parent::tearDown();
    }

    /**
     * @return void
     */
    public function testDefaultAgendaImportsGatheringBestowalsWithTiming(): void
    {
        $gathering = $this->getTableLocator()->get('Gatherings')
            ->find()
            ->select(['id', 'name', 'start_date', 'end_date'])
            ->firstOrFail();
        $award = $this->getTableLocator()->get('Awards.Awards')
            ->find()
            ->contain(['Levels'])
            ->firstOrFail();
        $scheduledActivity = $this->createScheduledActivityForAward((int)$gathering->id, (int)$award->id);

        $bestowals = $this->getTableLocator()->get('Awards.Bestowals');
        $bestowal = $bestowals->newEntity([
            'member_id' => self::ADMIN_MEMBER_ID,
            'award_id' => $award->id,
            'gathering_id' => $gathering->id,
            'gathering_scheduled_activity_id' => $scheduledActivity->id,
            'state' => 'Court Scheduled',
            'status' => 'Scheduling',
            'source' => Bestowal::SOURCE_AD_HOC,
            'stack_rank' => 10,
        ]);
        $bestowals->saveOrFail($bestowal);
        $secondBestowal = $bestowals->newEntity([
            'member_id' => self::ADMIN_MEMBER_ID,
            'award_id' => $award->id,
            'gathering_id' => $gathering->id,
            'gathering_scheduled_activity_id' => $scheduledActivity->id,
            'state' => 'Court Scheduled',
            'status' => 'Scheduling',
            'source' => Bestowal::SOURCE_AD_HOC,
            'stack_rank' => 20,
        ]);
        $bestowals->saveOrFail($secondBestowal);

        $agenda = $this->service->getOrCreateDefaultAgenda((int)$gathering->id, self::ADMIN_MEMBER_ID);
        $imported = $this->service->importGatheringBestowals((int)$agenda->id, self::ADMIN_MEMBER_ID);
        $viewModel = $this->service->buildAgendaViewModel((int)$agenda->id);
        $legacySegmentCount = $this->getTableLocator()->get('Awards.CourtAgendaSegments')->find()
            ->where([
                'court_agenda_id' => $agenda->id,
                'name' => 'Court Agenda',
                'gathering_scheduled_activity_id IS' => null,
            ])
            ->count();

        // Real-time agenda sync placed both bestowals when their court slots were
        // saved, so the manual import finds nothing new to add.
        $this->assertSame(0, $imported);
        $placedCount = $this->getTableLocator()->get('Awards.CourtAgendaItems')->find()
            ->where(['bestowal_id IN' => [(int)$bestowal->id, (int)$secondBestowal->id]])
            ->count();
        $this->assertSame(2, $placedCount);
        $this->assertSame((int)$gathering->id, (int)$viewModel['agenda']->gathering_id);
        $this->assertNotEmpty($viewModel['segments']);
        $this->assertGreaterThan(0, $viewModel['totalMinutes']);
        $this->assertSame(0, $legacySegmentCount);
    }

    /**
     * @return void
     */
    public function testAddBestowalToSegmentSyncsCanonicalCourtSlot(): void
    {
        $gathering = $this->getTableLocator()->get('Gatherings')
            ->find()
            ->select(['id', 'start_date', 'end_date'])
            ->firstOrFail();
        $award = $this->getTableLocator()->get('Awards.Awards')
            ->find()
            ->contain(['Levels'])
            ->firstOrFail();
        $scheduledActivity = $this->createScheduledActivityForAward((int)$gathering->id, (int)$award->id);
        $agenda = $this->service->getOrCreateDefaultAgenda((int)$gathering->id, self::ADMIN_MEMBER_ID);
        $bestowals = $this->getTableLocator()->get('Awards.Bestowals');
        $bestowal = $bestowals->newEntity([
            'member_id' => self::ADMIN_MEMBER_ID,
            'award_id' => $award->id,
            'gathering_id' => $gathering->id,
            'state' => 'Court Scheduled',
            'status' => 'Scheduling',
            'source' => Bestowal::SOURCE_AD_HOC,
            'stack_rank' => 70,
        ]);
        $bestowals->saveOrFail($bestowal);
        $this->service->ensureEligibleCourtSegments((int)$agenda->id, self::ADMIN_MEMBER_ID);
        $segment = $this->getTableLocator()->get('Awards.CourtAgendaSegments')
            ->find()
            ->where([
                'court_agenda_id' => $agenda->id,
                'gathering_scheduled_activity_id' => $scheduledActivity->id,
            ])
            ->firstOrFail();

        $item = $this->service->addBestowalToSegment(
            (int)$agenda->id,
            (int)$bestowal->id,
            (int)$segment->id,
            self::ADMIN_MEMBER_ID,
        );
        $updatedBestowal = $bestowals->get((int)$bestowal->id);

        $this->assertSame((int)$segment->id, (int)$item->court_agenda_segment_id);
        $this->assertSame((int)$scheduledActivity->id, (int)$updatedBestowal->gathering_scheduled_activity_id);
        $this->assertFalse((bool)$updatedBestowal->roaming_court);
        $this->assertSame(10, (int)$updatedBestowal->stack_rank);
    }

    /**
     * @return void
     */
    public function testAddBestowalToSegmentAutoCompletesSystemClosableAgendaTodo(): void
    {
        $gathering = $this->getTableLocator()->get('Gatherings')
            ->find()
            ->select(['id', 'start_date', 'end_date'])
            ->firstOrFail();
        $award = $this->getTableLocator()->get('Awards.Awards')
            ->find()
            ->contain(['Levels'])
            ->firstOrFail();
        $scheduledActivity = $this->createScheduledActivityForAward((int)$gathering->id, (int)$award->id);
        $agenda = $this->service->getOrCreateDefaultAgenda((int)$gathering->id, self::ADMIN_MEMBER_ID);
        $bestowals = $this->getTableLocator()->get('Awards.Bestowals');
        $bestowal = $bestowals->newEntity([
            'member_id' => self::ADMIN_MEMBER_ID,
            'award_id' => $award->id,
            'gathering_id' => $gathering->id,
            'state' => 'Court Scheduled',
            'status' => 'Scheduling',
            'source' => Bestowal::SOURCE_AD_HOC,
            'stack_rank' => 70,
        ]);
        $bestowals->saveOrFail($bestowal);
        $this->getTableLocator()->get('ActionItems')->saveOrFail(
            $this->getTableLocator()->get('ActionItems')->newEntity([
                'entity_type' => Bestowal::ACTION_ITEM_ENTITY_TYPE,
                'entity_id' => (int)$bestowal->id,
                'title' => 'Added to Agenda',
                'assignee_type' => ActionItem::ASSIGNEE_TYPE_MEMBER,
                'assignee_config' => ['member_id' => self::ADMIN_MEMBER_ID],
                'branch_id' => self::KINGDOM_BRANCH_ID,
                'status' => ActionItem::STATUS_OPEN,
                'is_gating' => true,
                'sort_order' => 20,
                'source_ref' => BestowalTodoTemplateItem::ITEM_KEY_ADDED_TO_AGENDA,
                'completion_config' => [
                    ActionItem::COMPLETION_CONFIG_AUTO_COMPLETE => true,
                    'required_fields' => [
                        [
                            'provider' => BestowalTodoTemplateItem::COMPLETION_PROVIDER_BESTOWAL_COURT_SLOT,
                            'field' => BestowalTodoTemplateItem::REQUIRED_FIELD_COURT_SLOT,
                            'conditional_complete_on_assign' => true,
                        ],
                    ],
                ],
            ]),
        );
        $this->service->ensureEligibleCourtSegments((int)$agenda->id, self::ADMIN_MEMBER_ID);
        $segment = $this->getTableLocator()->get('Awards.CourtAgendaSegments')
            ->find()
            ->where([
                'court_agenda_id' => $agenda->id,
                'gathering_scheduled_activity_id' => $scheduledActivity->id,
            ])
            ->firstOrFail();

        $this->service->addBestowalToSegment(
            (int)$agenda->id,
            (int)$bestowal->id,
            (int)$segment->id,
            self::ADMIN_MEMBER_ID,
        );
        $reloadedTodo = $this->getTableLocator()->get('ActionItems')->find()
            ->where([
                'entity_type' => Bestowal::ACTION_ITEM_ENTITY_TYPE,
                'entity_id' => (int)$bestowal->id,
                'source_ref' => BestowalTodoTemplateItem::ITEM_KEY_ADDED_TO_AGENDA,
            ])
            ->firstOrFail();

        $this->assertTrue($reloadedTodo->isCompleted());
        $this->assertNull($reloadedTodo->completed_by);
    }

    /**
     * @return void
     */
    public function testMoveToRoamingCourtClearsCourtSlotAndRanksRoamingCourt(): void
    {
        $gathering = $this->getTableLocator()->get('Gatherings')
            ->find()
            ->select(['id', 'start_date', 'end_date'])
            ->firstOrFail();
        $award = $this->getTableLocator()->get('Awards.Awards')
            ->find()
            ->contain(['Levels'])
            ->firstOrFail();
        $scheduledActivity = $this->createScheduledActivityForAward((int)$gathering->id, (int)$award->id);
        $bestowals = $this->getTableLocator()->get('Awards.Bestowals');
        $bestowal = $bestowals->newEntity([
            'member_id' => self::ADMIN_MEMBER_ID,
            'award_id' => $award->id,
            'gathering_id' => $gathering->id,
            'gathering_scheduled_activity_id' => $scheduledActivity->id,
            'state' => 'Court Scheduled',
            'status' => 'Scheduling',
            'source' => Bestowal::SOURCE_AD_HOC,
            'stack_rank' => 20,
        ]);
        $bestowals->saveOrFail($bestowal);
        $agenda = $this->service->getOrCreateDefaultAgenda((int)$gathering->id, self::ADMIN_MEMBER_ID);
        $this->service->importGatheringBestowals((int)$agenda->id, self::ADMIN_MEMBER_ID);
        $item = $this->getTableLocator()->get('Awards.CourtAgendaItems')
            ->find()
            ->where(['bestowal_id' => $bestowal->id])
            ->firstOrFail();

        $movedItem = $this->service->moveToRoamingCourt(
            (int)$agenda->id,
            (int)$item->id,
            null,
            self::ADMIN_MEMBER_ID,
        );
        $updatedBestowal = $bestowals->get((int)$bestowal->id);

        $this->assertSame((int)$item->id, (int)$movedItem->id);
        $this->assertTrue((bool)$updatedBestowal->roaming_court);
        $this->assertNull($updatedBestowal->gathering_scheduled_activity_id);
        $this->assertSame(10, (int)$updatedBestowal->stack_rank);
    }

    /**
     * @return void
     */
    public function testRemoveItemClearsBestowalCourtSlotAndReopensAgendaTodo(): void
    {
        $gathering = $this->getTableLocator()->get('Gatherings')
            ->find()
            ->select(['id', 'start_date', 'end_date'])
            ->firstOrFail();
        $award = $this->getTableLocator()->get('Awards.Awards')
            ->find()
            ->contain(['Levels'])
            ->firstOrFail();
        $scheduledActivity = $this->createScheduledActivityForAward((int)$gathering->id, (int)$award->id);
        $agenda = $this->service->getOrCreateDefaultAgenda((int)$gathering->id, self::ADMIN_MEMBER_ID);
        $bestowals = $this->getTableLocator()->get('Awards.Bestowals');
        $bestowal = $bestowals->newEntity([
            'member_id' => self::ADMIN_MEMBER_ID,
            'award_id' => $award->id,
            'gathering_id' => $gathering->id,
            'gathering_scheduled_activity_id' => $scheduledActivity->id,
            'state' => 'Court Scheduled',
            'status' => 'Scheduling',
            'source' => Bestowal::SOURCE_AD_HOC,
            'stack_rank' => 20,
        ]);
        $bestowals->saveOrFail($bestowal);
        $todo = $this->getTableLocator()->get('ActionItems')->newEntity([
            'entity_type' => Bestowal::ACTION_ITEM_ENTITY_TYPE,
            'entity_id' => (int)$bestowal->id,
            'title' => 'Added to Agenda',
            'assignee_type' => ActionItem::ASSIGNEE_TYPE_MEMBER,
            'assignee_config' => ['member_id' => self::ADMIN_MEMBER_ID],
            'branch_id' => self::KINGDOM_BRANCH_ID,
            'status' => ActionItem::STATUS_COMPLETED,
            'completed_by' => self::ADMIN_MEMBER_ID,
            'completed_at' => date('Y-m-d H:i:s'),
            'is_gating' => true,
            'sort_order' => 20,
            'source_ref' => BestowalTodoTemplateItem::ITEM_KEY_ADDED_TO_AGENDA,
            'completion_config' => [
                ActionItem::COMPLETION_CONFIG_AUTO_COMPLETE => true,
                'required_fields' => [
                    [
                        'provider' => BestowalTodoTemplateItem::COMPLETION_PROVIDER_BESTOWAL_COURT_SLOT,
                        'field' => BestowalTodoTemplateItem::REQUIRED_FIELD_COURT_SLOT,
                        'conditional_complete_on_assign' => true,
                    ],
                ],
            ],
        ]);
        $this->getTableLocator()->get('ActionItems')->saveOrFail($todo);
        $this->service->importGatheringBestowals((int)$agenda->id, self::ADMIN_MEMBER_ID);
        $item = $this->getTableLocator()->get('Awards.CourtAgendaItems')
            ->find()
            ->where(['bestowal_id' => $bestowal->id])
            ->firstOrFail();

        $this->service->removeItem((int)$item->id, self::ADMIN_MEMBER_ID);
        $updatedBestowal = $bestowals->get((int)$bestowal->id);
        $updatedTodo = $this->getTableLocator()->get('ActionItems')->get((int)$todo->id);

        $this->assertFalse($this->getTableLocator()->get('Awards.CourtAgendaItems')->exists([
            'id' => (int)$item->id,
        ]));
        $this->assertNull($updatedBestowal->gathering_scheduled_activity_id);
        $this->assertFalse((bool)$updatedBestowal->roaming_court);
        $this->assertTrue($updatedTodo->isOpen());
        $this->assertNull($updatedTodo->completed_by);
    }

    /**
     * @return void
     */
    public function testAddBestowalRejectsSegmentFromDifferentGathering(): void
    {
        $gathering = $this->getTableLocator()->get('Gatherings')
            ->find()
            ->select(['id'])
            ->firstOrFail();
        $otherGatheringId = $this->createGathering('Other Test Event', '+14 days');
        $award = $this->getTableLocator()->get('Awards.Awards')
            ->find()
            ->contain(['Levels'])
            ->firstOrFail();
        $otherScheduledActivity = $this->createScheduledActivityForAward($otherGatheringId, (int)$award->id);
        $agenda = $this->service->getOrCreateDefaultAgenda((int)$gathering->id, self::ADMIN_MEMBER_ID);
        $segments = $this->getTableLocator()->get('Awards.CourtAgendaSegments');
        $badSegment = $segments->newEntity([
            'court_agenda_id' => $agenda->id,
            'gathering_scheduled_activity_id' => $otherScheduledActivity->id,
            'name' => 'Wrong Event Court',
            'court_type' => 'court',
            'sort_order' => 20,
            'created_by' => self::ADMIN_MEMBER_ID,
        ]);
        $segments->saveOrFail($badSegment);
        $bestowals = $this->getTableLocator()->get('Awards.Bestowals');
        $bestowal = $bestowals->newEntity([
            'member_id' => self::ADMIN_MEMBER_ID,
            'award_id' => $award->id,
            'gathering_id' => $gathering->id,
            'state' => 'Court Scheduled',
            'status' => 'Scheduling',
            'source' => Bestowal::SOURCE_AD_HOC,
            'stack_rank' => 70,
        ]);
        $bestowals->saveOrFail($bestowal);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('This award cannot be given in that court session.');

        $this->service->addBestowalToSegment(
            (int)$agenda->id,
            (int)$bestowal->id,
            (int)$badSegment->id,
            self::ADMIN_MEMBER_ID,
        );
    }

    /**
     * @return void
     */
    public function testAddBlockContributesToAgendaRuntime(): void
    {
        $gathering = $this->getTableLocator()->get('Gatherings')
            ->find()
            ->select(['id'])
            ->firstOrFail();
        $award = $this->getTableLocator()->get('Awards.Awards')
            ->find()
            ->contain(['Levels'])
            ->firstOrFail();
        $scheduledActivity = $this->createScheduledActivityForAward((int)$gathering->id, (int)$award->id);
        $agenda = $this->service->getOrCreateDefaultAgenda((int)$gathering->id, self::ADMIN_MEMBER_ID);
        $segment = $this->service->addSegment([
            'court_agenda_id' => $agenda->id,
            'gathering_scheduled_activity_id' => $scheduledActivity->id,
            'name' => 'Morning Court',
            'court_type' => 'court',
        ], self::ADMIN_MEMBER_ID);

        $this->service->addBlock([
            'court_agenda_segment_id' => $segment->id,
            'title' => 'Herald break',
            'estimated_minutes' => 12,
        ], self::ADMIN_MEMBER_ID);

        $viewModel = $this->service->buildAgendaViewModel((int)$agenda->id);

        $this->assertGreaterThanOrEqual(12, $viewModel['totalMinutes']);
        $this->assertSame('Herald break', $viewModel['segments'][0]['items'][0]['label']);
    }

    /**
     * @return void
     */
    public function testImportDeletesEmptyLegacyBlankAutoSegments(): void
    {
        $gathering = $this->getTableLocator()->get('Gatherings')
            ->find()
            ->select(['id'])
            ->firstOrFail();
        $agenda = $this->service->getOrCreateDefaultAgenda((int)$gathering->id, self::ADMIN_MEMBER_ID);
        $segments = $this->getTableLocator()->get('Awards.CourtAgendaSegments');
        $duplicateSegment = $segments->newEntity([
            'court_agenda_id' => $agenda->id,
            'name' => 'Court Agenda',
            'court_type' => 'court',
            'sort_order' => 20,
        ]);
        $legacySegment = $segments->saveOrFail($duplicateSegment);

        $this->service->importGatheringBestowals((int)$agenda->id, self::ADMIN_MEMBER_ID);
        $legacySegmentCount = $segments->find()
            ->where([
                'court_agenda_id' => $agenda->id,
                'name' => 'Court Agenda',
                'gathering_scheduled_activity_id IS' => null,
            ])
            ->count();

        $this->assertSame(0, $legacySegmentCount);
        $this->assertFalse($segments->exists(['id' => (int)$legacySegment->id]));
    }

    /**
     * @return void
     */
    public function testMoveItemMovesAcrossSegmentsAndRenumbersSource(): void
    {
        $gathering = $this->getTableLocator()->get('Gatherings')
            ->find()
            ->select(['id'])
            ->firstOrFail();
        $award = $this->getTableLocator()->get('Awards.Awards')
            ->find()
            ->contain(['Levels'])
            ->firstOrFail();
        $sourceActivity = $this->createScheduledActivityForAward((int)$gathering->id, (int)$award->id);
        $targetActivity = $this->createScheduledActivityForAward((int)$gathering->id, (int)$award->id);
        $agenda = $this->service->getOrCreateDefaultAgenda((int)$gathering->id, self::ADMIN_MEMBER_ID);
        $sourceSegment = $this->service->addSegment([
            'court_agenda_id' => $agenda->id,
            'gathering_scheduled_activity_id' => $sourceActivity->id,
            'name' => 'Morning Court',
            'court_type' => 'court',
        ], self::ADMIN_MEMBER_ID);
        $targetSegment = $this->service->addSegment([
            'court_agenda_id' => $agenda->id,
            'gathering_scheduled_activity_id' => $targetActivity->id,
            'name' => 'Evening Court',
            'court_type' => 'court',
        ], self::ADMIN_MEMBER_ID);

        $firstItem = $this->service->addBlock([
            'court_agenda_segment_id' => $sourceSegment->id,
            'title' => 'First block',
            'estimated_minutes' => 5,
        ], self::ADMIN_MEMBER_ID);
        $secondItem = $this->service->addBlock([
            'court_agenda_segment_id' => $sourceSegment->id,
            'title' => 'Second block',
            'estimated_minutes' => 5,
        ], self::ADMIN_MEMBER_ID);

        $this->service->moveItem((int)$firstItem->id, (int)$targetSegment->id, 10, self::ADMIN_MEMBER_ID);
        $items = $this->getTableLocator()->get('Awards.CourtAgendaItems');
        $movedItem = $items->get((int)$firstItem->id);
        $remainingItem = $items->get((int)$secondItem->id);

        $this->assertSame((int)$targetSegment->id, (int)$movedItem->court_agenda_segment_id);
        $this->assertSame((int)$sourceSegment->id, (int)$remainingItem->court_agenda_segment_id);
        $this->assertSame(10, (int)$remainingItem->sort_order);
    }

    /**
     * @return void
     */
    public function testEnsureEligibleCourtSegmentsCreatesLanesWithoutBestowals(): void
    {
        $gathering = $this->getTableLocator()->get('Gatherings')
            ->find()
            ->select(['id', 'start_date', 'end_date'])
            ->firstOrFail();
        $award = $this->getTableLocator()->get('Awards.Awards')
            ->find()
            ->firstOrFail();
        $scheduledActivity = $this->createScheduledActivityForAward((int)$gathering->id, (int)$award->id);
        $agenda = $this->service->getOrCreateDefaultAgenda((int)$gathering->id, self::ADMIN_MEMBER_ID);

        $created = $this->service->ensureEligibleCourtSegments((int)$agenda->id, self::ADMIN_MEMBER_ID);

        $this->assertGreaterThanOrEqual(1, $created);
        $segment = $this->getTableLocator()->get('Awards.CourtAgendaSegments')
            ->find()
            ->where([
                'court_agenda_id' => $agenda->id,
                'gathering_scheduled_activity_id' => $scheduledActivity->id,
            ])
            ->first();
        $this->assertNotNull($segment);

        $this->assertNotNull($segment->planned_start_time);
        $this->assertLessThanOrEqual(20, strlen((string)$segment->planned_start_time));

        $createdAgain = $this->service->ensureEligibleCourtSegments((int)$agenda->id, self::ADMIN_MEMBER_ID);
        $this->assertSame(0, $createdAgain);
    }

    /**
     * @return void
     */
    public function testBestowalCourtSlotChangesSyncAgendaInRealTime(): void
    {
        $gathering = $this->getTableLocator()->get('Gatherings')
            ->find()
            ->select(['id', 'start_date', 'end_date'])
            ->firstOrFail();
        $award = $this->getTableLocator()->get('Awards.Awards')
            ->find()
            ->contain(['Levels'])
            ->firstOrFail();
        $firstActivity = $this->createScheduledActivityForAward((int)$gathering->id, (int)$award->id);
        $secondActivity = $this->createScheduledActivityForAward((int)$gathering->id, (int)$award->id);

        $bestowals = $this->getTableLocator()->get('Awards.Bestowals');
        $items = $this->getTableLocator()->get('Awards.CourtAgendaItems');
        $bestowal = $bestowals->saveOrFail($bestowals->newEntity([
            'member_id' => self::ADMIN_MEMBER_ID,
            'award_id' => $award->id,
            'gathering_id' => $gathering->id,
            'state' => 'Court Scheduled',
            'status' => 'Scheduling',
            'source' => Bestowal::SOURCE_AD_HOC,
            'stack_rank' => 10,
        ]));

        $itemFinder = fn() => $items->find()
            ->contain(['CourtAgendaSegments'])
            ->where(['CourtAgendaItems.bestowal_id' => (int)$bestowal->id])
            ->first();
        $this->assertNull($itemFinder(), 'No agenda item expected before a court slot is assigned.');

        // Assigning a court slot places the bestowal on the agenda without an import.
        $bestowal->gathering_scheduled_activity_id = (int)$firstActivity->id;
        $bestowals->saveOrFail($bestowal);
        $item = $itemFinder();
        $this->assertNotNull($item, 'Agenda item expected after court slot assignment.');
        $this->assertSame(
            (int)$firstActivity->id,
            (int)$item->court_agenda_segment->gathering_scheduled_activity_id,
        );

        // Re-assigning to another court activity moves the same agenda item.
        $bestowal->gathering_scheduled_activity_id = (int)$secondActivity->id;
        $bestowals->saveOrFail($bestowal);
        $moved = $itemFinder();
        $this->assertSame((int)$item->id, (int)$moved->id);
        $this->assertSame(
            (int)$secondActivity->id,
            (int)$moved->court_agenda_segment->gathering_scheduled_activity_id,
        );

        // Switching to roaming court moves the item into the roaming lane.
        $bestowal->roaming_court = true;
        $bestowal->gathering_scheduled_activity_id = null;
        $bestowals->saveOrFail($bestowal);
        $roaming = $itemFinder();
        $this->assertNull($roaming->court_agenda_segment->gathering_scheduled_activity_id);
        $this->assertSame('Roaming Court', (string)$roaming->court_agenda_segment->name);

        // Clearing the assignment removes the agenda item.
        $bestowal->roaming_court = false;
        $bestowal->gathering_scheduled_activity_id = null;
        $bestowals->saveOrFail($bestowal);
        $this->assertNull($itemFinder(), 'Agenda item should be removed when the court slot is cleared.');
    }

    /**
     * @return void
     */
    public function testAddSegmentRequiresScheduledActivity(): void
    {
        $gathering = $this->getTableLocator()->get('Gatherings')
            ->find()
            ->select(['id'])
            ->firstOrFail();
        $agenda = $this->service->getOrCreateDefaultAgenda((int)$gathering->id, self::ADMIN_MEMBER_ID);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Court activities must be linked to a scheduled gathering activity.');

        $this->service->addSegment([
            'court_agenda_id' => $agenda->id,
            'name' => 'Manual Court',
            'court_type' => 'court',
        ], self::ADMIN_MEMBER_ID);
    }

    /**
     * @param int $gatheringId Gathering ID.
     * @param int $awardId Award ID.
     * @return \App\Model\Entity\GatheringScheduledActivity
     */
    private function createScheduledActivityForAward(int $gatheringId, int $awardId)
    {
        $gathering = $this->getTableLocator()->get('Gatherings')->get($gatheringId);
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
            'start_datetime' => (clone $gathering->start_date)->modify('+1 hour'),
            'end_datetime' => (clone $gathering->start_date)->modify('+2 hours'),
            'has_end_time' => true,
            'display_title' => 'Court Session',
            'description' => 'Court Session description.',
            'pre_register' => false,
            'is_other' => false,
            'created_by' => self::ADMIN_MEMBER_ID,
        ]);

        return $scheduledActivities->saveOrFail($scheduledActivity);
    }

    /**
     * @param string $name Gathering name.
     * @param string $startModifier Relative start date.
     * @return int
     */
    private function createGathering(string $name, string $startModifier): int
    {
        $branch = $this->getTableLocator()->get('Branches')
            ->find()
            ->select(['id'])
            ->firstOrFail();
        $type = $this->getTableLocator()->get('GatheringTypes')
            ->find()
            ->select(['id'])
            ->firstOrFail();
        $startDate = date('Y-m-d H:i:s', strtotime($startModifier));
        $gatherings = $this->getTableLocator()->get('Gatherings');
        $gathering = $gatherings->newEntity([
            'branch_id' => (int)$branch->id,
            'gathering_type_id' => (int)$type->id,
            'name' => $name,
            'start_date' => $startDate,
            'end_date' => date('Y-m-d H:i:s', strtotime($startModifier . ' +1 day')),
            'location' => 'Test Hall',
            'created_by' => self::ADMIN_MEMBER_ID,
        ]);
        $gatherings->saveOrFail($gathering);

        return (int)$gathering->id;
    }
}
