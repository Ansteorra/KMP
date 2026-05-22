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

class PlatformScheduleRunCommand extends Command
{
    private ?PlatformScheduleRunner $runner;

    /**
     * Constructor.
     *
     * @param mixed $runner Optional runner for tests; Cake passes CommandFactory in normal CLI construction.
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
        return 'platform schedule run';
    }

    /**
     * @inheritDoc
     */
    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        return parent::buildOptionParser($parser)
            ->setDescription('Run an enabled platform schedule by name.')
            ->addArgument('name', [
                'help' => 'Unique platform schedule name.',
                'required' => true,
            ]);
    }

    /**
     * @inheritDoc
     */
    public function execute(Arguments $args, ConsoleIo $io): int
    {
        $name = (string)$args->getArgument('name');
        try {
            $result = $this->getRunner()->run($name);
        } catch (Throwable $e) {
            $io->err($e->getMessage());

            return self::CODE_ERROR;
        }

        if ($result['status'] === 'skipped') {
            $io->out(sprintf('Platform schedule "%s" is disabled; no jobs were created.', $name));

            return self::CODE_SUCCESS;
        }

        $io->out(sprintf(
            'Platform schedule "%s" %s: %d completed, %d failed, %d job(s) created.',
            $name,
            $result['status'],
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
