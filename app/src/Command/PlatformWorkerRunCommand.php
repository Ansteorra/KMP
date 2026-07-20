<?php
declare(strict_types=1);

namespace App\Command;

use App\Services\Platform\PlatformQueueDrainService;
use App\Services\Platform\PlatformWorkerService;
use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\Database\Connection;
use Cake\Database\Driver\Postgres;
use Cake\Datasource\ConnectionManager;
use RuntimeException;

/**
 * Runs one bounded platform schedule and queue worker cycle.
 */
class PlatformWorkerRunCommand extends Command
{
    private const ADVISORY_LOCK_KEY = 'kmp:platform-worker';

    /**
     * @param \App\Services\Platform\PlatformWorkerService|null $workerService Worker orchestrator
     * @param \Cake\Database\Connection|null $platformConnection Optional platform connection override
     */
    public function __construct(
        private readonly ?PlatformWorkerService $workerService = null,
        private readonly ?Connection $platformConnection = null,
    ) {
    }

    /**
     * @inheritDoc
     */
    public static function defaultName(): string
    {
        return 'platform worker run';
    }

    /**
     * @inheritDoc
     */
    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        return parent::buildOptionParser($parser)
            ->setDescription('Run one bounded platform schedule and queue worker cycle.')
            ->addOption('schedule-limit', [
                'help' => 'Maximum due schedules inspected.',
                'default' => '100',
            ])
            ->addOption('max-jobs', [
                'help' => 'Maximum Queue plugin jobs attempted per datasource.',
                'default' => '100',
            ])
            ->addOption('max-runtime', [
                'help' => 'Maximum Queue plugin runtime per datasource in seconds.',
                'default' => '45',
            ])
            ->addOption('cycle-budget', [
                'help' => 'Overall default and tenant queue budget in seconds.',
                'default' => (string)PlatformQueueDrainService::DEFAULT_CYCLE_BUDGET_SECONDS,
            ])
            ->addOption('platform-limit', [
                'help' => 'Maximum queued platform jobs claimed per worker cycle.',
                'default' => '1',
            ])
            ->addOption('json', [
                'help' => 'Emit a machine-readable JSON summary.',
                'boolean' => true,
                'default' => false,
            ])
            ->addOption('fail-on-overlap', [
                'help' => 'Return an error when another worker owns the global lock.',
                'boolean' => true,
                'default' => false,
            ]);
    }

    /**
     * @inheritDoc
     */
    public function execute(Arguments $args, ConsoleIo $io): int
    {
        if ($this->workerService === null) {
            throw new RuntimeException('Platform worker service is not configured.');
        }

        $connection = $this->platformConnection ?? ConnectionManager::get('platform');
        if (!$this->acquireWorkerLock($connection)) {
            if ($args->getOption('json')) {
                $io->out('{"overlapSkipped":true}');
            } else {
                $io->out('Another platform worker cycle is active; this execution was skipped.');
            }

            return $args->getOption('fail-on-overlap') ? self::CODE_ERROR : self::CODE_SUCCESS;
        }

        try {
            $result = $this->workerService->run(
                (int)$args->getOption('schedule-limit'),
                (int)$args->getOption('max-jobs'),
                (int)$args->getOption('max-runtime'),
                (int)$args->getOption('cycle-budget'),
                (int)$args->getOption('platform-limit'),
                fn(object|string $command, array $commandArgs): ?int => $this->executeCommand(
                    $command,
                    $commandArgs,
                    $io,
                ),
            );
            $result['overlapSkipped'] = false;

            if ($args->getOption('json')) {
                $io->out((string)json_encode($result, JSON_THROW_ON_ERROR));
            } else {
                $io->out(sprintf(
                    'Worker cycle: %d schedule(s), %d datasource(s), %d queue job(s), '
                    . '%d platform job(s), %d failure(s), %.2f ms.',
                    $result['summary']['schedulesDispatched'],
                    $result['summary']['datasourcesProcessed'],
                    $result['summary']['queueJobsProcessed'],
                    $result['summary']['platformJobsCompleted'],
                    $this->failureCount($result),
                    $result['elapsedMs'],
                ));
            }

            return $this->failureCount($result) > 0 ? self::CODE_ERROR : self::CODE_SUCCESS;
        } finally {
            $this->releaseWorkerLock($connection);
        }
    }

    /**
     * @param array<string, mixed> $result Worker result
     */
    private function failureCount(array $result): int
    {
        return (int)$result['schedules']['failed']
            + count($result['queues']['failures'])
            + (int)$result['platformJobs']['failed']
            + count($result['errors']);
    }

    /**
     * Acquire the process-wide worker lock when PostgreSQL is available.
     */
    private function acquireWorkerLock(Connection $connection): bool
    {
        if (!$connection->getDriver() instanceof Postgres) {
            return true;
        }

        $row = $connection->execute(
            'SELECT pg_try_advisory_lock(hashtext(:lockKey)) AS acquired',
            ['lockKey' => self::ADVISORY_LOCK_KEY],
        )->fetch('assoc');

        return filter_var($row['acquired'] ?? false, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Release the process-wide worker lock when PostgreSQL is available.
     */
    private function releaseWorkerLock(Connection $connection): void
    {
        if (!$connection->getDriver() instanceof Postgres) {
            return;
        }

        $connection->execute(
            'SELECT pg_advisory_unlock(hashtext(:lockKey))',
            ['lockKey' => self::ADVISORY_LOCK_KEY],
        );
    }
}
