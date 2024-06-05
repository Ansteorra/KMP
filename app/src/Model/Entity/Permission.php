<?php

declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * Permission Entity
 *
 * @property int $id
 * @property string $name
 * @property int|null $activity_id
 * @property bool $require_active_membership
 * @property bool $require_active_background_check
 * @property bool $require_min_age
 * @property bool $system
 * @property bool $is_super_user
 *
 * @property \App\Model\Entity\Activity $activity
 * @property \App\Model\Entity\Role[] $roles
 */
class Permission extends Entity
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
        "name" => true,
        "activity_id" => true,
        "require_active_membership" => true,
        "require_active_background_check" => true,
        "require_min_age" => true,
        "system" => true,
        "is_super_user" => true,
        "activity" => true,
        "requires_warrant" => true,
        "roles" => true,
    ];
}
