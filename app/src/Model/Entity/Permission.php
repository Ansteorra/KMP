<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;



/**
 * Permission Entity
 *
 * @property int $id
 * @property string $name
 * @property int|null $authorization_type_id
 * @property bool $system
 * @property bool $is_super_user
 *
 * @property \App\Model\Entity\AuthorizationType $authorization_type
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
        'name' => true,
        'authorization_type_id' => true,
        'system' => true,
        'is_super_user' => true,
        'authorization_type' => true,
        'roles' => true,
    ];
}
