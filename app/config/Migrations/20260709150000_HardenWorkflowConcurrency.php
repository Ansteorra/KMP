<?php
declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * Add atomic active-instance uniqueness and recoverable scheduler claims.
 */
class HardenWorkflowConcurrency extends BaseMigration
{
    /**
     * Add recoverable database enforcement for workflow concurrency.
     */
    public function up(): void
    {
        $this->assertNoDuplicateActiveInstances();

        $instancesTable = $this->table('workflow_instances');
        if (!$instancesTable->hasColumn('active_entity_key')) {
            $instancesTable->addColumn('active_entity_key', 'string', [
                'limit' => 64,
                'null' => true,
                'default' => null,
                'after' => 'entity_id',
                'comment' => 'Unique hash while an entity-backed workflow instance is running or waiting',
            ])
                ->update();
        }
        $this->backfillActiveEntityKeys();

        $instancesTable = $this->table('workflow_instances');
        if (!$instancesTable->hasIndexByName('idx_wf_instances_active_entity_unique')) {
            $instancesTable->addIndex(['active_entity_key'], [
                'name' => 'idx_wf_instances_active_entity_unique',
                'unique' => true,
            ])
                ->update();
        }

        $schedulesTable = $this->table('workflow_schedules');
        $scheduleColumnsChanged = false;
        if (!$schedulesTable->hasColumn('claim_token')) {
            $schedulesTable->addColumn('claim_token', 'string', [
                'limit' => 36,
                'null' => true,
                'default' => null,
                'after' => 'is_enabled',
                'comment' => 'Lease token held by the scheduler currently dispatching this workflow',
            ]);
            $scheduleColumnsChanged = true;
        }
        if (!$schedulesTable->hasColumn('claimed_at')) {
            $schedulesTable->addColumn('claimed_at', 'datetime', [
                'null' => true,
                'default' => null,
                'after' => 'claim_token',
                'comment' => 'When the current scheduler lease was acquired',
            ]);
            $scheduleColumnsChanged = true;
        }
        if ($scheduleColumnsChanged) {
            $schedulesTable->update();
        }

        $schedulesTable = $this->table('workflow_schedules');
        if (!$schedulesTable->hasIndexByName('idx_wf_schedules_claimed_at')) {
            $schedulesTable->addIndex(['claimed_at'], [
                'name' => 'idx_wf_schedules_claimed_at',
            ])
                ->update();
        }
    }

    /**
     * Remove workflow concurrency columns and indexes.
     */
    public function down(): void
    {
        $schedulesTable = $this->table('workflow_schedules');
        if ($schedulesTable->hasIndexByName('idx_wf_schedules_claimed_at')) {
            $schedulesTable->removeIndexByName('idx_wf_schedules_claimed_at')->update();
        }
        $schedulesTable = $this->table('workflow_schedules');
        if ($schedulesTable->hasColumn('claimed_at')) {
            $schedulesTable->removeColumn('claimed_at')->update();
        }
        $schedulesTable = $this->table('workflow_schedules');
        if ($schedulesTable->hasColumn('claim_token')) {
            $schedulesTable->removeColumn('claim_token')->update();
        }

        $instancesTable = $this->table('workflow_instances');
        if ($instancesTable->hasIndexByName('idx_wf_instances_active_entity_unique')) {
            $instancesTable->removeIndexByName('idx_wf_instances_active_entity_unique')->update();
        }
        $instancesTable = $this->table('workflow_instances');
        if ($instancesTable->hasColumn('active_entity_key')) {
            $instancesTable->removeColumn('active_entity_key')->update();
        }
    }

    /**
     * Fail before DDL when existing rows violate the new uniqueness invariant.
     */
    private function assertNoDuplicateActiveInstances(): void
    {
        $duplicate = $this->fetchRow(
            "SELECT workflow_definition_id, COALESCE(entity_type, '') AS entity_type_key, "
            . 'entity_id, COUNT(*) AS duplicate_count '
            . 'FROM workflow_instances '
            . "WHERE status IN ('running', 'waiting') AND entity_id IS NOT NULL "
            . "GROUP BY workflow_definition_id, COALESCE(entity_type, ''), entity_id "
            . 'HAVING COUNT(*) > 1 LIMIT 1',
        );
        if ($duplicate !== false) {
            throw new RuntimeException(sprintf(
                'Cannot enforce active workflow uniqueness: definition %s, entity type "%s", '
                . 'and entity ID %s have %s active instances.',
                $duplicate['workflow_definition_id'],
                $duplicate['entity_type_key'],
                $duplicate['entity_id'],
                $duplicate['duplicate_count'],
            ));
        }
    }

    /**
     * Populate deterministic keys after a new or partially applied migration.
     */
    private function backfillActiveEntityKeys(): void
    {
        $this->execute('UPDATE workflow_instances SET active_entity_key = NULL');
        $activeInstances = $this->fetchAll(
            "SELECT id, workflow_definition_id, entity_type, entity_id
             FROM workflow_instances
             WHERE entity_id IS NOT NULL
               AND status IN ('running', 'waiting')",
        );
        foreach ($activeInstances as $instance) {
            $key = hash(
                'sha256',
                (int)$instance['workflow_definition_id']
                    . "\0" . (string)($instance['entity_type'] ?? '')
                    . "\0" . (int)$instance['entity_id'],
            );
            $this->execute(
                "UPDATE workflow_instances
                 SET active_entity_key = '{$key}'
                 WHERE id = " . (int)$instance['id'],
            );
        }
    }
}
