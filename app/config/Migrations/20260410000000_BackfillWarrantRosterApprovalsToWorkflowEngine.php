<?php

declare(strict_types=1);

use Migrations\AbstractMigration;
use App\Migrations\CrossEngineMigrationTrait;

/**
 * Backfill all existing warrant roster approvals into the workflow engine tables.
 *
 * Creates workflow_instances, workflow_execution_logs, workflow_approvals, and
 * workflow_approval_responses for every record in warrant_rosters,
 * preserving the original approval chain and status history.
 *
 * Idempotent — skips if migrated instances already exist.
 */
class BackfillWarrantRosterApprovalsToWorkflowEngine extends AbstractMigration
{
    use CrossEngineMigrationTrait;

    public function up(): void
    {
        // Skip backfill if the source table doesn't exist yet. warrant_rosters
        // is created by a core migration but belongs to a feature that may
        // not have been set up on a given install.
        if (!$this->tableExistsInDb('warrant_rosters')) {
            echo "Skipping backfill: warrant_rosters table does not exist (fresh install).\n";
            return;
        }

        $ctxText = $this->jsonAsText('context');
        // Step 1: Idempotency check — skip if we already have migrated records
        $existing = $this->fetchRow(
            "SELECT COUNT(*) AS cnt FROM workflow_instances " .
            "WHERE entity_type = 'WarrantRosters' AND $ctxText LIKE '%\"migrated\":true%'"
        );
        if ($existing && (int)$existing['cnt'] > 0) {
            echo "Backfill already applied ({$existing['cnt']} migrated instances found). Skipping.\n";
            return;
        }

        // Step 2: Find the workflow definition and published version
        $defRow = $this->fetchRow(
            "SELECT id FROM workflow_definitions WHERE slug = 'warrants-roster-approval'"
        );
        if (!$defRow) {
            echo "Workflow definition 'warrants-roster-approval' not found. Skipping backfill.\n";
            return;
        }
        $defId = (int)$defRow['id'];

        $versionRow = $this->fetchRow(
            "SELECT id FROM workflow_versions " .
            "WHERE workflow_definition_id = {$defId} AND status = 'published' " .
            "ORDER BY version_number DESC LIMIT 1"
        );
        if (!$versionRow) {
            echo "No published workflow version found. Skipping backfill.\n";
            return;
        }
        $versionId = (int)$versionRow['id'];

        // Step 3: Fetch all warrant rosters
        $rosters = $this->fetchAll("SELECT * FROM warrant_rosters");
        if (empty($rosters)) {
            echo "No warrant rosters to migrate.\n";
            return;
        }

        $now = date('Y-m-d H:i:s');

        // Status mappings
        $instanceStatusMap = [
            'pending'  => 'waiting',
            'approved' => 'completed',
            'declined' => 'completed',
        ];

        $logStatusMap = [
            'pending'  => 'waiting',
            'approved' => 'completed',
            'declined' => 'completed',
        ];

        $approvalStatusMap = [
            'pending'  => 'pending',
            'approved' => 'approved',
            'declined' => 'rejected',
        ];

        $counters = ['pending' => 0, 'approved' => 0, 'declined' => 0];
        $totalResponses = 0;

        foreach ($rosters as $roster) {
            $rosterId = (int)$roster['id'];
            $rosterName = $this->sqlEscape($roster['name']);
            $approvalsRequired = (int)$roster['approvals_required'];
            $status = strtolower($roster['status']);
            $created = $roster['created'];
            $startedBy = $roster['created_by'] !== null ? (int)$roster['created_by'] : 'NULL';

            // Count unique approved responders for this roster. Legacy backups can
            // contain duplicate rows for the same approver, while workflow responses
            // are unique by approval/member.
            $countsRow = $this->fetchRow(
                "SELECT COUNT(DISTINCT approver_id) AS approved_count " .
                "FROM warrant_roster_approvals " .
                "WHERE warrant_roster_id = {$rosterId} AND approved_on IS NOT NULL"
            );
            $approvedCount = $countsRow ? (int)$countsRow['approved_count'] : 0;

            // Determine rejected_count
            $rejectedCount = ($status === 'declined') ? 1 : 0;

            // Determine completed_at for terminal statuses
            $completedAt = null;
            $isTerminal = in_array($status, ['approved', 'declined']);
            if ($isTerminal) {
                $lastResponse = $this->fetchRow(
                    "SELECT MAX(approved_on) AS last_approved " .
                    "FROM warrant_roster_approvals " .
                    "WHERE warrant_roster_id = {$rosterId} AND approved_on IS NOT NULL"
                );
                $completedAt = ($lastResponse && $lastResponse['last_approved'])
                    ? $lastResponse['last_approved']
                    : $roster['modified'];
            }

            // Build statuses
            $instanceStatus = $instanceStatusMap[$status] ?? 'completed';
            $logStatus = $logStatusMap[$status] ?? 'completed';
            $approvalStatus = $approvalStatusMap[$status] ?? 'pending';

            // Active nodes — CRITICAL for in-flight pending rosters
            $activeNodes = ($status === 'pending') ? '["approval-gate"]' : '[]';

            // Context JSON
            $context = json_encode([
                'trigger' => [
                    'rosterId' => $rosterId,
                    'rosterName' => $roster['name'],
                    'approvalsRequired' => $approvalsRequired,
                ],
                'migrated' => true,
                'migratedAt' => $now,
            ]);
            $contextEsc = $this->sqlEscape($context);

            // (a) Create workflow_instances
            $completedAtSql = $completedAt ? "'{$completedAt}'" : 'NULL';
            $startedBySql = is_int($startedBy) ? (string)$startedBy : 'NULL';
            $this->execute(
                "INSERT INTO workflow_instances " .
                "(workflow_definition_id, workflow_version_id, entity_type, entity_id, " .
                "status, context, active_nodes, started_by, started_at, completed_at, created, modified) " .
                "VALUES ({$defId}, {$versionId}, 'WarrantRosters', {$rosterId}, " .
                "'{$instanceStatus}', '{$contextEsc}', '{$activeNodes}', {$startedBySql}, " .
                "'{$created}', {$completedAtSql}, '{$created}', '{$now}')"
            );

            // Retrieve the newly inserted instance ID
            $instanceRow = $this->fetchRow(
                "SELECT id FROM workflow_instances " .
                "WHERE entity_type = 'WarrantRosters' AND entity_id = {$rosterId} " .
                "AND $ctxText LIKE '%\"migrated\":true%' " .
                "ORDER BY id DESC LIMIT 1"
            );
            if (!$instanceRow) {
                continue;
            }
            $instanceId = (int)$instanceRow['id'];

            // (b) Create workflow_execution_logs
            $logCompletedAtSql = $completedAt ? "'{$completedAt}'" : 'NULL';
            $this->execute(
                "INSERT INTO workflow_execution_logs " .
                "(workflow_instance_id, node_id, node_type, attempt_number, " .
                "status, started_at, completed_at, created) " .
                "VALUES ({$instanceId}, 'approval-gate', 'approval', 1, " .
                "'{$logStatus}', '{$created}', {$logCompletedAtSql}, '{$created}')"
            );

            // Retrieve the execution log ID
            $logRow = $this->fetchRow(
                "SELECT id FROM workflow_execution_logs " .
                "WHERE workflow_instance_id = {$instanceId} AND node_id = 'approval-gate' " .
                "ORDER BY id DESC LIMIT 1"
            );
            if (!$logRow) {
                continue;
            }
            $logId = (int)$logRow['id'];

            // (c) Create workflow_approvals
            $approverConfig = json_encode([
                'permission' => 'Can Approve Warrant Rosters',
                'policyClass' => 'App\\Policy\\WarrantRosterPolicy',
                'policyAction' => 'canApprove',
                'entityTable' => 'WarrantRosters',
                'entityIdKey' => 'trigger.rosterId',
            ]);
            $approverConfigEsc = $this->sqlEscape($approverConfig);
            $approvalToken = bin2hex(random_bytes(16));

            $this->execute(
                "INSERT INTO workflow_approvals " .
                "(workflow_instance_id, node_id, execution_log_id, " .
                "approver_type, approver_config, current_approver_id, " .
                "required_count, approved_count, rejected_count, " .
                "status, allow_parallel, deadline, version, approval_token, created, modified) " .
                "VALUES ({$instanceId}, 'approval-gate', {$logId}, " .
                "'policy', '{$approverConfigEsc}', NULL, " .
                "{$approvalsRequired}, {$approvedCount}, {$rejectedCount}, " .
                "'{$approvalStatus}', TRUE, NULL, 1, '{$approvalToken}', '{$created}', '{$now}')"
            );

            // Retrieve the workflow approval ID
            $approvalRow = $this->fetchRow(
                "SELECT id FROM workflow_approvals " .
                "WHERE workflow_instance_id = {$instanceId} AND node_id = 'approval-gate' " .
                "ORDER BY id DESC LIMIT 1"
            );
            if (!$approvalRow) {
                continue;
            }
            $workflowApprovalId = (int)$approvalRow['id'];

            // (d) Create workflow_approval_responses for each unique legacy approver.
            $responses = $this->fetchAll(
                "SELECT approver_id, MAX(approved_on) AS approved_on " .
                "FROM warrant_roster_approvals " .
                "WHERE warrant_roster_id = {$rosterId} AND approved_on IS NOT NULL " .
                "GROUP BY approver_id ORDER BY approved_on"
            );

            foreach ($responses as $resp) {
                $respMemberId = (int)$resp['approver_id'];
                $respondedOn = $resp['approved_on'];

                $this->execute(
                    "INSERT INTO workflow_approval_responses " .
                    "(workflow_approval_id, member_id, decision, comment, responded_at, created) " .
                    "VALUES ({$workflowApprovalId}, {$respMemberId}, 'approve', " .
                    "NULL, '{$respondedOn}', '{$respondedOn}')"
                );
                $totalResponses++;
            }

            if (isset($counters[$status])) {
                $counters[$status]++;
            }
        }

        // Step 4: Verification output
        $totalMigrated = $counters['pending'] + $counters['approved'] + $counters['declined'];
        $instanceCount = $this->fetchRow(
            "SELECT COUNT(*) AS cnt FROM workflow_instances " .
            "WHERE entity_type = 'WarrantRosters' AND $ctxText LIKE '%\"migrated\":true%'"
        );
        $instTotal = $instanceCount ? (int)$instanceCount['cnt'] : 0;

        echo "Backfill complete: {$totalMigrated} warrant rosters migrated → {$instTotal} workflow instances.\n";
        echo "  Pending: {$counters['pending']}, Approved: {$counters['approved']}, Declined: {$counters['declined']}\n";
        echo "  Total approval responses migrated: {$totalResponses}\n";
    }

    public function down(): void
    {
        $this->execute(
            "DELETE FROM workflow_approval_responses WHERE workflow_approval_id IN " .
            "(SELECT id FROM workflow_approvals WHERE workflow_instance_id IN " .
            "(SELECT id FROM workflow_instances " .
            "WHERE entity_type = 'WarrantRosters' AND $ctxText LIKE '%\"migrated\":true%'))"
        );
        $this->execute(
            "DELETE FROM workflow_approvals WHERE workflow_instance_id IN " .
            "(SELECT id FROM workflow_instances " .
            "WHERE entity_type = 'WarrantRosters' AND $ctxText LIKE '%\"migrated\":true%')"
        );
        $this->execute(
            "DELETE FROM workflow_execution_logs WHERE workflow_instance_id IN " .
            "(SELECT id FROM workflow_instances " .
            "WHERE entity_type = 'WarrantRosters' AND $ctxText LIKE '%\"migrated\":true%')"
        );
        $this->execute(
            "DELETE FROM workflow_instances " .
            "WHERE entity_type = 'WarrantRosters' AND $ctxText LIKE '%\"migrated\":true%'"
        );
    }
}
