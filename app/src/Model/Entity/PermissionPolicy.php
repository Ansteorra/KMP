<?php

declare(strict_types=1);

namespace App\Model\Entity;

/**
 * PermissionPolicy Entity - Dynamic Permission Authorization
 *
 * Associates permissions with custom policy classes and methods for
 * complex, context-aware authorization beyond basic RBAC.
 *
 * @property int $id Primary key
 * @property int $permission_id Foreign key to permissions
 * @property string $policy_class Full policy class name
 * @property string $policy_method Method name in policy class
 * @property \App\Model\Entity\Permission $permission
 */
class PermissionPolicy extends BaseEntity
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
        'policy_class' => true,
        'policy_method' => true,
        'permission' => true,
    ];
}
