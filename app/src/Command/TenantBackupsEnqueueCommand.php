<?php
declare(strict_types=1);

namespace App\Command;

use App\Services\Platform\PlatformAdminJobEnqueuer;
use App\Services\Platform\PlatformAuditService;
use App\Services\Platform\PlatformBackupPolicyService;
use App\Services\Platform\PlatformJobRunner;
use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\Database\Connection;
use Cake\Datasource\ConnectionManager;
use Cake\I18n\DateTime;
use RuntimeException;

/**
 * Enqueues managed tenant backups for every active tenant whose latest
 * completed backup is older than the global backup-policy cadence.
 *
 * This is the single scheduled entry point for tenant backup creation;
 * it runs from the `tenant-backup-fleet` platform schedule.
 */
final class TenantBackupsEnqueueCommand extends Command
{
    /**
     * @inheritDoc
     */
    public static function defaultName(): string
    {
        return 'tenant_backups_enqueue';
    }

    /**
     * @inheritDoc
     */
    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        return parent::buildOptionParser($parser)
            ->setDescription('Enqueue managed backups for active tenants that are due per the global backup policy.');
    }

    /**
     * @inheritDoc
     */
    public function execute(Arguments $args, ConsoleIo $io): int
    {
        try {
            $platform = ConnectionManager::get('platform');
            if (!$platform instanceof Connection) {
                throw new RuntimeException('Platform database connection is unavailable.');
            }
        } catch (RuntimeException $exception) {
            $io->err($exception->getMessage());

            return self::CODE_ERROR;
        }

        $policy = new PlatformBackupPolicyService($platform);
        $retentionDays = $policy->retentionDays();
        $dueBefore = DateTime::now('UTC')
            ->subHours($policy->cadenceHours())
            ->format('Y-m-d H:i:s');

        $tenants = $platform->execute(
            "SELECT id, slug FROM tenants WHERE status = 'active' ORDER BY slug ASC",
        )->fetchAll('assoc');
        $latestCompleted = $this->latestCompletedByTenant($platform);
        $enqueuer = new PlatformAdminJobEnqueuer($platform, new PlatformAuditService($platform));

        $enqueued = 0;
        $fresh = 0;
        $skipped = 0;
        $failed = 0;
        foreach ($tenants as $tenant) {
            $tenantId = (string)$tenant['id'];
            $slug = (string)$tenant['slug'];
            $latest = $latestCompleted[$tenantId] ?? null;
            if ($latest !== null && $latest >= $dueBefore) {
                $fresh++;
                continue;
            }

            try {
                $enqueuer->enqueue(
                    PlatformJobRunner::JOB_TENANT_BACKUP,
                    $tenantId,
                    null,
                    [
                        'tenant_slug' => $slug,
                        'retention_days' => $retentionDays,
                        'initiator' => 'schedule',
                    ],
                    sprintf('tenant_backup_scheduled:%s:%s', $slug, gmdate('Ymd')),
                    'Scheduled fleet backup',
                );
                $enqueued++;
            } catch (RuntimeException $exception) {
                // Another lifecycle operation in flight is expected fleet noise;
                // anything else counts against the exit code but must not stop the sweep.
                if (str_contains($exception->getMessage(), 'already queued or running')) {
                    $skipped++;
                    continue;
                }
                $failed++;
                $io->err(sprintf('Tenant "%s": %s', $slug, $exception->getMessage()));
            }
        }

        $io->out(sprintf(
            'Fleet backups: %d enqueued, %d fresh, %d skipped (operation in flight), %d failed.',
            $enqueued,
            $fresh,
            $skipped,
            $failed,
        ));

        return $failed > 0 ? self::CODE_ERROR : self::CODE_SUCCESS;
    }

    /**
     * Latest completed backup timestamp per tenant.
     *
     * @return array<string, string>
     */
    private function latestCompletedByTenant(Connection $platform): array
    {
        $rows = $platform->execute(
            "SELECT tenant_id, MAX(completed_at) AS latest_completed_at
               FROM tenant_backups
              WHERE status = 'completed'
           GROUP BY tenant_id",
        )->fetchAll('assoc');

        $latest = [];
        foreach ($rows as $row) {
            $latest[(string)$row['tenant_id']] = (string)$row['latest_completed_at'];
        }

        return $latest;
    }
}
