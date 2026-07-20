<?php
declare(strict_types=1);

namespace App\Command;

use App\Services\Platform\PlatformJobRunner;
use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use RuntimeException;

class PlatformJobsRunCommand extends Command
{
    private ?PlatformJobRunner $runner;

    /**
     * @var array{claimed: int, completed: int, failed: int}
     */
    private array $lastResult = ['claimed' => 0, 'completed' => 0, 'failed' => 0];

    /**
     * Constructor.
     */
    public function __construct(mixed $runner = null)
    {
        $this->runner = $runner instanceof PlatformJobRunner ? $runner : null;
    }

    /**
     * @inheritDoc
     */
    public static function defaultName(): string
    {
        return 'platform jobs run';
    }

    /**
     * @inheritDoc
     */
    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        return parent::buildOptionParser($parser)
            ->setDescription('Run queued executable platform admin jobs.')
            ->addOption('limit', [
                'help' => 'Maximum number of jobs to claim.',
                'default' => '10',
            ]);
    }

    /**
     * @inheritDoc
     */
    public function execute(Arguments $args, ConsoleIo $io): int
    {
        $this->lastResult = ['claimed' => 0, 'completed' => 0, 'failed' => 0];
        try {
            $result = $this->getRunner()->run(
                (int)$args->getOption('limit'),
                fn(object|string $command, array $commandArgs): ?int => $this->executeCommand(
                    $command,
                    $commandArgs,
                    $io,
                ),
            );
        } catch (RuntimeException $exception) {
            $io->err($exception->getMessage());

            return self::CODE_ERROR;
        }
        $this->lastResult = $result;

        $io->out(sprintf(
            'Platform jobs runner claimed %d job(s): %d completed, %d failed.',
            $result['claimed'],
            $result['completed'],
            $result['failed'],
        ));

        return $result['failed'] > 0 ? self::CODE_ERROR : self::CODE_SUCCESS;
    }

    /**
     * Return the most recent execution summary.
     *
     * @return array{claimed: int, completed: int, failed: int}
     */
    public function lastResult(): array
    {
        return $this->lastResult;
    }

    /**
     * Return the configured platform job runner.
     */
    private function getRunner(): PlatformJobRunner
    {
        return $this->runner ?? new PlatformJobRunner();
    }
}
