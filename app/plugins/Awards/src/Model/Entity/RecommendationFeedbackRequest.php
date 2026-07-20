<?php
declare(strict_types=1);

namespace Awards\Model\Entity;

use App\Model\Entity\BaseEntity;

class RecommendationFeedbackRequest extends BaseEntity
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_RETRACTED = 'retracted';
    public const STATUS_EXPIRED = 'expired';

    protected array $_accessible = [
        'requester_id' => true,
        'status' => true,
        'message' => true,
        'deadline' => true,
        'workflow_instance_id' => true,
        'completed_at' => true,
        'retracted_at' => true,
        'expired_at' => true,
        'created_by' => true,
        'modified_by' => true,
        'requester' => true,
        'items' => true,
        'recipients' => true,
    ];
}
