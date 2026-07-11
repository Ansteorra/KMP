<?php
declare(strict_types=1);

// phpcs:disable CakePHP.Commenting.FunctionComment.Missing, Generic.PHP.NoSilencedErrors.Discouraged

namespace App\Command;

use App\Services\Backups\BackupStorageFactory;
use App\Services\Backups\JsonTenantBackupDumper;
use App\Services\Backups\TenantBackupEncryptor;
use App\Services\Backups\TenantBackupService;
use App\Services\Secrets\SecretStoreFactory;
use App\Services\TenantConnectionManager;
use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
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
            ->setDescription('Create an encrypted JSON logical backup for a tenant.')
            ->addOption('tenant', [
                'help' => 'Tenant slug to back up.',
                'required' => true,
            ])
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
            $result = $service->backupTenant(
                (string)$args->getOption('tenant'),
                (int)$args->getOption('retention-days'),
                $args->getOption('platform-job-id') === null
                    ? null
                    : (string)$args->getOption('platform-job-id'),
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
        /** @var \Cake\Database\Connection $platform */
        $platform = ConnectionManager::get('platform');
        $secretStore = SecretStoreFactory::fromConfig();

        return new TenantBackupService(
            $platform,
            $secretStore,
            new JsonTenantBackupDumper(new TenantConnectionManager($secretStore)),
            new TenantBackupEncryptor(),
            BackupStorageFactory::tenant(),
        );
    }
}
