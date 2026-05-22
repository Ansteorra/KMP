<?php
declare(strict_types=1);

// phpcs:disable CakePHP.Commenting.FunctionComment.Missing

namespace App\Command;

use App\Services\Backups\LocalTenantBackupStorage;
use App\Services\Backups\PgRestoreTenantBackupRestorer;
use App\Services\Backups\TenantBackupEncryptor;
use App\Services\Backups\TenantRestoreService;
use App\Services\Secrets\SecretStoreFactory;
use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\Core\Configure;
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
            ->setDescription('Restore an encrypted tenant pg_dump backup.')
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
                'help' => 'Required for destructive same-tenant restores.',
                'boolean' => true,
                'default' => false,
            ])
            ->addOption('dry-run', [
                'help' => 'Validate metadata, secrets, decryption, and restore argv without executing pg_restore.',
                'boolean' => true,
                'default' => false,
            ]);
    }

    public function execute(Arguments $args, ConsoleIo $io): int
    {
        try {
            $service = $this->buildService();
            $result = $service->restoreTenantBackup(
                (string)$args->getOption('backup'),
                (string)$args->getOption('mode'),
                $args->getOption('target-tenant') === null ? null : (string)$args->getOption('target-tenant'),
                (bool)$args->getOption('confirm-destructive'),
                (bool)$args->getOption('dry-run'),
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

    private function buildService(): TenantRestoreService
    {
        $enabled = (bool)Configure::read('TenantBackups.local.enabled', false);
        $root = (string)Configure::read('TenantBackups.local.path', TMP . 'backups');
        if (env('KMP_LOCAL_BACKUPS_ENABLED', null) !== null) {
            $enabled = filter_var(env('KMP_LOCAL_BACKUPS_ENABLED', false), FILTER_VALIDATE_BOOLEAN);
        }
        $configuredRoot = env('KMP_LOCAL_BACKUPS_PATH', null);
        if (is_string($configuredRoot) && $configuredRoot !== '') {
            $root = $configuredRoot;
        }
        /** @var \Cake\Database\Connection $platform */
        $platform = ConnectionManager::get('platform');

        return new TenantRestoreService(
            $platform,
            SecretStoreFactory::fromConfig(),
            new LocalTenantBackupStorage($root, $enabled),
            new TenantBackupEncryptor(),
            new PgRestoreTenantBackupRestorer(),
        );
    }
}
