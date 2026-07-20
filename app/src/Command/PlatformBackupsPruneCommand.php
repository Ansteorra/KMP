<?php
declare(strict_types=1);

namespace App\Command;

use App\Services\Backups\BackupRetentionService;
use App\Services\Backups\BackupStorageFactory;
use App\Services\Platform\PlatformAuditService;
use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\Database\Connection;
use Cake\Datasource\ConnectionManager;
use RuntimeException;

final class PlatformBackupsPruneCommand extends Command
{
    /**
     * @inheritDoc
     */
    public static function defaultName(): string
    {
        return 'platform backups prune';
    }

    /**
     * @inheritDoc
     */
    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        return parent::buildOptionParser($parser)
            ->setDescription('Delete backup objects whose retention period has expired.')
            ->addOption('limit', [
                'help' => 'Maximum tenant and platform objects to process.',
                'default' => '200',
            ]);
    }

    /**
     * @inheritDoc
     */
    public function execute(Arguments $args, ConsoleIo $io): int
    {
        $limit = (int)$args->getOption('limit');
        if ($limit < 1 || $limit > 1000) {
            $io->err('Backup retention limit must be between 1 and 1000.');

            return self::CODE_ERROR;
        }

        try {
            $platform = ConnectionManager::get('platform');
            if (!$platform instanceof Connection) {
                throw new RuntimeException('Platform database connection is unavailable.');
            }
            $result = (new BackupRetentionService(
                $platform,
                BackupStorageFactory::tenant(),
                BackupStorageFactory::platform(),
                BackupStorageFactory::legacy(),
                new PlatformAuditService($platform),
            ))->prune(null, $limit);
        } catch (RuntimeException $exception) {
            $io->err($exception->getMessage());

            return self::CODE_ERROR;
        }

        $io->out(sprintf(
            'Backup retention: %d tenant expired, %d platform expired, %d failed.',
            $result['tenant_expired'],
            $result['platform_expired'],
            $result['failed'],
        ));

        return $result['failed'] > 0 ? self::CODE_ERROR : self::CODE_SUCCESS;
    }
}
