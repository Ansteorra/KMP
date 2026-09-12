<?php
declare(strict_types=1);

namespace Awards\Test\TestCase\Controller;

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

    public function testViewShowsBackgroundProgressWithoutCountingCandidates(): void
    {
        $process = $this->createApprovalProcess('Background process');
        $service = $this->createMock(RecommendationApprovalWorkflowSyncService::class);
        $service->expects($this->never())->method('countOutdatedRecommendations');
        $this->mockService(RecommendationApprovalWorkflowSyncService::class, static fn() => $service);
        $this->get('/awards/approval-processes/view/' . $process->id);
        $this->assertResponseOk();
        $this->assertResponseContains('Refresh progress');
        $this->assertResponseContains('awards-approval-sync');
    }

    public function testSyncEnqueuesAndRepeatedClickReturnsSameRun(): void
    {
        $process = $this->createApprovalProcess('Queued process');
        $this->post('/awards/approval-processes/sync-approval-process/' . $process->id);
        $this->assertRedirectContains('/awards/approval-processes/view/' . $process->id);
        $this->post('/awards/approval-processes/sync-approval-process/' . $process->id);
        $this->assertSame(1, $this->getTableLocator()->get('Awards.ApprovalSyncRuns')->find()->where([
            'approval_process_id' => $process->id,
        ])->count());
    }

    public function testSyncStatusRequiresSynchronizationPermission(): void
    {
        $process = $this->createApprovalProcess('Protected progress');
        $this->authenticateAsMember(self::TEST_MEMBER_AGATHA_ID);
        $this->get('/awards/approval-processes/sync-status/' . $process->id);
        $this->assertRedirectContains('/pages/unauthorized');
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
