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
 * @property string $configuration_signature
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

    /**
     * Produce a stable fingerprint of fields that affect recommendation approval behavior.
     */
    protected function _getConfigurationSignature(): string
    {
        $steps = $this->approval_process_steps ?? [];
        usort(
            $steps,
            static fn($left, $right): int => [(int)$left->sequence, (string)$left->step_key]
                <=> [(int)$right->sequence, (string)$right->step_key],
        );

        $configuration = [];
        foreach ($steps as $step) {
            $configuration[] = [
                'step_key' => (string)$step->step_key,
                'label' => (string)$step->label,
                'sequence' => (int)$step->sequence,
                'step_type' => (string)$step->step_type,
                'approver_type' => (string)$step->approver_type,
                'approver_source_id' => $step->approver_source_id === null
                    ? null
                    : (int)$step->approver_source_id,
                'approver_source_key' => $step->approver_source_key,
                'branch_mode' => (string)$step->branch_mode,
                'branch_type' => $step->branch_type,
                'threshold_mode' => (string)$step->threshold_mode,
                'required_count' => $step->required_count === null ? null : (int)$step->required_count,
                'on_reject' => (string)$step->on_reject,
                'on_request_changes' => (string)$step->on_request_changes,
                'retain_read_visibility' => (bool)$step->retain_read_visibility,
            ];
        }

        return hash('sha256', json_encode($configuration, JSON_THROW_ON_ERROR));
    }
}
