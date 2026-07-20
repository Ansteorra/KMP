<?php
declare(strict_types=1);

namespace Awards\Model\Entity;

use App\Model\Entity\BaseEntity;

/**
 * BestowalRecommendation Entity - Join record linking a bestowal to a recommendation.
 *
 * @property int $id
 * @property int $bestowal_id
 * @property int $recommendation_id
 * @property \Cake\I18n\DateTime $created
 *
 * @property \Awards\Model\Entity\Bestowal $bestowal
 * @property \Awards\Model\Entity\Recommendation $recommendation
 */
class BestowalRecommendation extends BaseEntity
{
    /**
     * @var array<string, bool>
     */
    protected array $_accessible = [
        'bestowal_id' => true,
        'recommendation_id' => true,
        'created' => true,
        'bestowal' => true,
        'recommendation' => true,
    ];
}
