<?php
declare(strict_types=1);

namespace App\Services\Platform;

use App\KMP\TenantContext;
use Cake\Console\CommandInterface;
use Cake\Console\ConsoleIo;
use Cake\Core\ContainerInterface;
use Closure;
use InvalidArgumentException;
use Psr\Log\NullLogger;
use Queue\Console\Io;
use Queue\Queue\Processor;
use RuntimeException;

/**
 * Runs a bounded queue worker against the active tenant connection.
 */
class TenantQueueDrainService
{
    public const DEFAULT_MAX_JOBS = 25;
    public const MAX_JOBS = 100;
    public const DEFAULT_MAX_RUNTIME_SECONDS = 45;
    public const MAX_RUNTIME_SECONDS = 55;

    private readonly Closure $processorFactory;

    /**
     * @param \Cake\Core\ContainerInterface $container Application service container
     * @param \Closure|null $processorFactory Optional processor factory for tests
     */
    public function __construct(
        private readonly ContainerInterface $container,
        ?Closure $processorFactory = null,
    ) {
        $this->processorFactory = $processorFactory ?? static fn(
            Io $io,
            NullLogger $logger,
            ContainerInterface $container,
        ): Processor => new Processor($io, $logger, $container);
    }

    /**
     * Drain due jobs from the active tenant database.
     *
     * @return int Number of jobs attempted
     */
    public function drain(
        int $maxJobs = self::DEFAULT_MAX_JOBS,
        int $maxRuntimeSeconds = self::DEFAULT_MAX_RUNTIME_SECONDS,
    ): int {
        $tenant = TenantContext::current();
        if ($maxJobs < 1 || $maxJobs > self::MAX_JOBS) {
            throw new InvalidArgumentException(sprintf(
                'Tenant queue max jobs must be between 1 and %d.',
                self::MAX_JOBS,
            ));
        }
        if ($maxRuntimeSeconds < 1 || $maxRuntimeSeconds > self::MAX_RUNTIME_SECONDS) {
            throw new InvalidArgumentException(sprintf(
                'Tenant queue max runtime must be between 1 and %d seconds.',
                self::MAX_RUNTIME_SECONDS,
            ));
        }

        $consoleIo = new ConsoleIo();
        $consoleIo->level(ConsoleIo::QUIET);
        $consoleIo->setInteractive(false);
        $processor = ($this->processorFactory)(
            new Io($consoleIo),
            new NullLogger(),
            $this->container,
        );
        if (!$processor instanceof Processor) {
            throw new RuntimeException('Tenant queue processor factory returned an invalid processor.');
        }

        $exitCode = $processor->run([
            'max-jobs' => $maxJobs,
            'max-runtime' => $maxRuntimeSeconds,
            'exit-when-empty' => true,
            'quiet' => true,
        ]);
        if ($exitCode !== CommandInterface::CODE_SUCCESS) {
            throw new RuntimeException(sprintf(
                'Tenant queue worker failed for tenant "%s" with exit code %d.',
                $tenant->slug,
                $exitCode,
            ));
        }

        return $processor->getProcessedJobs();
    }
}
