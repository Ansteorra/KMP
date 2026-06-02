<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use App\Test\TestCase\Support\HttpIntegrationTestCase;
use Cake\I18n\DateTime;

/**
 * WorkflowDefinitions and WorkflowInstances authorization integration tests.
 *
 * @uses \App\Controller\WorkflowDefinitionsController
 * @uses \App\Controller\WorkflowInstancesController
 * @uses \App\Controller\ApprovalsController
 */
class WorkflowsControllerTest extends HttpIntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->enableCsrfToken();
        $this->enableSecurityToken();
    }

    // =====================================================
    // Unauthenticated → redirect
    // =====================================================

    public function testUnauthenticatedIndexRedirects(): void
    {
        $this->get('/workflows');
        $this->assertRedirectContains('/login');
    }

    public function testUnauthenticatedDesignerRedirects(): void
    {
        $this->get('/workflows/designer');
        $this->assertRedirectContains('/login');
    }

    public function testUnauthenticatedSaveRedirects(): void
    {
        $this->post('/workflows/save', []);
        $this->assertRedirectContains('/login');
    }

    public function testUnauthenticatedPublishRedirects(): void
    {
        $this->post('/workflows/publish', []);
        $this->assertRedirectContains('/login');
    }

    public function testUnauthenticatedAddRedirects(): void
    {
        $this->get('/workflows/add');
        $this->assertRedirectContains('/login');
    }

    public function testUnauthenticatedInstancesRedirects(): void
    {
        $this->get('/workflows/instances');
        $this->assertRedirectContains('/login');
    }

    public function testUnauthenticatedInstancesGridDataRedirects(): void
    {
        $this->get('/workflows/instances/grid-data');
        $this->assertRedirectContains('/login');
    }

    public function testUnauthenticatedApprovalsRedirects(): void
    {
        $this->get('/workflows/approvals');
        $this->assertRedirectContains('/login');
    }

    public function testUnauthenticatedVersionsRedirects(): void
    {
        $this->get('/workflows/versions/1');
        $this->assertRedirectContains('/login');
    }

    public function testUnauthenticatedToggleActiveRedirects(): void
    {
        $this->post('/workflows/toggle-active/1');
        $this->assertRedirectContains('/login');
    }

    public function testUnauthenticatedArchiveRedirects(): void
    {
        $this->post('/workflows/archive/1');
        $this->assertRedirectContains('/login');
    }

    public function testUnauthenticatedDeleteRedirects(): void
    {
        $this->post('/workflows/delete/1');
        $this->assertRedirectContains('/login');
    }

    public function testUnauthenticatedCreateDraftRedirects(): void
    {
        $this->post('/workflows/create-draft', []);
        $this->assertRedirectContains('/login');
    }

    // =====================================================
    // Super user can access admin actions
    // =====================================================

    public function testSuperUserCanAccessIndex(): void
    {
        $this->authenticateAsSuperUser();
        $this->get('/workflows');
        $this->assertResponseOk();
    }

    public function testSuperUserCanAccessDesigner(): void
    {
        $this->authenticateAsSuperUser();
        $this->get('/workflows/designer');
        $this->assertResponseOk();
    }

    public function testSuperUserCanAccessAdd(): void
    {
        $this->authenticateAsSuperUser();
        $this->get('/workflows/add');
        $this->assertResponseOk();
    }

    public function testSuperUserCanAccessInstances(): void
    {
        $this->authenticateAsSuperUser();
        $this->get('/workflows/instances');
        $this->assertResponseOk();
    }

    public function testSuperUserCanAccessInstancesGridData(): void
    {
        $this->authenticateAsSuperUser();
        $this->get('/workflows/instances/grid-data');
        $this->assertResponseOk();
        $this->assertNotNull($this->viewVariable('gridState'));
        $this->assertNotNull($this->viewVariable('data'));
    }

    public function testWorkflowInstancesGridDataDefaultsToStartedDateFilter(): void
    {
        $this->authenticateAsSuperUser();
        $this->get('/workflows/instances/grid-data');
        $this->assertResponseOk();

        $gridState = $this->viewVariable('gridState');
        $this->assertSame('sys-workflow-instances-recent', $gridState['view']['currentId']);
        $this->assertArrayHasKey('started_at_start', $gridState['filters']['active']);
        $this->assertNotEmpty($gridState['filters']['active']['started_at_start']);
    }

    public function testSuperUserCanAccessApprovals(): void
    {
        $this->authenticateAsSuperUser();
        $this->get('/workflows/approvals');
        $this->assertResponseOk();
    }

    // =====================================================
    // Non-super user blocked from admin actions
    // =====================================================

    public function testNonSuperUserBlockedFromIndex(): void
    {
        $this->authenticateAsMember(self::TEST_MEMBER_AGATHA_ID);
        $this->get('/workflows');
        $this->assertRedirect();
    }

    public function testNonSuperUserBlockedFromDesigner(): void
    {
        $this->authenticateAsMember(self::TEST_MEMBER_AGATHA_ID);
        $this->get('/workflows/designer');
        $this->assertRedirect();
    }

    public function testNonSuperUserBlockedFromAdd(): void
    {
        $this->authenticateAsMember(self::TEST_MEMBER_AGATHA_ID);
        $this->get('/workflows/add');
        $this->assertRedirect();
    }

    public function testNonSuperUserBlockedFromInstances(): void
    {
        $this->authenticateAsMember(self::TEST_MEMBER_AGATHA_ID);
        $this->get('/workflows/instances');
        $this->assertRedirect();
    }

    public function testNonSuperUserBlockedFromInstancesGridData(): void
    {
        $this->authenticateAsMember(self::TEST_MEMBER_AGATHA_ID);
        $this->get('/workflows/instances/grid-data');
        $this->assertRedirect();
    }

    public function testNonSuperUserBlockedFromSave(): void
    {
        $this->authenticateAsMember(self::TEST_MEMBER_AGATHA_ID);
        $this->post('/workflows/save', ['name' => 'Test']);
        $this->assertRedirect();
    }

    public function testNonSuperUserBlockedFromUpdateMetadata(): void
    {
        $workflow = $this->createWorkflowDefinition();

        $this->authenticateAsMember(self::TEST_MEMBER_AGATHA_ID);
        $this->post('/workflows/update-metadata/' . $workflow->id, ['name' => 'Blocked']);
        $this->assertRedirect();
    }

    public function testNonSuperUserBlockedFromPublish(): void
    {
        $this->authenticateAsMember(self::TEST_MEMBER_AGATHA_ID);
        $this->post('/workflows/publish', ['versionId' => 1]);
        $this->assertRedirect();
    }

    public function testNonSuperUserBlockedFromToggleActive(): void
    {
        $this->authenticateAsMember(self::TEST_MEMBER_AGATHA_ID);
        $this->post('/workflows/toggle-active/1');
        $this->assertRedirect();
    }

    public function testNonSuperUserBlockedFromArchive(): void
    {
        $workflow = $this->createWorkflowDefinition();

        $this->authenticateAsMember(self::TEST_MEMBER_AGATHA_ID);
        $this->post('/workflows/archive/' . $workflow->id);
        $this->assertRedirect();
    }

    public function testNonSuperUserBlockedFromDelete(): void
    {
        $workflow = $this->createWorkflowDefinition();

        $this->authenticateAsMember(self::TEST_MEMBER_AGATHA_ID);
        $this->post('/workflows/delete/' . $workflow->id);
        $this->assertRedirect();
    }

    public function testNonSuperUserBlockedFromCreateDraft(): void
    {
        $this->authenticateAsMember(self::TEST_MEMBER_AGATHA_ID);
        $this->post('/workflows/create-draft', ['workflowId' => 1]);
        $this->assertRedirect();
    }

    // =====================================================
    // Authenticated users CAN access approvals
    // =====================================================

    public function testAuthenticatedUserCanAccessApprovals(): void
    {
        $this->authenticateAsMember(self::TEST_MEMBER_AGATHA_ID);
        $this->get('/workflows/approvals');
        $this->assertResponseOk();
    }

    public function testSuperUserCanUpdateWorkflowMetadata(): void
    {
        $workflow = $this->createWorkflowDefinition();

        $this->authenticateAsSuperUser();
        $this->configRequest([
            'headers' => ['Accept' => 'application/json'],
        ]);
        $this->post('/workflows/update-metadata/' . $workflow->id, [
            'name' => 'Updated Workflow',
            'slug' => 'updated-workflow',
            'description' => 'Updated from designer',
            'trigger_type' => 'manual',
            'entity_type' => 'App\Model\Entity\Member',
            'execution_mode' => 'ephemeral',
        ]);

        $this->assertResponseOk();
        $response = (string)$this->_response->getBody();
        $payload = json_decode($response, true);
        $this->assertTrue($payload['success']);
        $this->assertSame('Updated Workflow', $payload['workflow']['name']);
        $this->assertSame('ephemeral', $payload['workflow']['executionMode']);

        $updated = $this->getTableLocator()->get('WorkflowDefinitions')->get($workflow->id);
        $this->assertSame('updated-workflow', $updated->slug);
        $this->assertSame('App\Model\Entity\Member', $updated->entity_type);
    }

    public function testUpdateWorkflowMetadataReturnsValidationErrors(): void
    {
        $workflow = $this->createWorkflowDefinition();

        $this->authenticateAsSuperUser();
        $this->configRequest([
            'headers' => ['Accept' => 'application/json'],
        ]);
        $this->post('/workflows/update-metadata/' . $workflow->id, [
            'name' => '',
            'slug' => 'Invalid Slug',
            'trigger_type' => 'invalid',
            'execution_mode' => 'durable',
        ]);

        $this->assertResponseCode(422);
        $payload = json_decode((string)$this->_response->getBody(), true);
        $this->assertFalse($payload['success']);
        $this->assertStringContainsString('name', $payload['reason']);
        $this->assertStringContainsString('slug', $payload['reason']);
        $this->assertStringContainsString('trigger_type', $payload['reason']);
    }

    public function testSuperUserCanDeleteUnusedWorkflow(): void
    {
        $workflow = $this->createWorkflowDefinition();
        $version = $this->createWorkflowVersion((int)$workflow->id);
        $this->setCurrentWorkflowVersion((int)$workflow->id, (int)$version->id);
        $schedule = $this->createWorkflowSchedule((int)$workflow->id);

        $this->authenticateAsSuperUser();
        $this->post('/workflows/delete/' . $workflow->id);
        $this->assertRedirect(['controller' => 'WorkflowDefinitions', 'action' => 'index']);

        $definitionsTable = $this->getTableLocator()->get('WorkflowDefinitions');
        $versionsTable = $this->getTableLocator()->get('WorkflowVersions');
        $schedulesTable = $this->getTableLocator()->get('WorkflowSchedules');

        $this->assertFalse($definitionsTable->exists(['id' => $workflow->id]));
        $this->assertFalse($versionsTable->exists(['id' => $version->id]));
        $this->assertFalse($schedulesTable->exists(['id' => $schedule->id]));
    }

    public function testDeleteWorkflowWithHistoryIsBlocked(): void
    {
        $workflow = $this->createWorkflowDefinition();
        $version = $this->createWorkflowVersion((int)$workflow->id);
        $this->setCurrentWorkflowVersion((int)$workflow->id, (int)$version->id);
        $this->createWorkflowInstance((int)$workflow->id, (int)$version->id);

        $this->authenticateAsSuperUser();
        $this->post('/workflows/delete/' . $workflow->id);
        $this->assertRedirect(['controller' => 'WorkflowDefinitions', 'action' => 'index']);

        $definitionsTable = $this->getTableLocator()->get('WorkflowDefinitions');
        $versionsTable = $this->getTableLocator()->get('WorkflowVersions');

        $this->assertTrue($definitionsTable->exists(['id' => $workflow->id]));
        $this->assertTrue($versionsTable->exists(['id' => $version->id]));
    }

    public function testSuperUserCanArchiveWorkflowWithHistory(): void
    {
        $workflow = $this->createWorkflowDefinition();
        $version = $this->createWorkflowVersion((int)$workflow->id);
        $this->setCurrentWorkflowVersion((int)$workflow->id, (int)$version->id);
        $this->createWorkflowInstance((int)$workflow->id, (int)$version->id);

        $this->authenticateAsSuperUser();
        $this->post('/workflows/archive/' . $workflow->id);
        $this->assertRedirect(['controller' => 'WorkflowDefinitions', 'action' => 'index']);

        $archived = $this->getTableLocator()->get('WorkflowDefinitions')->get($workflow->id);
        $this->assertFalse($archived->is_active);
        $this->assertNotNull($archived->deleted);
    }

    public function testArchivedWorkflowIsHiddenFromIndex(): void
    {
        $workflow = $this->createWorkflowDefinitionWithName('Hidden Archived Workflow');
        $definitionsTable = $this->getTableLocator()->get('WorkflowDefinitions');
        $workflow->deleted = new DateTime();
        $definitionsTable->saveOrFail($workflow);

        $this->authenticateAsSuperUser();
        $this->get('/workflows');
        $this->assertResponseOk();
        $this->assertResponseNotContains('Hidden Archived Workflow');
    }

    /**
     * Create a valid workflow definition for metadata endpoint tests.
     *
     * @return \App\Model\Entity\WorkflowDefinition
     */
    private function createWorkflowDefinition()
    {
        return $this->createWorkflowDefinitionWithName('Original Workflow');
    }

    /**
     * Create a valid workflow definition.
     *
     * @param string $name Workflow name
     * @return \App\Model\Entity\WorkflowDefinition
     */
    private function createWorkflowDefinitionWithName(string $name)
    {
        $definitionsTable = $this->getTableLocator()->get('WorkflowDefinitions');
        $workflow = $definitionsTable->newEntity([
            'name' => $name,
            'slug' => 'original-workflow-' . uniqid(),
            'description' => 'Original description',
            'trigger_type' => 'event',
            'entity_type' => 'App\Model\Entity\Member',
            'execution_mode' => 'durable',
            'is_active' => true,
        ]);

        return $definitionsTable->saveOrFail($workflow);
    }

    /**
     * Create a workflow version.
     *
     * @param int $definitionId Workflow definition ID
     * @return \App\Model\Entity\WorkflowVersion
     */
    private function createWorkflowVersion(int $definitionId)
    {
        $versionsTable = $this->getTableLocator()->get('WorkflowVersions');
        $version = $versionsTable->newEntity([
            'workflow_definition_id' => $definitionId,
            'version_number' => 1,
            'definition' => ['nodes' => []],
            'canvas_layout' => ['nodes' => []],
            'status' => 'published',
            'published_at' => new DateTime(),
            'published_by' => self::ADMIN_MEMBER_ID,
        ]);

        return $versionsTable->saveOrFail($version);
    }

    /**
     * Set a workflow's current version.
     *
     * @param int $definitionId Workflow definition ID
     * @param int $versionId Workflow version ID
     * @return void
     */
    private function setCurrentWorkflowVersion(int $definitionId, int $versionId): void
    {
        $definitionsTable = $this->getTableLocator()->get('WorkflowDefinitions');
        $workflow = $definitionsTable->get($definitionId);
        $workflow->current_version_id = $versionId;
        $definitionsTable->saveOrFail($workflow);
    }

    /**
     * Create a workflow schedule.
     *
     * @param int $definitionId Workflow definition ID
     * @return \App\Model\Entity\WorkflowSchedule
     */
    private function createWorkflowSchedule(int $definitionId)
    {
        $schedulesTable = $this->getTableLocator()->get('WorkflowSchedules');
        $schedule = $schedulesTable->newEntity([
            'workflow_definition_id' => $definitionId,
            'next_run_at' => new DateTime('+1 day'),
            'is_enabled' => true,
        ]);

        return $schedulesTable->saveOrFail($schedule);
    }

    /**
     * Create a workflow instance.
     *
     * @param int $definitionId Workflow definition ID
     * @param int $versionId Workflow version ID
     * @return \App\Model\Entity\WorkflowInstance
     */
    private function createWorkflowInstance(int $definitionId, int $versionId)
    {
        $instancesTable = $this->getTableLocator()->get('WorkflowInstances');
        $instance = $instancesTable->newEntity([
            'workflow_definition_id' => $definitionId,
            'workflow_version_id' => $versionId,
            'status' => 'completed',
        ]);

        return $instancesTable->saveOrFail($instance);
    }
}
