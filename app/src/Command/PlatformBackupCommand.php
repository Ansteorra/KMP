<?php
declare(strict_types=1);

// phpcs:disable CakePHP.Commenting.FunctionComment.Missing, Generic.PHP.NoSilencedErrors.Discouraged

namespace App\Command;

use App\Services\Backups\LocalPlatformDatabaseBackupStorage;
use App\Services\Backups\PgDumpPlatformDatabaseBackupDumper;
use App\Services\Backups\PlatformDatabaseBackupEncryptor;
use App\Services\Backups\PlatformDatabaseBackupService;
use App\Services\Secrets\SecretStoreFactory;
use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\Core\Configure;
use Cake\Datasource\ConnectionManager;
use RuntimeException;

class PlatformBackupCommand extends Command
{
    public static function defaultName(): string
    {
        return 'platform backup';
    }

    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        return parent::buildOptionParser($parser)
            ->setDescription('Create an encrypted pg_dump backup for the platform metadata database.')
            ->addOption('retention-days', [
                'help' => 'Retention period for the backup metadata.',
                'default' => '30',
            ]);
    }

    public function execute(Arguments $args, ConsoleIo $io): int
    {
        try {
            $service = $this->buildService();
            $result = $service->backupPlatformDatabase((int)$args->getOption('retention-days'));
            $io->success(sprintf('Platform database backup completed: %s (%s)', $result->backupId, $result->objectUri));

            return self::CODE_SUCCESS;
        } catch (RuntimeException $e) {
            $io->err($e->getMessage());

            return self::CODE_ERROR;
        }
    }

    private function buildService(): PlatformDatabaseBackupService
    {
        $enabled = (bool)Configure::read('PlatformBackups.local.enabled', false);
        $root = (string)Configure::read('PlatformBackups.local.path', TMP . 'platform-backups');
        if (env('KMP_PLATFORM_LOCAL_BACKUPS_ENABLED', null) !== null) {
            $enabled = filter_var(env('KMP_PLATFORM_LOCAL_BACKUPS_ENABLED', false), FILTER_VALIDATE_BOOLEAN);
        }
        $configuredRoot = env('KMP_PLATFORM_LOCAL_BACKUPS_PATH', null);
        if (is_string($configuredRoot) && $configuredRoot !== '') {
            $root = $configuredRoot;
        }
        /** @var \Cake\Database\Connection $platform */
        $platform = ConnectionManager::get('platform');

        return new PlatformDatabaseBackupService(
            $platform,
            (array)ConnectionManager::getConfig('platform'),
            SecretStoreFactory::fromConfig(),
            new PgDumpPlatformDatabaseBackupDumper(),
            new PlatformDatabaseBackupEncryptor(),
            new LocalPlatformDatabaseBackupStorage(
                $root,
                $enabled,
                (string)env('KMP_ENV', env('APP_ENV', 'production')),
            ),
        );
    }
}
