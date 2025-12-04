<?php

declare(strict_types=1);

namespace Awards\Model\Entity;

use Cake\ORM\Entity;
use App\Model\Entity\BaseEntity;

/**
 * Event Entity - Award ceremony and presentation events.
 *
 * Events are scheduled occasions where recommendations are fulfilled through
 * formal presentation (e.g., Royal Courts, Baronial Courts, Special Events).
 *
 * @property int $id
 * @property string $name
 * @property string $description
 * @property int $branch_id
 * @property \Cake\I18n\DateTime|null $start_date
 * @property \Cake\I18n\DateTime|null $end_date
 * @property bool|null $closed
 * @property \Cake\I18n\DateTime|null $modified
 * @property \Cake\I18n\DateTime $created
 * @property int|null $created_by
 * @property int|null $modified_by
 * @property \Cake\I18n\DateTime|null $deleted
 *
 * @property \App\Model\Entity\Branch $branch
 */
class Event extends BaseEntity
{
    /**
     * @var array<string, bool>
     */
    protected array $_accessible = [
        'name' => true,
        'description' => true,
        'branch_id' => true,
        'start_date' => true,
        'end_date' => true,
        'modified' => true,
        'created' => true,
        'created_by' => true,
        'modified_by' => true,
        'deleted' => true,
        'branch' => true,
        'closed' => true,
    ];
}
