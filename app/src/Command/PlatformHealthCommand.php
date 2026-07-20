<?php
declare(strict_types=1);

namespace App\Command;

use App\Services\Platform\PlatformHealthService;
use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\Core\Configure;

/**
 * Reports platform metadata database health for operators.
 */
class PlatformHealthCommand extends Command
{
    /**
     * @inheritDoc
     */
    public static function defaultName(): string
    {
        return 'platform_health';
    }

    /**
     * @inheritDoc
     */
    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        return parent::buildOptionParser($parser)
            ->setDescription('Check platform metadata database availability.')
            ->addOption('json', [
                'help' => 'Emit diagnostics-safe JSON.',
                'boolean' => true,
            ]);
    }

    /**
     * @inheritDoc
     */
    public function execute(Arguments $args, ConsoleIo $io): int
    {
        $service = new PlatformHealthService(
            'platform',
            (int)Configure::read('Platform.health.retryAttempts', 0),
            (int)Configure::read('Platform.health.retryDelayMs', 0),
        );
        $status = $service->check();
        $safeStatus = $status->toSafeArray();

        if ($args->getOption('json')) {
            $io->out((string)json_encode($safeStatus, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $io->out(sprintf('Platform metadata database: %s', $safeStatus['state']));
            $io->out((string)$safeStatus['message']);
            if (!$status->isHealthy()) {
                $io->err(sprintf('Degraded reason: %s', (string)$safeStatus['error_class']));
            }
        }

        return $status->isHealthy() ? self::CODE_SUCCESS : self::CODE_ERROR;
    }
}
