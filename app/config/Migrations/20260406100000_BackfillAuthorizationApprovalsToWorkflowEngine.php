<?php

declare(strict_types=1);

use Migrations\AbstractMigration;
use App\Migrations\CrossEngineMigrationTrait;

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
            "SELECT COUNT(*) AS cnt FROM workflow_instances " .
            "WHERE entity_type = 'Activities.Authorizations' AND $ctxText LIKE '%\"migrated\":true%'"
        );
        if ($existing && (int)$existing['cnt'] > 0) {
            echo "Backfill already applied ({$existing['cnt']} migrated instances found). Skipping.\n";
            return;
        }

        // Step 1: Find the workflow definition and published version
        $defRow = $this->fetchRow(
            "SELECT id FROM workflow_definitions WHERE slug = 'activities-authorization-request'"
        );
        if (!$defRow) {
            echo "Workflow definition 'activities-authorization-request' not found. Skipping backfill.\n";
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

        // Step 2: Fetch all authorizations
        $authorizations = $this->fetchAll("SELECT * FROM activities_authorizations");
        if (empty($authorizations)) {
            echo "No authorizations to migrate.\n";
            return;
        }

        $now = date('Y-m-d H:i:s');

        // Status mappings
        $instanceStatusMap = [
            'pending'   => 'waiting',
            'approved'  => 'completed',
            'denied'    => 'completed',
            'revoked'   => 'completed',
            'expired'   => 'completed',
            'retracted' => 'cancelled',
        ];

        $logStatusMap = [
            'pending'   => 'waiting',
            'approved'  => 'completed',
            'denied'    => 'completed',
            'revoked'   => 'completed',
            'expired'   => 'completed',
            'retracted' => 'completed',
        ];

        $approvalStatusMap = [
            'pending'   => 'pending',
            'approved'  => 'approved',
            'denied'    => 'rejected',
            'revoked'   => 'approved',
            'expired'   => 'expired',
            'retracted' => 'cancelled',
        ];

        foreach ($authorizations as $auth) {
            $authId = (int)$auth['id'];
            $memberId = (int)$auth['member_id'];
            $activityId = (int)$auth['activity_id'];
            $status = strtolower($auth['status']);
            $isRenewal = !empty($auth['is_renewal']) ? 1 : 0;
            $created = $auth['created'];

            // Get required approvals count from the activity
            $activityRow = $this->fetchRow(
                "SELECT num_required_authorizors, num_required_renewers " .
                "FROM activities_activities WHERE id = {$activityId}"
            );
            $requiredCount = 1;
            if ($activityRow) {
                $requiredCount = $isRenewal
                    ? (int)$activityRow['num_required_renewers']
                    : (int)$activityRow['num_required_authorizors'];
            }

            // Count approved and rejected responses
            $countsRow = $this->fetchRow(
                "SELECT " .
                "COUNT(CASE WHEN approved = TRUE AND responded_on IS NOT NULL THEN 1 END) AS approved_count, " .
                "COUNT(CASE WHEN approved = FALSE AND responded_on IS NOT NULL THEN 1 END) AS rejected_count " .
                "FROM activities_authorization_approvals WHERE authorization_id = {$authId}"
            );
            $approvedCount = $countsRow ? (int)$countsRow['approved_count'] : 0;
            $rejectedCount = $countsRow ? (int)$countsRow['rejected_count'] : 0;

            // Determine completed_at for terminal statuses
            $completedAt = null;
            $isTerminal = in_array($status, ['approved', 'denied', 'revoked', 'expired', 'retracted']);
            if ($isTerminal) {
                $lastResponse = $this->fetchRow(
                    "SELECT MAX(responded_on) AS last_responded " .
                    "FROM activities_authorization_approvals " .
                    "WHERE authorization_id = {$authId} AND responded_on IS NOT NULL"
                );
                $completedAt = ($lastResponse && $lastResponse['last_responded'])
                    ? $lastResponse['last_responded']
                    : $created;
            }

            // Build instance status
            $instanceStatus = $instanceStatusMap[$status] ?? 'completed';
            $logStatus = $logStatusMap[$status] ?? 'completed';
            $approvalStatus = $approvalStatusMap[$status] ?? 'pending';

            // Active nodes
            $activeNodes = ($status === 'pending') ? '["approval-gate"]' : '[]';

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

            // (a) Create workflow_instances
            $completedAtSql = $completedAt ? "'{$completedAt}'" : 'NULL';
            $this->execute(
                "INSERT INTO workflow_instances " .
                "(workflow_definition_id, workflow_version_id, entity_type, entity_id, " .
                "status, context, active_nodes, started_by, started_at, completed_at, created, modified) " .
                "VALUES ({$defId}, {$versionId}, 'Activities.Authorizations', {$authId}, " .
                "'{$instanceStatus}', '{$contextEsc}', '{$activeNodes}', {$memberId}, " .
                "'{$created}', {$completedAtSql}, '{$created}', '{$now}')"
            );

            // Retrieve the newly inserted instance ID
            $instanceRow = $this->fetchRow(
                "SELECT id FROM workflow_instances " .
                "WHERE entity_type = 'Activities.Authorizations' AND entity_id = {$authId} " .
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
            $approverConfig = [
                'service' => 'Activities.AuthorizationApproverResolver',
                'method' => 'getEligibleApproverIds',
                'activity_id' => $activityId,
            ];

            // For pending authorizations, find the current pending approver
            if ($status === 'pending') {
                $pendingApproval = $this->fetchRow(
                    "SELECT approver_id FROM activities_authorization_approvals " .
                    "WHERE authorization_id = {$authId} AND responded_on IS NULL " .
                    "ORDER BY requested_on DESC LIMIT 1"
                );
                if ($pendingApproval) {
                    $approverConfig['current_approver_id'] = (int)$pendingApproval['approver_id'];
                }
            }

            $approverConfigJson = $this->sqlEscape(json_encode($approverConfig));
            $approvalToken = bin2hex(random_bytes(16));

            $this->execute(
                "INSERT INTO workflow_approvals " .
                "(workflow_instance_id, node_id, execution_log_id, " .
                "approver_type, approver_config, required_count, approved_count, rejected_count, " .
                "status, allow_parallel, deadline, version, approval_token, created, modified) " .
                "VALUES ({$instanceId}, 'approval-gate', {$logId}, " .
                "'dynamic', '{$approverConfigJson}', {$requiredCount}, {$approvedCount}, {$rejectedCount}, " .
                "'{$approvalStatus}', FALSE, NULL, 1, '{$approvalToken}', '{$created}', '{$now}')"
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

            // (d) Create workflow_approval_responses for responded approvals
            $responses = $this->fetchAll(
                "SELECT approver_id, approved, approver_notes, responded_on " .
                "FROM activities_authorization_approvals " .
                "WHERE authorization_id = {$authId} AND responded_on IS NOT NULL"
            );

            foreach ($responses as $resp) {
                $respMemberId = (int)$resp['approver_id'];
                $decision = ((int)$resp['approved'] === 1) ? 'approve' : 'reject';
                $comment = $resp['approver_notes']
                    ? "'" . $this->sqlEscape($resp['approver_notes']) . "'"
                    : 'NULL';
                $respondedOn = $resp['responded_on'];

                $this->execute(
                    "INSERT INTO workflow_approval_responses " .
                    "(workflow_approval_id, member_id, decision, comment, responded_at, created) " .
                    "VALUES ({$workflowApprovalId}, {$respMemberId}, '{$decision}', " .
                    "{$comment}, '{$respondedOn}', '{$respondedOn}')"
                );
            }
        }

        // Step 3: Verification
        $authCount = $this->fetchRow("SELECT COUNT(*) AS cnt FROM activities_authorizations");
        $instanceCount = $this->fetchRow(
            "SELECT COUNT(*) AS cnt FROM workflow_instances " .
            "WHERE entity_type = 'Activities.Authorizations' AND $ctxText LIKE '%\"migrated\":true%'"
        );
        $approvalCount = $this->fetchRow(
            "SELECT COUNT(*) AS cnt FROM workflow_approval_responses " .
            "WHERE workflow_approval_id IN (" .
            "SELECT id FROM workflow_approvals WHERE workflow_instance_id IN (" .
            "SELECT id FROM workflow_instances " .
            "WHERE entity_type = 'Activities.Authorizations' AND $ctxText LIKE '%\"migrated\":true%'))"
        );

        $authTotal = $authCount ? (int)$authCount['cnt'] : 0;
        $instTotal = $instanceCount ? (int)$instanceCount['cnt'] : 0;
        $respTotal = $approvalCount ? (int)$approvalCount['cnt'] : 0;

        echo "Backfill complete: {$authTotal} authorizations → {$instTotal} workflow instances, {$respTotal} approval responses.\n";
    }

    public function down(): void
    {
        $this->execute(
            "DELETE FROM workflow_approval_responses WHERE workflow_approval_id IN " .
            "(SELECT id FROM workflow_approvals WHERE workflow_instance_id IN " .
            "(SELECT id FROM workflow_instances " .
            "WHERE entity_type = 'Activities.Authorizations' AND $ctxText LIKE '%\"migrated\":true%'))"
        );
        $this->execute(
            "DELETE FROM workflow_approvals WHERE workflow_instance_id IN " .
            "(SELECT id FROM workflow_instances " .
            "WHERE entity_type = 'Activities.Authorizations' AND $ctxText LIKE '%\"migrated\":true%')"
        );
        $this->execute(
            "DELETE FROM workflow_execution_logs WHERE workflow_instance_id IN " .
            "(SELECT id FROM workflow_instances " .
            "WHERE entity_type = 'Activities.Authorizations' AND $ctxText LIKE '%\"migrated\":true%')"
        );
        $this->execute(
            "DELETE FROM workflow_instances " .
            "WHERE entity_type = 'Activities.Authorizations' AND $ctxText LIKE '%\"migrated\":true%'"
        );
    }
}
