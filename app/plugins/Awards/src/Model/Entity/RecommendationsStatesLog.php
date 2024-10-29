<?php
declare(strict_types=1);

namespace Awards\Model\Entity;

use Cake\ORM\Entity;

/**
 * AwardsRecommendationsStatesLog Entity
 *
 * @property int $id
 * @property int $recommendation_id
 * @property string $from_state
 * @property string $to_state
 * @property \Cake\I18n\DateTime $created
 * @property int|null $created_by
 *
 * @property \Awards\Model\Entity\AwardsRecommendation $awards_recommendation
 */
class RecommendationsStatesLog extends Entity
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
        'recommendation_id' => true,
        'from_state' => true,
        'to_state' => true,
        'created' => true,
        'created_by' => true,
        'awards_recommendation' => true,
    ];
}
