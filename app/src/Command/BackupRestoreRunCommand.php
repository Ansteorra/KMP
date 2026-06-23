<?php
declare(strict_types=1);

namespace App\Command;

use App\Services\BackupService;
use App\Services\RestoreStagingService;
use App\Services\RestoreStatusService;
use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Exception;

/**
 * Internal restore runner for web-initiated background restores.
 */
class BackupRestoreRunCommand extends Command
{
    public static function defaultName(): string
    {
        return 'backup_restore_run';
    }

    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        return $parser
            ->setDescription('Internal command that runs a staged backup restore.')
            ->addArgument('token', [
                'help' => 'Restore staging token',
                'required' => true,
            ]);
    }

    public function execute(Arguments $args, ConsoleIo $io): ?int
    {
        $token = (string)$args->getArgument('token');
        $restoreStatusService = new RestoreStatusService();

        try {
            $staged = (new RestoreStagingService())->consume($token);
            $context = $staged['context'];
            if (!$restoreStatusService->isLocked()) {
                $restoreStatusService->acquireLock(array_merge($context, [
                    'message' => 'Restore runner recovered missing restore lock.',
                ]));
            }

            $restoreStatusService->updateStatus('starting', 'Background restore runner started.', $context);
            $lastPhase = null;
            $stats = (new BackupService())->import(
                $staged['encrypted_data'],
                $staged['encryption_key'],
                function (array $progress) use ($restoreStatusService, $context, $io, &$lastPhase): void {
                    $phase = (string)($progress['phase'] ?? 'running');
                    $message = (string)($progress['message'] ?? 'Restore in progress.');
                    unset($progress['phase'], $progress['message']);
                    $restoreStatusService->updateStatus($phase, $message, array_merge($progress, $context));

                    if ($phase !== $lastPhase) {
                        $io->out($message);
                        $lastPhase = $phase;
                    }
                },
                function () use ($io): void {
                    $this->executeCommand(UpdateDatabaseCommand::class, [], $io);
                },
            );

            $restoreStatusService->markCompleted(sprintf(
                'Restore/import completed: %d tables, %d rows.',
                $stats['table_count'],
                $stats['row_count'],
            ), array_merge($context, [
                'table_count' => $stats['table_count'],
                'tables_processed' => $stats['table_count'],
                'row_count' => $stats['row_count'],
                'rows_processed' => $stats['row_count'],
            ]));

            return self::CODE_SUCCESS;
        } catch (Exception $e) {
            $restoreStatusService->markFailed('Restore/import failed: ' . $e->getMessage());
            $io->error('Restore failed: ' . $e->getMessage());

            return self::CODE_ERROR;
        } finally {
            $restoreStatusService->releaseLock();
        }
    }
}
