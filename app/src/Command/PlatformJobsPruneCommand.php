<?php
declare(strict_types=1);

namespace App\Command;

use App\Services\Platform\PlatformJobRetentionService;
use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\Database\Connection;
use Cake\Datasource\ConnectionManager;
use InvalidArgumentException;
use RuntimeException;

final class PlatformJobsPruneCommand extends Command
{
    /**
     * @inheritDoc
     */
    public static function defaultName(): string
    {
        return 'platform jobs prune';
    }

    /**
     * @inheritDoc
     */
    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        return parent::buildOptionParser($parser)
            ->setDescription('Delete expired terminal platform jobs and their event records.')
            ->addOption('schedule-days', [
                'default' => (string)PlatformJobRetentionService::DEFAULT_SCHEDULE_DAYS,
            ])
            ->addOption('completed-days', [
                'default' => (string)PlatformJobRetentionService::DEFAULT_COMPLETED_DAYS,
            ])
            ->addOption('failed-days', [
                'default' => (string)PlatformJobRetentionService::DEFAULT_FAILED_DAYS,
            ])
            ->addOption('limit', [
                'default' => (string)PlatformJobRetentionService::DEFAULT_LIMIT,
            ]);
    }

    /**
     * @inheritDoc
     */
    public function execute(Arguments $args, ConsoleIo $io): int
    {
        try {
            $connection = ConnectionManager::get('platform');
            if (!$connection instanceof Connection) {
                throw new RuntimeException('Platform database connection is unavailable.');
            }
            $result = (new PlatformJobRetentionService($connection))->prune(
                (int)$args->getOption('schedule-days'),
                (int)$args->getOption('completed-days'),
                (int)$args->getOption('failed-days'),
                (int)$args->getOption('limit'),
            );
        } catch (InvalidArgumentException | RuntimeException $exception) {
            $io->err($exception->getMessage());

            return self::CODE_ERROR;
        }

        $io->out(sprintf(
            'Deleted %d completed schedule job(s), %d completed operational job(s), and %d failed job(s).',
            $result['schedule_completed'],
            $result['completed'],
            $result['failed'],
        ));

        return self::CODE_SUCCESS;
    }
}
