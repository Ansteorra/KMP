<?php
declare(strict_types=1);

namespace App\Command;

use App\Services\BackupRestoreRunnerService;
use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use RuntimeException;
use Throwable;

/**
 * Internal restore runner for manually resuming staged restores from CLI.
 */
class BackupRestoreRunCommand extends Command
{
    /**
     * Return the internal restore runner command name.
     */
    public static function defaultName(): string
    {
        return 'backup_restore_run';
    }

    /**
     * Configure the restore token and ownership arguments.
     *
     * @param \Cake\Console\ConsoleOptionParser $parser Console option parser
     * @return \Cake\Console\ConsoleOptionParser
     */
    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        return $parser
            ->setDescription('Internal command that runs a staged backup restore.')
            ->addArgument('token', [
                'help' => 'Restore staging token',
                'required' => true,
            ])
            ->addArgument('restore_id', [
                'help' => 'Restore ownership identifier',
                'required' => true,
            ]);
    }

    /**
     * Run a staged restore and return the command exit code.
     *
     * @param \Cake\Console\Arguments $args Command arguments
     * @param \Cake\Console\ConsoleIo $io Console IO
     * @return int|null
     */
    public function execute(Arguments $args, ConsoleIo $io): ?int
    {
        $token = (string)$args->getArgument('token');
        $restoreId = (string)($args->getArgument('restore_id') ?? '');

        try {
            (new BackupRestoreRunnerService())->run(
                $token,
                $restoreId,
                fn(string $message): ?int => $io->out($message),
                function () use ($io): void {
                    $exitCode = $this->executeCommand(UpdateDatabaseCommand::class, [], $io);
                    if ($exitCode !== null && $exitCode !== self::CODE_SUCCESS) {
                        throw new RuntimeException('Database migrations failed during restore.');
                    }
                },
            );

            return self::CODE_SUCCESS;
        } catch (Throwable $e) {
            $io->error('Restore failed: ' . $e->getMessage());

            return self::CODE_ERROR;
        }
    }
}
