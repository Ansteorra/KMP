<?php
declare(strict_types=1);

namespace App\Services;

use App\Application;
use App\KMP\StaticHelpers;
use App\Model\Entity\WorkflowApproval;
use App\Model\Entity\WorkflowApprovalResponse;
use App\Model\Entity\WorkflowExecutionLog;
use App\Model\Entity\WorkflowInstance;
use App\Model\Table\WorkflowInstancesTable;
use App\Services\WorkflowEngine\DefaultWorkflowEngine;
use App\Services\WorkflowEngine\TriggerDispatcher;
use App\Services\WorkflowRegistry\WorkflowPluginLoader;
use Awards\Model\Entity\RecommendationMigrationRun;
use Awards\Services\RecommendationApprovalProcessService;
use Awards\Services\RecommendationMigrationService;
use Cake\Core\Container;
use Cake\Core\ContainerInterface;
use Cake\Database\Connection;
use Cake\Database\Driver\Postgres;
use Cake\I18n\DateTime;
use Cake\Log\Log;
use Cake\ORM\Locator\LocatorAwareTrait;
use Cake\ORM\Table;
use InitWorkflowDefinitionsSeed;
use Migrations\Migrations;
use RuntimeException;
use Throwable;

/**
 * Reconciles current-version derived/config data after a logical restore.
 *
 * Schema migrations cannot safely be rerun after restoring old rows into a
 * newer schema because Phinx sees the target schema as already migrated. This
 * service keeps idempotent data repair steps close to restore instead.
 */
class BackupRestoreCompatibilityService
{
    use LocatorAwareTrait;

    private const AWARDS_EXISTING_RECOMMENDATION_WORKFLOW_SLUG = 'awards-existing-recommendation-approval';
    private const AWARDS_RECOMMENDATION_ENTITY_TYPE = 'Awards.Recommendations';
    private const AWARDS_APPROVAL_NODE_ID = 'award-approval-gate';
    private const RESTORE_MIGRATION_MARKER = 'BackupRestoreCompatibilityService';
    private const PRINCIPALITY_AWARD_DOMAIN_ID = 11;

    /**
     * @param \Cake\Core\ContainerInterface|null $container Optional container for restore-time workflow execution.
     */
    public function __construct(private ?ContainerInterface $container = null)
    {
    }

    /**
     * @param array<string, mixed> $payload Decoded backup payload.
     * @param callable(array<string, mixed>):void|null $progressReporter
     * @return array<string, int>
     */
    public function reconcile(Connection $connection, array $payload, ?callable $progressReporter = null): array
    {
        $this->reportProgress(
            $progressReporter,
            'post_restore_migrations',
            'Reconciling restored data with the current application version.',
        );

        $stats = [
            'legacy_backup_tables_available' => $this->countLegacyBackupTables($connection, $payload),
            'backup_migration_log_tables_available' => $this->countBackupMigrationLogTables($payload),
            'workflow_definitions_created' => 0,
            'workflow_versions_published' => 0,
            'bestowal_reference_seeded' => 0,
            'bestowals_backfilled' => 0,
            'bestowal_recommendation_links_backfilled' => 0,
            'bestowal_reason_summaries_backfilled' => 0,
            'bestowal_specialties_backfilled' => 0,
            'award_approval_processes_seeded' => 0,
            'award_approval_workflows_backfilled' => 0,
            'warrant_roster_approval_workflows_backfilled' => 0,
            'award_recommendation_migration_closed' => 0,
            'award_recommendation_migration_bestowal' => 0,
            'award_recommendation_migration_approval_workflow' => 0,
            'award_recommendation_migration_manual_review' => 0,
            'award_recommendation_migration_skipped' => 0,
            'award_recommendation_migration_error' => 0,
        ];

        $stats = array_merge($stats, $this->syncWorkflowDefinitions($connection));
        $stats = array_merge($stats, $this->seedBaselineAwardApprovalProcesses($connection, $payload));
        $stats = array_merge($stats, $this->seedBestowalReferenceData($connection));
        $stats = array_merge($stats, $this->backfillBestowalsFromRecommendations($connection));
        $stats = array_merge($stats, $this->backfillBestowalReasonSummaries($connection));
        $stats = array_merge($stats, $this->backfillBestowalSpecialties($connection));
        $stats = array_merge($stats, $this->backfillSubmittedRecommendationApprovalWorkflows($connection));
        $stats = array_merge($stats, $this->backfillWarrantRosterApprovalWorkflows($connection));
        $stats = array_merge($stats, $this->runAwardRecommendationLifecycleMigration($connection));

        $this->reportProgress(
            $progressReporter,
            'post_restore_migrations',
            'Post-restore data reconciliation completed.',
            ['post_restore' => $stats],
        );

        return $stats;
    }

    /**
     * @param array<string, mixed> $payload Decoded backup payload.
     */
    private function countLegacyBackupTables(Connection $connection, array $payload): int
    {
        if (!isset($payload['tables']) || !is_array($payload['tables'])) {
            return 0;
        }

        $currentTables = $connection->getSchemaCollection()->listTables();
        $legacyTableCount = 0;
        foreach (array_keys($payload['tables']) as $tableName) {
            if (
                is_string($tableName)
                && !in_array($tableName, $currentTables, true)
                && !$this->isMigrationLogTable($tableName)
            ) {
                $legacyTableCount++;
            }
        }

        return $legacyTableCount;
    }

    /**
     * @param array<string, mixed> $payload Decoded backup payload.
     */
    private function countBackupMigrationLogTables(array $payload): int
    {
        if (!isset($payload['tables']) || !is_array($payload['tables'])) {
            return 0;
        }

        $migrationLogTableCount = 0;
        foreach (array_keys($payload['tables']) as $tableName) {
            if (is_string($tableName) && $this->isMigrationLogTable($tableName)) {
                $migrationLogTableCount++;
            }
        }

        return $migrationLogTableCount;
    }

    /**
     * Return true for core/plugin migration log tables.
     */
    private function isMigrationLogTable(string $tableName): bool
    {
        return (bool)preg_match('/(?:^|_)phinxlog$/i', $tableName);
    }

    /**
     * Ensure workflow definitions point at current JSON definitions.
     *
     * @return array{workflow_definitions_created: int, workflow_versions_published: int}
     */
    private function syncWorkflowDefinitions(Connection $connection): array
    {
        if (!$this->hasTables($connection, ['workflow_definitions', 'workflow_versions'])) {
            return ['workflow_definitions_created' => 0, 'workflow_versions_published' => 0];
        }

        $seedPath = ROOT . DS . 'config' . DS . 'Seeds' . DS . 'InitWorkflowDefinitionsSeed.php';
        $jsonDir = dirname($seedPath) . DS . 'WorkflowDefinitions' . DS;
        if (!file_exists($seedPath) || !is_dir($jsonDir)) {
            return ['workflow_definitions_created' => 0, 'workflow_versions_published' => 0];
        }
        require_once $seedPath;

        $seed = new InitWorkflowDefinitionsSeed();
        $created = 0;
        $published = 0;
        $now = date('Y-m-d H:i:s');
        $definitionColumns = $this->tableColumns($connection, 'workflow_definitions');
        $versionColumns = $this->tableColumns($connection, 'workflow_versions');

        foreach ($seed->getWorkflowMeta() as $meta) {
            $jsonPath = $jsonDir . $meta['json_file'];
            if (!file_exists($jsonPath)) {
                throw new RuntimeException("Workflow definition file not found: {$jsonPath}");
            }

            $definition = json_decode((string)file_get_contents($jsonPath), true, 512, JSON_THROW_ON_ERROR);
            $definitionJson = json_encode($definition, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if ($definitionJson === false) {
                throw new RuntimeException(sprintf('Unable to encode workflow definition %s.', $meta['slug']));
            }

            $definitionId = $this->workflowDefinitionId($connection, (string)$meta['slug']);
            if ($definitionId === null) {
                $connection->insert('workflow_definitions', $this->filterColumns([
                    'name' => $meta['name'],
                    'slug' => $meta['slug'],
                    'description' => $meta['description'],
                    'trigger_type' => $meta['trigger_type'],
                    'trigger_config' => json_encode($meta['trigger_config'], JSON_UNESCAPED_SLASHES),
                    'entity_type' => $meta['entity_type'],
                    'is_active' => !empty($meta['is_active']) ? 1 : 0,
                    'execution_mode' => $meta['execution_mode'] ?? 'durable',
                    'current_version_id' => null,
                    'created_by' => 1,
                    'modified_by' => 1,
                    'created' => $now,
                    'modified' => $now,
                ], $definitionColumns));
                $definitionId = $this->workflowDefinitionId($connection, (string)$meta['slug']);
                if ($definitionId === null) {
                    throw new RuntimeException(sprintf('Failed to create workflow definition %s.', $meta['slug']));
                }
                $created++;
            }

            if ($this->currentWorkflowVersionMatches($connection, $definitionId, $definition, $meta)) {
                continue;
            }

            $versionNumber = $this->nextWorkflowVersionNumber($connection, $definitionId);
            $connection->insert('workflow_versions', $this->filterColumns([
                'workflow_definition_id' => $definitionId,
                'version_number' => $versionNumber,
                'definition' => $definitionJson,
                'canvas_layout' => '{}',
                'status' => 'published',
                'published_at' => $now,
                'published_by' => 1,
                'change_notes' => 'Post-restore compatibility publish',
                'created_by' => 1,
                'modified_by' => 1,
                'created' => $now,
                'modified' => $now,
            ], $versionColumns));

            $versionId = $this->workflowVersionId($connection, $definitionId, $versionNumber);
            if ($versionId === null) {
                throw new RuntimeException(sprintf('Failed to publish workflow version for %s.', $meta['slug']));
            }

            $connection->update('workflow_definitions', $this->filterColumns([
                'name' => $meta['name'],
                'description' => $meta['description'],
                'trigger_type' => $meta['trigger_type'],
                'trigger_config' => json_encode($meta['trigger_config'], JSON_UNESCAPED_SLASHES),
                'entity_type' => $meta['entity_type'],
                'is_active' => !empty($meta['is_active']) ? 1 : 0,
                'execution_mode' => $meta['execution_mode'] ?? 'durable',
                'current_version_id' => $versionId,
                'modified_by' => 1,
                'modified' => $now,
            ], $definitionColumns), ['id' => $definitionId]);
            $published++;
        }

        return [
            'workflow_definitions_created' => $created,
            'workflow_versions_published' => $published,
        ];
    }

    /**
     * @return array{bestowal_reference_seeded: int}
     */
    private function seedBestowalReferenceData(Connection $connection): array
    {
        if (
            !$this->hasTables($connection, [
                'awards_bestowal_statuses',
                'awards_bestowal_states',
                'awards_bestowal_state_field_rules',
                'awards_bestowal_state_transitions',
            ])
        ) {
            return ['bestowal_reference_seeded' => 0];
        }

        $count = (int)$connection
            ->execute('SELECT COUNT(*) FROM awards_bestowal_statuses')
            ->fetchColumn(0);
        if ($count > 0) {
            return ['bestowal_reference_seeded' => 0];
        }

        try {
            (new Migrations())->seed([
                'connection' => $connection->configName(),
                'plugin' => 'Awards',
                'seed' => 'InitBestowalReferenceSeed',
            ]);
        } catch (Throwable $e) {
            Log::error('Post-restore bestowal reference seed failed: ' . $e->getMessage());
            throw $e;
        }

        return ['bestowal_reference_seeded' => 1];
    }

    /**
     * @return array{bestowals_backfilled: int, bestowal_recommendation_links_backfilled: int}
     */
    private function backfillBestowalsFromRecommendations(Connection $connection): array
    {
        if (
            !$this->hasTables($connection, [
                'awards_bestowals',
                'awards_bestowal_recommendations',
                'awards_recommendations',
                'awards_bestowal_states',
                'awards_bestowal_statuses',
            ])
        ) {
            return ['bestowals_backfilled' => 0, 'bestowal_recommendation_links_backfilled' => 0];
        }
        if (!$this->hasColumns($connection, 'awards_recommendations', ['bestowal_id', 'recommendation_group_id'])) {
            return ['bestowals_backfilled' => 0, 'bestowal_recommendation_links_backfilled' => 0];
        }

        $now = date('Y-m-d H:i:s');
        $stateExpression = "CASE
            WHEN r.state = 'Need to Schedule' AND r.gathering_id IS NOT NULL THEN 'Gathering Assigned'
            WHEN r.state = 'Need to Schedule' THEN 'Created'
            WHEN r.state = 'Scheduled' THEN 'Court Scheduled'
            WHEN r.state = 'Given' THEN 'Given'
            WHEN r.state = 'Announced Not Given' THEN 'Announced Not Given'
            ELSE 'Created'
        END";
        $parentCondition = "r.deleted IS NULL
              AND r.recommendation_group_id IS NULL
              AND r.state IN ('Need to Schedule', 'Scheduled', 'Given', 'Announced Not Given')
              AND r.bestowal_id IS NULL";

        $beforeBestowals = $this->countRows($connection, 'awards_bestowals');
        $beforeLinks = $this->countRows($connection, 'awards_bestowal_recommendations');

        $connection->execute(
            "INSERT INTO awards_bestowals (
                member_id, member_sca_name, gathering_id, primary_recommendation_id, status, state,
                stack_rank, bestowed_at, source, noble_notes, herald_notes,
                call_into_court, court_availability, person_to_notify, created, modified,
                created_by, modified_by
            )
            SELECT
                r.member_id,
                r.member_sca_name,
                r.gathering_id,
                r.id,
                COALESCE(bs.name, 'Planning'),
                {$stateExpression},
                0,
                r.given,
                'recommendation',
                r.reason,
                NULL,
                r.call_into_court,
                r.court_availability,
                r.person_to_notify,
                COALESCE(r.created, ?),
                ?,
                r.created_by,
                r.modified_by
            FROM awards_recommendations AS r
            LEFT JOIN awards_bestowal_states AS s ON s.name = {$stateExpression}
            LEFT JOIN awards_bestowal_statuses AS bs ON bs.id = s.status_id
            WHERE {$parentCondition}",
            [$now, $now],
        );

        $connection->execute(
            "UPDATE awards_recommendations AS r
            SET bestowal_id = (
                SELECT MIN(b.id)
                FROM awards_bestowals AS b
                WHERE b.primary_recommendation_id = r.id
                  AND b.source = 'recommendation'
            )
            WHERE {$parentCondition}",
        );

        $connection->execute('DROP TABLE IF EXISTS awards_bestowal_backfill_map');
        $connection->execute(
            "CREATE TEMPORARY TABLE awards_bestowal_backfill_map AS
            SELECT r.id AS recommendation_id, r.bestowal_id
            FROM awards_recommendations AS r
            WHERE r.deleted IS NULL
              AND r.recommendation_group_id IS NULL
              AND r.state IN ('Need to Schedule', 'Scheduled', 'Given', 'Announced Not Given')
              AND r.bestowal_id IS NOT NULL",
        );

        $connection->execute(
            "INSERT INTO awards_bestowal_recommendations (bestowal_id, recommendation_id, created)
            SELECT m.bestowal_id, m.recommendation_id, ?
            FROM awards_bestowal_backfill_map AS m
            WHERE NOT EXISTS (
                SELECT 1
                FROM awards_bestowal_recommendations AS existing
                WHERE existing.bestowal_id = m.bestowal_id
                  AND existing.recommendation_id = m.recommendation_id
            )",
            [$now],
        );

        $connection->execute(
            "UPDATE awards_recommendations AS r
            SET bestowal_id = (
                SELECT m.bestowal_id
                FROM awards_bestowal_backfill_map AS m
                WHERE m.recommendation_id = r.recommendation_group_id
            )
            WHERE r.deleted IS NULL
              AND r.recommendation_group_id IS NOT NULL
              AND r.bestowal_id IS NULL
              AND EXISTS (
                  SELECT 1
                  FROM awards_bestowal_backfill_map AS m
                  WHERE m.recommendation_id = r.recommendation_group_id
              )",
        );

        $connection->execute(
            "INSERT INTO awards_bestowal_recommendations (bestowal_id, recommendation_id, created)
            SELECT m.bestowal_id, child.id, ?
            FROM awards_recommendations AS child
            INNER JOIN awards_bestowal_backfill_map AS m
                ON m.recommendation_id = child.recommendation_group_id
            WHERE child.deleted IS NULL
              AND child.bestowal_id = m.bestowal_id
              AND NOT EXISTS (
                  SELECT 1
                  FROM awards_bestowal_recommendations AS existing
                  WHERE existing.bestowal_id = m.bestowal_id
                    AND existing.recommendation_id = child.id
              )",
            [$now],
        );

        $connection->execute('DROP TABLE IF EXISTS awards_bestowal_backfill_map');

        return [
            'bestowals_backfilled' => max(0, $this->countRows($connection, 'awards_bestowals') - $beforeBestowals),
            'bestowal_recommendation_links_backfilled' => max(
                0,
                $this->countRows($connection, 'awards_bestowal_recommendations') - $beforeLinks,
            ),
        ];
    }

    /**
     * Replay default award approval-process seed data for baseline backups that
     * predate those tables. Backups that contain approval-process tables stay
     * authoritative, even when they intentionally contain no process rows.
     *
     * @param array<string, mixed> $payload Decoded backup payload.
     * @return array{award_approval_processes_seeded: int}
     */
    private function seedBaselineAwardApprovalProcesses(Connection $connection, array $payload): array
    {
        if (
            $this->payloadHasAnyTables($payload, [
                'awards_approval_processes',
                'awards_approval_process_steps',
            ])
        ) {
            return ['award_approval_processes_seeded' => 0];
        }
        if (
            !$this->hasTables($connection, [
                'awards_approval_processes',
                'awards_approval_process_steps',
                'awards_awards',
                'awards_levels',
                'branches',
                'officers_offices',
            ])
            || !$this->hasColumns($connection, 'awards_awards', ['approval_process_id'])
        ) {
            return ['award_approval_processes_seeded' => 0];
        }

        $ansteorraBranchId = $this->lookupOptionalId(
            $connection,
            'SELECT id FROM branches WHERE name = ? AND type = ? AND deleted IS NULL LIMIT 1',
            ['Ansteorra', 'Kingdom'],
        );
        $nonArmigerousLevelId = $this->lookupOptionalId(
            $connection,
            'SELECT id FROM awards_levels WHERE name = ? AND deleted IS NULL LIMIT 1',
            ['Non-Armigerous'],
        );
        $crownOfficeId = $this->lookupOptionalId(
            $connection,
            'SELECT id FROM officers_offices WHERE name = ? AND deleted IS NULL LIMIT 1',
            ['Crown'],
        );
        $landedNobilityOfficeId = $this->lookupOptionalId(
            $connection,
            'SELECT id FROM officers_offices
             WHERE name = ? AND deleted IS NULL
             LIMIT 1',
            ['Landed Nobility'],
        );
        $principalityCoronetOfficeId = $this->lookupOptionalId(
            $connection,
            'SELECT id FROM officers_offices
             WHERE name = ? AND deleted IS NULL
             LIMIT 1',
            ['Principality Coronet'],
        );

        if (
            $ansteorraBranchId === null
            || $nonArmigerousLevelId === null
            || $crownOfficeId === null
            || $landedNobilityOfficeId === null
            || $principalityCoronetOfficeId === null
        ) {
            return ['award_approval_processes_seeded' => 0];
        }

        $singleCrownProcessId = $this->upsertAwardApprovalProcess(
            $connection,
            'Single Approver - Crown',
            'Single approval queue for kingdom awards. Any current Crown office holder may approve.',
        );
        $singleLocalProcessId = $this->upsertAwardApprovalProcess(
            $connection,
            'Single Approver - Local',
            'Single local approval queue for non-armigerous local awards.',
        );
        $singlePrincipalityProcessId = $this->upsertAwardApprovalProcess(
            $connection,
            'Single Approver - Principality Coronet',
            'Single approval queue for principality awards. Any current Coronet office holder may approve.',
        );
        $localThenCrownProcessId = $this->upsertAwardApprovalProcess(
            $connection,
            'Dual Approver - Local then Crown',
            'Local approval followed by Crown approval for armigerous local awards.',
        );

        $this->replaceAwardApprovalProcessSteps($connection, $singleCrownProcessId, [[
            'step_key' => 'crown',
            'label' => 'Crown Approval',
            'sequence' => 1,
            'approver_source_id' => $crownOfficeId,
            'branch_mode' => 'award_branch',
            'branch_type' => null,
            'threshold_mode' => 'all',
        ]]);
        $this->replaceAwardApprovalProcessSteps($connection, $singleLocalProcessId, [[
            'step_key' => 'local',
            'label' => 'Local Approval',
            'sequence' => 1,
            'approver_source_id' => $landedNobilityOfficeId,
            'branch_mode' => 'award_branch',
            'branch_type' => null,
            'threshold_mode' => 'all',
        ]]);
        $this->replaceAwardApprovalProcessSteps($connection, $singlePrincipalityProcessId, [[
            'step_key' => 'principality',
            'label' => 'Principality Coronet Approval',
            'sequence' => 1,
            'approver_source_id' => $principalityCoronetOfficeId,
            'branch_mode' => 'award_branch',
            'branch_type' => null,
            'threshold_mode' => 'all',
        ]]);
        $this->replaceAwardApprovalProcessSteps($connection, $localThenCrownProcessId, [
            [
                'step_key' => 'local',
                'label' => 'Local Approval',
                'sequence' => 1,
                'approver_source_id' => $landedNobilityOfficeId,
                'branch_mode' => 'award_branch',
                'branch_type' => null,
                'threshold_mode' => 'all',
            ],
            [
                'step_key' => 'crown',
                'label' => 'Crown Approval',
                'sequence' => 2,
                'approver_source_id' => $crownOfficeId,
                'branch_mode' => 'ancestor_branch_type',
                'branch_type' => 'Kingdom',
                'threshold_mode' => 'all',
            ],
        ]);

        $connection->execute(
            'UPDATE awards_awards SET approval_process_id = ? WHERE branch_id = ? AND deleted IS NULL',
            [$singleCrownProcessId, $ansteorraBranchId],
        );
        $connection->execute(
            'UPDATE awards_awards SET approval_process_id = ?
             WHERE domain_id = ?
               AND deleted IS NULL',
            [$singlePrincipalityProcessId, self::PRINCIPALITY_AWARD_DOMAIN_ID],
        );
        $connection->execute(
            'UPDATE awards_awards SET approval_process_id = ? '
                . 'WHERE branch_id <> ? '
               . 'AND (domain_id IS NULL OR domain_id <> ?) '
                . 'AND level_id = ? AND deleted IS NULL',
            [$singleLocalProcessId, $ansteorraBranchId, self::PRINCIPALITY_AWARD_DOMAIN_ID, $nonArmigerousLevelId],
        );
        $connection->execute(
            'UPDATE awards_awards SET approval_process_id = ? '
                . 'WHERE branch_id <> ? '
               . 'AND (domain_id IS NULL OR domain_id <> ?) '
                . 'AND level_id <> ? AND deleted IS NULL',
            [$localThenCrownProcessId, $ansteorraBranchId, self::PRINCIPALITY_AWARD_DOMAIN_ID, $nonArmigerousLevelId],
        );

        return ['award_approval_processes_seeded' => 4];
    }

    /**
     * Recreate current approval workflow state for restored baseline
     * recommendations that were still awaiting award approval.
     *
     * @return array{award_approval_workflows_backfilled: int}
     */
    private function backfillSubmittedRecommendationApprovalWorkflows(Connection $connection): array
    {
        if (
            !$this->hasTables($connection, [
                'awards_recommendations',
                'awards_awards',
                'awards_recommendation_approval_runs',
                'workflow_definitions',
                'workflow_versions',
                'workflow_instances',
                'workflow_execution_logs',
                'workflow_approvals',
            ])
            || !$this->hasColumns($connection, 'awards_awards', ['approval_process_id'])
        ) {
            return ['award_approval_workflows_backfilled' => 0];
        }

        $workflow = $connection->execute(
            'SELECT wd.id AS definition_id, wd.current_version_id AS version_id
             FROM workflow_definitions wd
             WHERE wd.slug = ? AND wd.is_active = TRUE AND wd.current_version_id IS NOT NULL
             LIMIT 1',
            [self::AWARDS_EXISTING_RECOMMENDATION_WORKFLOW_SLUG],
        )->fetch('assoc');
        if (!is_array($workflow)) {
            return ['award_approval_workflows_backfilled' => 0];
        }

        $rows = $connection->execute(
            "SELECT
                r.id,
                r.requester_id,
                r.created_by,
                r.created,
                r.member_sca_name
             FROM awards_recommendations r
             INNER JOIN awards_awards a ON a.id = r.award_id
             INNER JOIN awards_approval_processes ap ON ap.id = a.approval_process_id
             WHERE r.deleted IS NULL
               AND r.state = 'Submitted'
               AND r.member_id IS NOT NULL
               AND r.recommendation_group_id IS NULL
               AND a.deleted IS NULL
               AND ap.deleted IS NULL
               AND ap.is_active = TRUE
               AND NOT EXISTS (
                   SELECT 1
                   FROM awards_recommendation_approval_runs existing_run
                   WHERE existing_run.recommendation_id = r.id
                     AND existing_run.status IN ('in_progress', 'changes_requested')
                     AND existing_run.deleted IS NULL
               )
               AND NOT EXISTS (
                   SELECT 1
                   FROM workflow_instances wi
                   INNER JOIN workflow_approvals wa ON wa.workflow_instance_id = wi.id
                   WHERE wi.entity_type = ?
                     AND wi.entity_id = r.id
                     AND wi.status IN ('running', 'waiting')
                     AND wa.status = 'pending'
               )
             ORDER BY r.id",
            [self::AWARDS_RECOMMENDATION_ENTITY_TYPE],
        )->fetchAll('assoc') ?: [];
        if ($rows === []) {
            return ['award_approval_workflows_backfilled' => 0];
        }

        $instances = $this->fetchTable('WorkflowInstances');
        $logs = $this->fetchTable('WorkflowExecutionLogs');
        $approvals = $this->fetchTable('WorkflowApprovals');
        $approvalProcessService = new RecommendationApprovalProcessService();
        $count = 0;

        foreach ($rows as $row) {
            $recommendationId = (int)$row['id'];
            $actorId = (int)($row['requester_id'] ?? $row['created_by'] ?? 0);
            $actorId = $actorId > 0 ? $actorId : null;
            $created = !empty($row['created']) ? new DateTime((string)$row['created']) : DateTime::now();
            $trigger = [
                'recommendationId' => $recommendationId,
                'actorId' => $actorId,
                'restoreCompatibility' => true,
                'migration' => self::RESTORE_MIGRATION_MARKER,
            ];

            $instance = $instances->newEntity([
                'workflow_definition_id' => (int)$workflow['definition_id'],
                'workflow_version_id' => (int)$workflow['version_id'],
                'entity_type' => self::AWARDS_RECOMMENDATION_ENTITY_TYPE,
                'entity_id' => $recommendationId,
                'status' => WorkflowInstance::STATUS_RUNNING,
                'context' => [
                    'trigger' => $trigger,
                    'triggeredBy' => $actorId,
                    'nodes' => [],
                    'migrated' => true,
                    'migration' => self::RESTORE_MIGRATION_MARKER,
                ],
                'active_nodes' => [],
                'started_by' => $actorId,
                'started_at' => $created,
                'created' => $created,
                'modified' => DateTime::now(),
            ]);
            $instances->saveOrFail($instance);

            $this->createWorkflowExecutionLog(
                $logs,
                (int)$instance->id,
                'trigger',
                'trigger',
                WorkflowExecutionLog::STATUS_COMPLETED,
                $trigger,
                $trigger,
                $created,
                $created,
            );

            $result = $approvalProcessService->startProcess(
                ['instanceId' => (int)$instance->id, 'trigger' => $trigger, 'triggeredBy' => $actorId],
                ['recommendationId' => $recommendationId, 'actorId' => $actorId],
            );
            if (!$result->isSuccess()) {
                $instance->status = WorkflowInstance::STATUS_FAILED;
                $instance->completed_at = DateTime::now();
                $instance->error_info = [
                    'migration' => self::RESTORE_MIGRATION_MARKER,
                    'error' => $result->getError(),
                ];
                $instances->saveOrFail($instance);
                Log::warning(sprintf(
                    'Post-restore award approval workflow repair skipped recommendation %d: %s',
                    $recommendationId,
                    (string)$result->getError(),
                ));
                continue;
            }

            $data = $result->getData() ?? [];
            $actionResult = [
                'success' => true,
                'error' => null,
            ] + $data;

            $this->createWorkflowExecutionLog(
                $logs,
                (int)$instance->id,
                'start-approval-process',
                'action',
                WorkflowExecutionLog::STATUS_COMPLETED,
                ['recommendationId' => $recommendationId, 'actorId' => $actorId],
                $actionResult,
                $created,
                DateTime::now(),
            );
            $this->createWorkflowExecutionLog(
                $logs,
                (int)$instance->id,
                'start-approval-succeeded',
                'condition',
                WorkflowExecutionLog::STATUS_COMPLETED,
                ['field' => '$.nodes.start-approval-process.result.success', 'value' => true],
                ['result' => true, 'port' => 'true'],
                $created,
                DateTime::now(),
            );
            $approvalLog = $this->createWorkflowExecutionLog(
                $logs,
                (int)$instance->id,
                self::AWARDS_APPROVAL_NODE_ID,
                'approval',
                WorkflowExecutionLog::STATUS_WAITING,
                [
                    'approverType' => WorkflowApproval::APPROVER_TYPE_DYNAMIC,
                    'requiredCount' => $data['requiredCount'] ?? 1,
                ],
                null,
                $created,
                null,
            );

            $currentStepContext = [
                'approvalApproverConfig' => $data['approvalApproverConfig'] ?? [],
                'requiredCount' => (int)($data['requiredCount'] ?? 1),
                'currentStepKey' => $data['currentStepKey'] ?? null,
                'currentStepLabel' => $data['currentStepLabel'] ?? null,
            ];
            $context = [
                'trigger' => $trigger,
                'triggeredBy' => $actorId,
                'nodes' => [
                    'trigger' => ['result' => $trigger],
                    'start-approval-process' => ['result' => $actionResult],
                    'start-approval-succeeded' => ['result' => true, 'port' => 'true'],
                ],
                'awardApprovalCurrentStep' => $currentStepContext,
                'migrated' => true,
                'migration' => self::RESTORE_MIGRATION_MARKER,
                'migratedAt' => DateTime::now()->toDateTimeString(),
            ];
            $instance->context = $context;
            $instance->active_nodes = [self::AWARDS_APPROVAL_NODE_ID];
            $instance->status = WorkflowInstance::STATUS_WAITING;
            $instance->modified = DateTime::now();
            $instances->saveOrFail($instance);

            $approverConfig = $data['approvalApproverConfig'] ?? [];
            $approverConfig['requires_bestowal_gathering'] = !empty($approverConfig['award_approval_is_final_step']);
            $approval = $approvals->newEntity([
                'workflow_instance_id' => (int)$instance->id,
                'node_id' => self::AWARDS_APPROVAL_NODE_ID,
                'execution_log_id' => (int)$approvalLog->id,
                'approver_type' => WorkflowApproval::APPROVER_TYPE_DYNAMIC,
                'approver_config' => $approverConfig,
                'current_approver_id' => null,
                'request_title' => mb_substr(
                    'Award Recommendation: ' . trim((string)($row['member_sca_name'] ?? '')),
                    0,
                    255,
                ),
                'required_count' => (int)($data['requiredCount'] ?? 1),
                'approved_count' => 0,
                'rejected_count' => 0,
                'status' => WorkflowApproval::STATUS_PENDING,
                'allow_parallel' => false,
                'deadline' => null,
                'escalation_config' => null,
                'version' => 1,
                'approval_token' => StaticHelpers::generateToken(32),
                'created' => $created,
                'modified' => DateTime::now(),
            ]);
            $approvals->saveOrFail($approval);
            $count++;
        }

        return ['award_approval_workflows_backfilled' => $count];
    }

    /**
     * Recreate workflow approval rows for legacy warrant roster approvals.
     *
     * The original migration drops warrant_roster_approvals after copying it to
     * workflow tables. During logical restore, Phinx can see the migration as
     * already applied while the restored payload still contains the legacy
     * source table, so restore must replay the data move idempotently.
     *
     * @return array{warrant_roster_approval_workflows_backfilled: int}
     */
    private function backfillWarrantRosterApprovalWorkflows(Connection $connection): array
    {
        if (
            !$this->hasTables($connection, [
                'warrant_rosters',
                'warrant_roster_approvals',
                'workflow_definitions',
                'workflow_versions',
                'workflow_instances',
                'workflow_execution_logs',
                'workflow_approvals',
                'workflow_approval_responses',
            ])
        ) {
            return ['warrant_roster_approval_workflows_backfilled' => 0];
        }

        $connection->execute(
            "UPDATE workflow_definitions
                SET entity_type = 'WarrantRosters'
              WHERE slug = 'warrants-roster-approval' AND entity_type = 'Warrants'",
        );
        $this->rewriteWorkflowInstanceEntityType(
            $connection,
            'warrants-roster-approval',
            'Warrants',
            'WarrantRosters',
        );

        $contextSql = $connection->getDriver() instanceof Postgres ? 'context::text' : 'context';
        $existing = (int)$connection->execute(
            "SELECT COUNT(*)
               FROM workflow_instances
              WHERE entity_type = 'WarrantRosters'
                AND {$contextSql} LIKE ?",
            ['%"migrated":true%'],
        )->fetchColumn(0);
        if ($existing > 0) {
            return ['warrant_roster_approval_workflows_backfilled' => 0];
        }

        $workflow = $connection->execute(
            "SELECT wd.id AS definition_id, wd.current_version_id AS version_id
               FROM workflow_definitions wd
              WHERE wd.slug = 'warrants-roster-approval'
              LIMIT 1",
        )->fetch('assoc');
        if (!is_array($workflow)) {
            return ['warrant_roster_approval_workflows_backfilled' => 0];
        }

        $versionId = !empty($workflow['version_id']) ? (int)$workflow['version_id'] : null;
        if ($versionId === null) {
            $versionId = $this->workflowVersionId(
                $connection,
                (int)$workflow['definition_id'],
                $this->nextWorkflowVersionNumber($connection, (int)$workflow['definition_id']) - 1,
            );
        }
        if ($versionId === null) {
            return ['warrant_roster_approval_workflows_backfilled' => 0];
        }

        $rosters = $connection->execute(
            'SELECT * FROM warrant_rosters ORDER BY id',
        )->fetchAll('assoc') ?: [];
        if ($rosters === []) {
            return ['warrant_roster_approval_workflows_backfilled' => 0];
        }

        $instances = $this->fetchTable('WorkflowInstances');
        $logs = $this->fetchTable('WorkflowExecutionLogs');
        $approvals = $this->fetchTable('WorkflowApprovals');
        $responses = $this->fetchTable('WorkflowApprovalResponses');
        $now = DateTime::now();
        $count = 0;

        foreach ($rosters as $roster) {
            $rosterId = (int)$roster['id'];
            $status = strtolower((string)($roster['status'] ?? ''));
            $isPending = $status === 'pending';
            $isDeclined = $status === 'declined';
            $isTerminal = in_array($status, ['approved', 'declined'], true);
            $created = !empty($roster['created']) ? new DateTime((string)$roster['created']) : $now;
            $completedAt = null;
            if ($isTerminal) {
                $lastApproved = $connection->execute(
                    'SELECT MAX(approved_on) FROM warrant_roster_approvals
                      WHERE warrant_roster_id = ? AND approved_on IS NOT NULL',
                    [$rosterId],
                )->fetchColumn(0);
                $completedAt = !empty($lastApproved)
                    ? new DateTime((string)$lastApproved)
                    : (!empty($roster['modified']) ? new DateTime((string)$roster['modified']) : $now);
            }

            $approvedCount = (int)$connection->execute(
                'SELECT COUNT(DISTINCT approver_id) FROM warrant_roster_approvals
                  WHERE warrant_roster_id = ? AND approved_on IS NOT NULL',
                [$rosterId],
            )->fetchColumn(0);
            $rejectedCount = $isDeclined ? 1 : 0;

            $trigger = [
                'rosterId' => $rosterId,
                'rosterName' => (string)($roster['name'] ?? ''),
                'approvalsRequired' => (int)($roster['approvals_required'] ?? 1),
            ];
            $instance = $instances->newEntity([
                'workflow_definition_id' => (int)$workflow['definition_id'],
                'workflow_version_id' => $versionId,
                'entity_type' => 'WarrantRosters',
                'entity_id' => $rosterId,
                'status' => $isPending ? WorkflowInstance::STATUS_WAITING : WorkflowInstance::STATUS_COMPLETED,
                'context' => [
                    'trigger' => $trigger,
                    'migrated' => true,
                    'migratedAt' => $now->toDateTimeString(),
                    'migration' => self::RESTORE_MIGRATION_MARKER,
                ],
                'active_nodes' => $isPending ? ['approval-gate'] : [],
                'started_by' => $roster['created_by'] !== null ? (int)$roster['created_by'] : null,
                'started_at' => $created,
                'completed_at' => $completedAt,
                'created' => $created,
                'modified' => $now,
            ]);
            $instances->saveOrFail($instance);

            $approvalLog = $this->createWorkflowExecutionLog(
                $logs,
                (int)$instance->id,
                'approval-gate',
                'approval',
                $isPending ? WorkflowInstance::STATUS_WAITING : WorkflowInstance::STATUS_COMPLETED,
                $trigger,
                null,
                $created,
                $completedAt,
            );
            $approval = $approvals->newEntity([
                'workflow_instance_id' => (int)$instance->id,
                'node_id' => 'approval-gate',
                'execution_log_id' => (int)$approvalLog->id,
                'approver_type' => WorkflowApproval::APPROVER_TYPE_POLICY,
                'approver_config' => [
                    'permission' => 'Can Approve Warrant Rosters',
                    'policyClass' => 'App\\Policy\\WarrantRosterPolicy',
                    'policyAction' => 'canApprove',
                    'entityTable' => 'WarrantRosters',
                    'entityIdKey' => 'trigger.rosterId',
                ],
                'current_approver_id' => null,
                'request_title' => mb_substr(
                    'Warrant Roster: ' . trim((string)($roster['name'] ?? '')),
                    0,
                    255,
                ),
                'required_count' => (int)($roster['approvals_required'] ?? 1),
                'approved_count' => $approvedCount,
                'rejected_count' => $rejectedCount,
                'status' => $isPending
                    ? WorkflowApproval::STATUS_PENDING
                    : ($isDeclined ? WorkflowApproval::STATUS_REJECTED : WorkflowApproval::STATUS_APPROVED),
                'allow_parallel' => true,
                'deadline' => null,
                'escalation_config' => null,
                'version' => 1,
                'approval_token' => StaticHelpers::generateToken(32),
                'created' => $created,
                'modified' => $now,
            ]);
            $approvals->saveOrFail($approval);

            $responseRows = $connection->execute(
                'SELECT approver_id, MAX(approved_on) AS approved_on
                   FROM warrant_roster_approvals
                  WHERE warrant_roster_id = ? AND approved_on IS NOT NULL
                  GROUP BY approver_id
                  ORDER BY approved_on',
                [$rosterId],
            )->fetchAll('assoc') ?: [];
            foreach ($responseRows as $responseRow) {
                $respondedAt = new DateTime((string)$responseRow['approved_on']);
                $response = $responses->newEntity([
                    'workflow_approval_id' => (int)$approval->id,
                    'member_id' => (int)$responseRow['approver_id'],
                    'decision' => WorkflowApprovalResponse::DECISION_APPROVE,
                    'comment' => null,
                    'responded_at' => $respondedAt,
                    'created' => $respondedAt,
                ]);
                $responses->saveOrFail($response);
            }

            $count++;
        }

        return ['warrant_roster_approval_workflows_backfilled' => $count];
    }

    /**
     * Rewrite restored workflow entity metadata without invalidating active uniqueness keys.
     */
    private function rewriteWorkflowInstanceEntityType(
        Connection $connection,
        string $workflowSlug,
        string $oldEntityType,
        string $newEntityType,
    ): void {
        $hasActiveEntityKey = $this->hasColumns(
            $connection,
            'workflow_instances',
            ['active_entity_key'],
        );
        $instances = $connection->execute(
            'SELECT wi.id, wi.workflow_definition_id, wi.entity_id, wi.status '
            . 'FROM workflow_instances wi '
            . 'INNER JOIN workflow_definitions wd ON wd.id = wi.workflow_definition_id '
            . 'WHERE wd.slug = ? AND wi.entity_type = ?',
            [$workflowSlug, $oldEntityType],
        )->fetchAll('assoc') ?: [];

        foreach ($instances as $instance) {
            $values = ['entity_type' => $newEntityType];
            if ($hasActiveEntityKey) {
                $entityId = $instance['entity_id'];
                $values['active_entity_key'] = $entityId !== null
                    && in_array($instance['status'], WorkflowInstance::ACTIVE_STATUSES, true)
                    ? WorkflowInstancesTable::buildActiveEntityKey(
                        (int)$instance['workflow_definition_id'],
                        $newEntityType,
                        (int)$entityId,
                    )
                    : null;
            }
            $connection->update('workflow_instances', $values, ['id' => (int)$instance['id']]);
        }
    }

    /**
     * Mirror the dev seeded reset lifecycle migration after restore.
     *
     * This replays `awards migrate_award_recommendations --apply
     * --allow-open-manual-review` so restored legacy recommendations are moved
     * into closed, bestowal, approval-workflow, or manual-review ownership even
     * when that work was originally captured outside a Phinx migration.
     *
     * @return array<string, int>
     */
    private function runAwardRecommendationLifecycleMigration(Connection $connection): array
    {
        $defaultStats = [
            'award_recommendation_migration_closed' => 0,
            'award_recommendation_migration_bestowal' => 0,
            'award_recommendation_migration_approval_workflow' => 0,
            'award_recommendation_migration_manual_review' => 0,
            'award_recommendation_migration_skipped' => 0,
            'award_recommendation_migration_error' => 0,
        ];
        if (
            !$this->hasTables($connection, [
                'awards_recommendations',
                'awards_recommendation_migration_runs',
                'awards_recommendation_migration_results',
                'awards_recommendation_approval_runs',
                'awards_recommendation_feedback_requests',
                'awards_recommendation_feedback_request_items',
                'awards_awards',
                'workflow_definitions',
                'workflow_instances',
                'workflow_approvals',
            ])
        ) {
            return $defaultStats;
        }

        $result = (new RecommendationMigrationService($this->createWorkflowTriggerDispatcher()))->run(
            RecommendationMigrationRun::MODE_APPLY,
            [],
            1,
            true,
        );
        if (!$result->isSuccess()) {
            throw new RuntimeException('Award recommendation lifecycle migration failed: ' . $result->getError());
        }

        $summary = (array)($result->getData()['summary'] ?? []);
        if ((int)($summary['error'] ?? 0) > 0) {
            $errorDetails = $this->formatAwardRecommendationLifecycleErrors(
                $connection,
                (int)($result->getData()['runId'] ?? 0),
            );
            throw new RuntimeException(sprintf(
                'Award recommendation lifecycle migration completed with %d record-level errors.%s',
                (int)$summary['error'],
                $errorDetails === '' ? '' : ' ' . $errorDetails,
            ));
        }

        return [
            'award_recommendation_migration_closed' => (int)($summary['closed'] ?? 0),
            'award_recommendation_migration_bestowal' => (int)($summary['bestowal'] ?? 0),
            'award_recommendation_migration_approval_workflow' => (int)($summary['approval_workflow'] ?? 0),
            'award_recommendation_migration_manual_review' => (int)($summary['manual_review'] ?? 0),
            'award_recommendation_migration_skipped' => (int)($summary['skipped'] ?? 0),
            'award_recommendation_migration_error' => (int)($summary['error'] ?? 0),
        ];
    }

    /**
     * Build a concise sample of record-level lifecycle migration failures.
     */
    private function formatAwardRecommendationLifecycleErrors(Connection $connection, int $runId): string
    {
        if ($runId <= 0) {
            return '';
        }

        $rows = $connection->execute(
            <<<'SQL'
SELECT recommendation_id, reason
FROM awards_recommendation_migration_results
WHERE migration_run_id = :runId
  AND result_status = 'error'
ORDER BY id ASC
LIMIT 5
SQL,
            ['runId' => $runId],
        )->fetchAll('assoc');
        if ($rows === []) {
            return '';
        }

        $samples = [];
        foreach ($rows as $row) {
            $samples[] = sprintf(
                '#%d: %s',
                (int)$row['recommendation_id'],
                (string)$row['reason'],
            );
        }

        return 'Sample: ' . implode('; ', $samples) . '.';
    }

    /**
     * Create a workflow trigger dispatcher for restore-time lifecycle replays.
     */
    private function createWorkflowTriggerDispatcher(): TriggerDispatcher
    {
        $container = $this->container ?? $this->createApplicationContainer();

        return new TriggerDispatcher(new DefaultWorkflowEngine($container));
    }

    /**
     * Build the minimal application service container and workflow registries needed during restore.
     *
     * @return \Cake\Core\ContainerInterface
     */
    private function createApplicationContainer(): ContainerInterface
    {
        $application = new Application(CONFIG);
        $application->bootstrap();
        $container = new Container();
        $application->services($container);
        foreach ($application->getPlugins() as $plugin) {
            if (method_exists($plugin, 'services')) {
                $plugin->services($container);
            }
        }
        WorkflowPluginLoader::loadFromPlugins($application->getPlugins());

        return $container;
    }

    /**
     * @param array<int, string> $tableNames
     * @param array<string, mixed> $payload Decoded backup payload.
     */
    private function payloadHasAnyTables(array $payload, array $tableNames): bool
    {
        $tables = $payload['tables'] ?? null;
        if (!is_array($tables)) {
            return false;
        }

        foreach ($tableNames as $tableName) {
            if (array_key_exists($tableName, $tables)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<int, mixed> $params
     */
    private function lookupOptionalId(Connection $connection, string $sql, array $params): ?int
    {
        $id = $connection->execute($sql, $params)->fetchColumn(0);

        return $id === false || $id === null ? null : (int)$id;
    }

    /**
     * Insert or reactivate one default award approval process.
     */
    private function upsertAwardApprovalProcess(
        Connection $connection,
        string $name,
        string $description,
    ): int {
        $now = date('Y-m-d H:i:s');
        $id = $this->lookupOptionalId(
            $connection,
            'SELECT id FROM awards_approval_processes WHERE name = ? LIMIT 1',
            [$name],
        );
        if ($id === null) {
            $connection->execute(
                'INSERT INTO awards_approval_processes
                    (name, description, is_active, created, modified, created_by, modified_by, deleted)
                 VALUES (?, ?, TRUE, ?, ?, 1, 1, NULL)',
                [$name, $description, $now, $now],
            );
            $id = $this->lookupOptionalId(
                $connection,
                'SELECT id FROM awards_approval_processes WHERE name = ? LIMIT 1',
                [$name],
            );
            if ($id === null) {
                throw new RuntimeException("Unable to seed award approval process {$name}.");
            }

            return $id;
        }

        $connection->execute(
            'UPDATE awards_approval_processes
                SET description = ?, is_active = TRUE, modified = ?, modified_by = 1, deleted = NULL
              WHERE id = ?',
            [$description, $now, $id],
        );

        return $id;
    }

    /**
     * @param array<int, array<string, mixed>> $steps
     */
    private function replaceAwardApprovalProcessSteps(Connection $connection, int $processId, array $steps): void
    {
        $connection->execute('DELETE FROM awards_approval_process_steps WHERE approval_process_id = ?', [$processId]);
        $now = date('Y-m-d H:i:s');

        foreach ($steps as $step) {
            $connection->execute(
                'INSERT INTO awards_approval_process_steps
                    (approval_process_id, step_key, label, sequence, step_type, approver_type, approver_source_id,
                     approver_source_key, branch_mode, branch_type, threshold_mode, required_count, on_reject,
                     on_request_changes, retain_read_visibility, created, modified, created_by, modified_by, deleted)
                 VALUES (?, ?, ?, ?, ?, ?, ?, NULL, ?, ?, ?, NULL, ?, ?, TRUE, ?, ?, 1, 1, NULL)',
                [
                    $processId,
                    $step['step_key'],
                    $step['label'],
                    $step['sequence'],
                    'approval',
                    'office',
                    $step['approver_source_id'],
                    $step['branch_mode'],
                    $step['branch_type'],
                    $step['threshold_mode'],
                    'return_previous',
                    'return_previous',
                    $now,
                    $now,
                ],
            );
        }
    }

    /**
     * Create one workflow execution log row for a rebuilt approval workflow.
     */
    private function createWorkflowExecutionLog(
        Table $logs,
        int $workflowInstanceId,
        string $nodeId,
        string $nodeType,
        string $status,
        mixed $inputData,
        mixed $outputData,
        DateTime $startedAt,
        ?DateTime $completedAt,
    ): WorkflowExecutionLog {
        $log = $logs->newEntity([
            'workflow_instance_id' => $workflowInstanceId,
            'node_id' => $nodeId,
            'node_type' => $nodeType,
            'attempt_number' => 1,
            'status' => $status,
            'input_data' => $inputData,
            'output_data' => $outputData,
            'started_at' => $startedAt,
            'completed_at' => $completedAt,
            'created' => $startedAt,
        ]);
        $logs->saveOrFail($log);

        return $log;
    }

    /**
     * @return array{bestowal_reason_summaries_backfilled: int}
     */
    private function backfillBestowalReasonSummaries(Connection $connection): array
    {
        if (
            !$this->hasTables($connection, [
                'awards_bestowals',
                'awards_bestowal_recommendations',
                'awards_recommendations',
            ])
        ) {
            return ['bestowal_reason_summaries_backfilled' => 0];
        }
        if (!$this->hasColumns($connection, 'awards_bestowals', ['reason_summary'])) {
            return ['bestowal_reason_summaries_backfilled' => 0];
        }

        $rows = $connection->execute(
            "SELECT
                b.id AS bestowal_id,
                r.reason,
                COALESCE(r.requester_sca_name, requester.sca_name, '') AS submitter_name
            FROM awards_bestowals b
            INNER JOIN awards_bestowal_recommendations br ON br.bestowal_id = b.id
            INNER JOIN awards_recommendations r ON r.id = br.recommendation_id
            LEFT JOIN members requester ON requester.id = r.requester_id
            WHERE b.reason_summary IS NULL OR b.reason_summary = ''
            ORDER BY b.id ASC, br.id ASC",
        )->fetchAll('assoc') ?: [];

        $summaries = [];
        foreach ($rows as $row) {
            $reason = trim((string)($row['reason'] ?? ''));
            if ($reason === '') {
                continue;
            }
            $submitter = trim((string)($row['submitter_name'] ?? ''));
            if ($submitter === '') {
                $submitter = 'Unknown submitter';
            }
            $summaries[(int)$row['bestowal_id']][] = 'Submitted by ' . $submitter . ":\n" . $reason;
        }

        foreach ($summaries as $bestowalId => $sections) {
            $connection->update('awards_bestowals', [
                'reason_summary' => implode("\n\n", $sections),
            ], ['id' => $bestowalId]);
        }

        return ['bestowal_reason_summaries_backfilled' => count($summaries)];
    }

    /**
     * @return array{bestowal_specialties_backfilled: int}
     */
    private function backfillBestowalSpecialties(Connection $connection): array
    {
        if (
            !$this->hasTables($connection, [
                'awards_bestowals',
                'awards_bestowal_recommendations',
                'awards_recommendations',
            ])
        ) {
            return ['bestowal_specialties_backfilled' => 0];
        }
        if (!$this->hasColumns($connection, 'awards_bestowals', ['specialty'])) {
            return ['bestowal_specialties_backfilled' => 0];
        }

        $beforeMissing = (int)$connection
            ->execute("SELECT COUNT(*) FROM awards_bestowals WHERE specialty IS NULL OR specialty = ''")
            ->fetchColumn(0);

        $connection->execute(
            "UPDATE awards_bestowals
                SET specialty = (
                    SELECT specialty
                    FROM awards_recommendations
                    WHERE awards_recommendations.id = awards_bestowals.primary_recommendation_id
                )
                WHERE (specialty IS NULL OR specialty = '')
                    AND primary_recommendation_id IS NOT NULL",
        );

        $connection->execute(
            "UPDATE awards_bestowals
                SET specialty = (
                    SELECT r.specialty
                    FROM awards_bestowal_recommendations br
                    INNER JOIN awards_recommendations r ON r.id = br.recommendation_id
                    WHERE br.bestowal_id = awards_bestowals.id
                        AND r.specialty IS NOT NULL
                        AND r.specialty <> ''
                    ORDER BY br.id ASC
                    LIMIT 1
                )
                WHERE specialty IS NULL OR specialty = ''",
        );

        $afterMissing = (int)$connection
            ->execute("SELECT COUNT(*) FROM awards_bestowals WHERE specialty IS NULL OR specialty = ''")
            ->fetchColumn(0);

        return ['bestowal_specialties_backfilled' => max(0, $beforeMissing - $afterMissing)];
    }

    /**
     * @param array<int, string> $tableNames
     */
    private function hasTables(Connection $connection, array $tableNames): bool
    {
        $tables = $connection->getSchemaCollection()->listTables();
        foreach ($tableNames as $tableName) {
            if (!in_array($tableName, $tables, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<int, string> $columnNames
     */
    private function hasColumns(Connection $connection, string $tableName, array $columnNames): bool
    {
        $columns = $this->tableColumns($connection, $tableName);
        foreach ($columnNames as $columnName) {
            if (!in_array($columnName, $columns, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<int, string>
     */
    private function tableColumns(Connection $connection, string $tableName): array
    {
        return $connection->getSchemaCollection()->describe($tableName)->columns();
    }

    /**
     * @param array<string, mixed> $values
     * @param array<int, string> $columns
     * @return array<string, mixed>
     */
    private function filterColumns(array $values, array $columns): array
    {
        return array_intersect_key($values, array_flip($columns));
    }

    /**
     * Find a workflow definition by slug.
     */
    private function workflowDefinitionId(Connection $connection, string $slug): ?int
    {
        $id = $connection
            ->execute('SELECT id FROM workflow_definitions WHERE slug = ? ORDER BY id DESC LIMIT 1', [$slug])
            ->fetchColumn(0);

        return $id === false || $id === null ? null : (int)$id;
    }

    /**
     * @param array<string, mixed> $definition
     * @param array<string, mixed> $meta
     */
    private function currentWorkflowVersionMatches(
        Connection $connection,
        int $definitionId,
        array $definition,
        array $meta,
    ): bool {
        $row = $connection->execute(
            'SELECT wd.name, wd.description, wd.trigger_type, wd.trigger_config, wd.entity_type, wd.is_active,
                    wd.execution_mode, wv.definition
             FROM workflow_definitions wd
             LEFT JOIN workflow_versions wv ON wv.id = wd.current_version_id
             WHERE wd.id = ?',
            [$definitionId],
        )->fetch('assoc');
        if (!is_array($row)) {
            return false;
        }

        $currentDefinition = json_decode((string)($row['definition'] ?? ''), true);
        $currentTriggerConfig = json_decode((string)($row['trigger_config'] ?? ''), true);

        return $currentDefinition === $definition
            && (string)$row['name'] === (string)$meta['name']
            && (string)$row['description'] === (string)$meta['description']
            && (string)$row['trigger_type'] === (string)$meta['trigger_type']
            && $currentTriggerConfig === $meta['trigger_config']
            && (string)$row['entity_type'] === (string)$meta['entity_type']
            && $this->normalizeBool($row['is_active'] ?? false) === !empty($meta['is_active'])
            && (string)($row['execution_mode'] ?? 'durable') === (string)($meta['execution_mode'] ?? 'durable');
    }

    /**
     * Return the next publishable workflow version number.
     */
    private function nextWorkflowVersionNumber(Connection $connection, int $definitionId): int
    {
        $version = $connection
            ->execute(
                'SELECT COALESCE(MAX(version_number), 0) + 1 FROM workflow_versions WHERE workflow_definition_id = ?',
                [$definitionId],
            )
            ->fetchColumn(0);

        return (int)$version;
    }

    /**
     * Find a workflow version by definition and version number.
     */
    private function workflowVersionId(Connection $connection, int $definitionId, int $versionNumber): ?int
    {
        $id = $connection
            ->execute(
                'SELECT id FROM workflow_versions WHERE workflow_definition_id = ? AND version_number = ? '
                . 'ORDER BY id DESC LIMIT 1',
                [$definitionId, $versionNumber],
            )
            ->fetchColumn(0);

        return $id === false || $id === null ? null : (int)$id;
    }

    /**
     * Count rows in a trusted application table.
     */
    private function countRows(Connection $connection, string $tableName): int
    {
        return (int)$connection->execute("SELECT COUNT(*) FROM {$tableName}")->fetchColumn(0);
    }

    /**
     * Normalize database boolean representations.
     */
    private function normalizeBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_int($value)) {
            return $value === 1;
        }
        if (is_string($value)) {
            return in_array(strtolower($value), ['1', 'true', 't', 'yes'], true);
        }

        return false;
    }

    /**
     * @param callable(array<string, mixed>):void|null $progressReporter
     * @param array<string, mixed> $context
     */
    private function reportProgress(
        ?callable $progressReporter,
        string $phase,
        string $message,
        array $context = [],
    ): void {
        if ($progressReporter === null) {
            return;
        }

        $progressReporter(array_merge($context, [
            'phase' => $phase,
            'message' => $message,
        ]));
    }
}
