<?php
declare(strict_types=1);

namespace App\Queue\Task;

use App\Command\UpdateDatabaseCommand;
use App\Services\BackupRestoreRunnerService;
use Cake\Command\Command;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOutput;
use Cake\Log\Log;
use InvalidArgumentException;
use Queue\Queue\Task;
use RuntimeException;
use Throwable;

/**
 * Runs staged backup restores on the queue worker.
 */
class BackupRestoreTask extends Task
{
    public ?int $timeout = 3600;
    public ?int $retries = 1;
    public bool $unique = true;

    /**
     * @param array<string, mixed> $data Restore staging token and restore id
     * @param int $jobId Queue job ID
     */
    public function run(array $data, int $jobId): void
    {
        $token = (string)($data['token'] ?? '');
        $restoreId = (string)($data['restore_id'] ?? '');
        if ($token === '' || $restoreId === '') {
            throw new InvalidArgumentException('Missing restore token or restore id.');
        }

        try {
            (new BackupRestoreRunnerService())->run(
                $token,
                $restoreId,
                function (string $message): void {
                    $this->io->out($message);
                },
                function (): void {
                    $io = new ConsoleIo(new ConsoleOutput('php://stdout'), new ConsoleOutput('php://stderr'));
                    $io->setInteractive(false);
                    $exitCode = (new UpdateDatabaseCommand())->run([], $io);
                    if ($exitCode !== null && $exitCode !== Command::CODE_SUCCESS) {
                        throw new RuntimeException('Database migrations failed during restore.');
                    }
                },
            );
        } catch (Throwable $e) {
            Log::error('Backup restore queue task failed: ' . $e->getMessage());

            throw $e;
        }
    }
}
