<?php
declare(strict_types=1);

namespace App\Queue\Task;

use App\Command\UpdateDatabaseCommand;
use App\KMP\TenantContext;
use App\Services\BackupRestoreRunnerService;
use App\Services\RestoreStatusService;
use App\Services\TenantConnectionManager;
use Cake\Command\Command;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOutput;
use Cake\Datasource\ConnectionManager;
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
            $restoreStatusService = new RestoreStatusService();
            $consoleOutput = function (string $message): void {
                $this->io->out($message);
            };
            $migrationOutput = function (string $message) use ($restoreStatusService, $consoleOutput): void {
                $consoleOutput($message);
                foreach (preg_split('/\R/', trim($message)) ?: [] as $line) {
                    $line = trim($line);
                    if ($line !== '') {
                        $restoreStatusService->appendLog($line);
                    }
                }
            };

            (new BackupRestoreRunnerService())->run(
                $token,
                $restoreId,
                $consoleOutput,
                function () use ($migrationOutput): void {
                    $output = new class ($migrationOutput) extends ConsoleOutput {
                        /**
                         * @var callable(string):void
                         */
                        private $callback;

                        /**
                         * @param callable(string):void $callback Output callback
                         */
                        public function __construct(callable $callback)
                        {
                            $this->callback = $callback;
                            parent::__construct('php://stdout');
                        }

                        /**
                         * @param string $message Console output
                         * @return int Bytes written
                         */
                        protected function _write(string $message): int
                        {
                            ($this->callback)($message);

                            return strlen($message);
                        }
                    };
                    $io = new ConsoleIo($output, $output);
                    $io->setInteractive(false);
                    $exitCode = (new UpdateDatabaseCommand())->run($this->migrationCommandArgs(), $io);
                    if ($exitCode !== null && $exitCode !== Command::CODE_SUCCESS) {
                        $this->resetDefaultConnectionAfterMigrationFailure();
                        throw new RuntimeException('Database migrations failed during restore; see restore log.');
                    }
                },
            );
        } catch (Throwable $e) {
            Log::error('Backup restore queue task failed: ' . $e->getMessage());

            throw $e;
        }
    }

    /**
     * Roll back and disconnect a tenant connection left dirty by migrations.
     */
    private function resetDefaultConnectionAfterMigrationFailure(): void
    {
        $connection = ConnectionManager::get('default');
        try {
            while ($connection->inTransaction()) {
                $connection->rollback(true);
            }
        } catch (Throwable $e) {
            Log::warning('Unable to roll back failed restore migration transaction: ' . $e->getMessage());
        }

        try {
            $connection->getDriver()->disconnect();
        } catch (Throwable $e) {
            Log::warning('Unable to disconnect failed restore migration connection: ' . $e->getMessage());
        }
    }

    /**
     * Use the durable tenant connection instead of the temporary default alias.
     *
     * @return list<string>
     */
    private function migrationCommandArgs(): array
    {
        $connection = TenantContext::tryCurrent() === null
            ? 'default'
            : TenantConnectionManager::CONNECTION_ALIAS;

        return ['--connection', $connection, '--no-lock'];
    }
}
