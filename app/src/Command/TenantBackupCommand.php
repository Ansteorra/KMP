<?php
declare(strict_types=1);

// phpcs:disable CakePHP.Commenting.FunctionComment.Missing, Generic.PHP.NoSilencedErrors.Discouraged

namespace App\Command;

use App\Services\Backups\LocalTenantBackupStorage;
use App\Services\Backups\PgDumpTenantBackupDumper;
use App\Services\Backups\TenantBackupEncryptor;
use App\Services\Backups\TenantBackupService;
use App\Services\Secrets\SecretStoreFactory;
use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\Core\Configure;
use Cake\Datasource\ConnectionManager;
use RuntimeException;

class TenantBackupCommand extends Command
{
    public static function defaultName(): string
    {
        return 'tenant backup';
    }

    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        return parent::buildOptionParser($parser)
            ->setDescription('Create an encrypted pg_dump backup for a tenant.')
            ->addOption('tenant', [
                'help' => 'Tenant slug to back up.',
                'required' => true,
            ])
            ->addOption('retention-days', [
                'help' => 'Retention period for the backup metadata.',
                'default' => '30',
            ]);
    }

    public function execute(Arguments $args, ConsoleIo $io): int
    {
        try {
            $service = $this->buildService();
            $result = $service->backupTenant(
                (string)$args->getOption('tenant'),
                (int)$args->getOption('retention-days'),
            );
            $io->success(sprintf('Tenant backup completed: %s (%s)', $result->backupId, $result->objectUri));

            return self::CODE_SUCCESS;
        } catch (RuntimeException $e) {
            $io->err($e->getMessage());

            return self::CODE_ERROR;
        }
    }

    private function buildService(): TenantBackupService
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

        return new TenantBackupService(
            $platform,
            SecretStoreFactory::fromConfig(),
            new PgDumpTenantBackupDumper(),
            new TenantBackupEncryptor(),
            new LocalTenantBackupStorage($root, $enabled),
        );
    }
}
