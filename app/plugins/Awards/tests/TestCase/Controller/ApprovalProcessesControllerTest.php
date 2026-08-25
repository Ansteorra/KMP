<?php
declare(strict_types=1);

namespace Awards\Test\TestCase\Controller;

use App\Services\ServiceResult;
use App\Test\TestCase\Support\HttpIntegrationTestCase;
use Awards\Model\Entity\ApprovalProcessStep;
use Awards\Services\RecommendationApprovalWorkflowSyncService;
use Cake\Datasource\EntityInterface;

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

    public function testIndexDoesNotRenderGlobalRecommendationSyncAction(): void
    {
        $this->get('/awards/approval-processes');

        $this->assertResponseOk();
        $this->assertResponseNotContains('Sync Open Recommendations Now');
    }

    public function testViewEnablesProcessSyncOnlyWhenOutdatedRecommendationsExist(): void
    {
        $process = $this->createApprovalProcess('Controller Enabled Sync Test');
        $service = $this->createMock(RecommendationApprovalWorkflowSyncService::class);
        $service->expects($this->once())
            ->method('countOutdatedRecommendations')
            ->with((int)$process->id)
            ->willReturn(3);
        $this->mockService(RecommendationApprovalWorkflowSyncService::class, static fn() => $service);

        $this->get('/awards/approval-processes/view/' . $process->id);

        $this->assertResponseOk();
        $this->assertResponseContains('Sync Outdated Recommendations (3)');
        $this->assertResponseContains(
            'action="/awards/approval-processes/sync-approval-process/' . $process->id . '"',
        );
        $this->assertResponseContains('Only recommendations using an older process snapshot or workflow version');
        $this->assertResponseContains('This action does not create a bestowal');
        $this->assertResponseContains('data-confirm-label="Sync Now"');
        $this->assertResponseContains('data-turbo-frame="_top"');
        $this->assertResponseRegExp(
            '~<button(?=[^>]*data-confirm-label="Sync Now")(?![^>]*\bdisabled\b)[^>]*>'
            . '\s*Sync Outdated Recommendations \(3\)\s*</button>~',
        );
    }

    public function testViewDisablesProcessSyncWhenAllRecommendationsAreCurrent(): void
    {
        $process = $this->createApprovalProcess('Controller Disabled Sync Test');
        $service = $this->createMock(RecommendationApprovalWorkflowSyncService::class);
        $service->expects($this->once())
            ->method('countOutdatedRecommendations')
            ->with((int)$process->id)
            ->willReturn(0);
        $this->mockService(RecommendationApprovalWorkflowSyncService::class, static fn() => $service);

        $this->get('/awards/approval-processes/view/' . $process->id);

        $this->assertResponseOk();
        $this->assertResponseRegExp(
            '~<button(?=[^>]*\bdisabled\b)(?=[^>]*aria-describedby="approval-process-sync-status")[^>]*>'
            . '\s*Sync Outdated Recommendations\s*</button>~',
        );
        $this->assertResponseContains('All open recommendations assigned to this process are current.');
        $this->assertResponseNotContains('/sync-approval-process/' . $process->id);
    }

    public function testSyncApprovalProcessUsesProcessAndActorAndFlashesSummary(): void
    {
        $process = $this->createApprovalProcess('Controller Process Sync Test');
        $service = $this->createMock(RecommendationApprovalWorkflowSyncService::class);
        $service->expects($this->once())
            ->method('syncApprovalProcess')
            ->with((int)$process->id, self::ADMIN_MEMBER_ID)
            ->willReturn(new ServiceResult(true, null, [
                'candidateCount' => 5,
                'processedCount' => 5,
                'restartedCount' => 4,
                'cancelledRunCount' => 5,
                'activeRunSkippedCount' => 1,
                'activeRunFailedCount' => 0,
                'skippedCount' => 1,
                'failedCount' => 0,
                'failures' => [],
            ]));
        $this->mockService(
            RecommendationApprovalWorkflowSyncService::class,
            static fn() => $service,
        );

        $this->post('/awards/approval-processes/sync-approval-process/' . $process->id);

        $this->assertRedirect(['controller' => 'ApprovalProcesses', 'action' => 'view', $process->id]);
        $this->assertFlashMessage(
            'Found 5 outdated open recommendation(s) assigned to Controller Process Sync Test: '
            . '4 restarted after cancelling 5 prior run(s), 1 skipped, and 0 failed.',
            'flash',
        );
        $this->assertFlashElement('flash/success');
    }

    public function testSyncApprovalProcessFlashesSafeFailureCategories(): void
    {
        $process = $this->createApprovalProcess('Controller Failed Sync Test');
        $service = $this->createMock(RecommendationApprovalWorkflowSyncService::class);
        $service->expects($this->once())
            ->method('syncApprovalProcess')
            ->with((int)$process->id, self::ADMIN_MEMBER_ID)
            ->willReturn(new ServiceResult(false, 'One or more workflows failed.', [
                'candidateCount' => 1,
                'processedCount' => 1,
                'restartedCount' => 0,
                'cancelledRunCount' => 0,
                'activeRunSkippedCount' => 0,
                'activeRunFailedCount' => 1,
                'skippedCount' => 0,
                'failedCount' => 1,
                'failures' => [[
                    'recommendationId' => 77,
                    'reason' => 'SQLSTATE secret schema detail that must not be shown.',
                ]],
            ]));
        $this->mockService(
            RecommendationApprovalWorkflowSyncService::class,
            static fn() => $service,
        );

        $this->post('/awards/approval-processes/sync-approval-process/' . $process->id);

        $this->assertRedirect(['controller' => 'ApprovalProcesses', 'action' => 'view', $process->id]);
        $this->assertFlashMessage(
            'Found 1 outdated open recommendation(s) assigned to Controller Failed Sync Test: '
            . '0 restarted after cancelling 0 prior run(s), 0 skipped, and 1 failed. '
            . 'Recommendations needing attention: #77. '
            . 'Failure categories: Active workflow restart error (1). '
            . 'One or more workflows failed.',
            'flash',
        );
        $this->assertFlashElement('flash/error');
    }

    public function testSyncApprovalProcessRejectsGet(): void
    {
        $process = $this->createApprovalProcess('Controller GET Sync Test');
        $service = $this->createMock(RecommendationApprovalWorkflowSyncService::class);
        $service->expects($this->never())->method('syncApprovalProcess');
        $this->mockService(
            RecommendationApprovalWorkflowSyncService::class,
            static fn() => $service,
        );

        $this->get('/awards/approval-processes/sync-approval-process/' . $process->id);

        $this->assertResponseCode(405);
    }

    public function testSyncApprovalProcessRequiresExplicitAuthorization(): void
    {
        $process = $this->createApprovalProcess('Controller Unauthorized Sync Test');
        $service = $this->createMock(RecommendationApprovalWorkflowSyncService::class);
        $service->expects($this->never())->method('syncApprovalProcess');
        $this->mockService(
            RecommendationApprovalWorkflowSyncService::class,
            static fn() => $service,
        );
        $this->authenticateAsMember(self::TEST_MEMBER_AGATHA_ID);

        $this->post('/awards/approval-processes/sync-approval-process/' . $process->id);

        $this->assertRedirectContains('/pages/unauthorized');
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

    private function createApprovalProcess(string $name): EntityInterface
    {
        $approvalProcesses = $this->getTableLocator()->get('Awards.ApprovalProcesses');

        return $approvalProcesses->saveOrFail($approvalProcesses->newEntity([
            'name' => $name,
            'description' => 'Created by controller workflow synchronization test',
            'is_active' => true,
        ]));
    }
}
