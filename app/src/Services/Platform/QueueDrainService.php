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
 * Runs bounded queue workers against the current default datasource.
 */
class QueueDrainService
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
     * Drain due jobs from the application's default datasource.
     */
    public function drainDefault(
        int $maxJobs = self::DEFAULT_MAX_JOBS,
        int $maxRuntimeSeconds = self::DEFAULT_MAX_RUNTIME_SECONDS,
    ): int {
        return $this->drain('default', $maxJobs, $maxRuntimeSeconds);
    }

    /**
     * Drain due jobs from the active tenant datasource.
     */
    public function drainTenant(
        int $maxJobs = self::DEFAULT_MAX_JOBS,
        int $maxRuntimeSeconds = self::DEFAULT_MAX_RUNTIME_SECONDS,
    ): int {
        return $this->drain('tenant "' . TenantContext::current()->slug . '"', $maxJobs, $maxRuntimeSeconds);
    }

    /**
     * Drain the datasource currently aliased as `default`.
     */
    private function drain(string $queueName, int $maxJobs, int $maxRuntimeSeconds): int
    {
        if ($maxJobs < 1 || $maxJobs > self::MAX_JOBS) {
            throw new InvalidArgumentException(sprintf(
                'Queue max jobs must be between 1 and %d.',
                self::MAX_JOBS,
            ));
        }
        if ($maxRuntimeSeconds < 1 || $maxRuntimeSeconds > self::MAX_RUNTIME_SECONDS) {
            throw new InvalidArgumentException(sprintf(
                'Queue max runtime must be between 1 and %d seconds.',
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
            throw new RuntimeException('Queue processor factory returned an invalid processor.');
        }

        $exitCode = $processor->run([
            'max-jobs' => $maxJobs,
            'max-runtime' => $maxRuntimeSeconds,
            'exit-when-empty' => true,
            'quiet' => true,
        ]);
        if ($exitCode !== CommandInterface::CODE_SUCCESS) {
            throw new RuntimeException(sprintf(
                'Queue worker failed for %s datasource with exit code %d.',
                $queueName,
                $exitCode,
            ));
        }

        return $processor->getProcessedJobs();
    }
}
