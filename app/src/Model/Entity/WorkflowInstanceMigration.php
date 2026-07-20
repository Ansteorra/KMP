<?php

declare(strict_types=1);

namespace App\Model\Entity;

/**
 * WorkflowInstanceMigration Entity
 *
 * Audit trail for workflow instance version migrations. Records how
 * nodes were mapped between versions and who triggered the migration.
 *
 * @property int $id
 * @property int $workflow_instance_id
 * @property int $from_version_id
 * @property int $to_version_id
 * @property string $migration_type
 * @property array|null $node_mapping
 * @property int|null $migrated_by
 * @property \Cake\I18n\DateTime|null $created
 *
 * @property \App\Model\Entity\WorkflowInstance $workflow_instance
 * @property \App\Model\Entity\WorkflowVersion $from_version
 * @property \App\Model\Entity\WorkflowVersion $to_version
 * @property \App\Model\Entity\Member|null $member
 */
class WorkflowInstanceMigration extends BaseEntity
{
    public const MIGRATION_TYPE_AUTOMATIC = 'automatic';
    public const MIGRATION_TYPE_MANUAL = 'manual';
    public const MIGRATION_TYPE_ADMIN = 'admin';

    /**
     * Fields that can be mass assigned.
     *
     * @var array<string, bool>
     */
    protected array $_accessible = [
        'workflow_instance_id' => true,
        'from_version_id' => true,
        'to_version_id' => true,
        'migration_type' => true,
        'node_mapping' => true,
        'migrated_by' => true,
    ];
}
