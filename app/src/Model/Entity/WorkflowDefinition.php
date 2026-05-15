<?php
declare(strict_types=1);

namespace App\Model\Entity;

/**
 * WorkflowDefinition Entity
 *
 * Blueprint/template for workflows. Each definition can have multiple
 * versioned snapshots and running instances.
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property string $trigger_type
 * @property array|null $trigger_config
 * @property string|null $entity_type
 * @property bool $is_active
 * @property int|null $current_version_id
 * @property int|null $created_by
 * @property int|null $modified_by
 * @property \Cake\I18n\DateTime|null $created
 * @property \Cake\I18n\DateTime|null $modified
 * @property \Cake\I18n\DateTime|null $deleted
 *
 * @property \App\Model\Entity\WorkflowVersion|null $current_version
 * @property \App\Model\Entity\WorkflowVersion[] $workflow_versions
 * @property \App\Model\Entity\WorkflowInstance[] $workflow_instances
 */
class WorkflowDefinition extends BaseEntity
{
    public const TRIGGER_EVENT = 'event';
    public const TRIGGER_MANUAL = 'manual';
    public const TRIGGER_SCHEDULED = 'scheduled';
    public const TRIGGER_API = 'api';

    /**
     * Fields that can be mass assigned.
     *
     * @var array<string, bool>
     */
    protected array $_accessible = [
        'name' => true,
        'slug' => true,
        'description' => true,
        'trigger_type' => true,
        'trigger_config' => true,
        'entity_type' => true,
        'is_active' => true,
        'execution_mode' => true,
        'current_version_id' => true,
        'created_by' => true,
        'modified_by' => true,
        'deleted' => true,
    ];
}
