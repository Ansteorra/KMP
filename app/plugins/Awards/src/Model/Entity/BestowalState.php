<?php
declare(strict_types=1);

namespace Awards\Model\Entity;

use App\Model\Entity\BaseEntity;

/**
 * BestowalState Entity - Specific workflow position within a bestowal status category.
 *
 * @property int $id
 * @property int $status_id
 * @property string $name
 * @property int $sort_order
 * @property int|null $sync_recommendation_state_id
 * @property int|null $unwind_recommendation_state_id
 * @property bool $locks_recommendations
 * @property bool $supports_gathering
 * @property bool $is_hidden
 * @property bool $is_system
 * @property \Cake\I18n\DateTime $created
 * @property \Cake\I18n\DateTime|null $modified
 * @property int|null $created_by
 * @property int|null $modified_by
 * @property \Cake\I18n\DateTime|null $deleted
 *
 * @property \Awards\Model\Entity\BestowalStatus $bestowal_status
 * @property \Awards\Model\Entity\RecommendationState|null $sync_recommendation_state
 * @property \Awards\Model\Entity\RecommendationState|null $unwind_recommendation_state
 * @property \Awards\Model\Entity\BestowalStateFieldRule[] $bestowal_state_field_rules
 * @property \Awards\Model\Entity\BestowalStateTransition[] $outgoing_transitions
 * @property \Awards\Model\Entity\BestowalStateTransition[] $incoming_transitions
 */
class BestowalState extends BaseEntity
{
    /**
     * @var array<string, bool>
     */
    protected array $_accessible = [
        'status_id' => true,
        'name' => true,
        'sort_order' => true,
        'sync_recommendation_state_id' => true,
        'unwind_recommendation_state_id' => true,
        'locks_recommendations' => true,
        'supports_gathering' => true,
        'is_hidden' => true,
        'is_system' => true,
        'created' => true,
        'modified' => true,
        'created_by' => true,
        'modified_by' => true,
        'deleted' => true,
    ];
}
