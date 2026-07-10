<?php
declare(strict_types=1);

namespace App\Test\TestCase\Model\Table;

use App\Model\Entity\WorkflowInstance;
use App\Model\Table\WorkflowInstancesTable;
use App\Test\TestCase\BaseTestCase;
use Cake\ORM\TableRegistry;
use Throwable;

class WorkflowInstancesTableTest extends BaseTestCase
{
    public function testOnlyOneActiveInstanceCanClaimAWorkflowEntity(): void
    {
        $definitionsTable = TableRegistry::getTableLocator()->get('WorkflowDefinitions');
        $definition = $definitionsTable->newEntity([
            'name' => 'Active Instance Uniqueness ' . uniqid(),
            'slug' => 'active-instance-' . uniqid(),
            'trigger_type' => 'manual',
        ]);
        $definitionsTable->saveOrFail($definition);

        $versionsTable = TableRegistry::getTableLocator()->get('WorkflowVersions');
        $version = $versionsTable->newEntity([
            'workflow_definition_id' => $definition->id,
            'version_number' => 1,
            'definition' => ['nodes' => []],
            'status' => 'published',
        ]);
        $versionsTable->saveOrFail($version);

        /** @var \App\Model\Table\WorkflowInstancesTable $instancesTable */
        $instancesTable = TableRegistry::getTableLocator()->get('WorkflowInstances');
        $instanceData = [
            'workflow_definition_id' => $definition->id,
            'workflow_version_id' => $version->id,
            'entity_type' => 'Members',
            'entity_id' => 42,
            'status' => WorkflowInstance::STATUS_WAITING,
        ];
        $first = $instancesTable->newEntity($instanceData);
        $instancesTable->saveOrFail($first);

        $expectedKey = WorkflowInstancesTable::buildActiveEntityKey(
            (int)$definition->id,
            'Members',
            42,
        );
        $this->assertSame($expectedKey, $first->active_entity_key);

        $second = $instancesTable->newEntity($instanceData);
        $duplicateRejected = false;
        $instancesTable->getConnection()->enableSavePoints();
        try {
            $instancesTable->getConnection()->transactional(
                fn() => $instancesTable->saveOrFail($second),
            );
        } catch (Throwable) {
            $duplicateRejected = true;
        }
        $this->assertTrue(
            $duplicateRejected,
            'The database must reject a second active workflow for the same entity.',
        );

        $first->status = WorkflowInstance::STATUS_COMPLETED;
        $instancesTable->saveOrFail($first);
        $this->assertNull($first->active_entity_key);

        $instancesTable->saveOrFail($second);
        $this->assertSame($expectedKey, $second->active_entity_key);
    }
}
