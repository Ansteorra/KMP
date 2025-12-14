<?php

declare(strict_types=1);

namespace App\Model\Entity;

/**
 * WarrantPeriod Entity - Warrant Period Templates
 *
 * Defines temporal boundaries for warrant activation/expiration. Used as templates
 * for consistent warrant durations and integrates with warrant roster workflows.
 *
 * @property int $id Primary key
 * @property \Cake\I18n\DateTime $start_date Period start date
 * @property \Cake\I18n\DateTime $end_date Period end date
 * @property \Cake\I18n\DateTime $created Creation timestamp
 * @property int|null $created_by Creator member ID
 * @property string $name Virtual: formatted "start_date ~ end_date"
 */
class WarrantPeriod extends BaseEntity
{
    /**
     * @var array<string, bool> Mass assignment fields
     */
    protected array $_accessible = [
        'start_date' => true,    // Warrant period start date
        'end_date' => true,      // Warrant period end date
        'created' => true,       // Creation timestamp
        'created_by' => true,    // Creator identification
    ];

    /**
     * Virtual property: formatted period display name.
     *
     * @return string "YYYY-MM-DD ~ YYYY-MM-DD" format
     */
    protected function _getName(): string
    {
        return $this->start_date->toDateString() . ' ~ ' . $this->end_date->toDateString();
    }
}
