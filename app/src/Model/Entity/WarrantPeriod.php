<?php

declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * WarrantPeriod Entity
 *
 * @property int $id
 * @property \Cake\I18n\DateTime $start_date
 * @property \Cake\I18n\DateTime $end_date
 * @property \Cake\I18n\DateTime $created
 * @property int|null $created_by
 */
class WarrantPeriod extends BaseEntity
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
        'start_date' => true,
        'end_date' => true,
        'created' => true,
        'created_by' => true,
    ];

    protected function _getName(): string
    {
        return $this->start_date->toDateString() . ' ~ ' . $this->end_date->toDateString();
    }
}
