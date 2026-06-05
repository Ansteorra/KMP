<?php
declare(strict_types=1);

namespace Awards\Model\Entity;

use App\Model\Entity\BaseEntity;

/**
 * Recommendation approval workflow projection.
 *
 * @property int $id
 * @property int $recommendation_id
 * @property int $approval_process_id
 * @property int $workflow_instance_id
 * @property string $status
 * @property string|null $current_step_key
 * @property string|null $current_step_label
 * @property \Cake\I18n\DateTime $started
 * @property \Cake\I18n\DateTime|null $completed
 */
class RecommendationApprovalRun extends BaseEntity
{
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_CHANGES_REQUESTED = 'changes_requested';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_CLOSED = 'closed';
    public const STATUS_CANCELLED = 'cancelled';

    protected array $_accessible = [
        'recommendation_id' => true,
        'approval_process_id' => true,
        'workflow_instance_id' => true,
        'status' => true,
        'current_step_key' => true,
        'current_step_label' => true,
        'started' => true,
        'completed' => true,
        'created' => true,
        'modified' => true,
        'created_by' => true,
        'modified_by' => true,
        'deleted' => true,
    ];
}
