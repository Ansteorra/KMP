<?php
declare(strict_types=1);

namespace Awards\Model\Entity;

use App\Model\Entity\BaseEntity;

class RecommendationMigrationRun extends BaseEntity
{
    public const MODE_DRY_RUN = 'dry-run';
    public const MODE_APPLY = 'apply';
    public const MODE_RESUME = 'resume';

    public const STATUS_RUNNING = 'running';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    protected array $_accessible = [
        'mode' => true,
        'status' => true,
        'filters' => true,
        'summary' => true,
        'started' => true,
        'completed' => true,
        'created_by' => true,
        'modified_by' => true,
    ];
}
