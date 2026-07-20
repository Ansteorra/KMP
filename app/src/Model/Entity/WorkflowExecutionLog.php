<?php

declare(strict_types=1);

namespace App\Model\Entity;

/**
 * WorkflowExecutionLog Entity
 *
 * Per-node execution record within a workflow instance. Tracks input/output
 * data, status, and timing for each node execution attempt.
 *
 * @property int $id
 * @property int $workflow_instance_id
 * @property string $node_id
 * @property string $node_type
 * @property int $attempt_number
 * @property string $status
 * @property array|null $input_data
 * @property array|null $output_data
 * @property string|null $error_message
 * @property \Cake\I18n\DateTime|null $started_at
 * @property \Cake\I18n\DateTime|null $completed_at
 * @property \Cake\I18n\DateTime|null $created
 *
 * @property \App\Model\Entity\WorkflowInstance $workflow_instance
 */
class WorkflowExecutionLog extends BaseEntity
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_RUNNING = 'running';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';
    public const STATUS_SKIPPED = 'skipped';
    public const STATUS_WAITING = 'waiting';

    /**
     * Fields that can be mass assigned.
     *
     * @var array<string, bool>
     */
    protected array $_accessible = [
        'workflow_instance_id' => true,
        'node_id' => true,
        'node_type' => true,
        'attempt_number' => true,
        'status' => true,
        'input_data' => true,
        'output_data' => true,
        'error_message' => true,
        'started_at' => true,
        'completed_at' => true,
    ];
}
