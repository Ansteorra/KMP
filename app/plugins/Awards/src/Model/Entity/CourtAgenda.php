<?php
declare(strict_types=1);

namespace Awards\Model\Entity;

use App\Model\Entity\BaseEntity;

/**
 * Persistent gathering-level court agenda.
 *
 * @property int $id
 * @property int $gathering_id
 * @property string $name
 * @property string|null $description
 * @property bool $is_default
 * @property \Cake\I18n\DateTime|null $modified
 * @property \Cake\I18n\DateTime $created
 * @property int|null $created_by
 * @property int|null $modified_by
 * @property \Cake\I18n\DateTime|null $deleted
 *
 * @property \App\Model\Entity\Gathering $gathering
 * @property \Awards\Model\Entity\CourtAgendaSegment[] $court_agenda_segments
 */
class CourtAgenda extends BaseEntity
{
    /**
     * @var array<string, bool>
     */
    protected array $_accessible = [
        'gathering_id' => true,
        'name' => true,
        'description' => true,
        'is_default' => true,
        'modified' => true,
        'created' => true,
        'created_by' => true,
        'modified_by' => true,
        'deleted' => true,
        'gathering' => true,
        'court_agenda_segments' => true,
    ];

    /**
     * @return int|null
     */
    public function getBranchId(): ?int
    {
        return $this->gathering->branch_id ?? null;
    }
}
