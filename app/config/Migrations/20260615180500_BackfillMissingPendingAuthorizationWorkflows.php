<?php

declare(strict_types=1);

use App\Migrations\CrossEngineMigrationTrait;
use Migrations\AbstractMigration;

/**
 * Backfill pending Activities authorizations that do not have an actionable workflow approval.
 */
class BackfillMissingPendingAuthorizationWorkflows extends AbstractMigration
{
    use CrossEngineMigrationTrait;

    private const WORKFLOW_SLUG = 'activities-authorization-request';
    private const ENTITY_TYPE = 'Activities.Authorizations';
    private const MIGRATION_MARKER = 'BackfillMissingPendingAuthorizationWorkflows';
    private const COMMENT_WARNING = 'Comments may be visible to the person who submitted this request.';

    /**
     * Create missing workflow approval rows for pending authorization requests.
     *
     * @return void
     */
    public function up(): void
    {
        if (!$this->tableExistsInDb('activities_authorizations')) {
            echo "Skipping pending authorization workflow repair: activities_authorizations table does not exist.\n";

            return;
        }

        $definition = $this->fetchRow(sprintf(
            "SELECT id FROM workflow_definitions WHERE slug = '%s'",
            self::WORKFLOW_SLUG,
        ));
        if (!$definition) {
            echo "Skipping pending authorization workflow repair: authorization workflow definition not found.\n";

            return;
        }

        $definitionId = (int)$definition['id'];
        $version = $this->fetchRow(
            'SELECT id FROM workflow_versions ' .
            "WHERE workflow_definition_id = {$definitionId} AND status = 'published' " .
            'ORDER BY version_number DESC LIMIT 1',
        );
        if (!$version) {
            echo "Skipping pending authorization workflow repair: no published authorization workflow version found.\n";

            return;
        }

        $versionId = (int)$version['id'];
        $authorizations = $this->fetchMissingPendingAuthorizations();
        if ($authorizations === []) {
            echo "No pending authorization workflow repairs needed.\n";

            return;
        }

        $authorizationIds = array_map(static fn(array $row): int => (int)$row['id'], $authorizations);
        $legacyApprovals = $this->fetchLegacyApprovals($authorizationIds);
        $now = date('Y-m-d H:i:s');

        $instanceRows = [];
        foreach ($authorizations as $authorization) {
            $authorizationId = (int)$authorization['id'];
            $memberId = (int)$authorization['member_id'];
            $activityId = (int)$authorization['activity_id'];
            $isRenewal = $this->isTruthy($authorization['is_renewal'] ?? false);
            $requiredCount = $this->requiredApprovalCount($authorization, $isRenewal);
            $pendingApproval = $legacyApprovals[$authorizationId]['pending'] ?? null;
            $created = (string)$authorization['created'];
            $context = [
                'trigger' => [
                    'authorizationId' => $authorizationId,
                    'memberId' => $memberId,
                    'activityId' => $activityId,
                    'isRenewal' => $isRenewal,
                    'requiredApprovals' => $requiredCount,
                    'approvalPermission' => $authorization['approval_permission'] ?? null,
                    'approverId' => $pendingApproval['approver_id'] ?? null,
                ],
                'nodes' => [
                    'validate-request' => [
                        'result' => [
                            'authorizationId' => $authorizationId,
                            'authorizationToken' => $pendingApproval['authorization_token'] ?? null,
                        ],
                    ],
                ],
                'migrated' => true,
                'migration' => self::MIGRATION_MARKER,
                'migratedAt' => $now,
            ];

            $instanceRows[] = sprintf(
                "(%d, %d, '%s', %d, 'waiting', '%s', '[\"approval-gate\"]', %d, '%s', NULL, '%s', '%s')",
                $definitionId,
                $versionId,
                self::ENTITY_TYPE,
                $authorizationId,
                $this->sqlEscape(json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)),
                $memberId,
                $this->sqlEscape($created),
                $this->sqlEscape($created),
                $now,
            );
        }

        $this->bulkInsertSql(
            'workflow_instances',
            '(workflow_definition_id, workflow_version_id, entity_type, entity_id, status, context, active_nodes, ' .
            'started_by, started_at, completed_at, created, modified)',
            $instanceRows,
        );

        $instanceIdByAuthorization = $this->fetchRepairedInstanceIdMap();
        if ($instanceIdByAuthorization === []) {
            echo "Pending authorization workflow repair could not find inserted workflow instances.\n";

            return;
        }

        $logRows = [];
        foreach ($authorizations as $authorization) {
            $authorizationId = (int)$authorization['id'];
            if (!isset($instanceIdByAuthorization[$authorizationId])) {
                continue;
            }

            $created = $this->sqlEscape((string)$authorization['created']);
            $logRows[] = sprintf(
                "(%d, 'approval-gate', 'approval', 1, 'waiting', '%s', NULL, '%s')",
                $instanceIdByAuthorization[$authorizationId],
                $created,
                $created,
            );
        }
        $this->bulkInsertSql(
            'workflow_execution_logs',
            '(workflow_instance_id, node_id, node_type, attempt_number, status, started_at, completed_at, created)',
            $logRows,
        );

        $logIdByInstance = $this->fetchLogIdMap(array_values($instanceIdByAuthorization));
        $approvalRows = [];
        foreach ($authorizations as $authorization) {
            $authorizationId = (int)$authorization['id'];
            $instanceId = $instanceIdByAuthorization[$authorizationId] ?? null;
            if ($instanceId === null || !isset($logIdByInstance[$instanceId])) {
                continue;
            }

            $pendingApproval = $legacyApprovals[$authorizationId]['pending'] ?? null;
            $respondedApprovals = $legacyApprovals[$authorizationId]['responded'] ?? [];
            $approvedCount = $this->approvedCount($respondedApprovals);
            $rejectedCount = $this->rejectedCount($respondedApprovals);
            $currentApproverId = isset($pendingApproval['approver_id']) ? (int)$pendingApproval['approver_id'] : null;
            $approverConfig = [
                'permission' => $authorization['approval_permission'] ?? null,
                'serial_pick_next' => true,
                'comment_warning' => self::COMMENT_WARNING,
            ];
            if ($currentApproverId !== null) {
                $approverConfig['current_approver_id'] = $currentApproverId;
            }

            $requestTitlePrefix = $this->isTruthy($authorization['is_renewal'] ?? false) ? 'Renewal' : 'Authorization';
            $requestTitle = mb_substr(
                sprintf('%s: %s', $requestTitlePrefix, (string)($authorization['activity_name'] ?? 'Unknown Activity')),
                0,
                255,
            );
            $created = $this->sqlEscape((string)$authorization['created']);
            $approvalRows[] = sprintf(
                "(%d, 'approval-gate', %d, 'permission', '%s', %s, '%s', %d, %d, %d, " .
                "'pending', %s, NULL, 1, '%s', '%s', '%s')",
                $instanceId,
                $logIdByInstance[$instanceId],
                $this->sqlEscape(json_encode($approverConfig, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)),
                $currentApproverId === null ? 'NULL' : (string)$currentApproverId,
                $this->sqlEscape($requestTitle),
                $this->requiredApprovalCount($authorization, $this->isTruthy($authorization['is_renewal'] ?? false)),
                $approvedCount,
                $rejectedCount,
                $this->sqlBool(false),
                bin2hex(random_bytes(16)),
                $created,
                $now,
            );
        }
        $this->bulkInsertSql(
            'workflow_approvals',
            '(workflow_instance_id, node_id, execution_log_id, approver_type, approver_config, current_approver_id, ' .
            'request_title, required_count, approved_count, rejected_count, status, allow_parallel, deadline, ' .
            'version, approval_token, created, modified)',
            $approvalRows,
        );

        $approvalIdByAuthorization = $this->fetchRepairedApprovalIdMap();
        $responseRows = [];
        foreach ($legacyApprovals as $authorizationId => $approvalSet) {
            $workflowApprovalId = $approvalIdByAuthorization[$authorizationId] ?? null;
            if ($workflowApprovalId === null) {
                continue;
            }

            foreach ($approvalSet['responded'] ?? [] as $response) {
                $decision = $this->isTruthy($response['approved']) ? 'approve' : 'reject';
                $comment = $response['approver_notes'] !== null && $response['approver_notes'] !== ''
                    ? "'" . $this->sqlEscape((string)$response['approver_notes']) . "'"
                    : 'NULL';
                $respondedOn = $this->sqlEscape((string)$response['responded_on']);
                $responseRows[] = sprintf(
                    "(%d, %d, '%s', %s, '%s', '%s')",
                    $workflowApprovalId,
                    (int)$response['approver_id'],
                    $decision,
                    $comment,
                    $respondedOn,
                    $respondedOn,
                );
            }
        }
        $this->bulkInsertSql(
            'workflow_approval_responses',
            '(workflow_approval_id, member_id, decision, comment, responded_at, created)',
            $responseRows,
        );

        echo sprintf(
            "Pending authorization workflow repair complete: %d authorizations repaired.\n",
            count($approvalRows),
        );
    }

    /**
     * Remove workflow rows created by this repair migration.
     *
     * @return void
     */
    public function down(): void
    {
        $ctxText = $this->jsonAsText('context');
        $marker = $this->sqlEscape(self::MIGRATION_MARKER);

        $this->execute(
            'DELETE FROM workflow_approval_responses WHERE workflow_approval_id IN ' .
            '(SELECT id FROM workflow_approvals WHERE workflow_instance_id IN ' .
            '(SELECT id FROM workflow_instances ' .
            "WHERE entity_type = '" . self::ENTITY_TYPE . "' AND {$ctxText} LIKE '%\"migration\":\"{$marker}\"%'))",
        );
        $this->execute(
            'DELETE FROM workflow_approvals WHERE workflow_instance_id IN ' .
            '(SELECT id FROM workflow_instances ' .
            "WHERE entity_type = '" . self::ENTITY_TYPE . "' AND {$ctxText} LIKE '%\"migration\":\"{$marker}\"%')",
        );
        $this->execute(
            'DELETE FROM workflow_execution_logs WHERE workflow_instance_id IN ' .
            '(SELECT id FROM workflow_instances ' .
            "WHERE entity_type = '" . self::ENTITY_TYPE . "' AND {$ctxText} LIKE '%\"migration\":\"{$marker}\"%')",
        );
        $this->execute(
            'DELETE FROM workflow_instances ' .
            "WHERE entity_type = '" . self::ENTITY_TYPE . "' AND {$ctxText} LIKE '%\"migration\":\"{$marker}\"%'",
        );
    }

    /**
     * Fetch pending authorization rows that do not have an active pending workflow approval.
     *
     * @return array<int, array<string, mixed>>
     */
    private function fetchMissingPendingAuthorizations(): array
    {
        return $this->fetchAll(
            "SELECT aa.*, act.name AS activity_name, act.num_required_authorizors, act.num_required_renewers,
                    p.name AS approval_permission
             FROM activities_authorizations aa
             INNER JOIN activities_activities act ON act.id = aa.activity_id
             LEFT JOIN permissions p ON p.id = act.permission_id
             WHERE LOWER(aa.status) = 'pending'
               AND NOT EXISTS (
                   SELECT 1
                   FROM workflow_instances wi
                   INNER JOIN workflow_approvals wa ON wa.workflow_instance_id = wi.id
                   WHERE wi.entity_type IN ('Activities', '" . self::ENTITY_TYPE . "')
                     AND wi.entity_id = aa.id
                     AND wi.status IN ('pending', 'running', 'waiting')
                     AND wa.status = 'pending'
               )
             ORDER BY aa.id",
        );
    }

    /**
     * Fetch and group legacy authorization approval rows.
     *
     * @param array<int> $authorizationIds Authorization IDs being repaired.
     * @return array<int, array{pending?: array<string, mixed>, responded: array<int, array<string, mixed>>}>
     */
    private function fetchLegacyApprovals(array $authorizationIds): array
    {
        if ($authorizationIds === []) {
            return [];
        }

        $rows = $this->fetchAll(
            'SELECT authorization_id, approver_id, authorization_token, approved, approver_notes, ' .
            'requested_on, responded_on ' .
            'FROM activities_authorization_approvals ' .
            'WHERE authorization_id IN (' . implode(',', array_map('intval', $authorizationIds)) . ') ' .
            'ORDER BY authorization_id, requested_on, id',
        );

        $grouped = [];
        foreach ($rows as $row) {
            $authorizationId = (int)$row['authorization_id'];
            $grouped[$authorizationId] ??= ['responded' => []];
            if ($row['responded_on'] === null) {
                $grouped[$authorizationId]['pending'] = $row;
            } else {
                $grouped[$authorizationId]['responded'][] = $row;
            }
        }

        return $grouped;
    }

    /**
     * Insert raw SQL values in chunks.
     *
     * @param string $table Table name.
     * @param string $columns Column list, including parentheses.
     * @param array<string> $rows SQL value tuples.
     * @return void
     */
    private function bulkInsertSql(string $table, string $columns, array $rows): void
    {
        foreach (array_chunk($rows, 500) as $chunk) {
            if ($chunk === []) {
                continue;
            }

            $this->execute("INSERT INTO {$table} {$columns} VALUES " . implode(', ', $chunk));
        }
    }

    /**
     * Fetch repaired workflow instance IDs keyed by authorization ID.
     *
     * @return array<int, int>
     */
    private function fetchRepairedInstanceIdMap(): array
    {
        $ctxText = $this->jsonAsText('context');
        $marker = $this->sqlEscape(self::MIGRATION_MARKER);

        return $this->fetchIdMap(
            'SELECT id, entity_id FROM workflow_instances ' .
            "WHERE entity_type = '" . self::ENTITY_TYPE . "' AND {$ctxText} LIKE '%\"migration\":\"{$marker}\"%'",
        );
    }

    /**
     * Fetch approval IDs keyed by authorization ID.
     *
     * @return array<int, int>
     */
    private function fetchRepairedApprovalIdMap(): array
    {
        $ctxText = $this->jsonAsText('wi.context');
        $marker = $this->sqlEscape(self::MIGRATION_MARKER);

        return $this->fetchIdMap(
            'SELECT wa.id, wi.entity_id ' .
            'FROM workflow_approvals wa ' .
            'INNER JOIN workflow_instances wi ON wi.id = wa.workflow_instance_id ' .
            "WHERE wi.entity_type = '" . self::ENTITY_TYPE . "' AND {$ctxText} LIKE '%\"migration\":\"{$marker}\"%'",
        );
    }

    /**
     * Fetch execution log IDs keyed by workflow instance ID.
     *
     * @param array<int> $instanceIds Workflow instance IDs.
     * @return array<int, int>
     */
    private function fetchLogIdMap(array $instanceIds): array
    {
        if ($instanceIds === []) {
            return [];
        }

        return $this->fetchIdMap(
            'SELECT id, workflow_instance_id AS entity_id FROM workflow_execution_logs ' .
            "WHERE node_id = 'approval-gate' AND workflow_instance_id IN (" .
            implode(',', array_map('intval', $instanceIds)) . ')',
        );
    }

    /**
     * Fetch a map keyed by the entity_id alias from a query returning id.
     *
     * @param string $query SQL query.
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
     * Resolve the required approval count for an authorization row.
     *
     * @param array<string, mixed> $authorization Authorization row.
     * @param bool $isRenewal Whether this authorization is a renewal.
     * @return int
     */
    private function requiredApprovalCount(array $authorization, bool $isRenewal): int
    {
        $field = $isRenewal ? 'num_required_renewers' : 'num_required_authorizors';
        $requiredCount = (int)($authorization[$field] ?? 1);

        return max(1, $requiredCount);
    }

    /**
     * Count prior approved legacy responses.
     *
     * @param array<int, array<string, mixed>> $responses Legacy responses.
     * @return int
     */
    private function approvedCount(array $responses): int
    {
        return count(array_filter($responses, fn(array $response): bool => $this->isTruthy($response['approved'])));
    }

    /**
     * Count prior rejected legacy responses.
     *
     * @param array<int, array<string, mixed>> $responses Legacy responses.
     * @return int
     */
    private function rejectedCount(array $responses): int
    {
        return count(array_filter($responses, fn(array $response): bool => !$this->isTruthy($response['approved'])));
    }

    /**
     * Normalize cross-driver boolean values.
     *
     * @param mixed $value Boolean-ish value.
     * @return bool
     */
    private function isTruthy(mixed $value): bool
    {
        return in_array($value, [true, 1, '1', 't', 'true', 'TRUE'], true);
    }
}
