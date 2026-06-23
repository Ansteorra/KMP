<?php
declare(strict_types=1);

namespace App\Test\TestCase\Services;

use App\Model\Entity\WorkflowApproval;
use App\Model\Entity\WorkflowInstance;
use App\Services\BackupRestoreCompatibilityService;
use App\Test\TestCase\BaseTestCase;
use Awards\Model\Entity\ApprovalProcessStep;
use Awards\Model\Entity\RecommendationApprovalRun;
use Cake\Datasource\ConnectionManager;
use Cake\I18n\DateTime;
use ReflectionMethod;

/**
 * @covers \App\Services\BackupRestoreCompatibilityService
 */
class BackupRestoreCompatibilityServiceTest extends BaseTestCase
{
    private BackupRestoreCompatibilityService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new BackupRestoreCompatibilityService();
    }

    public function testBaselineRestoreReplaysDefaultAwardApprovalProcessSeeds(): void
    {
        $connection = ConnectionManager::get('default');
        $names = [
            'Single Approver - Crown',
            'Single Approver - Local',
            'Dual Approver - Local then Crown',
        ];
        $connection->execute(
            'UPDATE awards_awards SET approval_process_id = NULL WHERE approval_process_id IN (
                SELECT id FROM awards_approval_processes WHERE name IN (?, ?, ?)
            )',
            $names,
        );
        $connection->execute(
            'UPDATE awards_approval_processes SET is_active = FALSE, deleted = ? WHERE name IN (?, ?, ?)',
            [DateTime::now()->toDateTimeString(), ...$names],
        );

        $stats = $this->invokePrivate('seedBaselineAwardApprovalProcesses', [
            $connection,
            ['meta' => ['version' => 1], 'tables' => ['awards_recommendations' => []]],
        ]);

        $this->assertSame(['award_approval_processes_seeded' => 3], $stats);
        $processes = $this->getTableLocator()->get('Awards.ApprovalProcesses')->find()
            ->where(['name IN' => $names])
            ->all()
            ->indexBy('name')
            ->toArray();
        $this->assertCount(3, $processes);
        foreach ($names as $name) {
            $this->assertTrue((bool)$processes[$name]->is_active);
            $this->assertNull($processes[$name]->deleted);
        }

        $stepCount = $this->getTableLocator()->get('Awards.ApprovalProcessSteps')->find()
            ->where(['approval_process_id IN' => array_map(static fn($process): int => (int)$process->id, $processes)])
            ->count();
        $this->assertSame(4, $stepCount);
    }

    public function testBaselineAwardApprovalSeedDoesNotReplayWhenBackupContainsApprovalProcessTables(): void
    {
        $connection = ConnectionManager::get('default');

        $stats = $this->invokePrivate('seedBaselineAwardApprovalProcesses', [
            $connection,
            ['meta' => ['version' => 1], 'tables' => ['awards_approval_processes' => []]],
        ]);

        $this->assertSame(['award_approval_processes_seeded' => 0], $stats);
    }

    public function testSubmittedRecommendationApprovalWorkflowBackfillIsIdempotent(): void
    {
        $this->publishExistingRecommendationWorkflow();
        $this->getTableLocator()->get('Awards.Recommendations')->updateAll(
            ['state' => 'No Action'],
            ['state' => 'Submitted'],
        );

        $process = $this->createApprovalProcess();
        $award = $this->createAward((int)$process->id);
        $recommendation = $this->createRecommendation((int)$award->id);
        $connection = ConnectionManager::get('default');

        $firstStats = $this->invokePrivate('backfillSubmittedRecommendationApprovalWorkflows', [$connection]);
        $secondStats = $this->invokePrivate('backfillSubmittedRecommendationApprovalWorkflows', [$connection]);

        $this->assertSame(['award_approval_workflows_backfilled' => 1], $firstStats);
        $this->assertSame(['award_approval_workflows_backfilled' => 0], $secondStats);

        $run = $this->getTableLocator()->get('Awards.RecommendationApprovalRuns')->find()
            ->where([
                'recommendation_id' => (int)$recommendation->id,
                'status' => RecommendationApprovalRun::STATUS_IN_PROGRESS,
            ])
            ->firstOrFail();
        $this->assertSame('approval', $run->current_step_key);

        $instance = $this->getTableLocator()->get('WorkflowInstances')->get((int)$run->workflow_instance_id);
        $this->assertSame(WorkflowInstance::STATUS_WAITING, $instance->status);
        $this->assertSame('Awards.Recommendations', $instance->entity_type);
        $this->assertSame((int)$recommendation->id, (int)$instance->entity_id);
        $this->assertSame(['award-approval-gate'], $instance->active_nodes);

        $approval = $this->getTableLocator()->get('WorkflowApprovals')->find()
            ->where([
                'workflow_instance_id' => (int)$instance->id,
                'node_id' => 'award-approval-gate',
                'status' => WorkflowApproval::STATUS_PENDING,
            ])
            ->firstOrFail();
        $this->assertSame(WorkflowApproval::APPROVER_TYPE_DYNAMIC, $approval->approver_type);
        $this->assertSame('approval', $approval->approver_config['award_approval_step_key']);
        $this->assertTrue($approval->approver_config['requires_bestowal_gathering']);
    }

    /**
     * @param array<int, mixed> $args
     */
    private function invokePrivate(string $methodName, array $args): array
    {
        $method = new ReflectionMethod(BackupRestoreCompatibilityService::class, $methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($this->service, $args);
    }

    private function publishExistingRecommendationWorkflow(): void
    {
        $definitions = $this->getTableLocator()->get('WorkflowDefinitions');
        $versions = $this->getTableLocator()->get('WorkflowVersions');
        $definitionJson = json_decode(
            (string)file_get_contents(CONFIG . 'Seeds/WorkflowDefinitions/awards-existing-recommendation-approval.json'),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        $definition = $definitions->find()
            ->where(['slug' => 'awards-existing-recommendation-approval'])
            ->first();
        if (!$definition) {
            $definition = $definitions->newEntity([
                'name' => 'Existing Recommendation Approval',
                'slug' => 'awards-existing-recommendation-approval',
                'trigger_type' => 'event',
                'trigger_config' => ['event' => 'Awards.ExistingRecommendationApprovalRequested'],
                'entity_type' => 'Awards.Recommendations',
                'is_active' => true,
                'execution_mode' => 'durable',
            ]);
        }
        $definition->is_active = true;
        $definition->execution_mode = 'durable';
        $definition = $definitions->saveOrFail($definition);

        $versionNumber = (int)$versions->find()
            ->where(['workflow_definition_id' => $definition->id])
            ->count() + 1;
        $version = $versions->saveOrFail($versions->newEntity([
            'workflow_definition_id' => $definition->id,
            'version_number' => $versionNumber,
            'definition' => $definitionJson,
            'status' => 'published',
        ]));

        $definition->current_version_id = $version->id;
        $definitions->saveOrFail($definition);
    }

    private function createApprovalProcess()
    {
        $processes = $this->getTableLocator()->get('Awards.ApprovalProcesses');

        return $processes->saveOrFail($processes->newEntity([
            'name' => 'Restore Compatibility Approval Process ' . uniqid('', true),
            'is_active' => true,
            'approval_process_steps' => [[
                'step_key' => 'approval',
                'label' => 'Approval',
                'sequence' => 1,
                'step_type' => ApprovalProcessStep::STEP_TYPE_APPROVAL,
                'approver_type' => ApprovalProcessStep::APPROVER_TYPE_MEMBER,
                'approver_source_id' => self::ADMIN_MEMBER_ID,
                'branch_mode' => ApprovalProcessStep::BRANCH_MODE_AWARD,
                'threshold_mode' => ApprovalProcessStep::THRESHOLD_ANY,
                'on_reject' => ApprovalProcessStep::ACTION_RETURN_PREVIOUS,
                'on_request_changes' => ApprovalProcessStep::ACTION_RETURN_PREVIOUS,
                'retain_read_visibility' => true,
            ]],
        ], ['associated' => ['ApprovalProcessSteps']]));
    }

    private function createAward(int $processId)
    {
        $awards = $this->getTableLocator()->get('Awards.Awards');

        return $awards->saveOrFail($awards->newEntity([
            'name' => 'Restore Compatibility Award ' . uniqid('', true),
            'abbreviation' => strtoupper(substr(md5(uniqid('', true)), 0, 8)),
            'domain_id' => 2,
            'level_id' => 1,
            'branch_id' => self::KINGDOM_BRANCH_ID,
            'approval_process_id' => $processId,
            'is_active' => true,
        ]));
    }

    private function createRecommendation(int $awardId)
    {
        $recommendations = $this->getTableLocator()->get('Awards.Recommendations');

        return $recommendations->saveOrFail($recommendations->newEntity([
            'requester_id' => self::ADMIN_MEMBER_ID,
            'member_id' => self::ADMIN_MEMBER_ID,
            'branch_id' => self::KINGDOM_BRANCH_ID,
            'award_id' => $awardId,
            'status' => 'In Progress',
            'state' => 'Submitted',
            'state_date' => DateTime::now(),
            'requester_sca_name' => 'Admin von Admin',
            'member_sca_name' => 'Admin von Admin',
            'contact_email' => 'admin@amp.ansteorra.org',
            'contact_number' => '555-555-0100',
            'reason' => 'Testing restore compatibility approval repair',
            'call_into_court' => 'No',
            'court_availability' => 'Anytime',
        ]));
    }
}
