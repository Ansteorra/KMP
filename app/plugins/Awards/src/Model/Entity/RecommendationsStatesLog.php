<?php

declare(strict_types=1);

namespace Awards\Model\Entity;

use Cake\ORM\Entity;
use App\Model\Entity\BaseEntity;

/**
 * RecommendationsStatesLog Entity - Audit trail for recommendation state transitions.
 *
 * Logs every state change in the recommendation workflow for accountability and analytics.
 * Entries are append-only to preserve historical accuracy.
 *
 * @property int $id
 * @property int $recommendation_id
 * @property string $from_state
 * @property string $to_state
 * @property \Cake\I18n\DateTime $created
 * @property int|null $created_by
 *
 * @property \Awards\Model\Entity\Recommendation $recommendation
 */
class RecommendationsStatesLog extends BaseEntity
{
    /**
     * @var array<string, bool>
     */
    protected array $_accessible = [
        'recommendation_id' => true,
        'from_state' => true,
        'to_state' => true,
        'created' => true,
        'created_by' => true,
        'recommendation' => true,
    ];
}
