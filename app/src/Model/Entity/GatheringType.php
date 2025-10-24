<?php

declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * GatheringType Entity
 *
 * Defines types of gatherings (e.g., Tournament, Practice, Feast, Court).
 * Gathering types can be marked as clonable to serve as templates.
 *
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property bool $clonable
 * @property \Cake\I18n\DateTime $created
 * @property \Cake\I18n\DateTime $modified
 *
 * @property \App\Model\Entity\Gathering[] $gatherings
 */
class GatheringType extends BaseEntity
{
    /**
     * Fields that can be mass assigned using newEntity() or patchEntity().
     *
     * @var array<string, bool>
     */
    protected array $_accessible = [
        'name' => true,
        'description' => true,
        'clonable' => true,
        'created' => true,
        'modified' => true,
        'gatherings' => true,
    ];
}
