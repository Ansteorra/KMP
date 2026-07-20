<?php
declare(strict_types=1);

namespace App\Model\Entity;

/**
 * WorkflowInstance Entity
 *
 * A running or completed workflow execution, pinned to a specific
 * workflow version. Tracks execution state, context, and active nodes.
 *
 * @property int $id
 * @property int $workflow_definition_id
 * @property int $workflow_version_id
 * @property string|null $entity_type
 * @property int|null $entity_id
 * @property string|null $active_entity_key
 * @property string $status
 * @property array|null $context
 * @property array|null $active_nodes
 * @property array|null $error_info
 * @property int|null $started_by
 * @property \Cake\I18n\DateTime|null $started_at
 * @property \Cake\I18n\DateTime|null $completed_at
 * @property \Cake\I18n\DateTime|null $created
 * @property \Cake\I18n\DateTime|null $modified
 *
 * @property \App\Model\Entity\WorkflowDefinition $workflow_definition
 * @property \App\Model\Entity\WorkflowVersion $workflow_version
 * @property \App\Model\Entity\WorkflowExecutionLog[] $workflow_execution_logs
 * @property \App\Model\Entity\WorkflowApproval[] $workflow_approvals
 */
class WorkflowInstance extends BaseEntity
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_RUNNING = 'running';
    public const STATUS_WAITING = 'waiting';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';
    public const STATUS_CANCELLED = 'cancelled';

    public const ACTIVE_STATUSES = [
        self::STATUS_RUNNING,
        self::STATUS_WAITING,
    ];

    /**
     * Fields that can be mass assigned.
     *
     * @var array<string, bool>
     */
    protected array $_accessible = [
        'workflow_definition_id' => true,
        'workflow_version_id' => true,
        'entity_type' => true,
        'entity_id' => true,
        'status' => true,
        'context' => true,
        'active_nodes' => true,
        'error_info' => true,
        'started_by' => true,
        'started_at' => true,
        'completed_at' => true,
    ];

    /**
     * Check if the instance is currently running.
     */
    public function isRunning(): bool
    {
        return $this->status === self::STATUS_RUNNING;
    }

    /**
     * Check if the instance is waiting for external input.
     */
    public function isWaiting(): bool
    {
        return $this->status === self::STATUS_WAITING;
    }

    /**
     * Check if the instance completed successfully.
     */
    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    /**
     * Check if the instance has reached a terminal state.
     */
    public function isTerminal(): bool
    {
        return in_array($this->status, [
            self::STATUS_COMPLETED,
            self::STATUS_FAILED,
            self::STATUS_CANCELLED,
        ], true);
    }
}
