<?php
declare(strict_types=1);

namespace Awards\Model\Entity;

use App\Model\Entity\BaseEntity;

/**
 * Named court, break, or planning segment inside a court agenda.
 *
 * @property int $id
 * @property int $court_agenda_id
 * @property int|null $gathering_scheduled_activity_id
 * @property string $name
 * @property string $court_type
 * @property int $sort_order
 * @property string|null $planned_start_time
 * @property int $planned_duration_minutes
 * @property string|null $notes
 *
 * @property \Awards\Model\Entity\CourtAgenda $court_agenda
 * @property \App\Model\Entity\GatheringScheduledActivity|null $gathering_scheduled_activity
 * @property \Awards\Model\Entity\CourtAgendaItem[] $court_agenda_items
 */
class CourtAgendaSegment extends BaseEntity
{
    public const TYPE_COURT = 'court';
    public const TYPE_BREAK = 'break';
    public const TYPE_BUSINESS = 'business';

    /**
     * @var array<string, bool>
     */
    protected array $_accessible = [
        'court_agenda_id' => true,
        'gathering_scheduled_activity_id' => true,
        'name' => true,
        'court_type' => true,
        'sort_order' => true,
        'planned_start_time' => true,
        'planned_duration_minutes' => true,
        'notes' => true,
        'modified' => true,
        'created' => true,
        'created_by' => true,
        'modified_by' => true,
        'deleted' => true,
        'court_agenda' => true,
        'gathering_scheduled_activity' => true,
        'court_agenda_items' => true,
    ];
}
