<?php

declare(strict_types=1);

namespace App\Model\Entity;

/**
 * WorkflowTask Entity
 *
 * Represents a human task within a workflow instance. Created when a
 * humanTask node executes, pausing the workflow until a user submits
 * the configured form.
 *
 * @property int $id
 * @property int $workflow_instance_id
 * @property string $node_id
 * @property int|null $assigned_to
 * @property string|null $assigned_by_role
 * @property string|null $task_title
 * @property array|null $form_definition
 * @property array|null $form_data
 * @property string $status
 * @property \Cake\I18n\DateTime|null $due_date
 * @property \Cake\I18n\DateTime|null $completed_at
 * @property int|null $completed_by
 * @property \Cake\I18n\DateTime|null $created
 * @property \Cake\I18n\DateTime|null $modified
 *
 * @property \App\Model\Entity\WorkflowInstance $workflow_instance
 * @property \App\Model\Entity\Member|null $assigned_member
 * @property \App\Model\Entity\Member|null $completing_member
 */
class WorkflowTask extends BaseEntity
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_EXPIRED = 'expired';

    /**
     * Fields that can be mass assigned.
     *
     * @var array<string, bool>
     */
    protected array $_accessible = [
        'workflow_instance_id' => true,
        'node_id' => true,
        'assigned_to' => true,
        'assigned_by_role' => true,
        'task_title' => true,
        'form_definition' => true,
        'form_data' => true,
        'status' => true,
        'due_date' => true,
        'completed_at' => true,
        'completed_by' => true,
    ];

    /**
     * Check if this task is still pending.
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Check if this task has been completed.
     */
    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    /**
     * Check if the task has reached a terminal state.
     */
    public function isResolved(): bool
    {
        return in_array($this->status, [
            self::STATUS_COMPLETED,
            self::STATUS_CANCELLED,
            self::STATUS_EXPIRED,
        ], true);
    }
}
