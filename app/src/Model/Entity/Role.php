<?php

declare(strict_types=1);

namespace App\Model\Entity;

use JeremyHarris\LazyLoad\ORM\LazyLoadEntityTrait;

/**
 * Role Entity - KMP RBAC Role Management
 *
 * Represents a security role containing permissions. Assigned to members
 * through time-bounded MemberRole assignments.
 *
 * @property int $id Primary key
 * @property string $name Unique role name
 * @property \App\Model\Entity\Member[] $Members Members with this role
 * @property \App\Model\Entity\Permission[] $permissions Role permissions
 */
class Role extends BaseEntity
{
    use LazyLoadEntityTrait;

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
        'Members' => true,
        'permissions' => true,
    ];
}
