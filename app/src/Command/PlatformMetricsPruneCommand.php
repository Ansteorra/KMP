<?php
declare(strict_types=1);

namespace App\Command;

use App\Services\Platform\TenantOperationalMetricsService;
use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\Database\Connection;
use Cake\Datasource\ConnectionManager;
use InvalidArgumentException;
use RuntimeException;

final class PlatformMetricsPruneCommand extends Command
{
    /**
     * @inheritDoc
     */
    public static function defaultName(): string
    {
        return 'platform metrics prune';
    }

    /**
     * @inheritDoc
     */
    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        return parent::buildOptionParser($parser)
            ->setDescription('Delete expired hourly tenant request aggregates.')
            ->addOption('retention-days', [
                'help' => 'Number of days of hourly metrics to retain.',
                'default' => (string)TenantOperationalMetricsService::DEFAULT_RETENTION_DAYS,
            ]);
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
            $deleted = (new TenantOperationalMetricsService($platform))->prune(
                (int)$args->getOption('retention-days'),
            );
        } catch (InvalidArgumentException | RuntimeException $exception) {
            $io->err($exception->getMessage());

            return self::CODE_ERROR;
        }

        $io->out(sprintf('Deleted %d expired tenant metric aggregates.', $deleted));

        return self::CODE_SUCCESS;
    }
}
