<?php
declare(strict_types=1);

namespace Awards\Test\TestCase\Controller;

use App\Services\ServiceResult;
use App\Test\TestCase\Support\HttpIntegrationTestCase;
use Awards\Model\Entity\ApprovalProcessStep;
use Awards\Services\RecommendationApprovalWorkflowSyncService;

class ApprovalProcessesControllerTest extends HttpIntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->enableRetainFlashMessages();
        $this->authenticateAsSuperUser();
    }

    public function testIndexRendersConfirmedPostSyncAction(): void
    {
        $this->get('/awards/approval-processes');

        $this->assertResponseOk();
        $this->assertResponseContains('Sync Open Recommendations Now');
        $this->assertResponseContains('action="/awards/approval-processes/sync-open-recommendations"');
        $this->assertResponseContains('data-confirm-message=');
        $this->assertResponseContains('create exactly one bestowal');
        $this->assertResponseContains('Synchronization never marks a bestowal Given');
        $this->assertResponseContains('data-confirm-title="Synchronize open recommendations"');
        $this->assertResponseContains('data-confirm-label="Sync Now"');
        $this->assertResponseContains('data-turbo-frame="_top"');
    }

    public function testSyncOpenRecommendationsUsesActorAndFlashesSummary(): void
    {
        $service = $this->createMock(RecommendationApprovalWorkflowSyncService::class);
        $service->expects($this->once())
            ->method('syncOpenRecommendations')
            ->with(self::ADMIN_MEMBER_ID)
            ->willReturn(new ServiceResult(true, null, [
                'backfillCandidateCount' => 3,
                'backfilledCount' => 1,
                'backfillUnchangedCount' => 0,
                'backfillSkippedCount' => 2,
                'backfillFailedCount' => 0,
                'backfillSkips' => [
                    ['recommendationId' => 40, 'reason' => '<em>Active</em> feedback request.'],
                    ['recommendationId' => 41, 'reason' => 'No approval process.'],
                ],
                'processedCount' => 5,
                'synchronizedCount' => 2,
                'advancedCount' => 1,
                'versionMigratedCount' => 1,
                'unchangedCount' => 2,
                'activeRunSkippedCount' => 1,
                'activeRunFailedCount' => 0,
                'skippedCount' => 3,
                'failedCount' => 0,
                'failures' => [],
            ]));
        $this->mockService(
            RecommendationApprovalWorkflowSyncService::class,
            static fn() => $service,
        );

        $this->post('/awards/approval-processes/sync-open-recommendations');

        $this->assertRedirect(['controller' => 'ApprovalProcesses', 'action' => 'index']);
        $this->assertFlashMessage(
            'Ownership backfill reviewed 3 open recommendation(s) without an active run: '
            . '1 started, 0 unchanged, 2 skipped, and 0 failed. '
            . 'Processed 5 active workflow(s): 2 synchronized '
            . '(1 advanced; 1 workflow version(s) migrated), 2 unchanged, 1 skipped, and 0 failed. '
            . 'Recommendations needing attention: #40, #41. '
            . 'Backfill skip reasons: Active feedback request (1); No approval process (1).',
            'flash',
        );
    }

    public function testSyncOpenRecommendationsFlashesSafeFailureCategories(): void
    {
        $service = $this->createMock(RecommendationApprovalWorkflowSyncService::class);
        $service->expects($this->once())
            ->method('syncOpenRecommendations')
            ->with(self::ADMIN_MEMBER_ID)
            ->willReturn(new ServiceResult(false, 'One or more workflows failed.', [
                'backfillCandidateCount' => 0,
                'backfilledCount' => 0,
                'backfillUnchangedCount' => 0,
                'backfillSkippedCount' => 0,
                'backfillFailedCount' => 0,
                'backfillSkips' => [],
                'processedCount' => 1,
                'synchronizedCount' => 0,
                'advancedCount' => 0,
                'versionMigratedCount' => 0,
                'unchangedCount' => 0,
                'activeRunSkippedCount' => 0,
                'activeRunFailedCount' => 1,
                'skippedCount' => 0,
                'failedCount' => 1,
                'failures' => [[
                    'runId' => 77,
                    'reason' => 'SQLSTATE secret schema detail that must not be shown.',
                ]],
            ]));
        $this->mockService(
            RecommendationApprovalWorkflowSyncService::class,
            static fn() => $service,
        );

        $this->post('/awards/approval-processes/sync-open-recommendations');

        $this->assertRedirect(['controller' => 'ApprovalProcesses', 'action' => 'index']);
        $this->assertFlashMessage(
            'Ownership backfill reviewed 0 open recommendation(s) without an active run: '
            . '0 started, 0 unchanged, 0 skipped, and 0 failed. '
            . 'Processed 1 active workflow(s): 0 synchronized '
            . '(0 advanced; 0 workflow version(s) migrated), 0 unchanged, 0 skipped, and 1 failed. '
            . 'Workflow runs needing attention: #77. '
            . 'Failure categories: Active workflow synchronization error (1). '
            . 'One or more workflows failed.',
            'flash',
        );
    }

    public function testSyncOpenRecommendationsRejectsGet(): void
    {
        $service = $this->createMock(RecommendationApprovalWorkflowSyncService::class);
        $service->expects($this->never())->method('syncOpenRecommendations');
        $this->mockService(
            RecommendationApprovalWorkflowSyncService::class,
            static fn() => $service,
        );

        $this->get('/awards/approval-processes/sync-open-recommendations');

        $this->assertResponseCode(405);
    }

    public function testAddStepAcceptsTypedComboboxSourceField(): void
    {
        $approvalProcesses = $this->getTableLocator()->get('Awards.ApprovalProcesses');
        $steps = $this->getTableLocator()->get('Awards.ApprovalProcessSteps');
        $roles = $this->getTableLocator()->get('Roles');

        $role = $roles->find()->firstOrFail();
        $process = $approvalProcesses->saveOrFail($approvalProcesses->newEntity([
            'name' => 'Controller Add Step Test ' . uniqid(),
            'description' => 'Created by controller regression test',
            'is_active' => true,
        ]));
        $stepKey = 'controller_step_' . uniqid();

        $this->post('/awards/approval-processes/add-step/' . $process->id, [
            'label' => 'Controller Step',
            'step_key' => $stepKey,
            'sequence' => '1',
            'approver_type' => ApprovalProcessStep::APPROVER_TYPE_ROLE,
            'role_source_id' => (string)$role->id,
            'role_source' => (string)$role->name,
            'branch_mode' => ApprovalProcessStep::BRANCH_MODE_AWARD,
            'threshold_mode' => ApprovalProcessStep::THRESHOLD_ANY,
            'on_reject' => ApprovalProcessStep::ACTION_RETURN_PREVIOUS,
            'on_request_changes' => ApprovalProcessStep::ACTION_RETURN_PREVIOUS,
            'retain_read_visibility' => '1',
        ]);

        $this->assertRedirect(['controller' => 'ApprovalProcesses', 'action' => 'view', $process->id]);

        $createdStep = $steps->find()
            ->where([
                'approval_process_id' => $process->id,
                'step_key' => $stepKey,
            ])
            ->firstOrFail();

        $this->assertSame((int)$role->id, (int)$createdStep->approver_source_id);
        $this->assertSame(ApprovalProcessStep::APPROVER_TYPE_ROLE, $createdStep->approver_type);
    }

    public function testPreviewApproversRendersTurboFrameOnly(): void
    {
        $approvalProcesses = $this->getTableLocator()->get('Awards.ApprovalProcesses');
        $awards = $this->getTableLocator()->get('Awards.Awards');
        $process = $approvalProcesses->saveOrFail($approvalProcesses->newEntity([
            'name' => 'Controller Preview Frame Test ' . uniqid(),
            'description' => 'Created by controller regression test',
            'is_active' => true,
        ]));
        $award = $awards->find()->select(['id'])->firstOrFail();
        $awards->updateAll(['approval_process_id' => $process->id], ['id' => $award->id]);

        $this->configRequest([
            'headers' => ['Turbo-Frame' => 'approval-process-approver-preview'],
        ]);
        $this->get('/awards/approval-processes/view/' . $process->id . '?preview_award_id=' . $award->id);

        $this->assertResponseOk();
        $this->assertResponseContains('<turbo-frame id="approval-process-approver-preview">');
        $this->assertResponseContains('data-turbo-frame="approval-process-approver-preview"');
        $this->assertResponseContains('aria-live="polite"');
        $this->assertResponseNotContains('<main id="main-content"');
        $this->assertResponseNotContains('data-controller="detail-tabs"');
    }

    public function testPreviewAwardDropdownOnlyIncludesAssignedAwards(): void
    {
        $approvalProcesses = $this->getTableLocator()->get('Awards.ApprovalProcesses');
        $awards = $this->getTableLocator()->get('Awards.Awards');
        $process = $approvalProcesses->saveOrFail($approvalProcesses->newEntity([
            'name' => 'Controller Preview Award Filter Test ' . uniqid(),
            'description' => 'Created by controller regression test',
            'is_active' => true,
        ]));
        $awardRows = $awards->find()
            ->select(['id', 'name'])
            ->orderBy(['id' => 'ASC'])
            ->limit(2)
            ->all()
            ->toList();
        $this->assertGreaterThanOrEqual(2, count($awardRows));
        $assignedAward = $awardRows[0];
        $unassignedAward = $awardRows[1];
        $awards->updateAll(['approval_process_id' => $process->id], ['id' => $assignedAward->id]);
        $awards->updateAll(['approval_process_id' => null], ['id' => $unassignedAward->id]);

        $this->get('/awards/approval-processes/view/' . $process->id);

        $this->assertResponseOk();
        $this->assertResponseContains(h($assignedAward->name));
        $this->assertResponseNotContains(h($unassignedAward->name));
    }
}
