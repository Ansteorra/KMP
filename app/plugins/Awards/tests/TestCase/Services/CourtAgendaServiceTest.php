<?php
declare(strict_types=1);

namespace Awards\Test\TestCase\Services;

use App\Test\TestCase\BaseTestCase;
use Awards\Model\Entity\Bestowal;
use Awards\Services\CourtAgendaService;

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
    }

    /**
     * @return void
     */
    public function testDefaultAgendaImportsGatheringBestowalsWithTiming(): void
    {
        $gathering = $this->getTableLocator()->get('Gatherings')
            ->find()
            ->select(['id', 'name'])
            ->firstOrFail();
        $award = $this->getTableLocator()->get('Awards.Awards')
            ->find()
            ->contain(['Levels'])
            ->firstOrFail();

        $bestowals = $this->getTableLocator()->get('Awards.Bestowals');
        $bestowal = $bestowals->newEntity([
            'member_id' => self::ADMIN_MEMBER_ID,
            'award_id' => $award->id,
            'gathering_id' => $gathering->id,
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
            'state' => 'Court Scheduled',
            'status' => 'Scheduling',
            'source' => Bestowal::SOURCE_AD_HOC,
            'stack_rank' => 20,
        ]);
        $bestowals->saveOrFail($secondBestowal);

        $agenda = $this->service->getOrCreateDefaultAgenda((int)$gathering->id, self::ADMIN_MEMBER_ID);
        $imported = $this->service->importGatheringBestowals((int)$agenda->id, self::ADMIN_MEMBER_ID);
        $viewModel = $this->service->buildAgendaViewModel((int)$agenda->id);
        $defaultSegmentCount = $this->getTableLocator()->get('Awards.CourtAgendaSegments')->find()
            ->where([
                'court_agenda_id' => $agenda->id,
                'name' => 'Court Agenda',
                'gathering_scheduled_activity_id IS' => null,
            ])
            ->count();

        $this->assertGreaterThanOrEqual(1, $imported);
        $this->assertSame((int)$gathering->id, (int)$viewModel['agenda']->gathering_id);
        $this->assertNotEmpty($viewModel['segments']);
        $this->assertGreaterThan(0, $viewModel['totalMinutes']);
        $this->assertSame(1, $defaultSegmentCount);
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
        $agenda = $this->service->getOrCreateDefaultAgenda((int)$gathering->id, self::ADMIN_MEMBER_ID);
        $segment = $this->getTableLocator()->get('Awards.CourtAgendaSegments')
            ->find()
            ->where(['court_agenda_id' => $agenda->id])
            ->firstOrFail();

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
    public function testImportConsolidatesDuplicateBlankAutoSegments(): void
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
        $segments->saveOrFail($duplicateSegment);
        $block = $this->service->addBlock([
            'court_agenda_segment_id' => $duplicateSegment->id,
            'title' => 'Duplicate segment block',
            'estimated_minutes' => 5,
        ], self::ADMIN_MEMBER_ID);

        $this->service->importGatheringBestowals((int)$agenda->id, self::ADMIN_MEMBER_ID);
        $defaultSegmentCount = $segments->find()
            ->where([
                'court_agenda_id' => $agenda->id,
                'name' => 'Court Agenda',
                'gathering_scheduled_activity_id IS' => null,
            ])
            ->count();
        $movedBlock = $this->getTableLocator()->get('Awards.CourtAgendaItems')->get((int)$block->id);

        $this->assertSame(1, $defaultSegmentCount);
        $this->assertNotSame((int)$duplicateSegment->id, (int)$movedBlock->court_agenda_segment_id);
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
        $agenda = $this->service->getOrCreateDefaultAgenda((int)$gathering->id, self::ADMIN_MEMBER_ID);
        $segments = $this->getTableLocator()->get('Awards.CourtAgendaSegments');
        $sourceSegment = $segments->find()
            ->where(['court_agenda_id' => $agenda->id])
            ->firstOrFail();
        $targetSegment = $this->service->addSegment([
            'court_agenda_id' => $agenda->id,
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
}
