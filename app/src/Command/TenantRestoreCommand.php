<?php
declare(strict_types=1);

// phpcs:disable CakePHP.Commenting.FunctionComment.Missing

namespace App\Command;

use App\Services\Backups\BackupStorageFactory;
use App\Services\Backups\JsonTenantBackupRestorer;
use App\Services\Backups\PgRestoreTenantBackupRestorer;
use App\Services\Backups\TenantBackupEncryptor;
use App\Services\Backups\TenantRestoreService;
use App\Services\Secrets\SecretStoreFactory;
use App\Services\TenantConnectionManager;
use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\Datasource\ConnectionManager;
use RuntimeException;

class TenantRestoreCommand extends Command
{
    public static function defaultName(): string
    {
        return 'tenant restore';
    }

    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        return parent::buildOptionParser($parser)
            ->setDescription('Restore an encrypted tenant JSON logical backup.')
            ->addOption('backup', [
                'help' => 'Tenant backup UUID from tenant_backups.',
                'required' => true,
            ])
            ->addOption('mode', [
                'help' => 'Restore mode: same-tenant or cross-tenant.',
                'default' => TenantRestoreService::MODE_SAME_TENANT,
            ])
            ->addOption('target-tenant', [
                'help' => 'Target tenant slug. Required for cross-tenant restores.',
            ])
            ->addOption('confirm-destructive', [
                'help' => 'Required for any destructive (non-dry-run) restore.',
                'boolean' => true,
                'default' => false,
            ])
            ->addOption('dry-run', [
                'help' => 'Validate metadata, secrets, decryption, JSON payload, and target without changing data.',
                'boolean' => true,
                'default' => false,
            ])
            ->addOption('platform-job-id', [
                'help' => 'Existing Platform Admin job UUID to update.',
            ]);
    }

    public function execute(Arguments $args, ConsoleIo $io): int
    {
        try {
            $service = $this->buildService($io);
            $result = $service->restoreTenantBackup(
                (string)$args->getOption('backup'),
                (string)$args->getOption('mode'),
                $args->getOption('target-tenant') === null ? null : (string)$args->getOption('target-tenant'),
                (bool)$args->getOption('confirm-destructive'),
                (bool)$args->getOption('dry-run'),
                $args->getOption('platform-job-id') === null
                    ? null
                    : (string)$args->getOption('platform-job-id'),
            );
            $io->success(sprintf(
                'Tenant restore %s: %s backup=%s mode=%s source=%s target=%s job=%s',
                $result->dryRun ? 'planned' : 'completed',
                $result->status,
                $result->backupId,
                $result->mode,
                $result->sourceTenantSlug,
                $result->targetTenantSlug,
                $result->jobId,
            ));

            return self::CODE_SUCCESS;
        } catch (RuntimeException $e) {
            $io->err($e->getMessage());

            return self::CODE_ERROR;
        }
    }

    private function buildService(ConsoleIo $io): TenantRestoreService
    {
        /** @var \Cake\Database\Connection $platform */
        $platform = ConnectionManager::get('platform');
        $secretStore = SecretStoreFactory::fromConfig();
        $tenantConnections = new TenantConnectionManager($secretStore);
        $migrationRunner = static function () use ($io): void {
            $exitCode = (new UpdateDatabaseCommand())->run(
                ['--connection', TenantConnectionManager::CONNECTION_ALIAS, '--no-lock'],
                $io,
            );
            if ($exitCode !== null && $exitCode !== Command::CODE_SUCCESS) {
                throw new RuntimeException('Tenant migrations failed during JSON restore.');
            }
        };

        return new TenantRestoreService(
            $platform,
            $secretStore,
            BackupStorageFactory::tenant(),
            new TenantBackupEncryptor(),
            new JsonTenantBackupRestorer($tenantConnections, $migrationRunner),
            new PgRestoreTenantBackupRestorer(),
        );
    }
}
