<?php
declare(strict_types=1);

// phpcs:disable CakePHP.Commenting.FunctionComment.Missing

namespace App\Command;

use App\Services\Backups\BackupStorageFactory;
use App\Services\Backups\JsonTenantBackupRestorer;
use App\Services\Backups\PgRestoreTenantBackupRestorer;
use App\Services\Backups\TenantBackupEncryptor;
use App\Services\Backups\TenantRestoreDrillService;
use App\Services\Backups\TenantRestoreService;
use App\Services\Backups\TenantRestoreServiceDrillVerifier;
use App\Services\Secrets\SecretStoreFactory;
use App\Services\TenantConnectionManager;
use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\Datasource\ConnectionManager;
use RuntimeException;

class TenantRestoreDrillCommand extends Command
{
    private const DESTRUCTIVE_CONFIRMATION = 'RESTORE-DRILL-DESTRUCTIVE';

    public static function defaultName(): string
    {
        return 'tenant restore_drill';
    }

    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        return parent::buildOptionParser($parser)
            ->setDescription('Plan and record a tenant restore drill from a recent successful backup.')
            ->addOption('tenant', [
                'help' => 'Optional tenant slug to drill. Defaults to the most recent completed backup across tenants.',
            ])
            ->addOption('lookback-hours', [
                'help' => 'Only consider completed backups newer than this many hours.',
                'default' => '36',
            ])
            ->addOption('execute-restore', [
                'help' => 'Execute a same-tenant restore instead of the non-destructive dry-run plan.',
                'boolean' => true,
                'default' => false,
            ])
            ->addOption('confirm-destructive-drill', [
                'help' => 'Required value for --execute-restore: ' . self::DESTRUCTIVE_CONFIRMATION,
            ]);
    }

    public function execute(Arguments $args, ConsoleIo $io): int
    {
        try {
            $executeRestore = (bool)$args->getOption('execute-restore');
            $confirmation = (string)($args->getOption('confirm-destructive-drill') ?? '');
            if ($executeRestore && $confirmation !== self::DESTRUCTIVE_CONFIRMATION) {
                throw new RuntimeException(
                    'Destructive restore drill execution requires --confirm-destructive-drill='
                    . self::DESTRUCTIVE_CONFIRMATION,
                );
            }
            $service = $this->buildService($io);
            $result = $service->planRecentDrill(
                $args->getOption('tenant') === null ? null : (string)$args->getOption('tenant'),
                (int)$args->getOption('lookback-hours'),
                $executeRestore,
                $confirmation === self::DESTRUCTIVE_CONFIRMATION,
            );
            $io->success(sprintf(
                'Tenant restore drill %s: tenant=%s backup=%s job=%s mode=%s',
                $result->status,
                $result->tenantSlug,
                $result->backupId,
                $result->jobId,
                $result->dryRun ? 'dry-run' : 'destructive-execute',
            ));

            return self::CODE_SUCCESS;
        } catch (RuntimeException $e) {
            $io->err($e->getMessage());

            return self::CODE_ERROR;
        }
    }

    private function buildService(ConsoleIo $io): TenantRestoreDrillService
    {
        /** @var \Cake\Database\Connection $platform */
        $platform = ConnectionManager::get('platform');
        $secretStore = SecretStoreFactory::fromConfig();
        $migrationRunner = static function () use ($io): void {
            $exitCode = (new UpdateDatabaseCommand())->run(
                ['--connection', TenantConnectionManager::CONNECTION_ALIAS, '--no-lock'],
                $io,
            );
            if ($exitCode !== null && $exitCode !== Command::CODE_SUCCESS) {
                throw new RuntimeException('Tenant migrations failed during JSON restore drill.');
            }
        };
        $restoreService = new TenantRestoreService(
            $platform,
            $secretStore,
            BackupStorageFactory::tenant(),
            new TenantBackupEncryptor(),
            new JsonTenantBackupRestorer(new TenantConnectionManager($secretStore), $migrationRunner),
            new PgRestoreTenantBackupRestorer(),
        );

        return new TenantRestoreDrillService($platform, new TenantRestoreServiceDrillVerifier($restoreService));
    }
}
