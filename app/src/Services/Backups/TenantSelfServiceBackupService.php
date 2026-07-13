<?php
declare(strict_types=1);

namespace App\Services\Backups;

use App\Services\Platform\PlatformAdminJobEnqueuer;
use App\Services\Platform\PlatformAuditService;
use App\Services\Platform\PlatformBackupPolicyService;
use Cake\Database\Connection;
use Cake\I18n\DateTime;
use RuntimeException;

/**
 * Tenant-facing view of the platform-managed backup system.
 *
 * Tenant admins can list their managed backups, see the effective global
 * backup policy, request an on-demand backup, and fetch backup rows for
 * download — but scheduling, retention, and restore remain platform-owned.
 */
class TenantSelfServiceBackupService
{
    /**
     * Minimum minutes between tenant-initiated backups.
     */
    public const MIN_INTERVAL_MINUTES = 60;

    /**
     * Constructor.
     */
    public function __construct(
        private readonly Connection $platformConnection,
        private readonly ?PlatformAdminJobEnqueuer $enqueuer = null,
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listManagedBackups(string $tenantId, int $limit = 25): array
    {
        return $this->platformConnection->execute(
            'SELECT id, backup_type, status, object_uri, object_size_bytes, encryption_algorithm,
                    error_summary, recovery_key_exported_at, created_at, completed_at, retention_until
               FROM tenant_backups
              WHERE tenant_id = :tenantId
           ORDER BY created_at DESC
              LIMIT :limit',
            ['tenantId' => $tenantId, 'limit' => max(1, min(100, $limit))],
            ['limit' => 'integer'],
        )->fetchAll('assoc');
    }

    /**
     * Read-only backup status for the tenant: latest backup + effective policy.
     *
     * @return array<string, mixed>
     */
    public function status(string $tenantId): array
    {
        $policy = new PlatformBackupPolicyService($this->platformConnection);
        $latestCompleted = $this->platformConnection->execute(
            "SELECT id, completed_at, object_size_bytes, retention_until
               FROM tenant_backups
              WHERE tenant_id = :tenantId AND status = 'completed'
           ORDER BY completed_at DESC
              LIMIT 1",
            ['tenantId' => $tenantId],
        )->fetch('assoc');
        $latestCompleted = is_array($latestCompleted) ? $latestCompleted : null;

        $stale = true;
        if ($latestCompleted !== null) {
            $completedAt = strtotime((string)$latestCompleted['completed_at'] . ' UTC');
            $stale = $completedAt === false
                || $completedAt <= time() - $policy->cadenceHours() * 3600;
        }

        return [
            'cadence' => $policy->cadence(),
            'retention_days' => $policy->retentionDays(),
            'latest_completed' => $latestCompleted,
            'stale' => $stale,
        ];
    }

    /**
     * Queue an on-demand managed backup for the tenant.
     *
     * @return array<string, mixed> The queued platform_jobs row
     */
    public function requestBackup(string $tenantId, string $slug, ?string $actorMemberId): array
    {
        $tenant = $this->platformConnection->execute(
            'SELECT id, slug, status FROM tenants WHERE id = :id AND slug = :slug LIMIT 1',
            ['id' => $tenantId, 'slug' => $slug],
        )->fetch('assoc');
        if (!is_array($tenant)) {
            throw new RuntimeException('Tenant was not found.');
        }
        if ((string)$tenant['status'] !== 'active') {
            throw new RuntimeException('Backups can only be requested while the tenant is active.');
        }

        $recent = $this->platformConnection->execute(
            "SELECT id FROM tenant_backups
              WHERE tenant_id = :tenantId
                AND status = 'completed'
                AND completed_at >= :since
              LIMIT 1",
            [
                'tenantId' => $tenantId,
                'since' => DateTime::now('UTC')->subMinutes(self::MIN_INTERVAL_MINUTES),
            ],
            ['since' => 'datetime'],
        )->fetch('assoc');
        if (is_array($recent)) {
            throw new RuntimeException(sprintf(
                'A backup completed within the last %d minutes. Try again later.',
                self::MIN_INTERVAL_MINUTES,
            ));
        }

        $policy = new PlatformBackupPolicyService($this->platformConnection);
        $enqueuer = $this->enqueuer ?? new PlatformAdminJobEnqueuer(
            $this->platformConnection,
            new PlatformAuditService($this->platformConnection),
        );

        return $enqueuer->enqueue(
            TenantBackupService::JOB_TYPE,
            $tenantId,
            null,
            [
                'tenant_slug' => $slug,
                'retention_days' => $policy->retentionDays(),
                'initiator' => 'tenant',
                'requested_by_member' => $actorMemberId,
            ],
            sprintf('tenant_self_backup:%s:%s', $slug, bin2hex(random_bytes(8))),
            'tenant self-service backup request',
            ['tenantId' => $tenantId],
        );
    }

    /**
     * Fetch a backup row for download, asserting tenant ownership.
     *
     * @return array<string, mixed>
     */
    public function getBackupForDownload(string $tenantId, string $backupId): array
    {
        $row = $this->platformConnection->execute(
            'SELECT id, tenant_id, backup_type, status, object_uri, object_size_bytes,
                    object_sha256, encryption_algorithm, wrapped_dek, wrapped_dek_key_name,
                    wrapped_dek_key_version, wrapped_dek_metadata, retention_until,
                    recovery_key_exported_at, recovery_key_exported_by
               FROM tenant_backups
              WHERE tenant_id = :tenantId
                AND id = :backupId
              LIMIT 1',
            ['tenantId' => $tenantId, 'backupId' => $backupId],
        )->fetch('assoc');
        if (!is_array($row)) {
            throw new RuntimeException('Backup was not found.');
        }

        return $row;
    }

    /**
     * Claim the one-time recovery-key export for a backup.
     *
     * Returns false when the key was already exported (the claim is atomic,
     * so concurrent requests cannot both succeed).
     */
    public function claimRecoveryKeyExport(string $backupId, string $exportedBy): bool
    {
        $updated = $this->platformConnection->execute(
            'UPDATE tenant_backups
                SET recovery_key_exported_at = :now,
                    recovery_key_exported_by = :by,
                    modified_at = :now
              WHERE id = :backupId
                AND recovery_key_exported_at IS NULL',
            [
                'now' => DateTime::now('UTC'),
                'by' => mb_substr($exportedBy, 0, 160),
                'backupId' => $backupId,
            ],
            ['now' => 'datetime'],
        )->rowCount();

        return $updated === 1;
    }

    /**
     * The tenant's platform registry row (needed for recovery-key export).
     *
     * @return array<string, mixed>
     */
    public function tenantRow(string $tenantId): array
    {
        $row = $this->platformConnection->execute(
            'SELECT * FROM tenants WHERE id = :id LIMIT 1',
            ['id' => $tenantId],
        )->fetch('assoc');
        if (!is_array($row)) {
            throw new RuntimeException('Tenant was not found.');
        }

        return $row;
    }
}
