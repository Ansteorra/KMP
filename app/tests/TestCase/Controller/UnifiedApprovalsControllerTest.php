<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use App\Controller\AppController;
use App\Model\Entity\WorkflowApproval;
use App\Model\Entity\WorkflowApprovalResponse;
use App\Model\Entity\WorkflowApprovalTriageState;
use App\Test\TestCase\Support\HttpIntegrationTestCase;
use Awards\Model\Entity\Recommendation;
use Awards\Model\Entity\RecommendationApprovalRun;
use Cake\I18n\DateTime;
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

    public function testMobileApprovalsRedirectsWhenNoPendingApprovals(): void
    {
        TableRegistry::getTableLocator()->get('WorkflowApprovals')->updateAll(
            ['status' => WorkflowApproval::STATUS_CANCELLED],
            ['status' => WorkflowApproval::STATUS_PENDING],
        );
        $this->authenticateAsMember(self::TEST_MEMBER_AGATHA_ID);

        $this->get('/approvals/mobile');

        $this->assertRedirectContains('/members/view-mobile-card');
    }

    public function testMobileApprovalsReturnsOkWhenPendingApprovalsExist(): void
    {
        $this->authenticateAsSuperUser();
        [$instanceId, $executionLogId] = $this->createWorkflowContext();
        $this->createApproval($instanceId, $executionLogId);

        $this->get('/approvals/mobile');

        $this->assertResponseOk();
        $this->assertResponseContains('mobile-approvals');
        $this->assertResponseContains('Approvals');
        $this->assertResponseContains(
            'data-mobile-approvals-per-page-value="' . AppController::MOBILE_QUEUE_DEFAULT_PER_PAGE . '"',
        );
    }

    public function testMobileApprovalsDataPaginatesPendingApprovals(): void
    {
        $this->authenticateAsSuperUser();
        [$instanceId, $executionLogId] = $this->createWorkflowContext();
        for ($i = 0; $i < 3; $i++) {
            $this->createApproval($instanceId, $executionLogId, 'Mobile Approval ' . $i);
        }
        $this->configRequest([
            'headers' => [
                'Accept' => 'application/json',
                'X-Requested-With' => 'XMLHttpRequest',
            ],
        ]);

        $this->get('/approvals/mobile-data?per_page=2&page=1');

        $this->assertResponseOk();
        $this->assertContentType('application/json');
        $pageOne = json_decode((string)$this->_response->getBody(), true);
        $this->assertCount(2, $pageOne['approvals'] ?? []);
        $this->assertSame(3, $pageOne['pagination']['total'] ?? null);
        $this->assertTrue($pageOne['pagination']['hasNextPage'] ?? false);

        $this->get('/approvals/mobile-data?per_page=2&page=2');

        $this->assertResponseOk();
        $pageTwo = json_decode((string)$this->_response->getBody(), true);
        $this->assertCount(1, $pageTwo['approvals'] ?? []);
        $this->assertFalse($pageTwo['pagination']['hasNextPage'] ?? true);
        $pageOneIds = array_column($pageOne['approvals'], 'id');
        $pageTwoIds = array_column($pageTwo['approvals'], 'id');
        $this->assertSame([], array_values(array_intersect($pageOneIds, $pageTwoIds)));
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

    public function testAwardApprovalGridPayloadRequiresGatheringFromWorkflowSlug(): void
    {
        $this->authenticateAsSuperUser();
        [$instanceId, $executionLogId] = $this->createWorkflowContext('awards-recommendation-submitted');
        $this->createApproval(
            $instanceId,
            $executionLogId,
            'Award Recommendation: Needs Gathering',
            ['member_id' => self::ADMIN_MEMBER_ID],
        );

        $this->get('/approvals/grid-data');

        $this->assertResponseOk();
        $rows = array_values(iterator_to_array($this->viewVariable('data')));
        $this->assertNotEmpty($rows);
        $targetRows = array_values(array_filter(
            $rows,
            static fn($row): bool => (string)($row->request ?? '') === 'Award Recommendation: Needs Gathering',
        ));
        $this->assertNotEmpty($targetRows);
        $payload = json_decode((string)$targetRows[0]->bulk_response_payload, true);
        $this->assertTrue($payload['approver_config']['requires_bestowal_gathering'] ?? false);
    }

    public function testAwardApprovalGridPayloadUsesAwardsRecommendationGatheringLookup(): void
    {
        $this->authenticateAsSuperUser();
        [$instanceId, $executionLogId] = $this->createWorkflowContext('awards-recommendation-submitted');
        $this->createApproval(
            $instanceId,
            $executionLogId,
            'Award Recommendation: Ranked Gathering Lookup',
            ['member_id' => self::ADMIN_MEMBER_ID],
        );
        $recommendation = $this->createExistingRecommendation();
        $this->createRecommendationApprovalRun((int)$recommendation->id, $instanceId);

        $this->get('/approvals/grid-data');

        $this->assertResponseOk();
        $rows = array_values(iterator_to_array($this->viewVariable('data')));
        $this->assertNotEmpty($rows);
        $targetRows = array_values(array_filter(
            $rows,
            static fn($row): bool => (string)($row->request ?? '') === 'Award Recommendation: Ranked Gathering Lookup',
        ));
        $this->assertNotEmpty($targetRows);
        $payload = json_decode((string)$targetRows[0]->bulk_response_payload, true);
        $this->assertSame(
            '/awards/bestowals/gatherings-for-bestowal-auto-complete?recommendation_id=' . (int)$recommendation->id,
            $payload['approver_config']['bestowal_gathering_url'] ?? null,
        );
    }

    public function testAwardApprovalKanbanPayloadRequiresGatheringFromWorkflowSlug(): void
    {
        $this->authenticateAsSuperUser();
        [$instanceId, $executionLogId] = $this->createWorkflowContext('awards-recommendation-submitted');
        $approvalId = $this->createApproval(
            $instanceId,
            $executionLogId,
            'Award Recommendation: Kanban Gathering',
            ['member_id' => self::ADMIN_MEMBER_ID],
        );

        $this->get('/approvals/kanban-lane?triage_state=new&view_id=sys-approvals-triage-board');

        $this->assertResponseOk();
        $this->assertResponseContains('Award Recommendation: Kanban Gathering');
        $this->assertResponseContains('&quot;id&quot;:' . $approvalId);
        $this->assertResponseContains('&quot;requires_bestowal_gathering&quot;:true');
    }

    public function testBestowalGatheringsAutoCompleteReturnsOnlyFutureGatherings(): void
    {
        $this->authenticateAsSuperUser();
        $futureId = $this->createGathering('ApprovalAuto Future', '+30 days');
        $this->createGathering('ApprovalAuto Past', '-30 days');

        $this->get('/approvals/bestowal-gatherings-auto-complete?q=ApprovalAuto');

        $this->assertResponseOk();
        $body = (string)$this->_response->getBody();
        $this->assertStringContainsString('Future', $body);
        $this->assertStringContainsString('data-ac-value="' . $futureId . '"', $body);
        $this->assertStringNotContainsString('ApprovalAuto Past', $body);
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

    public function testRecordApprovalAjaxFailureIncludesErrorField(): void
    {
        $this->authenticateAsSuperUser();
        $this->configRequest([
            'headers' => [
                'Accept' => 'application/json',
                'X-Requested-With' => 'XMLHttpRequest',
            ],
        ]);

        $this->post('/approvals/record', [
            'approvalId' => 999999,
            'decision' => WorkflowApprovalResponse::DECISION_APPROVE,
            'comment' => '',
        ]);

        $this->assertResponseOk();
        $this->assertContentType('application/json');
        $payload = json_decode((string)$this->_response->getBody(), true);
        $this->assertSame(false, $payload['success'] ?? null);
        $this->assertSame('Approval not found.', $payload['error'] ?? null);
    }

    public function testBulkRecordApprovalRecordsSameTypeResponses(): void
    {
        $this->authenticateAsSuperUser();
        [$instanceId, $executionLogId] = $this->createWorkflowContext();
        $firstApprovalId = $this->createApproval($instanceId, $executionLogId, 'Bulk Approval 1', requiredCount: 2);
        $secondApprovalId = $this->createApproval($instanceId, $executionLogId, 'Bulk Approval 2', requiredCount: 2);

        $this->post('/approvals/record', [
            'approvalIds' => implode(',', [$firstApprovalId, $secondApprovalId]),
            'decision' => WorkflowApprovalResponse::DECISION_APPROVE,
            'comment' => 'Bulk approved.',
            'page_context_url' => '/approvals',
        ]);

        $this->assertRedirectContains('/approvals');
        $responsesTable = TableRegistry::getTableLocator()->get('WorkflowApprovalResponses');
        $responses = $responsesTable->find()
            ->where(['workflow_approval_id IN' => [$firstApprovalId, $secondApprovalId]])
            ->all();
        $this->assertCount(2, $responses);
    }

    public function testBulkRecordApprovalRejectsMixedApprovalTypes(): void
    {
        $this->authenticateAsSuperUser();
        [$instanceId, $executionLogId] = $this->createWorkflowContext();
        $firstApprovalId = $this->createApproval($instanceId, $executionLogId, 'Bulk Approval 1');
        $secondApprovalId = $this->createApproval(
            $instanceId,
            $executionLogId,
            'Bulk Approval 2',
            ['member_id' => self::ADMIN_MEMBER_ID, 'requires_comment' => true],
        );

        $this->post('/approvals/record', [
            'approvalIds' => implode(',', [$firstApprovalId, $secondApprovalId]),
            'decision' => WorkflowApprovalResponse::DECISION_APPROVE,
            'comment' => '',
            'page_context_url' => '/approvals',
        ]);

        $this->assertRedirectContains('/approvals');
        $responsesTable = TableRegistry::getTableLocator()->get('WorkflowApprovalResponses');
        $responseCount = $responsesTable->find()
            ->where(['workflow_approval_id IN' => [$firstApprovalId, $secondApprovalId]])
            ->count();
        $this->assertSame(0, $responseCount);
    }

    public function testRecordApprovalAllowsAwardBestowalApprovalWithoutGathering(): void
    {
        $this->authenticateAsSuperUser();
        [$instanceId, $executionLogId] = $this->createWorkflowContext();
        $approvalId = $this->createApproval(
            $instanceId,
            $executionLogId,
            'Award Approval',
            [
                'member_id' => self::ADMIN_MEMBER_ID,
                'requires_bestowal_gathering' => true,
            ],
        );

        $this->post('/approvals/record', [
            'approvalId' => $approvalId,
            'decision' => WorkflowApprovalResponse::DECISION_APPROVE,
            'comment' => '',
            'page_context_url' => '/approvals',
        ]);

        $this->assertRedirectContains('/approvals');
        $responsesTable = TableRegistry::getTableLocator()->get('WorkflowApprovalResponses');
        $responseCount = $responsesTable->find()
            ->where(['workflow_approval_id' => $approvalId])
            ->count();
        $this->assertSame(1, $responseCount);
    }

    public function testRecordApprovalRejectsPastGatheringForAwardBestowalApproval(): void
    {
        $this->authenticateAsSuperUser();
        [$instanceId, $executionLogId] = $this->createWorkflowContext();
        $approvalId = $this->createApproval(
            $instanceId,
            $executionLogId,
            'Award Approval',
            [
                'member_id' => self::ADMIN_MEMBER_ID,
                'requires_bestowal_gathering' => true,
            ],
        );
        $pastGatheringId = $this->createGathering('ApprovalAuto Past Submit', '-30 days');

        $this->post('/approvals/record', [
            'approvalId' => $approvalId,
            'decision' => WorkflowApprovalResponse::DECISION_APPROVE,
            'bestowal_gathering_id' => $pastGatheringId,
            'comment' => '',
            'page_context_url' => '/approvals',
        ]);

        $this->assertRedirectContains('/approvals');
        $responsesTable = TableRegistry::getTableLocator()->get('WorkflowApprovalResponses');
        $responseCount = $responsesTable->find()
            ->where(['workflow_approval_id' => $approvalId])
            ->count();
        $this->assertSame(0, $responseCount);
    }

    /**
     * @return array{0:int,1:int}
     */
    private function createWorkflowContext(?string $definitionSlug = null): array
    {
        $defTable = TableRegistry::getTableLocator()->get('WorkflowDefinitions');
        $def = null;
        if ($definitionSlug !== null) {
            $def = $defTable->find()->where(['slug' => $definitionSlug])->first();
        }
        if (!$def) {
            $def = $defTable->newEntity([
                'name' => 'Approvals Controller Test ' . uniqid(),
                'slug' => $definitionSlug ?? 'approvals-controller-test-' . uniqid(),
                'trigger_type' => 'manual',
            ]);
            $defTable->saveOrFail($def);
        }

        $versionsTable = TableRegistry::getTableLocator()->get('WorkflowVersions');
        $versionNumber = ((int)$versionsTable->find()
            ->where(['workflow_definition_id' => $def->id])
            ->select(['max_version' => $versionsTable->find()->func()->max('version_number')])
            ->first()
            ?->get('max_version')) + 1;
        $version = $versionsTable->newEntity([
            'workflow_definition_id' => $def->id,
            'version_number' => $versionNumber,
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
        array $approverConfig = ['member_id' => self::ADMIN_MEMBER_ID],
        int $requiredCount = 1,
    ): int {
        $approvalsTable = TableRegistry::getTableLocator()->get('WorkflowApprovals');
        $approval = $approvalsTable->newEntity([
            'workflow_instance_id' => $instanceId,
            'node_id' => 'approval_node',
            'execution_log_id' => $executionLogId,
            'approver_type' => WorkflowApproval::APPROVER_TYPE_MEMBER,
            'approver_config' => $approverConfig,
            'request_title' => $requestTitle,
            'required_count' => $requiredCount,
            'approved_count' => 0,
            'rejected_count' => 0,
            'status' => WorkflowApproval::STATUS_PENDING,
            'allow_parallel' => true,
            'version' => 1,
        ]);
        $approvalsTable->saveOrFail($approval);

        return (int)$approval->id;
    }

    private function createExistingRecommendation(): Recommendation
    {
        $member = TableRegistry::getTableLocator()->get('Members')->get(self::ADMIN_MEMBER_ID);
        $award = TableRegistry::getTableLocator()->get('Awards.Awards')->find()->select(['id'])->firstOrFail();
        $statuses = Recommendation::getStatuses();
        $status = array_key_first($statuses);

        $recommendations = TableRegistry::getTableLocator()->get('Awards.Recommendations');
        $recommendation = $recommendations->newEntity([
            'requester_id' => (int)$member->id,
            'member_id' => (int)$member->id,
            'branch_id' => (int)$member->branch_id,
            'award_id' => (int)$award->id,
            'status' => $status,
            'state' => $statuses[$status][0],
            'state_date' => DateTime::now(),
            'requester_sca_name' => (string)$member->sca_name,
            'member_sca_name' => (string)$member->sca_name,
            'contact_email' => (string)$member->email_address,
            'contact_number' => (string)($member->phone_number ?? ''),
            'reason' => 'Unified approval gathering lookup test',
            'call_into_court' => 'Not Set',
            'court_availability' => 'Not Set',
            'person_to_notify' => '',
            'not_found' => false,
        ]);

        return $recommendations->saveOrFail($recommendation);
    }

    private function createRecommendationApprovalRun(int $recommendationId, int $workflowInstanceId): RecommendationApprovalRun
    {
        $approvalProcesses = TableRegistry::getTableLocator()->get('Awards.ApprovalProcesses');
        $approvalProcess = $approvalProcesses->find()->select(['id'])->first();
        if ($approvalProcess === null) {
            $approvalProcess = $approvalProcesses->saveOrFail($approvalProcesses->newEntity([
                'name' => 'Unified Approval Lookup Test',
                'description' => 'Seeded by unified approval controller test.',
                'is_active' => true,
            ]));
        }

        $runs = TableRegistry::getTableLocator()->get('Awards.RecommendationApprovalRuns');

        return $runs->saveOrFail($runs->newEntity([
            'recommendation_id' => $recommendationId,
            'approval_process_id' => (int)$approvalProcess->id,
            'workflow_instance_id' => $workflowInstanceId,
            'status' => RecommendationApprovalRun::STATUS_IN_PROGRESS,
            'current_step_key' => 'approval',
            'current_step_label' => 'Approval',
            'started' => DateTime::now(),
        ]));
    }

    private function createGathering(string $name, string $startModifier): int
    {
        $branches = TableRegistry::getTableLocator()->get('Branches');
        $branch = $branches->find()->select(['id'])->firstOrFail();
        $types = TableRegistry::getTableLocator()->get('GatheringTypes');
        $type = $types->find()->select(['id'])->firstOrFail();
        $startDate = date('Y-m-d H:i:s', strtotime($startModifier));

        $gatherings = TableRegistry::getTableLocator()->get('Gatherings');
        $gathering = $gatherings->newEntity([
            'branch_id' => (int)$branch->id,
            'gathering_type_id' => (int)$type->id,
            'name' => $name,
            'start_date' => $startDate,
            'end_date' => $startDate,
            'location' => 'Test Hall',
            'created_by' => self::ADMIN_MEMBER_ID,
        ]);
        $gatherings->saveOrFail($gathering);

        return (int)$gathering->id;
    }
}
