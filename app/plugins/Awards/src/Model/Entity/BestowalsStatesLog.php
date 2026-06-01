<?php
declare(strict_types=1);

namespace Awards\Model\Entity;

use App\Model\Entity\BaseEntity;

/**
 * BestowalsStatesLog Entity - Audit trail for bestowal state transitions.
 *
 * Logs every state change in the bestowal workflow for accountability and analytics.
 * Entries are append-only to preserve historical accuracy.
 *
 * @property int $id
 * @property int $bestowal_id
 * @property string $from_state
 * @property string $to_state
 * @property string $from_status
 * @property string $to_status
 * @property \Cake\I18n\DateTime $created
 * @property int|null $created_by
 *
 * @property \Awards\Model\Entity\Bestowal $bestowal
 */
class BestowalsStatesLog extends BaseEntity
{
    /**
     * @var array<string, bool>
     */
    protected array $_accessible = [
        'bestowal_id' => true,
        'from_state' => true,
        'to_state' => true,
        'from_status' => true,
        'to_status' => true,
        'created' => true,
        'created_by' => true,
        'bestowal' => true,
    ];
}
