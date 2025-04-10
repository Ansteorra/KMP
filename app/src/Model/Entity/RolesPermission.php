<?php

declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * RolesPermission Entity
 *
 * @property int $id
 * @property int $permission_id
 * @property int $role_id
 * @property \Cake\I18n\DateTime $created
 * @property int $created_by
 *
 * @property \App\Model\Entity\Permission $permission
 * @property \App\Model\Entity\Role $role
 */
class RolesPermission extends BaseEntity
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
        'permission_id' => true,
        'role_id' => true,
        'created' => true,
        'created_by' => true,
        'permission' => true,
        'role' => true,
    ];
}
