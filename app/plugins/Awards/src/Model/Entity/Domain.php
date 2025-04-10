<?php

declare(strict_types=1);

namespace Awards\Model\Entity;

use Cake\ORM\Entity;
use App\Model\Entity\BaseEntity;

/**
 * Domain Entity
 *
 * @property int $id
 * @property string $name
 * @property \Cake\I18n\DateTime|null $modified
 * @property \Cake\I18n\DateTime $created
 * @property int|null $created_by
 * @property int|null $modified_by
 * @property \Cake\I18n\DateTime|null $deleted
 */
class Domain extends BaseEntity
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
        'modified' => true,
        'created' => true,
        'created_by' => true,
        'modified_by' => true,
        'deleted' => true,
    ];
}
