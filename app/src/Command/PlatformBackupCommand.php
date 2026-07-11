<?php
declare(strict_types=1);

// phpcs:disable CakePHP.Commenting.FunctionComment.Missing, Generic.PHP.NoSilencedErrors.Discouraged

namespace App\Command;

use App\Services\Backups\BackupStorageFactory;
use App\Services\Backups\PgDumpPlatformDatabaseBackupDumper;
use App\Services\Backups\PlatformDatabaseBackupEncryptor;
use App\Services\Backups\PlatformDatabaseBackupService;
use App\Services\Secrets\SecretStoreFactory;
use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
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
            ])
            ->addOption('platform-job-id', [
                'help' => 'Existing Platform Admin job UUID to update.',
            ]);
    }

    public function execute(Arguments $args, ConsoleIo $io): int
    {
        try {
            $service = $this->buildService();
            $result = $service->backupPlatformDatabase(
                (int)$args->getOption('retention-days'),
                $args->getOption('platform-job-id') === null
                    ? null
                    : (string)$args->getOption('platform-job-id'),
            );
            $io->success(sprintf('Platform database backup completed: %s (%s)', $result->backupId, $result->objectUri));

            return self::CODE_SUCCESS;
        } catch (RuntimeException $e) {
            $io->err($e->getMessage());

            return self::CODE_ERROR;
        }
    }

    private function buildService(): PlatformDatabaseBackupService
    {
        /** @var \Cake\Database\Connection $platform */
        $platform = ConnectionManager::get('platform');

        return new PlatformDatabaseBackupService(
            $platform,
            (array)ConnectionManager::getConfig('platform'),
            SecretStoreFactory::fromConfig(),
            new PgDumpPlatformDatabaseBackupDumper(),
            new PlatformDatabaseBackupEncryptor(),
            BackupStorageFactory::platform(),
        );
    }
}
