<?php
declare(strict_types=1);

namespace Awards\Services;

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
            $this->ensureDefaultSegments((int)$agenda->id);

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
            ->contain(['Awards' => ['Levels'], 'GatheringScheduledActivities'])
            ->orderBy([
                'Bestowals.gathering_scheduled_activity_id' => 'ASC',
                'Bestowals.roaming_court' => 'DESC',
                'Bestowals.stack_rank' => 'ASC',
                'Bestowals.id' => 'ASC',
            ])
            ->all();

        $imported = 0;
        $items = $this->fetchTable('Awards.CourtAgendaItems');
        foreach ($bestowals as $bestowal) {
            $segmentId = $this->segmentIdForBestowal($agendaId, $bestowal, $actorId);
            $exists = $items->find()
                ->contain(['CourtAgendaSegments'])
                ->where([
                    'CourtAgendaSegments.court_agenda_id' => $agendaId,
                    'CourtAgendaItems.bestowal_id' => (int)$bestowal->id,
                    'CourtAgendaItems.role' => CourtAgendaItem::ROLE_PRESENT,
                ])
                ->first();
            if ($exists !== null) {
                continue;
            }

            $item = $items->newEntity([
                'court_agenda_segment_id' => $segmentId,
                'bestowal_id' => (int)$bestowal->id,
                'item_type' => CourtAgendaItem::TYPE_BESTOWAL,
                'role' => CourtAgendaItem::ROLE_PRESENT,
                'sort_order' => $this->nextItemSortOrder($segmentId),
                'planned_action' => null,
                'estimated_minutes' => $this->estimateMinutesForBestowal($bestowal),
                'duration_locked' => false,
                'include_reasons' => true,
                'include_specialties' => true,
                'created_by' => $actorId,
                'modified_by' => $actorId,
            ]);
            $items->saveOrFail($item);
            $imported++;
        }
        $this->consolidateAutoSegments((int)$agenda->id, $actorId);

        return $imported;
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
                'GatheringScheduledActivities',
                'CourtAgendaItems',
            ],
        ]);
        $this->hydrateAgendaItemBestowals($agenda->court_agenda_segments ?? []);

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
                    'minutes' => $minutes,
                ];
            }

            $agendaMinutes += $segmentMinutes;
            $segments[] = [
                'entity' => $segment,
                'items' => $items,
                'minutes' => $segmentMinutes,
                'warning' => $segmentMinutes >= self::LONG_SEGMENT_MINUTES
                    ? __('Consider a break or splitting this court segment.')
                    : null,
            ];
        }

        return [
            'agenda' => $agenda,
            'segments' => $segments,
            'totalMinutes' => $agendaMinutes,
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

        $segment = $segments->newEntity([
            'court_agenda_id' => $agendaId,
            'gathering_scheduled_activity_id' => $this->emptyToNull($data['gathering_scheduled_activity_id'] ?? null),
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

        $item = $items->newEntity([
            'court_agenda_segment_id' => $segmentId,
            'item_type' => CourtAgendaItem::TYPE_BLOCK,
            'role' => CourtAgendaItem::ROLE_BREAK,
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
                $item->court_agenda_segment_id = $targetSegmentId;
                $item->sort_order = max(0, $targetSortOrder);
                $item->modified_by = $actorId;
                $items->saveOrFail($item);
                if ($sourceSegmentId !== $targetSegmentId) {
                    $this->renumberItems($sourceSegmentId);
                }
                $this->renumberItems($targetSegmentId);
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
     * @param int $agendaId Agenda ID.
     * @return void
     */
    private function ensureDefaultSegments(int $agendaId): void
    {
        $segments = $this->fetchTable('Awards.CourtAgendaSegments');
        if ($segments->find()->where(['court_agenda_id' => $agendaId])->count() > 0) {
            return;
        }

        $segments->saveOrFail($segments->newEntity([
            'court_agenda_id' => $agendaId,
            'name' => 'Court Agenda',
            'court_type' => CourtAgendaSegment::TYPE_COURT,
            'sort_order' => 10,
        ]));
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
        if ($activityId !== null) {
            $segment = $segments->find()
                ->where([
                    'court_agenda_id' => $agendaId,
                    'gathering_scheduled_activity_id' => (int)$activityId,
                ])
                ->first();
            if ($segment !== null) {
                return (int)$segment->id;
            }
        }

        $name = !empty($bestowal->roaming_court)
            ? 'Roaming Court'
            : (string)($bestowal->gathering_scheduled_activity->display_title ?? 'Court Agenda');
        $segment = $segments->find()
            ->where([
                'court_agenda_id' => $agendaId,
                'gathering_scheduled_activity_id IS' => null,
                'name' => $name,
            ])
            ->first();
        if ($segment !== null) {
            return (int)$segment->id;
        }

        $segment = $segments->newEntity([
            'court_agenda_id' => $agendaId,
            'gathering_scheduled_activity_id' => $activityId,
            'name' => $name,
            'court_type' => CourtAgendaSegment::TYPE_COURT,
            'sort_order' => $this->nextSegmentSortOrder($agendaId),
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
        foreach ($rows as $row) {
            $row->sort_order = $order;
            $items->saveOrFail($row);
            $order += 10;
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
        foreach (['Court Agenda', 'Roaming Court'] as $name) {
            $segments = $this->fetchTable('Awards.CourtAgendaSegments');
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
        foreach ($rows as $item) {
            $item->court_agenda_segment_id = $targetSegmentId;
            $item->sort_order = $this->nextItemSortOrder($targetSegmentId);
            $item->modified_by = $actorId;
            $items->saveOrFail($item);
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
