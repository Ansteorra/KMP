<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * Office Entity
 *
 * @property int $id
 * @property string $name
 * @property int|null $department_id
 * @property bool $requires_warrant
 * @property bool $obly_one_per_branch
 * @property bool $is_at_large
 * @property int|null $deputy_to_id
 * @property int|null $grants_role_id
 * @property int $length
 * @property \Cake\I18n\Date|null $deleted
 *
 * @property \App\Model\Entity\Department $department
 * @property \App\Model\Entity\Officer[] $officers
 */
class Office extends Entity
{
    /**
     * Fields that can be mass assigned using newEntity() or patchEntity().
     *
     * Note that when '*' is set to true, this allows all unspecified fields to
     * be mass assigned. For security purposes, it is advised to set '*' to false
     * (or remove it), and explicitly make individual fields accessible as needed.
     *
     * @var array<string, bool>
     */
    protected array $_accessible = [
        'name' => true,
        'department_id' => true,
        'requires_warrant' => true,
        'obly_one_per_branch' => true,
        'is_at_large' => true,
        'deputy_to_id' => true,
        'grants_role_id' => true,
        'length' => true,
        'deleted' => true,
        'department' => true,
        'officers' => true,
    ];
}
