<?php
declare(strict_types=1);

namespace Awards\Model\Entity;

use App\Model\Entity\BaseEntity;

/**
 * Ordered item on a court agenda segment.
 *
 * @property int $id
 * @property int $court_agenda_segment_id
 * @property int|null $bestowal_id
 * @property string $item_type
 * @property string $role
 * @property string|null $title
 * @property int $sort_order
 * @property string|null $planned_action
 * @property int $estimated_minutes
 * @property bool $duration_locked
 * @property string|null $presentation_notes
 * @property string|null $print_notes
 * @property bool $is_optional
 * @property bool $include_reasons
 * @property bool $include_specialties
 *
 * @property \Awards\Model\Entity\CourtAgendaSegment $court_agenda_segment
 * @property \Awards\Model\Entity\Bestowal|null $bestowal
 */
class CourtAgendaItem extends BaseEntity
{
    public const TYPE_BESTOWAL = 'bestowal';
    public const TYPE_BLOCK = 'block';

    public const ROLE_PRESENT = 'present';
    public const ROLE_START = 'start';
    public const ROLE_FINISH = 'finish';
    public const ROLE_ANNOUNCE = 'announce';
    public const ROLE_BREAK = 'break';

    /**
     * @var array<string, bool>
     */
    protected array $_accessible = [
        'court_agenda_segment_id' => true,
        'bestowal_id' => true,
        'item_type' => true,
        'role' => true,
        'title' => true,
        'sort_order' => true,
        'planned_action' => true,
        'estimated_minutes' => true,
        'duration_locked' => true,
        'presentation_notes' => true,
        'print_notes' => true,
        'is_optional' => true,
        'include_reasons' => true,
        'include_specialties' => true,
        'modified' => true,
        'created' => true,
        'created_by' => true,
        'modified_by' => true,
        'deleted' => true,
        'court_agenda_segment' => true,
        'bestowal' => true,
    ];
}
