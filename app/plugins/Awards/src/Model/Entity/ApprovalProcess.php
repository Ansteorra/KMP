<?php
declare(strict_types=1);

namespace Awards\Model\Entity;

use App\Model\Entity\BaseEntity;

/**
 * Award approval process configuration.
 *
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property bool $is_active
 * @property \Awards\Model\Entity\ApprovalProcessStep[] $approval_process_steps
 */
class ApprovalProcess extends BaseEntity
{
    protected array $_accessible = [
        'name' => true,
        'description' => true,
        'is_active' => true,
        'created' => true,
        'modified' => true,
        'created_by' => true,
        'modified_by' => true,
        'deleted' => true,
        'approval_process_steps' => true,
    ];

    /**
     * Summarize configured steps for grids and detail views.
     *
     * @return string
     */
    protected function _getStepSummary(): string
    {
        $steps = $this->approval_process_steps ?? [];
        if ($steps === []) {
            return (string)__('No approval steps configured');
        }

        $labels = [];
        foreach ($steps as $step) {
            $labels[] = (string)$step->label;
        }

        return implode(' → ', $labels);
    }
}
