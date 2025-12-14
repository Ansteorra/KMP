<?php

declare(strict_types=1);

namespace Awards\Model\Entity;

use Cake\ORM\Entity;
use App\Model\Entity\BaseEntity;

/**
 * Level Entity - Award precedence tier (e.g., Entry, Advanced, Master, Peerage).
 *
 * Progression order determines hierarchical ranking for ceremonies and member advancement.
 *
 * @property int $id
 * @property string $name
 * @property int|null $progression_order
 * @property \Cake\I18n\DateTime|null $modified
 * @property \Cake\I18n\DateTime $created
 * @property int|null $created_by
 * @property int|null $modified_by
 * @property \Cake\I18n\DateTime|null $deleted
 */
class Level extends BaseEntity
{
    /**
     * @var array<string, bool>
     */
    protected array $_accessible = [
        'name' => true,
        'progression_order' => true,
        'modified' => true,
        'created' => true,
        'created_by' => true,
        'modified_by' => true,
        'deleted' => true,
    ];
}
