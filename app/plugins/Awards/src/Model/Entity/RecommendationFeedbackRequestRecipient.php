<?php
declare(strict_types=1);

namespace Awards\Model\Entity;

use App\Model\Entity\BaseEntity;

class RecommendationFeedbackRequestRecipient extends BaseEntity
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_RESPONDED = 'responded';
    public const STATUS_RETRACTED = 'retracted';
    public const STATUS_EXPIRED = 'expired';

    protected array $_accessible = [
        'feedback_request_id' => true,
        'recipient_id' => true,
        'workflow_approval_id' => true,
        'workflow_approval_response_id' => true,
        'status' => true,
        'response_comment' => true,
        'responded_at' => true,
        'retracted_at' => true,
        'expired_at' => true,
        'feedback_request' => true,
        'recipient' => true,
        'workflow_approval' => true,
        'workflow_approval_response' => true,
    ];
}
