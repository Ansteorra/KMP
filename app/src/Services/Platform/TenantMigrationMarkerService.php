<?php
declare(strict_types=1);

namespace App\Services\Platform;

use App\KMP\TenantMetadata;
use App\Services\Backups\TenantBackupService;
use Cake\Database\Connection;
use Cake\I18n\DateTime;
use Cake\Utility\Text;
use RuntimeException;
use Throwable;

/**
 * Records a pre-migration recovery marker and tags an on-demand logical backup.
 */
class TenantMigrationMarkerService implements TenantMigrationMarkerServiceInterface
{
    private const JOB_TYPE = 'tenant_migration_marker';

    /**
     * Constructor.
     */
    public function __construct(
        private readonly Connection $platformConnection,
        private readonly TenantBackupService $tenantBackupService,
    ) {
    }

    /**
     * @inheritDoc
     */
    public function createMarker(
        TenantMetadata $tenant,
        array $options,
        string $migrationJobId,
    ): TenantMigrationMarkerResult {
        $markerJobId = Text::uuid();
        $now = DateTime::now('UTC');
        $tag = $this->tag($options);
        $metadata = $this->baseMetadata($tenant, $options, $migrationJobId, $tag, $now);
        $this->insertMarkerJob($markerJobId, $tenant, $metadata, $now);

        try {
            $backup = $this->tenantBackupService->backupTenant($tenant->slug, $this->retentionDays($options));
            $this->tagBackupJob($backup->jobId, $tag, $migrationJobId, $markerJobId);
            $metadata['backup'] = [
                'backup_id' => $backup->backupId,
                'backup_job_id' => $backup->jobId,
                'object_uri' => $backup->objectUri,
                'status' => $backup->status,
                'tag' => $tag,
            ];
            $finishedAt = DateTime::now('UTC');
            $this->platformConnection->update('platform_jobs', [
                'status' => 'completed',
                'parameters' => json_encode($metadata, JSON_UNESCAPED_SLASHES),
                'finished_at' => $finishedAt,
                'modified_at' => $finishedAt,
            ], ['id' => $markerJobId]);

            return new TenantMigrationMarkerResult($markerJobId, $backup->backupId, $metadata);
        } catch (Throwable $e) {
            $finishedAt = DateTime::now('UTC');
            $message = TenantMigrateCommandScrubber::scrubString($e->getMessage());
            $this->platformConnection->update('platform_jobs', [
                'status' => 'failed',
                'last_error' => $message,
                'finished_at' => $finishedAt,
                'modified_at' => $finishedAt,
            ], ['id' => $markerJobId]);

            throw new RuntimeException('Pre-migration marker failed: ' . $message, 0, $e);
        }
    }

    /**
     * @param array<string, mixed> $metadata
     */
    private function insertMarkerJob(string $markerJobId, TenantMetadata $tenant, array $metadata, DateTime $now): void
    {
        $this->platformConnection->insert('platform_jobs', [
            'id' => $markerJobId,
            'tenant_id' => $tenant->id,
            'requested_by_platform_user_id' => null,
            'job_type' => self::JOB_TYPE,
            'status' => 'running',
            'idempotency_key' => null,
            'parameters' => json_encode($metadata, JSON_UNESCAPED_SLASHES),
            'log_uri' => null,
            'last_error' => null,
            'created_at' => $now,
            'started_at' => $now,
            'finished_at' => null,
            'modified_at' => $now,
        ]);
    }

    /**
     * Tag the backup job so operators can identify the rollback target.
     */
    private function tagBackupJob(string $backupJobId, string $tag, string $migrationJobId, string $markerJobId): void
    {
        $row = $this->platformConnection->execute(
            'SELECT parameters FROM platform_jobs WHERE id = :id LIMIT 1',
            ['id' => $backupJobId],
        )->fetch('assoc');
        $parameters = [];
        if (is_array($row) && is_string($row['parameters'] ?? null) && $row['parameters'] !== '') {
            $decoded = json_decode($row['parameters'], true);
            $parameters = is_array($decoded) ? $decoded : [];
        }
        $parameters['purpose'] = 'pre_migration';
        $parameters['tag'] = $tag;
        $parameters['migration_job_id'] = $migrationJobId;
        $parameters['migration_marker_job_id'] = $markerJobId;
        $this->platformConnection->update('platform_jobs', [
            'parameters' => json_encode($parameters, JSON_UNESCAPED_SLASHES),
            'modified_at' => DateTime::now('UTC'),
        ], ['id' => $backupJobId]);
    }

    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    private function baseMetadata(
        TenantMetadata $tenant,
        array $options,
        string $migrationJobId,
        string $tag,
        DateTime $now,
    ): array {
        return TenantMigrateCommandScrubber::scrubMetadata([
            'tenant_id' => $tenant->id,
            'tenant_slug' => $tenant->slug,
            'previous_schema_version' => $tenant->schemaVersion,
            'target_schema_version' => $this->targetSchema($options),
            'migration_job_id' => $migrationJobId,
            'marker_timestamp' => $now->format('Y-m-d H:i:s'),
            'backup' => null,
            'tag' => $tag,
        ]);
    }

    /**
     * @param array<string, mixed> $options
     */
    private function tag(array $options): string
    {
        return 'pre-migrate-' . preg_replace('/[^A-Za-z0-9_.-]+/', '-', $this->targetSchema($options));
    }

    /**
     * @param array<string, mixed> $options
     */
    private function targetSchema(array $options): string
    {
        if (!empty($options['target'])) {
            return (string)$options['target'];
        }
        if (!empty($options['date'])) {
            return 'date-' . (string)$options['date'];
        }

        return 'latest';
    }

    /**
     * @param array<string, mixed> $options
     */
    private function retentionDays(array $options): int
    {
        $retentionDays = (int)($options['marker_retention_days'] ?? 30);

        return max(1, $retentionDays);
    }
}
