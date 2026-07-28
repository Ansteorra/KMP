<?php
declare(strict_types=1);

namespace App\Test\TestCase\Services;

use App\Model\Entity\WorkflowApproval;
use App\Model\Entity\WorkflowInstance;
use App\Model\Table\WorkflowInstancesTable;
use App\Services\BackupRestoreCompatibilityService;
use App\Services\WorkflowEngine\TriggerDispatcher;
use App\Test\TestCase\BaseTestCase;
use Awards\Model\Entity\ApprovalProcessStep;
use Awards\Model\Entity\RecommendationApprovalRun;
use Cake\Database\Connection;
use Cake\Datasource\ConnectionManager;
use Cake\I18n\DateTime;
use ReflectionMethod;

/**
 * @covers \App\Services\BackupRestoreCompatibilityService
 */
class BackupRestoreCompatibilityServiceTest extends BaseTestCase
{
    private static bool $createdLegacyWarrantRosterApprovalsTable = false;

    private BackupRestoreCompatibilityService $service;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        $connection = ConnectionManager::get('test');
        if (in_array('warrant_roster_approvals', $connection->getSchemaCollection()->listTables(), true)) {
            return;
        }

        $connection->execute(
            'CREATE TABLE warrant_roster_approvals (
                warrant_roster_id INTEGER NOT NULL,
                approver_id INTEGER NOT NULL,
                approved_on TIMESTAMP NULL
            )',
        );
        self::$createdLegacyWarrantRosterApprovalsTable = true;
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$createdLegacyWarrantRosterApprovalsTable) {
            ConnectionManager::get('test')->execute('DROP TABLE warrant_roster_approvals');
            self::$createdLegacyWarrantRosterApprovalsTable = false;
        }

        parent::tearDownAfterClass();
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new BackupRestoreCompatibilityService();
    }

    public function testBaselineRestoreReplaysDefaultAwardApprovalProcessSeeds(): void
    {
        $connection = ConnectionManager::get('default');
        $this->ensureBaselineApprovalOfficeFixtures($connection);
        $names = [
            'Single Approver - Crown',
            'Single Approver - Local',
            'Single Approver - Principality Coronet',
            'Dual Approver - Local then Crown',
        ];
        $connection->execute(
            'UPDATE awards_awards SET approval_process_id = NULL WHERE approval_process_id IN (
                SELECT id FROM awards_approval_processes WHERE name IN (?, ?, ?, ?)
            )',
            $names,
        );
        $connection->execute(
            'UPDATE awards_approval_processes SET is_active = FALSE, deleted = ? WHERE name IN (?, ?, ?, ?)',
            [DateTime::now()->toDateTimeString(), ...$names],
        );
        $landedNobilityOfficeId = (int)$connection->execute(
            'SELECT id FROM officers_offices WHERE name = ? AND deleted IS NULL LIMIT 1',
            ['Landed Nobility'],
        )->fetchColumn(0);
        $principalityCoronetOfficeId = (int)$connection->execute(
            'SELECT id FROM officers_offices WHERE name = ? AND deleted IS NULL LIMIT 1',
            ['Principality Coronet'],
        )->fetchColumn(0);

        $stats = $this->invokePrivate('seedBaselineAwardApprovalProcesses', [
            $connection,
            ['meta' => ['version' => 1], 'tables' => ['awards_recommendations' => []]],
        ]);

        $this->assertSame(['award_approval_processes_seeded' => 4], $stats);
        $processes = $this->getTableLocator()->get('Awards.ApprovalProcesses')->find()
            ->where(['name IN' => $names])
            ->all()
            ->indexBy('name')
            ->toArray();
        $this->assertCount(4, $processes);
        foreach ($names as $name) {
            $this->assertTrue((bool)$processes[$name]->is_active);
            $this->assertNull($processes[$name]->deleted);
        }

        $stepCount = $this->getTableLocator()->get('Awards.ApprovalProcessSteps')->find()
            ->where(['approval_process_id IN' => array_map(static fn($process): int => (int)$process->id, $processes)])
            ->count();
        $this->assertSame(5, $stepCount);
        $localStepCount = $this->getTableLocator()->get('Awards.ApprovalProcessSteps')->find()
            ->where([
                'approval_process_id' => (int)$processes['Single Approver - Local']->id,
                'approver_source_id' => $landedNobilityOfficeId,
            ])
            ->count();
        $this->assertSame(1, $localStepCount);
        $principalityStepCount = $this->getTableLocator()->get('Awards.ApprovalProcessSteps')->find()
            ->where([
                'approval_process_id' => (int)$processes['Single Approver - Principality Coronet']->id,
                'approver_source_id' => $principalityCoronetOfficeId,
            ])
            ->count();
        $this->assertSame(1, $principalityStepCount);
        $principalityAwardCount = (int)$connection->execute(
            'SELECT COUNT(*) FROM awards_awards WHERE domain_id = ? AND approval_process_id = ? AND deleted IS NULL',
            [11, (int)$processes['Single Approver - Principality Coronet']->id],
        )->fetchColumn(0);
        $this->assertGreaterThan(0, $principalityAwardCount);
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

    public function testRestoreCreatesWorkflowTriggerDispatcherForRecommendationMigration(): void
    {
        $this->assertInstanceOf(
            TriggerDispatcher::class,
            $this->invokePrivateValue('createWorkflowTriggerDispatcher', []),
        );
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

    public function testRestoreLifecycleMigrationHydratesLegacySubmittedRecommendationWorkflow(): void
    {
        $this->publishExistingRecommendationWorkflow();
        $this->getTableLocator()->get('Awards.Recommendations')->updateAll(
            [
                'status' => 'Closed',
                'state' => 'No Action',
                'bestowal_id' => null,
            ],
            ['deleted IS' => null],
        );

        $process = $this->createApprovalProcess();
        $award = $this->createAward((int)$process->id);
        $recommendation = $this->createRecommendation((int)$award->id);
        $connection = ConnectionManager::get('default');

        $firstStats = $this->invokePrivate('runAwardRecommendationLifecycleMigration', [$connection]);

        $this->assertSame(1, $firstStats['award_recommendation_migration_approval_workflow']);
        $this->assertSame(0, $firstStats['award_recommendation_migration_error']);

        $run = $this->getTableLocator()->get('Awards.RecommendationApprovalRuns')->find()
            ->where([
                'recommendation_id' => (int)$recommendation->id,
                'status' => RecommendationApprovalRun::STATUS_IN_PROGRESS,
            ])
            ->firstOrFail();
        $this->assertSame('approval', $run->current_step_key);
        $this->assertSame('Approval', $run->current_step_label);

        $instance = $this->getTableLocator()->get('WorkflowInstances')->get((int)$run->workflow_instance_id);
        $this->assertSame(WorkflowInstance::STATUS_WAITING, $instance->status);
        $this->assertSame('Awards.Recommendations', $instance->entity_type);
        $this->assertSame((int)$recommendation->id, (int)$instance->entity_id);
        $this->assertSame(self::ADMIN_MEMBER_ID, (int)$instance->started_by);
        $this->assertSame(['award-approval-gate'], $instance->active_nodes);

        $approval = $this->getTableLocator()->get('WorkflowApprovals')->find()
            ->where([
                'workflow_instance_id' => (int)$instance->id,
                'node_id' => 'award-approval-gate',
                'status' => WorkflowApproval::STATUS_PENDING,
            ])
            ->firstOrFail();
        $this->assertSame(WorkflowApproval::APPROVER_TYPE_DYNAMIC, $approval->approver_type);
        $this->assertSame(self::ADMIN_MEMBER_ID, (int)$approval->current_approver_id);
        $this->assertSame('Approval Required: Awards.Recommendations', (string)$approval->request_title);

        $executionLogCount = $this->getTableLocator()->get('WorkflowExecutionLogs')->find()
            ->where([
                'workflow_instance_id' => (int)$instance->id,
                'node_id' => 'award-approval-gate',
            ])
            ->count();
        $this->assertGreaterThanOrEqual(1, $executionLogCount);

        $secondStats = $this->invokePrivate('runAwardRecommendationLifecycleMigration', [$connection]);
        $this->assertSame(1, $secondStats['award_recommendation_migration_approval_workflow']);
        $this->assertSame(1, $secondStats['award_recommendation_migration_skipped']);
        $this->assertSame(0, $secondStats['award_recommendation_migration_error']);
        $this->assertSame(
            1,
            $this->getTableLocator()->get('WorkflowInstances')->find()
                ->where([
                    'entity_type' => 'Awards.Recommendations',
                    'entity_id' => (int)$recommendation->id,
                ])
                ->count(),
        );
    }

    public function testWarrantRosterApprovalWorkflowBackfillIsIdempotent(): void
    {
        $connection = ConnectionManager::get('default');
        $createdLegacyTable = $this->createLegacyWarrantRosterApprovalSourceTable($connection);

        try {
            $this->invokePrivate('syncWorkflowDefinitions', [$connection]);
            $this->clearWarrantRosterWorkflowFixtures($connection);
            $rosterName = 'Restore Compatibility Warrant Roster ' . uniqid('', true);
            $connection->insert('warrant_rosters', [
                'name' => $rosterName,
                'approvals_required' => 1,
                'approval_count' => 1,
                'status' => 'approved',
                'created' => '2026-01-01 10:00:00',
                'modified' => '2026-01-01 10:05:00',
                'created_by' => self::ADMIN_MEMBER_ID,
                'modified_by' => self::ADMIN_MEMBER_ID,
            ]);
            $rosterId = (int)$connection->execute(
                'SELECT id FROM warrant_rosters WHERE name = ?',
                [$rosterName],
            )->fetchColumn(0);
            $connection->insert('warrant_roster_approvals', [
                'warrant_roster_id' => $rosterId,
                'approver_id' => self::ADMIN_MEMBER_ID,
                'approved_on' => '2026-01-01 10:05:00',
            ]);

            $beforeInstances = $this->getTableLocator()->get('WorkflowInstances')->find()
                ->where(['entity_type' => 'WarrantRosters'])
                ->count();
            $firstStats = $this->invokePrivate('backfillWarrantRosterApprovalWorkflows', [$connection]);
            $afterInstances = $this->getTableLocator()->get('WorkflowInstances')->find()
                ->where(['entity_type' => 'WarrantRosters'])
                ->count();
            $secondStats = $this->invokePrivate('backfillWarrantRosterApprovalWorkflows', [$connection]);

            $this->assertSame(
                ['warrant_roster_approval_workflows_backfilled' => $afterInstances - $beforeInstances],
                $firstStats,
            );
            $this->assertGreaterThanOrEqual(1, $firstStats['warrant_roster_approval_workflows_backfilled']);
            $this->assertSame(['warrant_roster_approval_workflows_backfilled' => 0], $secondStats);

            $instance = $this->getTableLocator()->get('WorkflowInstances')->find()
                ->where([
                    'entity_type' => 'WarrantRosters',
                    'entity_id' => $rosterId,
                    'status' => WorkflowInstance::STATUS_COMPLETED,
                ])
                ->firstOrFail();
            $this->assertSame([], $instance->active_nodes);

            $approval = $this->getTableLocator()->get('WorkflowApprovals')->find()
                ->where([
                    'workflow_instance_id' => (int)$instance->id,
                    'node_id' => 'approval-gate',
                    'status' => WorkflowApproval::STATUS_APPROVED,
                ])
                ->firstOrFail();
            $this->assertSame(1, (int)$approval->approved_count);
            $this->assertSame('App\\Policy\\WarrantRosterPolicy', $approval->approver_config['policyClass']);

            $response = $this->getTableLocator()->get('WorkflowApprovalResponses')->find()
                ->where([
                    'workflow_approval_id' => (int)$approval->id,
                    'member_id' => self::ADMIN_MEMBER_ID,
                    'decision' => 'approve',
                ])
                ->firstOrFail();
            $this->assertSame('2026-01-01 10:05:00', $response->responded_at->format('Y-m-d H:i:s'));
        } finally {
            if ($createdLegacyTable) {
                $connection->execute('DROP TABLE warrant_roster_approvals');
            }
        }
    }

    private function ensureBaselineApprovalOfficeFixtures(Connection $connection): void
    {
        $fixtures = [
            'Deleted: Landed Nobility' => 'Landed Nobility',
            'Principality Sovereign' => 'Principality Coronet',
        ];
        foreach ($fixtures as $sourceName => $targetName) {
            $connection->execute(
                'UPDATE officers_offices SET deleted = NULL WHERE name = ?',
                [$targetName],
            );
            $exists = (int)$connection->execute(
                'SELECT COUNT(*) FROM officers_offices WHERE name = ? AND deleted IS NULL',
                [$targetName],
            )->fetchColumn(0);
            if ($exists === 0) {
                $connection->execute(
                    'UPDATE officers_offices SET name = ?, deleted = NULL WHERE name = ?',
                    [$targetName, $sourceName],
                );
            }
        }
    }

    private function clearWarrantRosterWorkflowFixtures(Connection $connection): void
    {
        $instanceIds = "SELECT id FROM workflow_instances WHERE entity_type = 'WarrantRosters'";
        $approvalIds = "SELECT id FROM workflow_approvals WHERE workflow_instance_id IN ({$instanceIds})";

        // Approvals must be removed before execution logs because their log foreign key is NO_ACTION.
        $connection->execute(
            "DELETE FROM workflow_approval_responses WHERE workflow_approval_id IN ({$approvalIds})",
        );
        $connection->execute(
            "DELETE FROM workflow_approvals WHERE workflow_instance_id IN ({$instanceIds})",
        );
        $connection->execute(
            "DELETE FROM workflow_execution_logs WHERE workflow_instance_id IN ({$instanceIds})",
        );
        $connection->execute(
            "DELETE FROM workflow_instances WHERE entity_type = 'WarrantRosters'",
        );
    }

    public function testWarrantRosterEntityRewriteRefreshesActiveUniquenessKey(): void
    {
        $connection = ConnectionManager::get('default');
        $createdLegacyTable = $this->createLegacyWarrantRosterApprovalSourceTable($connection);

        try {
            $this->invokePrivate('syncWorkflowDefinitions', [$connection]);
            $definition = $this->getTableLocator()->get('WorkflowDefinitions')->find()
                ->where(['slug' => 'warrants-roster-approval'])
                ->firstOrFail();
            $connection->update(
                'workflow_definitions',
                ['entity_type' => 'Warrants'],
                ['id' => (int)$definition->id],
            );

            $entityId = -2147482999;
            $instancesTable = $this->getTableLocator()->get('WorkflowInstances');
            $instance = $instancesTable->saveOrFail($instancesTable->newEntity([
                'workflow_definition_id' => $definition->id,
                'workflow_version_id' => $definition->current_version_id,
                'entity_type' => 'Warrants',
                'entity_id' => $entityId,
                'status' => WorkflowInstance::STATUS_WAITING,
                'context' => [],
                'active_nodes' => ['approval-gate'],
                'started_by' => self::ADMIN_MEMBER_ID,
                'started_at' => DateTime::now(),
            ]));
            $oldKey = $instance->active_entity_key;

            $this->invokePrivate('backfillWarrantRosterApprovalWorkflows', [$connection]);

            $updated = $instancesTable->get($instance->id);
            $this->assertSame('WarrantRosters', $updated->entity_type);
            $this->assertNotSame($oldKey, $updated->active_entity_key);
            $this->assertSame(
                WorkflowInstancesTable::buildActiveEntityKey(
                    (int)$definition->id,
                    'WarrantRosters',
                    $entityId,
                ),
                $updated->active_entity_key,
            );
        } finally {
            if ($createdLegacyTable) {
                $connection->execute('DROP TABLE warrant_roster_approvals');
            }
        }
    }

    /**
     * @param array<int, mixed> $args
     */
    private function invokePrivate(string $methodName, array $args): array
    {
        return $this->invokePrivateValue($methodName, $args);
    }

    /**
     * @param array<int, mixed> $args
     */
    private function invokePrivateValue(string $methodName, array $args): mixed
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

    private function createLegacyWarrantRosterApprovalSourceTable(Connection $connection): bool
    {
        if (in_array('warrant_roster_approvals', $connection->getSchemaCollection()->listTables(), true)) {
            $connection->execute('DELETE FROM warrant_roster_approvals');

            return false;
        }

        $connection->execute(
            'CREATE TABLE warrant_roster_approvals (
                warrant_roster_id INTEGER NOT NULL,
                approver_id INTEGER NOT NULL,
                approved_on TIMESTAMP NULL
            )',
        );

        return true;
    }
}
