<?php
declare(strict_types=1);

namespace App\Command;

use App\Services\Platform\AllowlistedPlatformScheduleDispatcher;
use App\Services\Platform\PlatformScheduleRunner;
use App\Services\Secrets\SecretStoreFactory;
use App\Services\TenantConnectionManager;
use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Throwable;

final class PlatformSchedulesRunDueCommand extends Command
{
    private ?PlatformScheduleRunner $runner;

    /**
     * Constructor.
     *
     * @param mixed $runner Optional runner for tests; Cake passes CommandFactory when unregistered.
     */
    public function __construct(mixed $runner = null)
    {
        $this->runner = $runner instanceof PlatformScheduleRunner ? $runner : null;
    }

    /**
     * @inheritDoc
     */
    public static function defaultName(): string
    {
        return 'platform schedule due';
    }

    /**
     * @inheritDoc
     */
    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        return parent::buildOptionParser($parser)
            ->setDescription('Dispatch all platform schedules due in the current minute.')
            ->addOption('limit', [
                'help' => 'Maximum schedules to inspect.',
                'default' => '100',
            ]);
    }

    /**
     * @inheritDoc
     */
    public function execute(Arguments $args, ConsoleIo $io): int
    {
        try {
            $result = $this->getRunner()->runDue((int)$args->getOption('limit'));
        } catch (Throwable $exception) {
            $io->err($exception->getMessage());

            return self::CODE_ERROR;
        }

        $io->out(sprintf(
            'Due schedules: %d dispatched, %d completed, %d failed, %d target jobs.',
            $result['schedules'],
            $result['completed'],
            $result['failed'],
            $result['jobsCreated'],
        ));

        return $result['failed'] > 0 ? self::CODE_ERROR : self::CODE_SUCCESS;
    }

    /**
     * Resolve the schedule runner.
     *
     * @return \App\Services\Platform\PlatformScheduleRunner
     */
    private function getRunner(): PlatformScheduleRunner
    {
        return $this->runner ?? new PlatformScheduleRunner(
            new AllowlistedPlatformScheduleDispatcher(),
            new TenantConnectionManager(SecretStoreFactory::fromConfig()),
        );
    }
}
