<?php
declare(strict_types=1);

namespace App\Model\Entity;

/**
 * WorkflowApproval Entity
 *
 * Approval gate within a running workflow instance. Tracks required/received
 * approval counts, deadline, and escalation configuration.
 *
 * @property int $id
 * @property int $workflow_instance_id
 * @property string $node_id
 * @property int $execution_log_id
 * @property string $approver_type
 * @property array|null $approver_config
 * @property int|null $current_approver_id
 * @property int $required_count
 * @property int $approved_count
 * @property int $rejected_count
 * @property string $status
 * @property bool $allow_parallel
 * @property \Cake\I18n\DateTime|null $deadline
 * @property array|null $escalation_config
 * @property int $version
 * @property string|null $approval_token
 * @property \Cake\I18n\DateTime|null $created
 * @property \Cake\I18n\DateTime|null $modified
 *
 * @property \App\Model\Entity\WorkflowInstance $workflow_instance
 * @property \App\Model\Entity\WorkflowExecutionLog $workflow_execution_log
 * @property \App\Model\Entity\WorkflowApprovalResponse[] $workflow_approval_responses
 * @property \App\Model\Entity\WorkflowApprovalTriageState[] $workflow_approval_triage_states
 */
class WorkflowApproval extends BaseEntity
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_CANCELLED = 'cancelled';

    public const APPROVER_TYPE_PERMISSION = 'permission';
    public const APPROVER_TYPE_ROLE = 'role';
    public const APPROVER_TYPE_MEMBER = 'member';
    public const APPROVER_TYPE_DYNAMIC = 'dynamic';
    public const APPROVER_TYPE_POLICY = 'policy';

    /**
     * Fields that can be mass assigned.
     *
     * @var array<string, bool>
     */
    protected array $_accessible = [
        'workflow_instance_id' => true,
        'node_id' => true,
        'execution_log_id' => true,
        'approver_type' => true,
        'approver_config' => true,
        'current_approver_id' => true,
        'required_count' => true,
        'approved_count' => true,
        'rejected_count' => true,
        'status' => true,
        'allow_parallel' => true,
        'deadline' => true,
        'escalation_config' => true,
        'version' => true,
        'approval_token' => true,
        'workflow_approval_triage_states' => true,
    ];

    /**
     * Check if this approval gate is still pending.
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Check if the approval threshold has been reached.
     */
    public function hasReachedThreshold(): bool
    {
        return $this->approved_count >= $this->required_count;
    }

    /**
     * Check if this approval gate has been resolved (approved, rejected, expired, or cancelled).
     */
    public function isResolved(): bool
    {
        return in_array($this->status, [
            self::STATUS_APPROVED,
            self::STATUS_REJECTED,
            self::STATUS_EXPIRED,
            self::STATUS_CANCELLED,
        ], true);
    }
}
