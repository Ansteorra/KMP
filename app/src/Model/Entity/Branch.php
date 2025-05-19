<?php
declare(strict_types=1);

namespace App\Model\Entity;

/**
 * Branch Entity
 *
 * @property int $id
 * @property string $name
 * @property string $location
 * @property string $region
 */
class Branch extends BaseEntity
{
    /**
     * Fields that can be mass assigned using newEntity() or patchEntity().
     *
     * Note that when '*' is set to true, this allows all unspecified fields to
     * be mass assigned. For security purposes, it is advised to set '*' to false
     * (or remove it), and explicitly make individual fields accessible as needed.
     *
     * @var array
     */
    protected array $_accessible = [
        '*' => true,
        'id' => false,
    ];
}
