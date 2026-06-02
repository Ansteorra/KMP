<?php
declare(strict_types=1);

namespace Awards\Model\Entity;

use App\Model\Entity\BaseEntity;

class RecommendationFeedbackRequestItem extends BaseEntity
{
    protected array $_accessible = [
        'feedback_request_id' => true,
        'recommendation_id' => true,
        'snapshot' => true,
        'feedback_request' => true,
        'recommendation' => true,
    ];
}
