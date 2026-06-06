<?php
declare(strict_types=1);

namespace Awards\Model\Entity;

use App\Model\Entity\BaseEntity;

class RecommendationMigrationResult extends BaseEntity
{
    public const TARGET_CLOSED = 'closed';
    public const TARGET_BESTOWAL = 'bestowal';
    public const TARGET_APPROVAL_WORKFLOW = 'approval_workflow';
    public const TARGET_MANUAL_REVIEW = 'manual_review';
    public const TARGET_SKIPPED = 'skipped';

    public const STATUS_PLANNED = 'planned';
    public const STATUS_APPLIED = 'applied';
    public const STATUS_SKIPPED = 'skipped';
    public const STATUS_ERROR = 'error';

    protected array $_accessible = [
        'migration_run_id' => true,
        'recommendation_id' => true,
        'original_state' => true,
        'original_status' => true,
        'target_action' => true,
        'result_status' => true,
        'reason' => true,
        'bestowal_id' => true,
        'workflow_instance_id' => true,
        'approval_run_id' => true,
        'details' => true,
    ];
}
