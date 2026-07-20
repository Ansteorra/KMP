<?php
declare(strict_types=1);

namespace App\Test\TestCase\Services\WorkflowEngine;

use App\Model\Entity\WorkflowVersion;
use App\Services\WorkflowEngine\DefaultWorkflowVersionManager;
use App\Test\TestCase\BaseTestCase;
use Cake\ORM\TableRegistry;

/**
 * Integration tests for DefaultWorkflowVersionManager.
 */
class WorkflowVersionManagerTest extends BaseTestCase
{
    private DefaultWorkflowVersionManager $manager;
    private $definitionsTable;
    private $versionsTable;

    /**
     * Valid minimal definition with trigger + end node.
     */
    private array $validDefinition = [
        'schemaVersion' => '1.0',
        'nodes' => [
            'trigger1' => [
                'type' => 'trigger',
                'outputs' => [['target' => 'end1']],
            ],
            'end1' => [
                'type' => 'end',
                'outputs' => [],
            ],
        ],
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->manager = new DefaultWorkflowVersionManager();
        $this->definitionsTable = TableRegistry::getTableLocator()->get('WorkflowDefinitions');
        $this->versionsTable = TableRegistry::getTableLocator()->get('WorkflowVersions');
    }

    /**
     * Helper: create a workflow definition for testing.
     */
    private function createDefinition(string $name = 'Test Workflow'): int
    {
        $entity = $this->definitionsTable->newEntity([
            'name' => $name . ' ' . uniqid(),
            'slug' => 'test-' . uniqid(),
            'trigger_type' => 'manual',
        ]);
        $this->definitionsTable->saveOrFail($entity);

        return $entity->id;
    }

    // =====================================================
    // createDraft()
    // =====================================================

    public function testCreateDraftSuccess(): void
    {
        $defId = $this->createDefinition();
        $result = $this->manager->createDraft($defId, $this->validDefinition);

        $this->assertTrue($result->isSuccess());
        $this->assertEquals(1, $result->data['versionNumber']);
    }

    public function testCreateDraftAutoIncrementsVersion(): void
    {
        $defId = $this->createDefinition();

        $r1 = $this->manager->createDraft($defId, $this->validDefinition);
        $r2 = $this->manager->createDraft($defId, $this->validDefinition);

        $this->assertEquals(1, $r1->data['versionNumber']);
        $this->assertEquals(2, $r2->data['versionNumber']);
    }

    public function testCreateDraftSavesCanvasLayout(): void
    {
        $defId = $this->createDefinition();
        $layout = ['x' => 100, 'y' => 200];

        $result = $this->manager->createDraft($defId, $this->validDefinition, $layout, 'test notes');
        $version = $this->versionsTable->get($result->data['versionId']);

        $this->assertEquals($layout, $version->canvas_layout);
        $this->assertEquals('test notes', $version->change_notes);
    }

    public function testCreateDraftForMissingDefinitionFails(): void
    {
        $result = $this->manager->createDraft(999999, $this->validDefinition);
        $this->assertFalse($result->isSuccess());
        $this->assertStringContainsString('not found', $result->getError());
    }

    public function testCreateDraftStatusIsDraft(): void
    {
        $defId = $this->createDefinition();
        $result = $this->manager->createDraft($defId, $this->validDefinition);

        $version = $this->versionsTable->get($result->data['versionId']);
        $this->assertTrue($version->isDraft());
    }

    // =====================================================
    // updateDraft()
    // =====================================================

    public function testUpdateDraftSuccess(): void
    {
        $defId = $this->createDefinition();
        $createResult = $this->manager->createDraft($defId, $this->validDefinition);

        $newDef = $this->validDefinition;
        $newDef['nodes']['action1'] = [
            'type' => 'action',
            'outputs' => [['target' => 'end1']],
        ];
        $newDef['nodes']['trigger1']['outputs'][] = ['target' => 'action1'];

        $result = $this->manager->updateDraft($createResult->data['versionId'], $newDef);
        $this->assertTrue($result->isSuccess());
    }

    public function testUpdateDraftRejectsPublishedVersion(): void
    {
        $defId = $this->createDefinition();
        $createResult = $this->manager->createDraft($defId, $this->validDefinition);
        $this->manager->publish($createResult->data['versionId'], self::ADMIN_MEMBER_ID);

        $result = $this->manager->updateDraft($createResult->data['versionId'], $this->validDefinition);
        $this->assertFalse($result->isSuccess());
        $this->assertStringContainsString('draft', strtolower($result->getError()));
    }

    public function testUpdateDraftRejectsArchivedVersion(): void
    {
        $defId = $this->createDefinition();
        $createResult = $this->manager->createDraft($defId, $this->validDefinition);
        $versionId = $createResult->data['versionId'];

        // Publish then archive
        $this->manager->publish($versionId, self::ADMIN_MEMBER_ID);
        $this->manager->archive($versionId);

        $result = $this->manager->updateDraft($versionId, $this->validDefinition);
        $this->assertFalse($result->isSuccess());
    }

    // =====================================================
    // publish()
    // =====================================================

    public function testPublishSuccess(): void
    {
        $defId = $this->createDefinition();
        $createResult = $this->manager->createDraft($defId, $this->validDefinition);

        $result = $this->manager->publish($createResult->data['versionId'], self::ADMIN_MEMBER_ID);
        $this->assertTrue($result->isSuccess());

        $version = $this->versionsTable->get($createResult->data['versionId']);
        $this->assertTrue($version->isPublished());
        $this->assertNotNull($version->published_at);
        $this->assertEquals(self::ADMIN_MEMBER_ID, $version->published_by);
    }

    public function testPublishUpdatesDefinitionCurrentVersion(): void
    {
        $defId = $this->createDefinition();
        $createResult = $this->manager->createDraft($defId, $this->validDefinition);
        $versionId = $createResult->data['versionId'];

        $this->manager->publish($versionId, self::ADMIN_MEMBER_ID);

        $def = $this->definitionsTable->get($defId);
        $this->assertEquals($versionId, $def->current_version_id);
        $this->assertTrue($def->is_active);
    }

    public function testPublishArchivesPreviousPublished(): void
    {
        $defId = $this->createDefinition();

        // Create and publish first version
        $r1 = $this->manager->createDraft($defId, $this->validDefinition);
        $this->manager->publish($r1->data['versionId'], self::ADMIN_MEMBER_ID);

        // Create and publish second version
        $r2 = $this->manager->createDraft($defId, $this->validDefinition);
        $this->manager->publish($r2->data['versionId'], self::ADMIN_MEMBER_ID);

        $v1 = $this->versionsTable->get($r1->data['versionId']);
        $this->assertEquals(WorkflowVersion::STATUS_ARCHIVED, $v1->status);
    }

    public function testPublishRejectsNonDraft(): void
    {
        $defId = $this->createDefinition();
        $createResult = $this->manager->createDraft($defId, $this->validDefinition);
        $this->manager->publish($createResult->data['versionId'], self::ADMIN_MEMBER_ID);

        // Try to publish again
        $result = $this->manager->publish($createResult->data['versionId'], self::ADMIN_MEMBER_ID);
        $this->assertFalse($result->isSuccess());
    }

    public function testPublishRejectsInvalidDefinition(): void
    {
        $defId = $this->createDefinition();
        $invalidDef = [
            'schemaVersion' => '1.0',
            'nodes' => [
                'action1' => ['type' => 'action', 'outputs' => []],
            ],
        ];
        $createResult = $this->manager->createDraft($defId, $invalidDef);

        $result = $this->manager->publish($createResult->data['versionId'], self::ADMIN_MEMBER_ID);
        $this->assertFalse($result->isSuccess());
        $this->assertStringContainsString('validation', strtolower($result->getError()));
    }

    // =====================================================
    // archive()
    // =====================================================

    public function testArchiveDraftVersion(): void
    {
        $defId = $this->createDefinition();
        $createResult = $this->manager->createDraft($defId, $this->validDefinition);

        $result = $this->manager->archive($createResult->data['versionId']);
        $this->assertTrue($result->isSuccess());

        $version = $this->versionsTable->get($createResult->data['versionId']);
        $this->assertEquals(WorkflowVersion::STATUS_ARCHIVED, $version->status);
    }

    public function testArchivePublishedClearsCurrentVersion(): void
    {
        $defId = $this->createDefinition();
        $createResult = $this->manager->createDraft($defId, $this->validDefinition);
        $versionId = $createResult->data['versionId'];
        $this->manager->publish($versionId, self::ADMIN_MEMBER_ID);

        $result = $this->manager->archive($versionId);
        $this->assertTrue($result->isSuccess());

        $def = $this->definitionsTable->get($defId);
        $this->assertNull($def->current_version_id);
        $this->assertFalse($def->is_active);
    }

    // =====================================================
    // getCurrentVersion()
    // =====================================================

    public function testGetCurrentVersionReturnsPublished(): void
    {
        $defId = $this->createDefinition();
        $createResult = $this->manager->createDraft($defId, $this->validDefinition);
        $this->manager->publish($createResult->data['versionId'], self::ADMIN_MEMBER_ID);

        $version = $this->manager->getCurrentVersion($defId);
        $this->assertNotNull($version);
        $this->assertTrue($version->isPublished());
    }

    public function testGetCurrentVersionReturnsNullWhenNone(): void
    {
        $defId = $this->createDefinition();
        $version = $this->manager->getCurrentVersion($defId);
        $this->assertNull($version);
    }

    public function testGetCurrentVersionReturnsNullForMissing(): void
    {
        $version = $this->manager->getCurrentVersion(999999);
        $this->assertNull($version);
    }

    // =====================================================
    // getVersionHistory()
    // =====================================================

    public function testGetVersionHistoryOrdersNewestFirst(): void
    {
        $defId = $this->createDefinition();
        $this->manager->createDraft($defId, $this->validDefinition);
        $this->manager->createDraft($defId, $this->validDefinition);

        $history = $this->manager->getVersionHistory($defId);
        $this->assertCount(2, $history);
        $this->assertGreaterThan($history[1]->version_number, $history[0]->version_number);
    }

    // =====================================================
    // compareVersions()
    // =====================================================

    public function testCompareVersionsDetectsAddedNodes(): void
    {
        $defId = $this->createDefinition();

        $def1 = $this->validDefinition;
        $r1 = $this->manager->createDraft($defId, $def1);

        $def2 = $def1;
        $def2['nodes']['action1'] = ['type' => 'action', 'outputs' => [['target' => 'end1']]];
        $def2['nodes']['trigger1']['outputs'][] = ['target' => 'action1'];
        $r2 = $this->manager->createDraft($defId, $def2);

        $diff = $this->manager->compareVersions($r1->data['versionId'], $r2->data['versionId']);
        $this->assertArrayHasKey('action1', $diff['added']);
        $this->assertEmpty($diff['removed']);
    }

    public function testCompareVersionsDetectsRemovedNodes(): void
    {
        $defId = $this->createDefinition();

        $def1 = $this->validDefinition;
        $def1['nodes']['action1'] = ['type' => 'action', 'outputs' => [['target' => 'end1']]];
        $def1['nodes']['trigger1']['outputs'][] = ['target' => 'action1'];
        $r1 = $this->manager->createDraft($defId, $def1);

        $r2 = $this->manager->createDraft($defId, $this->validDefinition);

        $diff = $this->manager->compareVersions($r1->data['versionId'], $r2->data['versionId']);
        $this->assertArrayHasKey('action1', $diff['removed']);
        $this->assertEmpty($diff['added']);
    }

    // =====================================================
    // migrateInstance()
    // =====================================================

    public function testMigrateInstanceRejectsTerminal(): void
    {
        $defId = $this->createDefinition();
        $r1 = $this->manager->createDraft($defId, $this->validDefinition);
        $this->manager->publish($r1->data['versionId'], self::ADMIN_MEMBER_ID);

        // Create a terminal instance
        $instancesTable = TableRegistry::getTableLocator()->get('WorkflowInstances');
        $instance = $instancesTable->newEntity([
            'workflow_definition_id' => $defId,
            'workflow_version_id' => $r1->data['versionId'],
            'status' => 'completed',
        ]);
        $instancesTable->saveOrFail($instance);

        // Create a second published version
        $r2 = $this->manager->createDraft($defId, $this->validDefinition);
        $this->manager->publish($r2->data['versionId'], self::ADMIN_MEMBER_ID);

        $result = $this->manager->migrateInstance($instance->id, $r2->data['versionId'], self::ADMIN_MEMBER_ID);
        $this->assertFalse($result->isSuccess());
        $this->assertStringContainsString('terminal', strtolower($result->getError()));
    }

    public function testMigrateInstanceRejectsNonPublishedTarget(): void
    {
        $defId = $this->createDefinition();
        $r1 = $this->manager->createDraft($defId, $this->validDefinition);
        $this->manager->publish($r1->data['versionId'], self::ADMIN_MEMBER_ID);

        $instancesTable = TableRegistry::getTableLocator()->get('WorkflowInstances');
        $instance = $instancesTable->newEntity([
            'workflow_definition_id' => $defId,
            'workflow_version_id' => $r1->data['versionId'],
            'status' => 'running',
        ]);
        $instancesTable->saveOrFail($instance);

        $r2 = $this->manager->createDraft($defId, $this->validDefinition);

        $result = $this->manager->migrateInstance($instance->id, $r2->data['versionId'], self::ADMIN_MEMBER_ID);
        $this->assertFalse($result->isSuccess());
        $this->assertStringContainsString('published', strtolower($result->getError()));
    }

    public function testMigrateInstanceAutoMapsMatchingNodes(): void
    {
        $defId = $this->createDefinition();
        $r1 = $this->manager->createDraft($defId, $this->validDefinition);
        $this->manager->publish($r1->data['versionId'], self::ADMIN_MEMBER_ID);

        $instancesTable = TableRegistry::getTableLocator()->get('WorkflowInstances');
        $instance = $instancesTable->newEntity([
            'workflow_definition_id' => $defId,
            'workflow_version_id' => $r1->data['versionId'],
            'status' => 'waiting',
            'active_nodes' => ['trigger1'],
        ]);
        $instancesTable->saveOrFail($instance);

        $r2 = $this->manager->createDraft($defId, $this->validDefinition);
        $this->manager->publish($r2->data['versionId'], self::ADMIN_MEMBER_ID);

        $result = $this->manager->migrateInstance($instance->id, $r2->data['versionId'], self::ADMIN_MEMBER_ID);
        $this->assertTrue($result->isSuccess());

        $updated = $instancesTable->get($instance->id);
        $this->assertEquals($r2->data['versionId'], $updated->workflow_version_id);
    }
}
