<?php
declare(strict_types=1);

namespace App\Model\Entity;

/**
 * WorkflowSchedule Entity
 *
 * Tracks execution timing for scheduled workflow definitions.
 *
 * @property int $id
 * @property int $workflow_definition_id
 * @property \Cake\I18n\DateTime|null $last_run_at
 * @property \Cake\I18n\DateTime|null $next_run_at
 * @property bool $is_enabled
 * @property string|null $claim_token
 * @property \Cake\I18n\DateTime|null $claimed_at
 * @property \Cake\I18n\DateTime|null $created
 * @property \Cake\I18n\DateTime|null $modified
 *
 * @property \App\Model\Entity\WorkflowDefinition $workflow_definition
 */
class WorkflowSchedule extends BaseEntity
{
    /**
     * @var array<string, bool>
     */
    protected array $_accessible = [
        'workflow_definition_id' => true,
        'last_run_at' => true,
        'next_run_at' => true,
        'is_enabled' => true,
    ];
}
