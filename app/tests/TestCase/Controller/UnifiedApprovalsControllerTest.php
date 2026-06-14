<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use App\Model\Entity\WorkflowApproval;
use App\Model\Entity\WorkflowApprovalTriageState;
use App\Test\TestCase\Support\HttpIntegrationTestCase;
use Cake\ORM\TableRegistry;

/**
 * Unified Approvals route integration tests.
 *
 * Covers the /approvals scope and legacy redirect routes
 * defined in config/routes.php.
 *
 * @uses \App\Controller\ApprovalsController
 */
class UnifiedApprovalsControllerTest extends HttpIntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->skipIfPostgres();
        $this->enableCsrfToken();
        $this->enableSecurityToken();
    }

    // =====================================================
    // Unauthenticated → redirect to login
    // =====================================================

    public function testUnauthenticatedApprovalsRedirects(): void
    {
        $this->get('/approvals');
        $this->assertRedirectContains('/login');
    }

    public function testUnauthenticatedGridDataRedirects(): void
    {
        $this->get('/approvals/grid-data');
        $this->assertRedirectContains('/login');
    }

    public function testUnauthenticatedKanbanLaneRedirects(): void
    {
        $this->get('/approvals/kanban-lane?triage_state=new');
        $this->assertRedirectContains('/login');
    }

    public function testUnauthenticatedRespondRedirects(): void
    {
        $this->get('/approvals/respond/some-token');
        $this->assertRedirectContains('/login');
    }

    // =====================================================
    // Authenticated access
    // =====================================================

    public function testAuthenticatedApprovalsReturnsOk(): void
    {
        $this->authenticateAsSuperUser();
        $this->get('/approvals');
        $this->assertResponseOk();
    }

    public function testAuthenticatedNonSuperUserCanAccessApprovals(): void
    {
        $this->authenticateAsMember(self::TEST_MEMBER_AGATHA_ID);
        $this->get('/approvals');
        $this->assertResponseOk();
    }

    public function testAuthenticatedGridDataReturnsOk(): void
    {
        $this->authenticateAsSuperUser();
        $this->get('/approvals/grid-data');
        $this->assertResponseOk();
    }

    public function testAuthenticatedKanbanLaneReturnsOk(): void
    {
        $this->authenticateAsSuperUser();
        $this->get('/approvals/kanban-lane?triage_state=new&view_id=sys-approvals-triage-board');
        $this->assertResponseOk();
        $this->assertResponseContains('approval-kanban-lane-new');
    }

    public function testKanbanLaneShowsLoadMoreWhenMoreApprovalsExist(): void
    {
        $this->authenticateAsSuperUser();
        [$instanceId, $executionLogId] = $this->createWorkflowContext();
        for ($i = 0; $i < 21; $i++) {
            $this->createApproval($instanceId, $executionLogId);
        }
        $this->get('/approvals/kanban-lane?triage_state=new&view_id=sys-approvals-triage-board');

        $this->assertResponseOk();
        $this->assertResponseContains('21 approvals');
        $this->assertResponseContains('Load more');
        $this->assertResponseContains('Page 1 of 2');
        $this->assertResponseNotContains('All 20 approvals loaded');
    }

    public function testKanbanLaneSecondPageDoesNotRepeatFirstPageWhenModifiedTimestampsTie(): void
    {
        $this->authenticateAsSuperUser();
        [$instanceId, $executionLogId] = $this->createWorkflowContext();
        $approvalIds = [];
        for ($i = 0; $i < 25; $i++) {
            $approvalIds[] = $this->createApproval($instanceId, $executionLogId, 'Approval ' . $i);
        }

        TableRegistry::getTableLocator()->get('WorkflowApprovals')->updateAll(
            ['modified' => '2026-06-14 12:00:00'],
            ['id IN' => $approvalIds],
        );

        $this->get('/approvals/kanban-lane?triage_state=new&view_id=sys-approvals-triage-board&page=1');
        $this->assertResponseOk();
        preg_match_all('/<article[^>]+data-approval-id="(\d+)"/', (string)$this->_response->getBody(), $pageOneMatches);

        $this->get('/approvals/kanban-lane?triage_state=new&view_id=sys-approvals-triage-board&page=2');
        $this->assertResponseOk();
        preg_match_all('/<article[^>]+data-approval-id="(\d+)"/', (string)$this->_response->getBody(), $pageTwoMatches);

        $pageOneIds = $pageOneMatches[1] ?? [];
        $pageTwoIds = $pageTwoMatches[1] ?? [];
        $this->assertCount(20, $pageOneIds);
        $this->assertCount(5, $pageTwoIds);
        $this->assertSame([], array_values(array_intersect($pageOneIds, $pageTwoIds)));
    }

    public function testKanbanLaneNextPageUrlDropsEscapedAmpersandQueryKeys(): void
    {
        $this->authenticateAsSuperUser();
        [$instanceId, $executionLogId] = $this->createWorkflowContext();
        for ($i = 0; $i < 21; $i++) {
            $this->createApproval($instanceId, $executionLogId, 'Approval ' . $i);
        }

        $this->get(
            '/approvals/kanban-lane?view_id=sys-approvals-triage-board' .
            '&amp%3Btriage_state=reviewing&amp%3Bpage=9&triage_state=new&page=1',
        );

        $this->assertResponseOk();
        $body = (string)$this->_response->getBody();
        preg_match('/data-next-url="([^"]+)"/', $body, $matches);
        $nextUrl = $matches[1] ?? '';

        $this->assertSame(
            '/approvals/kanban-lane?view_id=sys-approvals-triage-board&amp;triage_state=new&amp;page=2',
            $nextUrl,
        );
        $this->assertStringNotContainsString('amp%3B', $nextUrl);
        $this->assertStringNotContainsString('&amp;amp', $nextUrl);
    }

    public function testKanbanLaneSearchReturnsOkWhenCurrentApproverColumnIsHidden(): void
    {
        $this->authenticateAsSuperUser();
        [$instanceId, $executionLogId] = $this->createWorkflowContext();
        $this->createApproval($instanceId, $executionLogId);

        $this->get('/approvals/kanban-lane?triage_state=new&view_id=sys-approvals-triage-board&search=499');

        $this->assertResponseOk();
        $this->assertResponseContains('approval-kanban-lane-new');
    }

    public function testKanbanGridSearchReturnsOkWhenCurrentApproverColumnIsHidden(): void
    {
        $this->authenticateAsSuperUser();
        [$instanceId, $executionLogId] = $this->createWorkflowContext();
        $this->createApproval($instanceId, $executionLogId);

        $this->get('/approvals/grid-data?view_id=sys-approvals-triage-board&search=499');

        $this->assertResponseOk();
        $this->assertResponseContains('approval-kanban');
    }

    public function testKanbanLaneSearchMatchesCachedApprovalRequestTitle(): void
    {
        $this->authenticateAsSuperUser();
        [$instanceId, $executionLogId] = $this->createWorkflowContext();
        $this->createApproval($instanceId, $executionLogId, 'Award Recommendation: Scale Member 0499');

        $this->get('/approvals/kanban-lane?triage_state=new&view_id=sys-approvals-triage-board&search=Scale%20Member%200499');

        $this->assertResponseOk();
        $this->assertResponseContains('Award Recommendation: Scale Member 0499');
    }

    public function testKanbanGridSearchMatchesCachedApprovalRequestTitle(): void
    {
        $this->authenticateAsSuperUser();
        [$instanceId, $executionLogId] = $this->createWorkflowContext();
        $this->createApproval($instanceId, $executionLogId, 'Warrant Roster: Kingdom Chatelaine');

        $this->get('/approvals/grid-data?view_id=sys-approvals-triage-board&search=Kingdom%20Chatelaine');

        $this->assertResponseOk();
        $rows = array_values(iterator_to_array($this->viewVariable('data')));
        $this->assertCount(1, $rows);
        $this->assertSame('Warrant Roster: Kingdom Chatelaine', $rows[0]->request);
    }

    // =====================================================
    // Token deep-link handling
    // =====================================================

    public function testTokenDeepLinkWithInvalidToken(): void
    {
        $this->authenticateAsSuperUser();
        $this->get('/approvals/respond/invalid-token');
        // Invalid token should not cause a 500; expect redirect or 4xx
        $code = $this->_response->getStatusCode();
        $this->assertTrue(
            $code >= 200 && $code < 500,
            "Expected a non-server-error response for invalid token, got {$code}",
        );
    }

    // =====================================================
    // Legacy redirect: /authorization-approvals/my-queue
    // =====================================================

    public function testOldMyQueueRedirects(): void
    {
        $this->get('/authorization-approvals/my-queue');
        $this->assertRedirectContains('/approvals');
        $this->assertResponseCode(302);
    }

    public function testOldMyQueueWithIdRedirects(): void
    {
        $this->get('/authorization-approvals/my-queue/123');
        $this->assertRedirectContains('/approvals');
        $this->assertResponseCode(302);
    }

    // =====================================================
    // Workflow-scoped approval routes still work
    // =====================================================

    public function testWorkflowApprovalsRouteStillWorks(): void
    {
        $this->authenticateAsSuperUser();
        $this->get('/workflows/approvals');
        $this->assertResponseOk();
    }

    // =====================================================
    // Grid data response format
    // =====================================================

    public function testGridDataReturnsExpectedViewVariables(): void
    {
        $this->authenticateAsSuperUser();
        $this->get('/approvals/grid-data');
        $this->assertResponseOk();

        $this->assertNotEmpty($this->viewVariable('columns'), 'Expected columns view variable to be set');
        $this->assertNotNull($this->viewVariable('gridState'), 'Expected gridState view variable to be set');
        $this->assertNotNull($this->viewVariable('data'), 'Expected data view variable to be set');
    }

    // =====================================================
    // Admin: all approvals access control
    // =====================================================

    public function testAllApprovalsRequiresAdminAccess(): void
    {
        $this->authenticateAsMember(self::TEST_MEMBER_AGATHA_ID);
        $this->get('/approvals/all');

        $code = $this->_response->getStatusCode();
        $this->assertTrue(
            $code === 403 || $code === 302,
            "Expected 403 Forbidden or 302 redirect for non-admin, got {$code}",
        );
    }

    public function testAllApprovalsAccessibleByAdmin(): void
    {
        $this->authenticateAsSuperUser();
        $this->get('/approvals/all');
        $this->assertResponseOk();
    }

    public function testAllApprovalsGridDataReturnsOkForAdmin(): void
    {
        $this->authenticateAsSuperUser();
        $this->get('/approvals/all/grid-data');
        $this->assertResponseOk();

        $this->assertNotNull($this->viewVariable('data'), 'Expected data view variable to be set');
        $this->assertNotNull($this->viewVariable('gridState'), 'Expected gridState view variable to be set');
        $this->assertNotNull($this->viewVariable('columns'), 'Expected columns view variable to be set');
    }

    // =====================================================
    // Eligible approvers requires authentication
    // =====================================================

    public function testEligibleApproversRequiresAuth(): void
    {
        $this->get('/approvals/eligible-approvers/1');
        $this->assertRedirectContains('/login');
    }

    // =====================================================
    // Record approval requires POST
    // =====================================================

    public function testRecordApprovalRequiresPost(): void
    {
        $this->authenticateAsSuperUser();
        $this->get('/approvals/record');

        $code = $this->_response->getStatusCode();
        $this->assertTrue(
            $code === 405 || $code >= 400,
            "Expected 405 Method Not Allowed or 4xx for GET to record endpoint, got {$code}",
        );
    }

    // =====================================================
    // Reassign approval requires admin
    // =====================================================

    public function testReassignApprovalRequiresAdmin(): void
    {
        $this->authenticateAsMember(self::TEST_MEMBER_AGATHA_ID);
        $this->post('/approvals/reassign', [
            'approvalId' => 1,
            'newApproverId' => 2,
        ]);

        $code = $this->_response->getStatusCode();
        $this->assertTrue(
            $code === 403 || $code === 302,
            "Expected 403 Forbidden or 302 redirect for non-admin reassign, got {$code}",
        );
    }

    // =====================================================
    // Approval detail requires authentication
    // =====================================================

    public function testApprovalDetailRequiresAuth(): void
    {
        $this->get('/approvals/detail/1');
        $this->assertRedirectContains('/login');
    }

    public function testUpdateTriageSavesPrivateNoteAsJson(): void
    {
        $this->authenticateAsSuperUser();
        [$instanceId, $executionLogId] = $this->createWorkflowContext();
        $approvalId = $this->createApproval($instanceId, $executionLogId);

        $this->configRequest([
            'headers' => [
                'Accept' => 'application/json',
                'X-Requested-With' => 'XMLHttpRequest',
            ],
        ]);
        $this->post('/approvals/triage', [
            'approvalId' => $approvalId,
            'state' => WorkflowApprovalTriageState::STATE_READY_TO_DECIDE,
            'note' => 'Checked the private context.',
        ]);

        $this->assertResponseOk();
        $this->assertContentType('application/json');
        $payload = json_decode((string)$this->_response->getBody(), true);
        $this->assertSame(true, $payload['success'] ?? null);
        $this->assertSame('Checked the private context.', $payload['triage']['note'] ?? null);

        $triage = TableRegistry::getTableLocator()->get('WorkflowApprovalTriageStates')->find()
            ->where([
                'workflow_approval_id' => $approvalId,
                'member_id' => self::ADMIN_MEMBER_ID,
            ])
            ->firstOrFail();
        $this->assertSame(WorkflowApprovalTriageState::STATE_READY_TO_DECIDE, $triage->get('state'));
        $this->assertSame('Checked the private context.', $triage->get('note'));
    }

    /**
     * @return array{0:int,1:int}
     */
    private function createWorkflowContext(): array
    {
        $defTable = TableRegistry::getTableLocator()->get('WorkflowDefinitions');
        $def = $defTable->newEntity([
            'name' => 'Approvals Controller Test ' . uniqid(),
            'slug' => 'approvals-controller-test-' . uniqid(),
            'trigger_type' => 'manual',
        ]);
        $defTable->saveOrFail($def);

        $versionsTable = TableRegistry::getTableLocator()->get('WorkflowVersions');
        $version = $versionsTable->newEntity([
            'workflow_definition_id' => $def->id,
            'version_number' => 1,
            'definition' => [
                'nodes' => [
                    'trigger' => ['type' => 'trigger', 'outputs' => [['target' => 'end']]],
                    'end' => ['type' => 'end', 'outputs' => []],
                ],
            ],
            'status' => 'published',
        ]);
        $versionsTable->saveOrFail($version);

        $instancesTable = TableRegistry::getTableLocator()->get('WorkflowInstances');
        $instance = $instancesTable->newEntity([
            'workflow_definition_id' => $def->id,
            'workflow_version_id' => $version->id,
            'status' => 'waiting',
            'context' => [],
        ]);
        $instancesTable->saveOrFail($instance);

        $logsTable = TableRegistry::getTableLocator()->get('WorkflowExecutionLogs');
        $log = $logsTable->newEntity([
            'workflow_instance_id' => $instance->id,
            'node_id' => 'approval_node',
            'node_type' => 'approval',
            'status' => 'waiting',
        ]);
        $logsTable->saveOrFail($log);

        return [(int)$instance->id, (int)$log->id];
    }

    private function createApproval(
        int $instanceId,
        int $executionLogId,
        string $requestTitle = 'Approval Required: Test Request',
    ): int {
        $approvalsTable = TableRegistry::getTableLocator()->get('WorkflowApprovals');
        $approval = $approvalsTable->newEntity([
            'workflow_instance_id' => $instanceId,
            'node_id' => 'approval_node',
            'execution_log_id' => $executionLogId,
            'approver_type' => WorkflowApproval::APPROVER_TYPE_MEMBER,
            'approver_config' => ['member_id' => self::ADMIN_MEMBER_ID],
            'request_title' => $requestTitle,
            'required_count' => 1,
            'approved_count' => 0,
            'rejected_count' => 0,
            'status' => WorkflowApproval::STATUS_PENDING,
            'allow_parallel' => true,
            'version' => 1,
        ]);
        $approvalsTable->saveOrFail($approval);

        return (int)$approval->id;
    }
}
