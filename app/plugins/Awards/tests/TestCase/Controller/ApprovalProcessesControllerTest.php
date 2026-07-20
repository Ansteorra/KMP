<?php
declare(strict_types=1);

namespace Awards\Test\TestCase\Controller;

use App\Test\TestCase\Support\HttpIntegrationTestCase;
use Awards\Model\Entity\ApprovalProcessStep;

class ApprovalProcessesControllerTest extends HttpIntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->authenticateAsSuperUser();
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
