<?php
declare(strict_types=1);

namespace App\Services\Platform;

use Cake\Database\Connection;
use Cake\Database\Driver\Postgres;
use Cake\I18n\DateTime;
use RuntimeException;

/**
 * Applies guarded tenant lifecycle transitions and records them in the platform audit chain.
 */
final class TenantLifecycleService
{
    /**
     * @var array<string, list<string>>
     */
    private const ALLOWED_TRANSITIONS = [
        'active' => ['suspended'],
        'suspended' => ['active', 'archived'],
        'provisioning' => ['archived'],
    ];

    /**
     * Constructor.
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly ?PlatformAuditService $auditService = null,
    ) {
    }

    /**
     * @param array<string, mixed> $auditOptions
     * @return array<string, mixed>
     */
    public function transition(
        string $tenantId,
        string $targetStatus,
        ?string $platformUserId,
        string $reason,
        array $auditOptions = [],
    ): array {
        $reason = trim($reason);
        if ($reason === '') {
            throw new RuntimeException('A tenant lifecycle reason is required.');
        }

        return $this->connection->transactional(function () use (
            $tenantId,
            $targetStatus,
            $platformUserId,
            $reason,
            $auditOptions,
        ): array {
            if ($this->connection->getDriver() instanceof Postgres) {
                $this->connection->execute(
                    'SELECT pg_advisory_xact_lock(hashtext(:scope))',
                    ['scope' => sprintf('platform-operation:%s', $tenantId)],
                );
            }
            $tenant = $this->connection->execute(
                'SELECT * FROM tenants WHERE id = :id LIMIT 1',
                ['id' => $tenantId],
            )->fetch('assoc');
            if (!is_array($tenant)) {
                throw new RuntimeException('Tenant was not found.');
            }

            $currentStatus = (string)$tenant['status'];
            if (!in_array($targetStatus, self::ALLOWED_TRANSITIONS[$currentStatus] ?? [], true)) {
                throw new RuntimeException(sprintf(
                    'Tenant lifecycle transition from %s to %s is not allowed.',
                    $currentStatus,
                    $targetStatus,
                ));
            }
            if ($targetStatus === 'active' && empty($tenant['schema_version'])) {
                throw new RuntimeException('Tenant cannot be reactivated until provisioning has completed.');
            }
            $this->assertNoActiveLifecycleJobs($tenantId);

            $now = DateTime::now('UTC')->format('Y-m-d H:i:s');
            $fields = [
                'status' => $targetStatus,
                'modified_at' => $now,
            ];
            if ($targetStatus === 'suspended') {
                $fields['suspended_at'] = $now;
            } elseif ($targetStatus === 'active') {
                $fields['activated_at'] = $tenant['activated_at'] ?: $now;
                $fields['suspended_at'] = null;
            } elseif ($targetStatus === 'archived') {
                $fields['archived_at'] = $now;
            }

            $this->connection->update('tenants', $fields, ['id' => $tenantId]);
            ($this->auditService ?? new PlatformAuditService($this->connection))->record(
                'tenant.' . $targetStatus,
                $platformUserId,
                'tenant',
                $tenantId,
                $reason,
                [
                    'slug' => (string)$tenant['slug'],
                    'previous_status' => $currentStatus,
                    'new_status' => $targetStatus,
                ],
                false,
                $auditOptions + ['tenantId' => $tenantId],
            );

            TenantHostResolver::clearCache();

            return array_merge($tenant, $fields);
        });
    }

    /**
     * Ensure tenant state cannot change underneath an executable operation.
     */
    private function assertNoActiveLifecycleJobs(string $tenantId): void
    {
        $activeJobs = (int)$this->connection->execute(
            "SELECT COUNT(*)
               FROM platform_jobs
              WHERE tenant_id = :tenantId
                AND status IN ('queued', 'running')
                AND job_type IN ('tenant_provision', 'tenant_backup', 'tenant_restore')",
            ['tenantId' => $tenantId],
        )->fetchColumn(0);
        if ($activeJobs > 0) {
            throw new RuntimeException('Tenant lifecycle cannot change while an operation is queued or running.');
        }
    }
}
