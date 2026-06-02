<?php

declare(strict_types=1);

use App\Migrations\CrossEngineMigrationTrait;
use Migrations\AbstractMigration;

/**
 * Backfill all existing Activities authorization approvals into the workflow engine tables.
 *
 * Creates workflow_instances, workflow_execution_logs, workflow_approvals, and
 * workflow_approval_responses for every record in activities_authorizations,
 * preserving the original approval chain and status history.
 *
 * Idempotent — skips if migrated instances already exist.
 */
class BackfillAuthorizationApprovalsToWorkflowEngine extends AbstractMigration
{
    use CrossEngineMigrationTrait;

    /**
     * Backfill existing authorization approval history into workflow tables.
     *
     * @return void
     */
    public function up(): void
    {
        // Skip backfill if the source plugin table doesn't exist yet.
        // On fresh installs, Activities plugin migrations run after core
        // migrations via the UpdateDatabaseCommand, so the table may be
        // absent when this migration runs.
        if (!$this->tableExistsInDb('activities_authorizations')) {
            echo "Skipping backfill: activities_authorizations table does not exist (fresh install).\n";

            return;
        }

        $ctxText = $this->jsonAsText('context');
        // Check idempotency — skip if we already have migrated records
        $existing = $this->fetchRow(
            'SELECT COUNT(*) AS cnt FROM workflow_instances ' .
            "WHERE entity_type = 'Activities.Authorizations' AND $ctxText LIKE '%\"migrated\":true%'",
        );
        if ($existing && (int)$existing['cnt'] > 0) {
            echo "Backfill already applied ({$existing['cnt']} migrated instances found). Skipping.\n";

            return;
        }

        // Step 1: Find the workflow definition and published version
        $defRow = $this->fetchRow(
            "SELECT id FROM workflow_definitions WHERE slug = 'activities-authorization-request'",
        );
        if (!$defRow) {
            echo "Workflow definition 'activities-authorization-request' not found. Skipping backfill.\n";

            return;
        }
        $defId = (int)$defRow['id'];

        $versionRow = $this->fetchRow(
            'SELECT id FROM workflow_versions ' .
            "WHERE workflow_definition_id = {$defId} AND status = 'published' " .
            'ORDER BY version_number DESC LIMIT 1',
        );
        if (!$versionRow) {
            echo "No published workflow version found. Skipping backfill.\n";

            return;
        }
        $versionId = (int)$versionRow['id'];

        // Step 2: Fetch all source data once. The seeded dev database contains
        // thousands of authorizations; doing selects/inserts per row makes reset
        // effectively hang on this migration.
        $authorizations = $this->fetchAll(
            'SELECT aa.*, act.num_required_authorizors, act.num_required_renewers ' .
            'FROM activities_authorizations aa ' .
            'LEFT JOIN activities_activities act ON act.id = aa.activity_id ' .
            'ORDER BY aa.id',
        );
        if (empty($authorizations)) {
            echo "No authorizations to migrate.\n";

            return;
        }

        $sourceApprovals = $this->fetchAll(
            'SELECT authorization_id, approver_id, approved, approver_notes, requested_on, responded_on ' .
            'FROM activities_authorization_approvals ORDER BY authorization_id, requested_on',
        );
        $approvalStats = [];
        $responsesByAuthorization = [];
        foreach ($sourceApprovals as $approval) {
            $authorizationId = (int)$approval['authorization_id'];
            $approvalStats[$authorizationId] ??= [
                'approved_count' => 0,
                'rejected_count' => 0,
                'last_responded' => null,
                'pending_approver_id' => null,
                'pending_requested_on' => null,
            ];

            if ($approval['responded_on'] !== null) {
                if ($this->isTruthy($approval['approved'])) {
                    $approvalStats[$authorizationId]['approved_count']++;
                } else {
                    $approvalStats[$authorizationId]['rejected_count']++;
                }
                if (
                    $approvalStats[$authorizationId]['last_responded'] === null ||
                    $approval['responded_on'] > $approvalStats[$authorizationId]['last_responded']
                ) {
                    $approvalStats[$authorizationId]['last_responded'] = $approval['responded_on'];
                }
                $responsesByAuthorization[$authorizationId][] = $approval;
            } elseif (
                $approvalStats[$authorizationId]['pending_requested_on'] === null ||
                $approval['requested_on'] > $approvalStats[$authorizationId]['pending_requested_on']
            ) {
                $approvalStats[$authorizationId]['pending_approver_id'] = (int)$approval['approver_id'];
                $approvalStats[$authorizationId]['pending_requested_on'] = $approval['requested_on'];
            }
        }

        $now = date('Y-m-d H:i:s');

        // Status mappings
        $instanceStatusMap = [
            'pending' => 'waiting',
            'approved' => 'completed',
            'denied' => 'completed',
            'revoked' => 'completed',
            'expired' => 'completed',
            'retracted' => 'cancelled',
        ];

        $logStatusMap = [
            'pending' => 'waiting',
            'approved' => 'completed',
            'denied' => 'completed',
            'revoked' => 'completed',
            'expired' => 'completed',
            'retracted' => 'completed',
        ];

        $approvalStatusMap = [
            'pending' => 'pending',
            'approved' => 'approved',
            'denied' => 'rejected',
            'revoked' => 'approved',
            'expired' => 'expired',
            'retracted' => 'cancelled',
        ];

        $instanceRows = [];
        $instanceMetadata = [];
        foreach ($authorizations as $auth) {
            $authId = (int)$auth['id'];
            $memberId = (int)$auth['member_id'];
            $activityId = (int)$auth['activity_id'];
            $status = strtolower($auth['status']);
            $isRenewal = $this->isTruthy($auth['is_renewal']) ? 1 : 0;
            $created = $auth['created'];
            $requiredCount = $isRenewal
                ? (int)($auth['num_required_renewers'] ?? 1)
                : (int)($auth['num_required_authorizors'] ?? 1);
            if ($requiredCount < 1) {
                $requiredCount = 1;
            }

            $stats = $approvalStats[$authId] ?? [];
            $approvedCount = (int)($stats['approved_count'] ?? 0);
            $rejectedCount = (int)($stats['rejected_count'] ?? 0);

            // Determine completed_at for terminal statuses
            $completedAt = null;
            $isTerminal = in_array($status, ['approved', 'denied', 'revoked', 'expired', 'retracted']);
            if ($isTerminal) {
                $completedAt = !empty($stats['last_responded'])
                    ? $stats['last_responded']
                    : $created;
            }

            // Build instance status
            $instanceStatus = $instanceStatusMap[$status] ?? 'completed';
            $logStatus = $logStatusMap[$status] ?? 'completed';
            $approvalStatus = $approvalStatusMap[$status] ?? 'pending';

            // Active nodes
            $activeNodes = $status === 'pending' ? '["approval-gate"]' : '[]';

            // Context JSON
            $context = json_encode([
                'trigger' => [
                    'authorizationId' => $authId,
                    'memberId' => $memberId,
                    'activityId' => $activityId,
                    'isRenewal' => (bool)$isRenewal,
                    'requiredApprovals' => $requiredCount,
                ],
                'migrated' => true,
                'migratedAt' => $now,
            ]);
            $contextEsc = $this->sqlEscape($context);

            $completedAtSql = $this->sqlDateOrNull($completedAt);
            $instanceRows[] = "({$defId}, {$versionId}, 'Activities.Authorizations', {$authId}, " .
                "'{$instanceStatus}', '{$contextEsc}', '{$activeNodes}', {$memberId}, " .
                "'{$created}', {$completedAtSql}, '{$created}', '{$now}')";
            $instanceMetadata[$authId] = [
                'log_status' => $logStatus,
                'approval_status' => $approvalStatus,
                'completed_at' => $completedAt,
                'created' => $created,
                'activity_id' => $activityId,
                'required_count' => $requiredCount,
                'approved_count' => $approvedCount,
                'rejected_count' => $rejectedCount,
                'pending_approver_id' => $stats['pending_approver_id'] ?? null,
            ];
        }
        $this->bulkInsertSql(
            'workflow_instances',
            '(workflow_definition_id, workflow_version_id, entity_type, entity_id, ' .
            'status, context, active_nodes, started_by, started_at, completed_at, created, modified)',
            $instanceRows,
        );

        $instanceIdByAuthorization = $this->fetchIdMap(
            'SELECT id, entity_id FROM workflow_instances ' .
            "WHERE entity_type = 'Activities.Authorizations' AND $ctxText LIKE '%\"migrated\":true%'",
        );

        $logRows = [];
        foreach ($instanceMetadata as $authId => $metadata) {
            if (!isset($instanceIdByAuthorization[$authId])) {
                continue;
            }
            $instanceId = $instanceIdByAuthorization[$authId];
            $completedAtSql = $this->sqlDateOrNull($metadata['completed_at']);
            $logRows[] = "({$instanceId}, 'approval-gate', 'approval', 1, " .
                "'{$metadata['log_status']}', '{$metadata['created']}', {$completedAtSql}, '{$metadata['created']}')";
        }
        $this->bulkInsertSql(
            'workflow_execution_logs',
            '(workflow_instance_id, node_id, node_type, attempt_number, status, started_at, completed_at, created)',
            $logRows,
        );

        $logIdByInstance = $this->fetchIdMap(
            'SELECT id, workflow_instance_id AS entity_id FROM workflow_execution_logs ' .
            "WHERE node_id = 'approval-gate' AND workflow_instance_id IN (" .
            implode(',', array_values($instanceIdByAuthorization)) . ')',
        );

        $approvalRows = [];
        foreach ($instanceMetadata as $authId => $metadata) {
            if (!isset($instanceIdByAuthorization[$authId])) {
                continue;
            }
            $instanceId = $instanceIdByAuthorization[$authId];
            if (!isset($logIdByInstance[$instanceId])) {
                continue;
            }

            $approverConfig = [
                'service' => 'Activities.AuthorizationApproverResolver',
                'method' => 'getEligibleApproverIds',
                'activity_id' => $metadata['activity_id'],
            ];
            if ($metadata['pending_approver_id'] !== null) {
                $approverConfig['current_approver_id'] = (int)$metadata['pending_approver_id'];
            }
            $approverConfigJson = $this->sqlEscape(json_encode($approverConfig));
            $approvalToken = bin2hex(random_bytes(16));
            $approvalRows[] = "({$instanceId}, 'approval-gate', {$logIdByInstance[$instanceId]}, " .
                "'dynamic', '{$approverConfigJson}', {$metadata['required_count']}, " .
                "{$metadata['approved_count']}, {$metadata['rejected_count']}, " .
                "'{$metadata['approval_status']}', FALSE, NULL, 1, '{$approvalToken}', " .
                "'{$metadata['created']}', '{$now}')";
        }
        $this->bulkInsertSql(
            'workflow_approvals',
            '(workflow_instance_id, node_id, execution_log_id, approver_type, approver_config, ' .
            'required_count, approved_count, rejected_count, status, allow_parallel, deadline, version, ' .
            'approval_token, created, modified)',
            $approvalRows,
        );

        $approvalIdByAuthorization = [];
        $approvalRows = $this->fetchAll(
            'SELECT wa.id, wi.entity_id ' .
            'FROM workflow_approvals wa ' .
            'INNER JOIN workflow_instances wi ON wi.id = wa.workflow_instance_id ' .
            "WHERE wi.entity_type = 'Activities.Authorizations' " .
            "AND $ctxText LIKE '%\"migrated\":true%'",
        );
        foreach ($approvalRows as $approvalRow) {
            $approvalIdByAuthorization[(int)$approvalRow['entity_id']] = (int)$approvalRow['id'];
        }

        $responseRows = [];
        foreach ($responsesByAuthorization as $authId => $responses) {
            if (!isset($approvalIdByAuthorization[$authId])) {
                continue;
            }
            foreach ($responses as $resp) {
                $respMemberId = (int)$resp['approver_id'];
                $decision = $this->isTruthy($resp['approved']) ? 'approve' : 'reject';
                $comment = $resp['approver_notes']
                    ? "'" . $this->sqlEscape($resp['approver_notes']) . "'"
                    : 'NULL';
                $respondedOn = $resp['responded_on'];
                $responseRows[] = "({$approvalIdByAuthorization[$authId]}, {$respMemberId}, '{$decision}', " .
                    "{$comment}, '{$respondedOn}', '{$respondedOn}')";
            }
        }
        $this->bulkInsertSql(
            'workflow_approval_responses',
            '(workflow_approval_id, member_id, decision, comment, responded_at, created)',
            $responseRows,
        );

        // Step 3: Verification
        $authCount = $this->fetchRow('SELECT COUNT(*) AS cnt FROM activities_authorizations');
        $instanceCount = $this->fetchRow(
            'SELECT COUNT(*) AS cnt FROM workflow_instances ' .
            "WHERE entity_type = 'Activities.Authorizations' AND $ctxText LIKE '%\"migrated\":true%'",
        );
        $approvalCount = $this->fetchRow(
            'SELECT COUNT(*) AS cnt FROM workflow_approval_responses ' .
            'WHERE workflow_approval_id IN (' .
            'SELECT id FROM workflow_approvals WHERE workflow_instance_id IN (' .
            'SELECT id FROM workflow_instances ' .
            "WHERE entity_type = 'Activities.Authorizations' AND $ctxText LIKE '%\"migrated\":true%'))",
        );

        $authTotal = $authCount ? (int)$authCount['cnt'] : 0;
        $instTotal = $instanceCount ? (int)$instanceCount['cnt'] : 0;
        $respTotal = $approvalCount ? (int)$approvalCount['cnt'] : 0;

        echo "Backfill complete: {$authTotal} authorizations -> " .
            "{$instTotal} workflow instances, {$respTotal} approval responses.\n";
    }

    /**
     * Insert raw SQL values in chunks.
     *
     * @param string $table Table name
     * @param string $columns Column list, including parentheses
     * @param array<string> $rows SQL value tuples
     * @return void
     */
    private function bulkInsertSql(string $table, string $columns, array $rows): void
    {
        foreach (array_chunk($rows, 500) as $chunk) {
            $this->execute("INSERT INTO {$table} {$columns} VALUES " . implode(', ', $chunk));
        }
    }

    /**
     * Fetch a map keyed by the `entity_id` alias from a query returning `id`.
     *
     * @param string $query SQL query
     * @return array<int, int>
     */
    private function fetchIdMap(string $query): array
    {
        $map = [];
        foreach ($this->fetchAll($query) as $row) {
            $map[(int)$row['entity_id']] = (int)$row['id'];
        }

        return $map;
    }

    /**
     * Convert date-ish values to nullable SQL literals.
     *
     * @param mixed $value Date value
     * @return string SQL literal
     */
    private function sqlDateOrNull(mixed $value): string
    {
        return $value ? "'" . $this->sqlEscape((string)$value) . "'" : 'NULL';
    }

    /**
     * Normalize cross-driver boolean values.
     *
     * @param mixed $value Boolean-ish value
     * @return bool
     */
    private function isTruthy(mixed $value): bool
    {
        return in_array($value, [true, 1, '1', 't', 'true', 'TRUE'], true);
    }

    /**
     * Remove migrated workflow history rows.
     *
     * @return void
     */
    public function down(): void
    {
        $ctxText = $this->jsonAsText('context');

        $this->execute(
            'DELETE FROM workflow_approval_responses WHERE workflow_approval_id IN ' .
            '(SELECT id FROM workflow_approvals WHERE workflow_instance_id IN ' .
            '(SELECT id FROM workflow_instances ' .
            "WHERE entity_type = 'Activities.Authorizations' AND $ctxText LIKE '%\"migrated\":true%'))",
        );
        $this->execute(
            'DELETE FROM workflow_approvals WHERE workflow_instance_id IN ' .
            '(SELECT id FROM workflow_instances ' .
            "WHERE entity_type = 'Activities.Authorizations' AND $ctxText LIKE '%\"migrated\":true%')",
        );
        $this->execute(
            'DELETE FROM workflow_execution_logs WHERE workflow_instance_id IN ' .
            '(SELECT id FROM workflow_instances ' .
            "WHERE entity_type = 'Activities.Authorizations' AND $ctxText LIKE '%\"migrated\":true%')",
        );
        $this->execute(
            'DELETE FROM workflow_instances ' .
            "WHERE entity_type = 'Activities.Authorizations' AND $ctxText LIKE '%\"migrated\":true%'",
        );
    }
}
