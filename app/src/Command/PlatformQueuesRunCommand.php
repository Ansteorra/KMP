<?php
declare(strict_types=1);

namespace App\Command;

use App\Services\Platform\PlatformJobRunner;
use App\Services\Platform\PlatformQueueDrainService;
use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use RuntimeException;

/**
 * Runs every application queue lane from one background worker authority.
 */
class PlatformQueuesRunCommand extends Command
{
    /**
     * @param \App\Services\Platform\PlatformQueueDrainService|null $queueDrainService Queue fleet processor
     * @param \App\Services\Platform\PlatformJobRunner|null $platformJobRunner Platform job processor
     */
    public function __construct(
        private readonly ?PlatformQueueDrainService $queueDrainService = null,
        private readonly ?PlatformJobRunner $platformJobRunner = null,
    ) {
    }

    /**
     * @inheritDoc
     */
    public static function defaultName(): string
    {
        return 'platform queues run';
    }

    /**
     * @inheritDoc
     */
    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        return parent::buildOptionParser($parser)
            ->setDescription('Drain default, tenant, and platform queues from one worker.')
            ->addOption('max-jobs', [
                'help' => 'Maximum Queue plugin jobs attempted per datasource.',
                'default' => '100',
            ])
            ->addOption('max-runtime', [
                'help' => 'Maximum Queue plugin runtime per datasource in seconds.',
                'default' => '45',
            ])
            ->addOption('platform-limit', [
                'help' => 'Maximum queued platform jobs claimed per worker cycle.',
                'default' => '1',
            ]);
    }

    /**
     * @inheritDoc
     */
    public function execute(Arguments $args, ConsoleIo $io): int
    {
        try {
            $queueResult = $this->getQueueDrainService()->drain(
                (int)$args->getOption('max-jobs'),
                (int)$args->getOption('max-runtime'),
            );
            $platformResult = $this->getPlatformJobRunner()->run(
                (int)$args->getOption('platform-limit'),
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

        foreach ($queueResult['failures'] as $tenant => $failure) {
            $io->err(sprintf('Tenant queue "%s" failed: %s', $tenant, $failure));
        }

        $io->out(sprintf(
            'Queue cycle processed %d default and %d tenant job(s); platform jobs: %d completed, %d failed.',
            $queueResult['default'],
            array_sum($queueResult['tenants']),
            $platformResult['completed'],
            $platformResult['failed'],
        ));

        return $queueResult['failures'] !== [] || $platformResult['failed'] > 0
            ? self::CODE_ERROR
            : self::CODE_SUCCESS;
    }

    /**
     * @return \App\Services\Platform\PlatformQueueDrainService
     */
    private function getQueueDrainService(): PlatformQueueDrainService
    {
        if ($this->queueDrainService === null) {
            throw new RuntimeException('Platform queue drain service is not configured.');
        }

        return $this->queueDrainService;
    }

    /**
     * @return \App\Services\Platform\PlatformJobRunner
     */
    private function getPlatformJobRunner(): PlatformJobRunner
    {
        return $this->platformJobRunner ?? new PlatformJobRunner();
    }
}
