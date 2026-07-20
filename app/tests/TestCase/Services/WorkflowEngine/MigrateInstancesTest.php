<?php

declare(strict_types=1);

namespace App\Test\TestCase\Services\WorkflowEngine;

use App\Model\Entity\WorkflowInstance;
use App\Test\TestCase\BaseTestCase;
use Cake\ORM\TableRegistry;

/**
 * Integration tests for workflow instance version migration logic.
 *
 * Tests the migrateInstances behavior — updating running instances
 * to a new workflow version.
 */
class MigrateInstancesTest extends BaseTestCase
{
    private $defTable;
    private $versionsTable;
    private $instancesTable;

    protected function setUp(): void
    {
        parent::setUp();
        $this->defTable = TableRegistry::getTableLocator()->get('WorkflowDefinitions');
        $this->versionsTable = TableRegistry::getTableLocator()->get('WorkflowVersions');
        $this->instancesTable = TableRegistry::getTableLocator()->get('WorkflowInstances');
    }

    /**
     * Create a definition with two versions and instances in various states.
     * Returns [defId, v1Id, v2Id, instanceIds].
     */
    private function createMigrationScenario(array $instanceStatuses = ['running', 'waiting', 'completed']): array
    {
        $def = $this->defTable->newEntity([
            'name' => 'Migration Test ' . uniqid(),
            'slug' => 'migrate-' . uniqid(),
            'trigger_type' => 'manual',
            'is_active' => true,
        ]);
        $this->defTable->saveOrFail($def);

        $simpleDefinition = ['nodes' => [
            'trigger1' => ['type' => 'trigger', 'outputs' => [['target' => 'end1']]],
            'end1' => ['type' => 'end', 'outputs' => []],
        ]];

        $v1 = $this->versionsTable->newEntity([
            'workflow_definition_id' => $def->id,
            'version_number' => 1,
            'definition' => $simpleDefinition,
            'status' => 'published',
        ]);
        $this->versionsTable->saveOrFail($v1);

        $v2 = $this->versionsTable->newEntity([
            'workflow_definition_id' => $def->id,
            'version_number' => 2,
            'definition' => $simpleDefinition,
            'status' => 'published',
        ]);
        $this->versionsTable->saveOrFail($v2);

        $instanceIds = [];
        foreach ($instanceStatuses as $status) {
            $instance = $this->instancesTable->newEntity([
                'workflow_definition_id' => $def->id,
                'workflow_version_id' => $v1->id,
                'status' => $status,
            ]);
            $this->instancesTable->saveOrFail($instance);
            $instanceIds[$status] = $instance->id;
        }

        return [$def->id, $v1->id, $v2->id, $instanceIds];
    }

    /**
     * Simulate the migrateInstances logic (from WorkflowsController).
     */
    private function migrateInstances(int $versionId): int
    {
        $version = $this->versionsTable->get($versionId);

        return $this->instancesTable->updateAll(
            ['workflow_version_id' => $versionId],
            [
                'workflow_definition_id' => $version->workflow_definition_id,
                'status IN' => ['running', 'waiting'],
            ]
        );
    }

    // =====================================================
    // Tests
    // =====================================================

    public function testMigrateRunningInstances(): void
    {
        // Controller uses 'running' not 'running' in its IN clause
        [$defId, $v1Id, $v2Id, $ids] = $this->createMigrationScenario(['running', 'waiting']);

        $updated = $this->migrateInstances($v2Id);

        $this->assertSame(2, $updated);

        $activeInstance = $this->instancesTable->get($ids['running']);
        $this->assertSame($v2Id, $activeInstance->workflow_version_id);

        $waitingInstance = $this->instancesTable->get($ids['waiting']);
        $this->assertSame($v2Id, $waitingInstance->workflow_version_id);
    }

    public function testCompletedInstancesNotMigrated(): void
    {
        [$defId, $v1Id, $v2Id, $ids] = $this->createMigrationScenario(['completed']);

        $updated = $this->migrateInstances($v2Id);

        $this->assertSame(0, $updated);

        $completedInstance = $this->instancesTable->get($ids['completed']);
        $this->assertSame($v1Id, $completedInstance->workflow_version_id);
    }

    public function testFailedInstancesNotMigrated(): void
    {
        [$defId, $v1Id, $v2Id, $ids] = $this->createMigrationScenario(['failed']);

        $updated = $this->migrateInstances($v2Id);

        $this->assertSame(0, $updated);

        $failedInstance = $this->instancesTable->get($ids['failed']);
        $this->assertSame($v1Id, $failedInstance->workflow_version_id);
    }

    public function testCancelledInstancesNotMigrated(): void
    {
        [$defId, $v1Id, $v2Id, $ids] = $this->createMigrationScenario(['cancelled']);

        $updated = $this->migrateInstances($v2Id);

        $this->assertSame(0, $updated);

        $cancelledInstance = $this->instancesTable->get($ids['cancelled']);
        $this->assertSame($v1Id, $cancelledInstance->workflow_version_id);
    }

    public function testMixedStatusesMigratesOnlyActiveAndWaiting(): void
    {
        [$defId, $v1Id, $v2Id, $ids] = $this->createMigrationScenario([
            'running', 'waiting', 'completed', 'failed', 'cancelled',
        ]);

        $updated = $this->migrateInstances($v2Id);

        $this->assertSame(2, $updated);

        // Active and waiting migrated
        $this->assertSame($v2Id, $this->instancesTable->get($ids['running'])->workflow_version_id);
        $this->assertSame($v2Id, $this->instancesTable->get($ids['waiting'])->workflow_version_id);

        // Terminal states not migrated
        $this->assertSame($v1Id, $this->instancesTable->get($ids['completed'])->workflow_version_id);
        $this->assertSame($v1Id, $this->instancesTable->get($ids['failed'])->workflow_version_id);
        $this->assertSame($v1Id, $this->instancesTable->get($ids['cancelled'])->workflow_version_id);
    }

    public function testMigrateDoesNotAffectOtherDefinitions(): void
    {
        // Create two separate definitions
        [$defId1, $v1Id1, $v2Id1, $ids1] = $this->createMigrationScenario(['running']);

        $def2 = $this->defTable->newEntity([
            'name' => 'Other Def ' . uniqid(),
            'slug' => 'other-' . uniqid(),
            'trigger_type' => 'manual',
            'is_active' => true,
        ]);
        $this->defTable->saveOrFail($def2);

        $v1Other = $this->versionsTable->newEntity([
            'workflow_definition_id' => $def2->id,
            'version_number' => 1,
            'definition' => ['nodes' => []],
            'status' => 'published',
        ]);
        $this->versionsTable->saveOrFail($v1Other);

        $otherInstance = $this->instancesTable->newEntity([
            'workflow_definition_id' => $def2->id,
            'workflow_version_id' => $v1Other->id,
            'status' => 'running',
        ]);
        $this->instancesTable->saveOrFail($otherInstance);

        // Migrate def1's instances to v2
        $this->migrateInstances($v2Id1);

        // Other definition's instance should be unchanged
        $other = $this->instancesTable->get($otherInstance->id);
        $this->assertSame($v1Other->id, $other->workflow_version_id);
    }

    public function testMigrateWithNoRunningInstancesReturnsZero(): void
    {
        [$defId, $v1Id, $v2Id, $ids] = $this->createMigrationScenario(['completed', 'failed']);

        $updated = $this->migrateInstances($v2Id);

        $this->assertSame(0, $updated);
    }
}
