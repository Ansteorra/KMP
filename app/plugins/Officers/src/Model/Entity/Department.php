<?php

declare(strict_types=1);

namespace Officers\Model\Entity;

use Cake\ORM\Entity;
use App\Model\Entity\BaseEntity;

/**
 * Department Entity - Organizational structure for officer categorization
 *
 * Groups offices into logical departmental units (e.g., Marshallate, Exchequer, Herald).
 * Serves as top-level organizational unit in the officer hierarchy system.
 *
 * @property int $id Primary key
 * @property string $name Department name (unique, required)
 * @property string $domain Operational domain designation
 * @property \Cake\I18n\Date|null $deleted Soft deletion timestamp
 * @property \Cake\I18n\DateTime $created Record creation timestamp
 * @property \Cake\I18n\DateTime $modified Last modification timestamp
 * @property int|null $created_by User ID who created this record
 * @property int|null $modified_by User ID who last modified this record
 * @property \Officers\Model\Entity\Office[] $offices Associated office positions
 *
 * @see /docs/5.1-officers-plugin.md
 * @see \Officers\Model\Table\DepartmentsTable
 */
class Department extends BaseEntity
{
    /**
     * Fields that can be mass assigned using newEntity() or patchEntity().
     *
     * @var array<string, bool>
     */
    protected array $_accessible = [
        'name' => true,
        'domain' => true,
        'deleted' => true,
        'offices' => true,
    ];
}
