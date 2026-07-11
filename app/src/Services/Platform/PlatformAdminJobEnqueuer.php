<?php
declare(strict_types=1);

namespace App\Services\Platform;

use App\Services\Backups\TenantBackupService;
use Cake\Database\Connection;
use Cake\Database\Driver\Postgres;
use Cake\I18n\DateTime;
use Cake\Utility\Text;
use RuntimeException;

/**
 * Enqueues platform-admin initiated operations with idempotency and audit.
 */
class PlatformAdminJobEnqueuer
{
    /**
     * Constructor.
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly PlatformAuditService $auditService,
    ) {
    }

    /**
     * @param array<string, mixed> $parameters
     * @param array<string, mixed> $auditOptions
     * @return array<string, mixed> platform_jobs row
     */
    public function enqueue(
        string $jobType,
        ?string $tenantId,
        ?string $platformUserId,
        array $parameters,
        string $idempotencyKey,
        string $reason,
        array $auditOptions = [],
    ): array {
        $jobType = trim($jobType);
        $idempotencyKey = trim($idempotencyKey);
        if ($jobType === '' || !preg_match('/^[a-z][a-z0-9_.:-]{1,119}$/', $jobType)) {
            throw new RuntimeException('Platform job type is invalid.');
        }
        if ($idempotencyKey === '' || strlen($idempotencyKey) > 255) {
            throw new RuntimeException('Platform job idempotency key is invalid.');
        }

        return $this->connection->transactional(function () use (
            $jobType,
            $tenantId,
            $platformUserId,
            $parameters,
            $idempotencyKey,
            $reason,
            $auditOptions,
        ): array {
            $this->lockOperationScope($jobType, $tenantId, $parameters);
            $existing = $this->findByIdempotencyKey($idempotencyKey);
            if ($existing !== null) {
                return $existing;
            }
            $this->assertNoActiveConflict($jobType, $tenantId, $parameters);
            $this->assertBackupAvailableForRestore($jobType, $tenantId, $parameters);

            $now = (new DateTime('now'))->format('Y-m-d H:i:s');
            $job = [
                'id' => Text::uuid(),
                'tenant_id' => $tenantId,
                'requested_by_platform_user_id' => $platformUserId,
                'job_type' => $jobType,
                'status' => 'queued',
                'idempotency_key' => $idempotencyKey,
                'parameters' => $this->encodeParameters($parameters),
                'log_uri' => null,
                'last_error' => null,
                'created_at' => $now,
                'started_at' => null,
                'finished_at' => null,
                'modified_at' => $now,
            ];
            $this->connection->insert('platform_jobs', $job);
            $this->auditService->record(
                'platform_job.queued',
                $platformUserId,
                'platform_job',
                (string)$job['id'],
                $reason,
                [
                    'job_type' => $jobType,
                    'tenant_id' => $tenantId,
                    'idempotency_key' => $idempotencyKey,
                    'parameters' => $parameters,
                ],
                false,
                $auditOptions + ['tenantId' => $tenantId],
            );

            return $job;
        });
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findByIdempotencyKey(string $idempotencyKey): ?array
    {
        $row = $this->connection->execute(
            'SELECT * FROM platform_jobs WHERE idempotency_key = :idempotencyKey LIMIT 1',
            ['idempotencyKey' => $idempotencyKey],
        )->fetch('assoc');

        return is_array($row) ? $row : null;
    }

    /**
     * @param array<string, mixed> $parameters
     */
    private function encodeParameters(array $parameters): string
    {
        $json = json_encode($parameters, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            throw new RuntimeException('Unable to encode platform job parameters.');
        }

        return $json;
    }

    /**
     * @param array<string, mixed> $parameters
     */
    private function lockOperationScope(string $jobType, ?string $tenantId, array $parameters): void
    {
        if (!$this->isExclusiveOperation($jobType)) {
            return;
        }
        if (!$this->connection->getDriver() instanceof Postgres) {
            return;
        }
        $tenantSlug = strtolower(trim((string)($parameters['tenant_slug'] ?? '')));
        $scope = $tenantId ?? ($tenantSlug !== '' ? $tenantSlug : 'platform');
        $this->connection->execute(
            'SELECT pg_advisory_xact_lock(hashtext(:scope))',
            ['scope' => sprintf('platform-operation:%s', $scope)],
        );
    }

    /**
     * @param array<string, mixed> $parameters
     */
    private function assertNoActiveConflict(string $jobType, ?string $tenantId, array $parameters): void
    {
        if (!$this->isExclusiveOperation($jobType)) {
            return;
        }

        if ($jobType === PlatformJobRunner::JOB_PLATFORM_BACKUP) {
            $row = $this->connection->execute(
                'SELECT id
                   FROM platform_jobs
                  WHERE job_type = :jobType
                    AND tenant_id IS NULL
                    AND status IN (:queued, :running)
                  LIMIT 1',
                ['jobType' => $jobType, 'queued' => 'queued', 'running' => 'running'],
            )->fetch('assoc');
            if (is_array($row)) {
                throw new RuntimeException('A platform database backup is already queued or running.');
            }

            return;
        }

        $tenantSlug = strtolower(trim((string)($parameters['tenant_slug'] ?? '')));
        $tenantScope = 'tenant_id IS NULL';
        $queryParameters = [
            'queued' => 'queued',
            'running' => 'running',
            'provision' => PlatformJobRunner::JOB_TENANT_PROVISION,
            'backup' => PlatformJobRunner::JOB_TENANT_BACKUP,
            'restore' => PlatformJobRunner::JOB_TENANT_RESTORE,
        ];
        if ($this->connection->getDriver() instanceof Postgres) {
            $scope = [];
            if ($tenantId !== null) {
                $scope[] = 'tenant_id = :tenantId';
                $queryParameters['tenantId'] = $tenantId;
            }
            if ($tenantSlug !== '') {
                $scope[] = "(tenant_id IS NULL AND LOWER(BTRIM(parameters ->> 'tenant_slug')) = :tenantSlug)";
                $queryParameters['tenantSlug'] = $tenantSlug;
            }
            if ($scope === []) {
                return;
            }
            $activeJob = $this->connection->execute(
                sprintf(
                    'SELECT id
                       FROM platform_jobs
                      WHERE status IN (:queued, :running)
                        AND job_type IN (:provision, :backup, :restore)
                        AND (%s)
                      LIMIT 1',
                    implode(' OR ', $scope),
                ),
                $queryParameters,
            )->fetchColumn(0);
            if ($activeJob !== false) {
                throw new RuntimeException('Another lifecycle operation is already queued or running for this tenant.');
            }

            return;
        }
        if ($tenantId !== null) {
            $tenantScope = '(tenant_id = :tenantId OR tenant_id IS NULL)';
            $queryParameters['tenantId'] = $tenantId;
        }
        $rows = $this->connection->execute(
            sprintf(
                'SELECT id, tenant_id, job_type, parameters
               FROM platform_jobs
              WHERE status IN (:queued, :running)
                AND job_type IN (:provision, :backup, :restore)
                AND %s',
                $tenantScope,
            ),
            $queryParameters,
        )->fetchAll('assoc');
        foreach ($rows as $row) {
            if ($tenantId !== null && (string)($row['tenant_id'] ?? '') === $tenantId) {
                throw new RuntimeException('Another lifecycle operation is already queued or running for this tenant.');
            }
            $existingParameters = json_decode((string)($row['parameters'] ?? ''), true);
            if (
                $tenantSlug !== ''
                && is_array($existingParameters)
                && strtolower(trim((string)($existingParameters['tenant_slug'] ?? ''))) === $tenantSlug
            ) {
                throw new RuntimeException('Another lifecycle operation is already queued or running for this tenant.');
            }
        }
    }

    /**
     * Recheck backup availability while holding the tenant operation lock.
     *
     * @param array<string, mixed> $parameters
     */
    private function assertBackupAvailableForRestore(
        string $jobType,
        ?string $tenantId,
        array $parameters,
    ): void {
        if ($jobType !== PlatformJobRunner::JOB_TENANT_RESTORE) {
            return;
        }
        $backupId = trim((string)($parameters['backup_id'] ?? ''));
        if ($tenantId === null || $backupId === '') {
            throw new RuntimeException('Tenant restore backup metadata is incomplete.');
        }
        if ($this->connection->getDriver() instanceof Postgres) {
            $this->connection->execute(
                'SELECT pg_advisory_xact_lock(hashtext(:scope))',
                ['scope' => sprintf('backup-archive:%s', $backupId)],
            );
        }
        $tenantStatus = $this->connection->execute(
            'SELECT status FROM tenants WHERE id = :tenantId LIMIT 1',
            ['tenantId' => $tenantId],
        )->fetchColumn(0);
        if ($tenantStatus !== 'suspended') {
            throw new RuntimeException('Tenant must remain suspended before a restore can be queued.');
        }
        $backup = $this->connection->execute(
            'SELECT tenant_id, backup_type, status
               FROM tenant_backups
              WHERE id = :backupId
              LIMIT 1',
            ['backupId' => $backupId],
        )->fetch('assoc');
        if (
            !is_array($backup)
            || (string)$backup['tenant_id'] !== $tenantId
            || (string)$backup['status'] !== 'completed'
            || !in_array((string)$backup['backup_type'], [TenantBackupService::BACKUP_TYPE, 'pg_dump'], true)
        ) {
            throw new RuntimeException('Tenant backup is no longer available for restore.');
        }
    }

    /**
     * Whether the job requires exclusive lifecycle scope.
     */
    private function isExclusiveOperation(string $jobType): bool
    {
        return in_array($jobType, [
            PlatformJobRunner::JOB_TENANT_PROVISION,
            PlatformJobRunner::JOB_TENANT_BACKUP,
            PlatformJobRunner::JOB_TENANT_RESTORE,
            PlatformJobRunner::JOB_PLATFORM_BACKUP,
        ], true);
    }
}
