<?php
declare(strict_types=1);

namespace Awards\Services;

use App\Services\ActionItems\ActionItemService;
use Awards\Model\Entity\Bestowal;
use Awards\Model\Entity\CourtAgenda;
use Awards\Model\Entity\CourtAgendaItem;
use Awards\Model\Entity\CourtAgendaSegment;
use Cake\ORM\Locator\LocatorAwareTrait;
use Cake\ORM\Table;
use RuntimeException;

/**
 * Builds and mutates gathering court agendas for visual planning and print output.
 */
class CourtAgendaService
{
    use LocatorAwareTrait;

    private const LONG_SEGMENT_MINUTES = 45;
    private const LONG_AGENDA_MINUTES = 90;
    private const ROAMING_SEGMENT_NAME = 'Roaming Court';

    /**
     * Request-local award/activity eligibility lookup keyed by "awardId:activityId".
     *
     * @var array<string, bool>
     */
    private array $awardActivityEligibility = [];

    /**
     * @param int $gatheringId Gathering ID.
     * @param int|null $actorId Optional actor ID for audit fields.
     * @return \Awards\Model\Entity\CourtAgenda
     */
    public function getOrCreateDefaultAgenda(int $gatheringId, ?int $actorId = null): CourtAgenda
    {
        $agendas = $this->fetchTable('Awards.CourtAgendas');
        $agenda = $agendas->find()
            ->where([
                'CourtAgendas.gathering_id' => $gatheringId,
                'CourtAgendas.is_default' => true,
            ])
            ->contain(['Gatherings'])
            ->first();
        if ($agenda !== null) {
            return $agenda;
        }

        return $agendas->getConnection()->transactional(function () use ($agendas, $gatheringId, $actorId) {
            $gathering = $this->fetchTable('Gatherings')->get($gatheringId);
            $agenda = $agendas->newEntity([
                'gathering_id' => $gatheringId,
                'name' => (string)$gathering->name . ' Court Agenda',
                'description' => null,
                'is_default' => true,
                'created_by' => $actorId,
                'modified_by' => $actorId,
            ]);
            $agenda = $agendas->saveOrFail($agenda);
            $agenda->gathering = $gathering;

            return $agenda;
        });
    }

    /**
     * Import bestowals already assigned to the agenda gathering.
     *
     * @param int $agendaId Agenda ID.
     * @param int|null $actorId Optional actor ID.
     * @return int Number of imported items.
     */
    public function importGatheringBestowals(int $agendaId, ?int $actorId = null): int
    {
        $agendas = $this->fetchTable('Awards.CourtAgendas');
        $agenda = $agendas->get($agendaId, contain: ['CourtAgendaSegments']);
        $bestowals = $this->fetchTable('Awards.Bestowals')->find()
            ->where(['Bestowals.gathering_id' => (int)$agenda->gathering_id])
            ->where([
                'OR' => [
                    'Bestowals.gathering_scheduled_activity_id IS NOT' => null,
                    'Bestowals.roaming_court' => true,
                ],
            ])
            ->contain(['Awards' => ['Levels'], 'GatheringScheduledActivities'])
            ->orderBy([
                'Bestowals.gathering_scheduled_activity_id' => 'ASC',
                'Bestowals.roaming_court' => 'DESC',
                'Bestowals.stack_rank' => 'ASC',
                'Bestowals.id' => 'ASC',
            ])
            ->all();

        $items = $this->fetchTable('Awards.CourtAgendaItems');
        $bestowalList = $bestowals->toList();

        return $items->getConnection()->transactional(
            function () use ($agenda, $agendaId, $actorId, $items, $bestowalList): int {
                $existingBestowalIds = $this->existingAgendaBestowalIds($agendaId, $bestowalList);
                $segmentIdsByActivity = $this->segmentIdsByScheduledActivity($agenda->court_agenda_segments ?? []);
                $nextSortBySegment = [];
                $newItems = [];

                foreach ($bestowalList as $bestowal) {
                    $bestowalId = (int)$bestowal->id;
                    if (isset($existingBestowalIds[$bestowalId])) {
                        continue;
                    }

                    $segmentId = $this->segmentIdForBestowalWithCache(
                        $agendaId,
                        $bestowal,
                        $actorId,
                        $segmentIdsByActivity,
                    );
                    $nextSortBySegment[$segmentId] ??= $this->nextItemSortOrder($segmentId);
                    $newItems[] = $items->newEntity([
                        'court_agenda_segment_id' => $segmentId,
                        'bestowal_id' => $bestowalId,
                        'item_type' => CourtAgendaItem::TYPE_BESTOWAL,
                        'role' => CourtAgendaItem::ROLE_PRESENT,
                        'sort_order' => $nextSortBySegment[$segmentId],
                        'planned_action' => null,
                        'estimated_minutes' => $this->estimateMinutesForBestowal($bestowal),
                        'duration_locked' => false,
                        'include_reasons' => true,
                        'include_specialties' => true,
                        'created_by' => $actorId,
                        'modified_by' => $actorId,
                    ]);
                    $nextSortBySegment[$segmentId] += 10;
                }

                if ($newItems !== []) {
                    $items->saveManyOrFail($newItems);
                }
                $this->consolidateAutoSegments((int)$agenda->id, $actorId);
                $this->syncBestowalStackRanksForAgenda((int)$agenda->id);

                return count($newItems);
            },
        );
    }

    /**
     * Ensure scheduled gathering activities that can host pending bestowals are represented as court lanes.
     *
     * @param int $agendaId Agenda ID.
     * @param int|null $actorId Optional actor ID.
     * @return int Number of created segments.
     */
    public function ensureEligibleCourtSegments(int $agendaId, ?int $actorId = null): int
    {
        $agenda = $this->fetchTable('Awards.CourtAgendas')->get($agendaId);
        $segments = $this->fetchTable('Awards.CourtAgendaSegments');
        $created = 0;

        foreach ($this->eligibleScheduledActivitiesForGathering((int)$agenda->gathering_id) as $activity) {
            $exists = $segments->find()
                ->where([
                    'court_agenda_id' => $agendaId,
                    'gathering_scheduled_activity_id' => (int)$activity->id,
                ])
                ->first();
            if ($exists !== null) {
                continue;
            }

            $segments->saveOrFail($segments->newEntity([
                'court_agenda_id' => $agendaId,
                'gathering_scheduled_activity_id' => (int)$activity->id,
                'name' => (string)$activity->display_title,
                'court_type' => CourtAgendaSegment::TYPE_COURT,
                'sort_order' => $this->nextSegmentSortOrder($agendaId),
                'planned_start_time' => $this->activityTimeLabel($activity),
                'created_by' => $actorId,
                'modified_by' => $actorId,
            ]));
            $created++;
        }

        return $created;
    }

    /**
     * @param int $agendaId Agenda ID.
     * @return array<string, mixed>
     */
    public function buildAgendaViewModel(int $agendaId): array
    {
        $agenda = $this->fetchTable('Awards.CourtAgendas')->get($agendaId, contain: [
            'Gatherings',
            'CourtAgendaSegments' => [
                'GatheringScheduledActivities' => ['GatheringActivities'],
                'CourtAgendaItems',
            ],
        ]);
        $this->hydrateAgendaItemBestowals($agenda->court_agenda_segments ?? []);
        $placedBestowalIds = $this->placedBestowalIds($agenda->court_agenda_segments ?? []);
        $unscheduledBestowals = $this->unscheduledBestowals($agenda, $placedBestowalIds);
        $this->primeAwardActivityEligibility(
            $this->bestowalsForEligibility($agenda->court_agenda_segments ?? [], $unscheduledBestowals),
            $agenda->court_agenda_segments ?? [],
        );
        $eligibleBestowalsBySegment = $this->eligibleBestowalsBySegment($agenda, $unscheduledBestowals);
        $segmentOptions = $this->segmentOptions($agenda->court_agenda_segments ?? []);

        $segments = [];
        $agendaMinutes = 0;
        foreach ($agenda->court_agenda_segments ?? [] as $segment) {
            $items = [];
            $segmentMinutes = (int)$segment->planned_duration_minutes;
            foreach ($segment->court_agenda_items ?? [] as $item) {
                $minutes = max(0, (int)$item->estimated_minutes);
                $segmentMinutes += $minutes;
                $items[] = [
                    'entity' => $item,
                    'label' => $this->itemLabel($item),
                    'awardLabel' => $this->awardLabel($item),
                    'durationHint' => $this->durationHint($item),
                    'reasons' => $this->recommendationReasons($item),
                    'specialties' => $this->recommendationSpecialties($item),
                    'moveSegmentOptions' => $this->moveSegmentOptions($item, $agenda->court_agenda_segments ?? []),
                    'minutes' => $minutes,
                ];
            }

            $agendaMinutes += $segmentMinutes;
            $segments[] = [
                'entity' => $segment,
                'items' => $items,
                'minutes' => $segmentMinutes,
                'isRoaming' => $this->isRoamingSegment($segment),
                'scheduledActivityLabel' => $this->scheduledActivityLabel($segment),
                'eligibleBestowals' => $eligibleBestowalsBySegment[(int)$segment->id] ?? [],
                'warning' => $segmentMinutes >= self::LONG_SEGMENT_MINUTES
                    ? __('Consider a break or splitting this court segment.')
                    : null,
            ];
        }

        return [
            'agenda' => $agenda,
            'segments' => $segments,
            'totalMinutes' => $agendaMinutes,
            'unscheduledBestowals' => $this->unscheduledBestowalData(
                $unscheduledBestowals,
                $agenda->court_agenda_segments ?? [],
                $eligibleBestowalsBySegment,
            ),
            'scheduledActivityOptions' => $this->scheduledActivityOptions((int)$agenda->gathering_id),
            'segmentOptions' => $segmentOptions,
            'totalWarning' => $agendaMinutes >= self::LONG_AGENDA_MINUTES
                ? __('This court agenda is running long. Consider adding breaks or splitting court.')
                : null,
        ];
    }

    /**
     * @param array<int, \Awards\Model\Entity\CourtAgendaSegment> $segments Court agenda segments.
     * @return void
     */
    private function hydrateAgendaItemBestowals(array $segments): void
    {
        $bestowalIds = [];
        foreach ($segments as $segment) {
            foreach ($segment->court_agenda_items ?? [] as $item) {
                $bestowalId = (int)($item->bestowal_id ?? 0);
                if ($bestowalId > 0) {
                    $bestowalIds[$bestowalId] = true;
                }
            }
        }
        if ($bestowalIds === []) {
            return;
        }

        $bestowals = $this->fetchTable('Awards.Bestowals')->find()
            ->where(['Bestowals.id IN' => array_keys($bestowalIds)])
            ->contain([
                'Members',
                'Awards' => ['Levels'],
                'GatheringScheduledActivities',
                'Recommendations' => ['Awards' => ['Levels']],
            ])
            ->all()
            ->combine('id', static fn($bestowal) => $bestowal)
            ->toArray();

        foreach ($segments as $segment) {
            foreach ($segment->court_agenda_items ?? [] as $item) {
                $bestowalId = (int)($item->bestowal_id ?? 0);
                if ($bestowalId > 0 && isset($bestowals[$bestowalId])) {
                    $item->bestowal = $bestowals[$bestowalId];
                }
            }
        }
    }

    /**
     * @param array<string, mixed> $data Submitted segment data.
     * @param int|null $actorId Actor ID.
     * @return \Awards\Model\Entity\CourtAgendaSegment
     */
    public function addSegment(array $data, ?int $actorId = null): CourtAgendaSegment
    {
        $segments = $this->fetchTable('Awards.CourtAgendaSegments');
        $agendaId = (int)($data['court_agenda_id'] ?? 0);
        if ($agendaId <= 0) {
            throw new RuntimeException('Court agenda is required.');
        }

        $agenda = $this->fetchTable('Awards.CourtAgendas')->get($agendaId);
        $scheduledActivityId = $this->emptyToNull($data['gathering_scheduled_activity_id'] ?? null);
        if ($scheduledActivityId === null) {
            throw new RuntimeException('Court activities must be linked to a scheduled gathering activity.');
        }
        if ($scheduledActivityId !== null) {
            $this->assertScheduledActivityBelongsToGathering((int)$scheduledActivityId, (int)$agenda->gathering_id);
        }

        $segment = $segments->newEntity([
            'court_agenda_id' => $agendaId,
            'gathering_scheduled_activity_id' => $scheduledActivityId,
            'name' => trim((string)($data['name'] ?? '')),
            'court_type' => (string)($data['court_type'] ?? CourtAgendaSegment::TYPE_COURT),
            'sort_order' => $this->nextSegmentSortOrder($agendaId),
            'planned_start_time' => $this->emptyToNull($data['planned_start_time'] ?? null),
            'planned_duration_minutes' => (int)($data['planned_duration_minutes'] ?? 0),
            'notes' => $this->emptyToNull($data['notes'] ?? null),
            'created_by' => $actorId,
            'modified_by' => $actorId,
        ]);

        return $segments->saveOrFail($segment);
    }

    /**
     * @param array<string, mixed> $data Submitted block data.
     * @param int|null $actorId Actor ID.
     * @return \Awards\Model\Entity\CourtAgendaItem
     */
    public function addBlock(array $data, ?int $actorId = null): CourtAgendaItem
    {
        $items = $this->fetchTable('Awards.CourtAgendaItems');
        $segmentId = (int)($data['court_agenda_segment_id'] ?? 0);
        if ($segmentId <= 0) {
            throw new RuntimeException('Court agenda segment is required.');
        }
        $segment = $this->fetchTable('Awards.CourtAgendaSegments')->get($segmentId);
        $this->assertSegmentCanAcceptAgendaItems($segment);

        $item = $items->newEntity([
            'court_agenda_segment_id' => $segmentId,
            'item_type' => CourtAgendaItem::TYPE_BLOCK,
            'role' => (string)($data['role'] ?? CourtAgendaItem::ROLE_BREAK),
            'title' => trim((string)($data['title'] ?? '')),
            'sort_order' => $this->nextItemSortOrder($segmentId),
            'estimated_minutes' => (int)($data['estimated_minutes'] ?? 5),
            'duration_locked' => true,
            'presentation_notes' => $this->emptyToNull($data['presentation_notes'] ?? null),
            'print_notes' => $this->emptyToNull($data['print_notes'] ?? null),
            'created_by' => $actorId,
            'modified_by' => $actorId,
        ]);

        return $items->saveOrFail($item);
    }

    /**
     * Add an eligible gathering bestowal to a court segment.
     *
     * @param int $agendaId Agenda ID.
     * @param int $bestowalId Bestowal ID.
     * @param int $targetSegmentId Target segment ID.
     * @param int|null $actorId Actor ID.
     * @return \Awards\Model\Entity\CourtAgendaItem
     */
    public function addBestowalToSegment(
        int $agendaId,
        int $bestowalId,
        int $targetSegmentId,
        ?int $actorId = null,
    ): CourtAgendaItem {
        $items = $this->fetchTable('Awards.CourtAgendaItems');

        return $items->getConnection()->transactional(
            function () use ($agendaId, $bestowalId, $targetSegmentId, $actorId, $items): CourtAgendaItem {
                $agenda = $this->fetchTable('Awards.CourtAgendas')->get($agendaId);
                $segment = $this->fetchTable('Awards.CourtAgendaSegments')->get($targetSegmentId);
                if ((int)$segment->court_agenda_id !== $agendaId) {
                    throw new RuntimeException('Bestowals can only be added to this court agenda.');
                }

                $bestowal = $this->fetchTable('Awards.Bestowals')->find()
                    ->where(['Bestowals.id' => $bestowalId])
                    ->epilog('FOR UPDATE')
                    ->firstOrFail();
                if ((int)$bestowal->gathering_id !== (int)$agenda->gathering_id) {
                    throw new RuntimeException('Bestowal is not scheduled for this gathering.');
                }
                $this->assertBestowalCanUseSegment($bestowal, $segment);

                $existing = $items->find()
                    ->contain(['CourtAgendaSegments'])
                    ->where([
                        'CourtAgendaSegments.court_agenda_id' => $agendaId,
                        'CourtAgendaItems.bestowal_id' => $bestowalId,
                        'CourtAgendaItems.role' => CourtAgendaItem::ROLE_PRESENT,
                    ])
                    ->first();

                if ($existing !== null) {
                    $this->moveItem(
                        (int)$existing->id,
                        $targetSegmentId,
                        $this->nextItemSortOrder($targetSegmentId),
                        $actorId,
                    );

                    return $items->get((int)$existing->id);
                }

                $item = $items->newEntity([
                    'court_agenda_segment_id' => $targetSegmentId,
                    'bestowal_id' => $bestowalId,
                    'item_type' => CourtAgendaItem::TYPE_BESTOWAL,
                    'role' => CourtAgendaItem::ROLE_PRESENT,
                    'sort_order' => $this->nextItemSortOrder($targetSegmentId),
                    'estimated_minutes' => $this->estimateMinutesForBestowal($bestowal),
                    'duration_locked' => false,
                    'include_reasons' => true,
                    'include_specialties' => true,
                    'created_by' => $actorId,
                    'modified_by' => $actorId,
                ]);
                $item = $items->saveOrFail($item);
                $this->syncBestowalPlacementFromSegment($bestowal, $segment, $actorId);
                $this->syncBestowalStackRanksForSegment($targetSegmentId);

                return $item;
            },
        );
    }

    /**
     * Move a bestowal or existing agenda item into roaming court.
     *
     * @param int $agendaId Agenda ID.
     * @param int|null $itemId Optional agenda item ID.
     * @param int|null $bestowalId Optional bestowal ID.
     * @param int|null $actorId Actor ID.
     * @return \Awards\Model\Entity\CourtAgendaItem
     */
    public function moveToRoamingCourt(
        int $agendaId,
        ?int $itemId = null,
        ?int $bestowalId = null,
        ?int $actorId = null,
    ): CourtAgendaItem {
        $roamingSegment = $this->ensureRoamingSegment($agendaId, $actorId);
        if ($itemId !== null && $itemId > 0) {
            $this->moveItem(
                $itemId,
                (int)$roamingSegment->id,
                $this->nextItemSortOrder((int)$roamingSegment->id),
                $actorId,
            );

            return $this->fetchTable('Awards.CourtAgendaItems')->get($itemId);
        }
        if ($bestowalId === null || $bestowalId <= 0) {
            throw new RuntimeException('Bestowal is required for roaming court.');
        }

        return $this->addBestowalToSegment($agendaId, $bestowalId, (int)$roamingSegment->id, $actorId);
    }

    /**
     * @param int $itemId Item ID.
     * @param array<string, mixed> $data Submitted data.
     * @param int|null $actorId Actor ID.
     * @return \Awards\Model\Entity\CourtAgendaItem
     */
    public function updateItem(int $itemId, array $data, ?int $actorId = null): CourtAgendaItem
    {
        $items = $this->fetchTable('Awards.CourtAgendaItems');
        $item = $items->get($itemId);
        $patch = [
            'estimated_minutes' => (int)($data['estimated_minutes'] ?? $item->estimated_minutes),
            'duration_locked' => true,
            'presentation_notes' => $this->emptyToNull($data['presentation_notes'] ?? $item->presentation_notes),
            'print_notes' => $this->emptyToNull($data['print_notes'] ?? $item->print_notes),
            'include_reasons' => !empty($data['include_reasons']),
            'include_specialties' => !empty($data['include_specialties']),
            'modified_by' => $actorId,
        ];
        if (array_key_exists('title', $data)) {
            $patch['title'] = $this->emptyToNull($data['title']);
        }
        if (array_key_exists('planned_action', $data)) {
            $patch['planned_action'] = $this->emptyToNull($data['planned_action']);
        }
        $item = $items->patchEntity($item, $patch);

        return $items->saveOrFail($item);
    }

    /**
     * @param int $itemId Item ID.
     * @param int $targetSegmentId Target segment ID.
     * @param int $targetSortOrder Target order.
     * @param int|null $actorId Actor ID.
     * @return void
     */
    public function moveItem(int $itemId, int $targetSegmentId, int $targetSortOrder, ?int $actorId = null): void
    {
        $items = $this->fetchTable('Awards.CourtAgendaItems');
        $items->getConnection()->transactional(
            function () use ($items, $itemId, $targetSegmentId, $targetSortOrder, $actorId): void {
                $item = $items->get($itemId, contain: ['CourtAgendaSegments']);
                $sourceSegmentId = (int)$item->court_agenda_segment_id;
                $targetSegment = $this->fetchTable('Awards.CourtAgendaSegments')->get($targetSegmentId);
                if ((int)$targetSegment->court_agenda_id !== (int)$item->court_agenda_segment->court_agenda_id) {
                    throw new RuntimeException('Agenda items can only move within the same court agenda.');
                }
                $this->assertSegmentCanAcceptAgendaItems($targetSegment);
                if ($item->bestowal_id !== null) {
                    $targetSegment = $this->fetchTable('Awards.CourtAgendaSegments')->get(
                        $targetSegmentId,
                        contain: ['GatheringScheduledActivities'],
                    );
                    $this->assertSegmentCanAcceptAgendaItems($targetSegment);
                    $bestowal = $this->fetchTable('Awards.Bestowals')->get((int)$item->bestowal_id);
                    $this->assertBestowalCanUseSegment($bestowal, $targetSegment);
                }
                $item->court_agenda_segment_id = $targetSegmentId;
                $item->sort_order = max(0, $targetSortOrder);
                $item->modified_by = $actorId;
                $items->saveOrFail($item);
                if ($item->bestowal_id !== null) {
                    $bestowal = $this->fetchTable('Awards.Bestowals')->get((int)$item->bestowal_id);
                    $this->syncBestowalPlacementFromSegment($bestowal, $targetSegment, $actorId);
                }
                if ($sourceSegmentId !== $targetSegmentId) {
                    $this->renumberItems($sourceSegmentId);
                    $this->syncBestowalStackRanksForSegment($sourceSegmentId);
                }
                $this->renumberItems($targetSegmentId);
                $this->syncBestowalStackRanksForSegment($targetSegmentId);
            },
        );
    }

    /**
     * Remove an agenda item from court planning.
     *
     * Bestowal items stay assigned to the gathering, but their court slot is cleared
     * so they return to the eligible backlog instead of roaming court.
     *
     * @param int $itemId Item ID.
     * @param int|null $actorId Actor ID.
     * @return void
     */
    public function removeItem(int $itemId, ?int $actorId = null): void
    {
        $items = $this->fetchTable('Awards.CourtAgendaItems');
        $items->getConnection()->transactional(
            function () use ($items, $itemId, $actorId): void {
                $item = $items->get($itemId, contain: ['CourtAgendaSegments']);
                $sourceSegmentId = (int)$item->court_agenda_segment_id;
                $bestowalId = $item->bestowal_id !== null ? (int)$item->bestowal_id : null;

                $items->deleteOrFail($item);
                if ($bestowalId !== null) {
                    $bestowal = $this->fetchTable('Awards.Bestowals')->get($bestowalId);
                    $this->clearBestowalCourtAssignment($bestowal, $actorId);
                }

                $this->renumberItems($sourceSegmentId);
                $this->syncBestowalStackRanksForSegment($sourceSegmentId);
            },
        );
    }

    /**
     * @param object $bestowal Bestowal entity.
     * @return int
     */
    public function estimateMinutesForBestowal(object $bestowal): int
    {
        $level = strtolower((string)($bestowal->award->level->name ?? ''));
        $awardName = strtolower((string)($bestowal->award->name ?? $bestowal->award->abbreviation ?? ''));

        if (str_contains($level, 'peer') || str_contains($awardName, 'peerage')) {
            return 15;
        }
        if (str_contains($level, 'grant')) {
            return 8;
        }
        if (
            str_contains($level, 'award')
            || str_contains($awardName, 'award of arms')
            || str_contains($awardName, 'aoa')
        ) {
            return 4;
        }

        return 5;
    }

    /**
     * @param array<int, \Awards\Model\Entity\CourtAgendaSegment> $segments Court segments.
     * @return array<int>
     */
    private function placedBestowalIds(array $segments): array
    {
        $ids = [];
        foreach ($segments as $segment) {
            foreach ($segment->court_agenda_items ?? [] as $item) {
                if ($item->bestowal_id !== null) {
                    $ids[(int)$item->bestowal_id] = (int)$item->bestowal_id;
                }
            }
        }

        return array_values($ids);
    }

    /**
     * @param int $agendaId Agenda ID.
     * @param array<int, \Awards\Model\Entity\Bestowal> $bestowals Candidate bestowals.
     * @return array<int, int>
     */
    private function existingAgendaBestowalIds(int $agendaId, array $bestowals): array
    {
        $bestowalIds = [];
        foreach ($bestowals as $bestowal) {
            $bestowalIds[(int)$bestowal->id] = (int)$bestowal->id;
        }
        if ($bestowalIds === []) {
            return [];
        }

        $rows = $this->fetchTable('Awards.CourtAgendaItems')->find()
            ->select(['bestowal_id' => 'CourtAgendaItems.bestowal_id'])
            ->innerJoinWith('CourtAgendaSegments')
            ->where([
                'CourtAgendaSegments.court_agenda_id' => $agendaId,
                'CourtAgendaItems.bestowal_id IN' => array_values($bestowalIds),
                'CourtAgendaItems.role' => CourtAgendaItem::ROLE_PRESENT,
            ])
            ->enableHydration(false)
            ->all();

        $existing = [];
        foreach ($rows as $row) {
            $bestowalId = (int)($row['bestowal_id'] ?? 0);
            if ($bestowalId > 0) {
                $existing[$bestowalId] = $bestowalId;
            }
        }

        return $existing;
    }

    /**
     * @param array<int, \Awards\Model\Entity\CourtAgendaSegment> $segments Court segments.
     * @return array<int, int>
     */
    private function segmentIdsByScheduledActivity(array $segments): array
    {
        $map = [];
        foreach ($segments as $segment) {
            if ($segment->gathering_scheduled_activity_id !== null) {
                $map[(int)$segment->gathering_scheduled_activity_id] = (int)$segment->id;
            }
        }

        return $map;
    }

    /**
     * @param int $agendaId Agenda ID.
     * @param object $bestowal Bestowal entity.
     * @param int|null $actorId Actor ID.
     * @param array<int, int> $segmentIdsByActivity Cached scheduled activity to segment map.
     * @return int
     */
    private function segmentIdForBestowalWithCache(
        int $agendaId,
        object $bestowal,
        ?int $actorId,
        array &$segmentIdsByActivity,
    ): int {
        if (!empty($bestowal->roaming_court)) {
            return (int)$this->ensureRoamingSegment($agendaId, $actorId)->id;
        }

        $activityId = $bestowal->gathering_scheduled_activity_id ?? null;
        if ($activityId === null) {
            throw new RuntimeException('Bestowal must be assigned to a scheduled court activity or roaming court.');
        }
        $activityId = (int)$activityId;
        if (isset($segmentIdsByActivity[$activityId])) {
            return $segmentIdsByActivity[$activityId];
        }

        $segmentId = $this->segmentIdForBestowal($agendaId, $bestowal, $actorId);
        $segmentIdsByActivity[$activityId] = $segmentId;

        return $segmentId;
    }

    /**
     * @param \Awards\Model\Entity\CourtAgenda $agenda Agenda.
     * @param array<int, \Awards\Model\Entity\Bestowal> $unscheduled Unscheduled bestowals.
     * @return array<int, array<int, \Awards\Model\Entity\Bestowal>>
     */
    private function eligibleBestowalsBySegment(CourtAgenda $agenda, array $unscheduled): array
    {
        if ($unscheduled === []) {
            return [];
        }

        $eligible = [];
        foreach ($agenda->court_agenda_segments ?? [] as $segment) {
            if ($this->isRoamingSegment($segment)) {
                $eligible[(int)$segment->id] = $unscheduled;
                continue;
            }
            if (empty($segment->gathering_scheduled_activity_id)) {
                $eligible[(int)$segment->id] = [];
                continue;
            }

            $segmentEligible = [];
            foreach ($unscheduled as $bestowal) {
                if ($this->bestowalCanUseSegment($bestowal, $segment)) {
                    $segmentEligible[] = $bestowal;
                }
            }
            $eligible[(int)$segment->id] = $segmentEligible;
        }

        return $eligible;
    }

    /**
     * @param array<int, \Awards\Model\Entity\CourtAgendaSegment> $segments Court segments.
     * @param array<int, \Awards\Model\Entity\Bestowal> $unscheduledBestowals Unscheduled bestowals.
     * @return array<int, \Awards\Model\Entity\Bestowal>
     */
    private function bestowalsForEligibility(array $segments, array $unscheduledBestowals): array
    {
        $bestowals = [];
        foreach ($unscheduledBestowals as $bestowal) {
            $bestowals[(int)$bestowal->id] = $bestowal;
        }
        foreach ($segments as $segment) {
            foreach ($segment->court_agenda_items ?? [] as $item) {
                if ($item->bestowal instanceof Bestowal) {
                    $bestowals[(int)$item->bestowal->id] = $item->bestowal;
                }
            }
        }

        return array_values($bestowals);
    }

    /**
     * @param array<int, \Awards\Model\Entity\Bestowal> $bestowals Bestowals.
     * @param array<int, \Awards\Model\Entity\CourtAgendaSegment> $segments Court segments.
     * @return void
     */
    private function primeAwardActivityEligibility(array $bestowals, array $segments): void
    {
        $awardIds = [];
        foreach ($bestowals as $bestowal) {
            if ($bestowal->award_id !== null) {
                $awardIds[(int)$bestowal->award_id] = (int)$bestowal->award_id;
            }
        }

        $activityIds = [];
        foreach ($segments as $segment) {
            $activityId = $segment->gathering_scheduled_activity->gathering_activity_id ?? null;
            if ($activityId !== null) {
                $activityIds[(int)$activityId] = (int)$activityId;
            }
        }
        if ($awardIds === [] || $activityIds === []) {
            return;
        }

        foreach ($awardIds as $awardId) {
            foreach ($activityIds as $activityId) {
                $this->awardActivityEligibility[$this->awardActivityEligibilityKey($awardId, $activityId)] = false;
            }
        }

        $rows = $this->fetchTable('Awards.AwardGatheringActivities')->find()
            ->select(['award_id', 'gathering_activity_id'])
            ->where([
                'award_id IN' => array_values($awardIds),
                'gathering_activity_id IN' => array_values($activityIds),
            ])
            ->enableHydration(false)
            ->all();
        foreach ($rows as $row) {
            $this->awardActivityEligibility[$this->awardActivityEligibilityKey(
                (int)$row['award_id'],
                (int)$row['gathering_activity_id'],
            )] = true;
        }
    }

    /**
     * @param \Awards\Model\Entity\CourtAgenda $agenda Agenda.
     * @param array<int> $placedBestowalIds Bestowal IDs already on the agenda.
     * @return array<int, \Awards\Model\Entity\Bestowal>
     */
    private function unscheduledBestowals(CourtAgenda $agenda, array $placedBestowalIds): array
    {
        $query = $this->fetchTable('Awards.Bestowals')->find()
            ->where([
                'Bestowals.gathering_id' => (int)$agenda->gathering_id,
                'Bestowals.gathering_scheduled_activity_id IS' => null,
                'Bestowals.roaming_court' => false,
                'Bestowals.lifecycle_status NOT IN' => [
                    Bestowal::LIFECYCLE_GIVEN,
                    Bestowal::LIFECYCLE_CANCELLED,
                ],
            ])
            ->contain([
                'Members',
                'Awards' => ['Levels', 'Branches'],
                'Recommendations' => ['Awards' => ['Levels']],
            ])
            ->orderBy(['Bestowals.stack_rank' => 'ASC', 'Bestowals.id' => 'ASC']);

        if ($placedBestowalIds !== []) {
            $query->where(['Bestowals.id NOT IN' => $placedBestowalIds]);
        }

        return $query->all()->toList();
    }

    /**
     * @param array<int, \Awards\Model\Entity\Bestowal> $bestowals Unscheduled bestowals.
     * @param array<int, \Awards\Model\Entity\CourtAgendaSegment> $segments Court segments.
     * @return array<int, array<string, mixed>>
     */
    private function unscheduledBestowalData(array $bestowals, array $segments, array $eligibleBySegment): array
    {
        $rows = [];
        foreach ($bestowals as $bestowal) {
            $eligibleSegmentOptions = $this->eligibleSegmentOptionsFromMap($bestowal, $segments, $eligibleBySegment);
            $rows[] = [
                'entity' => $bestowal,
                'label' => (string)(
                    $bestowal->member->sca_name
                    ?? $bestowal->member_sca_name
                    ?? __('Unknown recipient')
                ),
                'awardLabel' => $this->bestowalAwardLabel($bestowal),
                'eligibleSegmentOptions' => $eligibleSegmentOptions,
                'isSchedulable' => $eligibleSegmentOptions !== [],
            ];
        }

        return $rows;
    }

    /**
     * @param array<int, \Awards\Model\Entity\CourtAgendaSegment> $segments Court segments.
     * @return array<int, string>
     */
    private function segmentOptions(array $segments): array
    {
        $options = [];
        foreach ($segments as $segment) {
            $options[(int)$segment->id] = (string)$segment->name;
        }

        return $options;
    }

    /**
     * @param \Awards\Model\Entity\CourtAgendaItem $item Agenda item.
     * @param array<int, \Awards\Model\Entity\CourtAgendaSegment> $segments Court segments.
     * @return array<int, string>
     */
    private function moveSegmentOptions(CourtAgendaItem $item, array $segments): array
    {
        if ($item->bestowal === null) {
            $options = $this->segmentOptions($segments);
            unset($options[(int)$item->court_agenda_segment_id]);

            return $options;
        }

        $options = $this->eligibleSegmentOptionsForBestowal($item->bestowal, $segments);
        unset($options[(int)$item->court_agenda_segment_id]);

        return $options;
    }

    /**
     * @param \Awards\Model\Entity\Bestowal $bestowal Bestowal.
     * @param array<int, \Awards\Model\Entity\CourtAgendaSegment> $segments Court segments.
     * @return array<int, string>
     */
    private function eligibleSegmentOptionsForBestowal(Bestowal $bestowal, array $segments): array
    {
        $options = [];
        foreach ($segments as $segment) {
            if ($this->bestowalCanUseSegment($bestowal, $segment)) {
                $options[(int)$segment->id] = (string)$segment->name;
            }
        }

        return $options;
    }

    /**
     * @param \Awards\Model\Entity\Bestowal $bestowal Bestowal.
     * @param array<int, \Awards\Model\Entity\CourtAgendaSegment> $segments Court segments.
     * @param array<int, array<int, \Awards\Model\Entity\Bestowal>> $eligibleBySegment Eligible bestowals by segment.
     * @return array<int, string>
     */
    private function eligibleSegmentOptionsFromMap(Bestowal $bestowal, array $segments, array $eligibleBySegment): array
    {
        $segmentsById = [];
        foreach ($segments as $segment) {
            $segmentsById[(int)$segment->id] = $segment;
        }

        $options = [];
        $bestowalId = (int)$bestowal->id;
        foreach ($eligibleBySegment as $segmentId => $bestowals) {
            foreach ($bestowals as $eligibleBestowal) {
                if ((int)$eligibleBestowal->id === $bestowalId && isset($segmentsById[(int)$segmentId])) {
                    $options[(int)$segmentId] = (string)$segmentsById[(int)$segmentId]->name;
                    break;
                }
            }
        }

        return $options;
    }

    /**
     * @param int $awardId Award ID.
     * @param int $activityId Gathering activity ID.
     * @return string
     */
    private function awardActivityEligibilityKey(int $awardId, int $activityId): string
    {
        return $awardId . ':' . $activityId;
    }

    /**
     * @param int $gatheringId Gathering ID.
     * @return array<int|string, string>
     */
    private function scheduledActivityOptions(int $gatheringId): array
    {
        $options = [];
        foreach ($this->eligibleScheduledActivitiesForGathering($gatheringId) as $activity) {
            $options[(int)$activity->id] = $this->activityDisplayLabel($activity);
        }

        return $options;
    }

    /**
     * @param \Awards\Model\Entity\Bestowal $bestowal Bestowal.
     * @return string
     */
    private function bestowalAwardLabel(Bestowal $bestowal): string
    {
        if ($bestowal->award === null) {
            return '';
        }

        return (string)($bestowal->award->abbreviation ?? $bestowal->award->name ?? '');
    }

    /**
     * @param int $gatheringId Gathering ID.
     * @return array<int, object>
     */
    private function eligibleScheduledActivitiesForGathering(int $gatheringId): array
    {
        $bestowals = $this->fetchTable('Awards.Bestowals');
        $awardRows = $bestowals->find()
            ->select(['award_id'])
            ->where([
                'Bestowals.gathering_id' => $gatheringId,
                'Bestowals.award_id IS NOT' => null,
                'Bestowals.lifecycle_status NOT IN' => [
                    Bestowal::LIFECYCLE_GIVEN,
                    Bestowal::LIFECYCLE_CANCELLED,
                ],
            ])
            ->enableHydration(false)
            ->all();
        $awardIds = [];
        foreach ($awardRows as $row) {
            $awardIds[(int)$row['award_id']] = (int)$row['award_id'];
        }

        $scheduledRows = $bestowals->find()
            ->select(['gathering_scheduled_activity_id'])
            ->where([
                'Bestowals.gathering_id' => $gatheringId,
                'Bestowals.gathering_scheduled_activity_id IS NOT' => null,
            ])
            ->enableHydration(false)
            ->all();
        $alreadyScheduledActivityIds = [];
        foreach ($scheduledRows as $row) {
            $alreadyScheduledActivityIds[(int)$row['gathering_scheduled_activity_id']] =
                (int)$row['gathering_scheduled_activity_id'];
        }

        $activityIds = [];
        if ($awardIds !== []) {
            $activityRows = $this->fetchTable('Awards.AwardGatheringActivities')->find()
                ->select(['gathering_activity_id'])
                ->where(['award_id IN' => array_values($awardIds)])
                ->enableHydration(false)
                ->all();
            foreach ($activityRows as $row) {
                $activityIds[(int)$row['gathering_activity_id']] = (int)$row['gathering_activity_id'];
            }
        }

        $conditions = ['GatheringScheduledActivities.gathering_id' => $gatheringId];
        if ($activityIds !== [] && $alreadyScheduledActivityIds !== []) {
            $conditions['OR'] = [
                'GatheringScheduledActivities.gathering_activity_id IN' => array_values($activityIds),
                'GatheringScheduledActivities.id IN' => array_values($alreadyScheduledActivityIds),
            ];
        } elseif ($activityIds !== []) {
            $conditions['GatheringScheduledActivities.gathering_activity_id IN'] = array_values($activityIds);
        } elseif ($alreadyScheduledActivityIds !== []) {
            $conditions['GatheringScheduledActivities.id IN'] = array_values($alreadyScheduledActivityIds);
        } else {
            return [];
        }

        return $this->fetchTable('GatheringScheduledActivities')->find()
            ->where($conditions)
            ->contain(['GatheringActivities'])
            ->orderBy([
                'GatheringScheduledActivities.start_datetime' => 'ASC',
                'GatheringScheduledActivities.id' => 'ASC',
            ])
            ->all()
            ->toList();
    }

    /**
     * @param \Awards\Model\Entity\Bestowal $bestowal Bestowal.
     * @param \Awards\Model\Entity\CourtAgendaSegment $segment Target segment.
     * @return void
     */
    private function assertBestowalCanUseSegment(Bestowal $bestowal, CourtAgendaSegment $segment): void
    {
        if (!$this->bestowalCanUseSegment($bestowal, $segment)) {
            throw new RuntimeException('This award cannot be given in that court session.');
        }
    }

    /**
     * @param \Awards\Model\Entity\Bestowal $bestowal Bestowal.
     * @param \Awards\Model\Entity\CourtAgendaSegment $segment Target segment.
     * @return bool
     */
    private function bestowalCanUseSegment(Bestowal $bestowal, CourtAgendaSegment $segment): bool
    {
        if ($this->isRoamingSegment($segment)) {
            return true;
        }
        if (empty($segment->gathering_scheduled_activity_id)) {
            return false;
        }

        $scheduledActivity = $segment->gathering_scheduled_activity ?? null;
        if ($scheduledActivity === null) {
            $scheduledActivity = $this->fetchTable('GatheringScheduledActivities')->get(
                (int)$segment->gathering_scheduled_activity_id,
            );
        }
        if ((int)$scheduledActivity->gathering_id !== (int)$bestowal->gathering_id) {
            return false;
        }
        $activityId = $scheduledActivity->gathering_activity_id;
        if ($activityId === null || $bestowal->award_id === null) {
            return false;
        }

        $key = $this->awardActivityEligibilityKey((int)$bestowal->award_id, (int)$activityId);
        if (array_key_exists($key, $this->awardActivityEligibility)) {
            return $this->awardActivityEligibility[$key];
        }

        return $this->awardActivityEligibility[$key] = $this->fetchTable('Awards.AwardGatheringActivities')->exists([
            'award_id' => (int)$bestowal->award_id,
            'gathering_activity_id' => (int)$activityId,
        ]);
    }

    /**
     * @param int $scheduledActivityId Scheduled gathering activity ID.
     * @param int $gatheringId Agenda gathering ID.
     * @return void
     */
    private function assertScheduledActivityBelongsToGathering(int $scheduledActivityId, int $gatheringId): void
    {
        $belongsToGathering = $this->fetchTable('GatheringScheduledActivities')->exists([
            'GatheringScheduledActivities.id' => $scheduledActivityId,
            'GatheringScheduledActivities.gathering_id' => $gatheringId,
        ]);
        if (!$belongsToGathering) {
            throw new RuntimeException('Court sessions must belong to this gathering.');
        }
    }

    /**
     * @param \Awards\Model\Entity\Bestowal $bestowal Bestowal.
     * @param \Awards\Model\Entity\CourtAgendaSegment $segment Target segment.
     * @param int|null $actorId Actor ID.
     * @return void
     */
    private function syncBestowalPlacementFromSegment(
        Bestowal $bestowal,
        CourtAgendaSegment $segment,
        ?int $actorId,
    ): void {
        if ($this->isRoamingSegment($segment)) {
            $bestowal->set('roaming_court', true, ['guard' => false]);
            $bestowal->set('gathering_scheduled_activity_id', null, ['guard' => false]);
        } else {
            $bestowal->set('roaming_court', false, ['guard' => false]);
            $bestowal->set(
                'gathering_scheduled_activity_id',
                $segment->gathering_scheduled_activity_id !== null
                    ? (int)$segment->gathering_scheduled_activity_id
                    : null,
                ['guard' => false],
            );
        }
        $bestowal->set('modified_by', $actorId, ['guard' => false]);
        $this->fetchTable('Awards.Bestowals')->saveOrFail($bestowal);
        $result = (new ActionItemService())->autoCompleteSatisfiedRequirements(
            Bestowal::ACTION_ITEM_ENTITY_TYPE,
            (int)$bestowal->id,
            $actorId,
        );
        if (!$result->success) {
            throw new RuntimeException((string)$result->reason);
        }
    }

    /**
     * @param \Awards\Model\Entity\Bestowal $bestowal Bestowal.
     * @param int|null $actorId Actor ID.
     * @return void
     */
    private function clearBestowalCourtAssignment(Bestowal $bestowal, ?int $actorId): void
    {
        $bestowal->set('roaming_court', false, ['guard' => false]);
        $bestowal->set('gathering_scheduled_activity_id', null, ['guard' => false]);
        $bestowal->set('modified_by', $actorId, ['guard' => false]);
        $this->fetchTable('Awards.Bestowals')->saveOrFail($bestowal);

        $result = (new ActionItemService())->syncRequiredFieldCompletionStates(
            Bestowal::ACTION_ITEM_ENTITY_TYPE,
            (int)$bestowal->id,
            $actorId,
        );
        if (!$result->success) {
            throw new RuntimeException((string)$result->reason);
        }
    }

    /**
     * @param int $agendaId Agenda ID.
     * @return void
     */
    private function syncBestowalStackRanksForAgenda(int $agendaId): void
    {
        $segments = $this->fetchTable('Awards.CourtAgendaSegments')->find()
            ->where(['court_agenda_id' => $agendaId])
            ->contain(['CourtAgendas'])
            ->all();
        foreach ($segments as $segment) {
            $this->syncBestowalStackRanksForSegmentEntity($segment);
        }
    }

    /**
     * @param int $segmentId Segment ID.
     * @return void
     */
    private function syncBestowalStackRanksForSegment(int $segmentId): void
    {
        $segment = $this->fetchTable('Awards.CourtAgendaSegments')->get(
            $segmentId,
            contain: ['CourtAgendas'],
        );
        $this->syncBestowalStackRanksForSegmentEntity($segment);
    }

    /**
     * @param \Awards\Model\Entity\CourtAgendaSegment $segment Segment.
     * @return void
     */
    private function syncBestowalStackRanksForSegmentEntity(CourtAgendaSegment $segment): void
    {
        $gatheringId = (int)$segment->court_agenda->gathering_id;

        $items = $this->fetchTable('Awards.CourtAgendaItems')->find()
            ->where([
                'court_agenda_segment_id' => (int)$segment->id,
                'bestowal_id IS NOT' => null,
            ])
            ->orderBy(['sort_order' => 'ASC', 'id' => 'ASC'])
            ->all();
        $orderedBestowalIds = [];
        foreach ($items as $item) {
            $orderedBestowalIds[(int)$item->bestowal_id] = (int)$item->bestowal_id;
        }

        $bestowals = $this->fetchTable('Awards.Bestowals');
        $query = $bestowals->find()
            ->where(['gathering_id' => $gatheringId]);
        if ($this->isRoamingSegment($segment)) {
            $query->where(['roaming_court' => true]);
        } elseif ($segment->gathering_scheduled_activity_id !== null) {
            $query->where([
                'gathering_scheduled_activity_id' => (int)$segment->gathering_scheduled_activity_id,
                'roaming_court' => false,
            ]);
        } else {
            $query->where([
                'gathering_scheduled_activity_id IS' => null,
                'roaming_court' => false,
            ]);
        }

        $remaining = $query
            ->orderBy(['stack_rank' => 'ASC', 'id' => 'ASC'])
            ->all()
            ->combine('id', static fn($bestowal) => $bestowal)
            ->toArray();

        $ordered = [];
        foreach ($orderedBestowalIds as $bestowalId) {
            if (isset($remaining[$bestowalId])) {
                $ordered[] = $remaining[$bestowalId];
                unset($remaining[$bestowalId]);
            }
        }
        foreach ($remaining as $bestowal) {
            $ordered[] = $bestowal;
        }

        $rank = 10;
        $changed = [];
        foreach ($ordered as $bestowal) {
            if ((int)$bestowal->stack_rank === $rank) {
                $rank += 10;
                continue;
            }
            $bestowal->set('stack_rank', $rank, ['guard' => false]);
            $changed[] = $bestowal;
            $rank += 10;
        }
        if ($changed !== []) {
            $bestowals->saveManyOrFail($changed);
        }
    }

    /**
     * @param int $agendaId Agenda ID.
     * @param int|null $actorId Actor ID.
     * @return \Awards\Model\Entity\CourtAgendaSegment
     */
    private function ensureRoamingSegment(int $agendaId, ?int $actorId = null): CourtAgendaSegment
    {
        $segments = $this->fetchTable('Awards.CourtAgendaSegments');
        $segment = $segments->find()
            ->where([
                'court_agenda_id' => $agendaId,
                'gathering_scheduled_activity_id IS' => null,
                'name' => self::ROAMING_SEGMENT_NAME,
            ])
            ->first();
        if ($segment !== null) {
            return $segment;
        }

        return $segments->saveOrFail($segments->newEntity([
            'court_agenda_id' => $agendaId,
            'name' => self::ROAMING_SEGMENT_NAME,
            'court_type' => CourtAgendaSegment::TYPE_COURT,
            'sort_order' => $this->nextSegmentSortOrder($agendaId),
            'created_by' => $actorId,
            'modified_by' => $actorId,
        ]));
    }

    /**
     * @param \Awards\Model\Entity\CourtAgendaSegment $segment Segment.
     * @return bool
     */
    private function isRoamingSegment(CourtAgendaSegment $segment): bool
    {
        return $segment->gathering_scheduled_activity_id === null
            && (string)$segment->name === self::ROAMING_SEGMENT_NAME;
    }

    /**
     * @param \Awards\Model\Entity\CourtAgendaSegment $segment Segment.
     * @return string
     */
    private function scheduledActivityLabel(CourtAgendaSegment $segment): string
    {
        if ($this->isRoamingSegment($segment)) {
            return __('Roaming Court');
        }
        if ($segment->gathering_scheduled_activity === null) {
            return __('Unlinked legacy court activity');
        }

        return $this->activityDisplayLabel($segment->gathering_scheduled_activity);
    }

    /**
     * @param object $activity Scheduled activity.
     * @return string
     */
    private function activityDisplayLabel(object $activity): string
    {
        $time = $this->activityTimeLabel($activity);
        if ($time === null || $time === '') {
            return (string)$activity->display_title;
        }

        return (string)$activity->display_title . ' - ' . $time;
    }

    /**
     * @param object $activity Scheduled activity.
     * @return string|null
     */
    private function activityTimeLabel(object $activity): ?string
    {
        if (empty($activity->start_datetime)) {
            return null;
        }
        $start = $activity->start_datetime;
        if (empty($activity->end_datetime)) {
            return $start->format('D g:i A');
        }

        return $start->format('D g:i A') . ' - ' . $activity->end_datetime->format('g:i A');
    }

    /**
     * @param int $agendaId Agenda ID.
     * @param object $bestowal Bestowal entity.
     * @param int|null $actorId Actor ID.
     * @return int
     */
    private function segmentIdForBestowal(int $agendaId, object $bestowal, ?int $actorId): int
    {
        $segments = $this->fetchTable('Awards.CourtAgendaSegments');
        $activityId = $bestowal->gathering_scheduled_activity_id ?? null;
        if (!empty($bestowal->roaming_court)) {
            return (int)$this->ensureRoamingSegment($agendaId, $actorId)->id;
        }
        if ($activityId === null) {
            throw new RuntimeException('Bestowal must be assigned to a scheduled court activity or roaming court.');
        }
        $segment = $segments->find()
            ->where([
                'court_agenda_id' => $agendaId,
                'gathering_scheduled_activity_id' => (int)$activityId,
            ])
            ->first();
        if ($segment !== null) {
            return (int)$segment->id;
        }
        $activity = $bestowal->gathering_scheduled_activity ?? $this->fetchTable('GatheringScheduledActivities')->get(
            (int)$activityId,
        );
        if ((int)$activity->gathering_id !== (int)$bestowal->gathering_id) {
            throw new RuntimeException('Court activity must belong to the bestowal gathering.');
        }

        $segment = $segments->newEntity([
            'court_agenda_id' => $agendaId,
            'gathering_scheduled_activity_id' => $activityId,
            'name' => (string)$activity->display_title,
            'court_type' => CourtAgendaSegment::TYPE_COURT,
            'sort_order' => $this->nextSegmentSortOrder($agendaId),
            'planned_start_time' => $this->activityTimeLabel($activity),
            'created_by' => $actorId,
            'modified_by' => $actorId,
        ]);
        $segment = $segments->saveOrFail($segment);

        return (int)$segment->id;
    }

    /**
     * @param int $agendaId Agenda ID.
     * @return int
     */
    private function nextSegmentSortOrder(int $agendaId): int
    {
        return $this->nextSortOrder($this->fetchTable('Awards.CourtAgendaSegments'), 'court_agenda_id', $agendaId);
    }

    /**
     * @param int $segmentId Segment ID.
     * @return int
     */
    private function nextItemSortOrder(int $segmentId): int
    {
        return $this->nextSortOrder(
            $this->fetchTable('Awards.CourtAgendaItems'),
            'court_agenda_segment_id',
            $segmentId,
        );
    }

    /**
     * @param \Cake\ORM\Table $table Table instance.
     * @param string $foreignKey Foreign key.
     * @param int $foreignId Foreign ID.
     * @return int
     */
    private function nextSortOrder(Table $table, string $foreignKey, int $foreignId): int
    {
        $row = $table->find()
            ->select(['max_order' => $table->find()->func()->max('sort_order')])
            ->where([$foreignKey => $foreignId])
            ->first();

        return (int)($row->max_order ?? 0) + 10;
    }

    /**
     * @param int $segmentId Segment ID.
     * @return void
     */
    private function renumberItems(int $segmentId): void
    {
        $items = $this->fetchTable('Awards.CourtAgendaItems');
        $rows = $items->find()
            ->where(['court_agenda_segment_id' => $segmentId])
            ->orderBy(['sort_order' => 'ASC', 'id' => 'ASC'])
            ->all();
        $order = 10;
        $changed = [];
        foreach ($rows as $row) {
            if ((int)$row->sort_order === $order) {
                $order += 10;
                continue;
            }
            $row->sort_order = $order;
            $changed[] = $row;
            $order += 10;
        }
        if ($changed !== []) {
            $items->saveManyOrFail($changed);
        }
    }

    /**
     * Collapse duplicate blank auto-created segments from earlier imports.
     *
     * @param int $agendaId Agenda ID.
     * @param int|null $actorId Actor ID.
     * @return void
     */
    private function consolidateAutoSegments(int $agendaId, ?int $actorId): void
    {
        $segments = $this->fetchTable('Awards.CourtAgendaSegments');
        $this->deleteEmptyLegacyCourtSegments($agendaId);

        foreach (['Roaming Court'] as $name) {
            $rows = $segments->find()
                ->where([
                    'court_agenda_id' => $agendaId,
                    'gathering_scheduled_activity_id IS' => null,
                    'name' => $name,
                    'notes IS' => null,
                    'planned_start_time IS' => null,
                    'planned_duration_minutes' => 0,
                ])
                ->orderBy(['sort_order' => 'ASC', 'id' => 'ASC'])
                ->all()
                ->toList();
            if (count($rows) < 2) {
                continue;
            }

            $primarySegment = array_shift($rows);
            foreach ($rows as $duplicateSegment) {
                $this->moveSegmentItems((int)$duplicateSegment->id, (int)$primarySegment->id, $actorId);
                $segments->deleteOrFail($duplicateSegment);
            }
            $this->renumberItems((int)$primarySegment->id);
        }
    }

    /**
     * @param int $agendaId Agenda ID.
     * @return void
     */
    private function deleteEmptyLegacyCourtSegments(int $agendaId): void
    {
        $segments = $this->fetchTable('Awards.CourtAgendaSegments');
        $items = $this->fetchTable('Awards.CourtAgendaItems');
        $rows = $segments->find()
            ->where([
                'court_agenda_id' => $agendaId,
                'gathering_scheduled_activity_id IS' => null,
                'name' => 'Court Agenda',
                'notes IS' => null,
                'planned_start_time IS' => null,
                'planned_duration_minutes' => 0,
            ])
            ->all();
        foreach ($rows as $segment) {
            $hasItems = $items->exists(['court_agenda_segment_id' => (int)$segment->id]);
            if (!$hasItems) {
                $segments->deleteOrFail($segment);
            }
        }
    }

    /**
     * @param \Awards\Model\Entity\CourtAgendaSegment $segment Segment.
     * @return void
     */
    private function assertSegmentCanAcceptAgendaItems(CourtAgendaSegment $segment): void
    {
        if ($segment->gathering_scheduled_activity_id === null && !$this->isRoamingSegment($segment)) {
            throw new RuntimeException('Agenda items can only be added to scheduled court activities.');
        }
    }

    /**
     * @param int $sourceSegmentId Source segment ID.
     * @param int $targetSegmentId Target segment ID.
     * @param int|null $actorId Actor ID.
     * @return void
     */
    private function moveSegmentItems(int $sourceSegmentId, int $targetSegmentId, ?int $actorId): void
    {
        $items = $this->fetchTable('Awards.CourtAgendaItems');
        $rows = $items->find()
            ->where(['court_agenda_segment_id' => $sourceSegmentId])
            ->orderBy(['sort_order' => 'ASC', 'id' => 'ASC'])
            ->all();
        $sortOrder = $this->nextItemSortOrder($targetSegmentId);
        $changed = [];
        foreach ($rows as $item) {
            $item->court_agenda_segment_id = $targetSegmentId;
            $item->sort_order = $sortOrder;
            $item->modified_by = $actorId;
            $changed[] = $item;
            $sortOrder += 10;
        }
        if ($changed !== []) {
            $items->saveManyOrFail($changed);
        }
    }

    /**
     * @param \Awards\Model\Entity\CourtAgendaItem $item Agenda item.
     * @return string
     */
    private function itemLabel(CourtAgendaItem $item): string
    {
        if ($item->item_type === CourtAgendaItem::TYPE_BLOCK) {
            return (string)$item->title;
        }

        return (string)($item->bestowal->member->sca_name ?? __('Unknown recipient'));
    }

    /**
     * @param \Awards\Model\Entity\CourtAgendaItem $item Agenda item.
     * @return string
     */
    private function awardLabel(CourtAgendaItem $item): string
    {
        if ($item->bestowal === null || $item->bestowal->award === null) {
            return '';
        }

        return (string)($item->bestowal->award->abbreviation ?? $item->bestowal->award->name ?? '');
    }

    /**
     * @param \Awards\Model\Entity\CourtAgendaItem $item Agenda item.
     * @return string
     */
    private function durationHint(CourtAgendaItem $item): string
    {
        if ($item->item_type === CourtAgendaItem::TYPE_BLOCK) {
            return __('Manual block');
        }

        $level = (string)($item->bestowal->award->level->name ?? '');
        if (stripos($level, 'peer') !== false) {
            return __('Peerage hint: 10-20 min');
        }
        if (stripos($level, 'grant') !== false) {
            return __('Grant-level hint: 6-10 min');
        }

        return __('AoA/standard hint: 3-5 min');
    }

    /**
     * @param \Awards\Model\Entity\CourtAgendaItem $item Agenda item.
     * @return array<int, string>
     */
    private function recommendationReasons(CourtAgendaItem $item): array
    {
        if (!$item->include_reasons || $item->bestowal === null) {
            return [];
        }

        $reasons = [];
        foreach ($item->bestowal->recommendations ?? [] as $recommendation) {
            $reason = trim((string)($recommendation->reason ?? ''));
            if ($reason !== '') {
                $reasons[] = $reason;
            }
        }

        return $reasons;
    }

    /**
     * @param \Awards\Model\Entity\CourtAgendaItem $item Agenda item.
     * @return array<int, string>
     */
    private function recommendationSpecialties(CourtAgendaItem $item): array
    {
        if (!$item->include_specialties || $item->bestowal === null) {
            return [];
        }

        $bestowalSpecialty = trim((string)($item->bestowal->specialty ?? ''));
        if ($bestowalSpecialty !== '') {
            return [$bestowalSpecialty];
        }

        $specialties = [];
        foreach ($item->bestowal->recommendations ?? [] as $recommendation) {
            $specialty = trim((string)($recommendation->specialty ?? ''));
            if ($specialty !== '') {
                $specialties[] = $specialty;
            }
        }

        return array_values(array_unique($specialties));
    }

    /**
     * @param mixed $value Value.
     * @return mixed
     */
    private function emptyToNull(mixed $value): mixed
    {
        return $value === '' ? null : $value;
    }
}
