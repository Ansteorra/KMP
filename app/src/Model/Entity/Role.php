<?php

declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

use JeremyHarris\LazyLoad\ORM\LazyLoadEntityTrait;

/**
 * Role Entity
 *
 * @property int $id
 * @property string $name
 *
 * @property \App\Model\Entity\Member[] $Members
 * @property \App\Model\Entity\Permission[] $permissions
 */
class Role extends Entity
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
        "name" => true,
        "Members" => true,
        "permissions" => true,
    ];
}
