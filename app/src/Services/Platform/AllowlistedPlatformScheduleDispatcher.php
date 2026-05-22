<?php
declare(strict_types=1);

namespace App\Services\Platform;

use App\Command\AgeUpMembersCommand;
use App\Command\BackupCheckCommand;
use App\Command\SyncActiveWindowStatusesCommand;
use App\Command\SyncMemberWarrantableStatusesCommand;
use App\Command\WorkflowSchedulerCommand;
use App\KMP\TenantMetadata;
use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOutput;
use InvalidArgumentException;
use RuntimeException;

class AllowlistedPlatformScheduleDispatcher implements PlatformScheduleDispatcherInterface
{
    private const COMMAND_NOOP = 'platform:noop';
    private const COMMAND_SHARED_QUEUE_FANOUT = 'platform:shared-queue-fanout';
    private const COMMAND_RUN_CAKE_COMMAND = 'platform:run-cake-command';

    /**
     * @var array<string, class-string<\Cake\Command\Command>>
     */
    private const TENANT_SAFE_COMMANDS = [
        'workflow_scheduler' => WorkflowSchedulerCommand::class,
        'sync_active_window_statuses' => SyncActiveWindowStatusesCommand::class,
        'sync_member_warrantable_statuses' => SyncMemberWarrantableStatusesCommand::class,
        'age_up_members' => AgeUpMembersCommand::class,
        'backup_check' => BackupCheckCommand::class,
    ];

    /**
     * @inheritDoc
     */
    public function dispatch(array $schedule, ?TenantMetadata $tenant): void
    {
        $command = (string)($schedule['command'] ?? '');
        match ($command) {
            self::COMMAND_NOOP => null,
            self::COMMAND_SHARED_QUEUE_FANOUT => $this->dispatchSharedQueuePlaceholder($schedule, $tenant),
            self::COMMAND_RUN_CAKE_COMMAND => $this->runCakeCommand($schedule),
            default => throw new InvalidArgumentException(sprintf(
                'Platform schedule command "%s" is not allowlisted.',
                $command,
            )),
        };
    }

    /**
     * Placeholder for future shared-queue fan-out integration.
     *
     * @param array<string, mixed> $schedule Platform schedule row
     * @param \App\KMP\TenantMetadata|null $tenant Tenant target, if any
     * @return void
     */
    private function dispatchSharedQueuePlaceholder(array $schedule, ?TenantMetadata $tenant): void
    {
        // Intentionally log-only until the shared platform queue contract exists.
    }

    /**
     * Run a small allowlist of tenant-safe legacy commands inside the current tenant scope.
     *
     * @param array<string, mixed> $schedule Platform schedule row
     * @return void
     */
    private function runCakeCommand(array $schedule): void
    {
        $payload = (array)($schedule['payload'] ?? []);
        $name = (string)($payload['command'] ?? '');
        if (!isset(self::TENANT_SAFE_COMMANDS[$name])) {
            throw new InvalidArgumentException(sprintf('Cake command "%s" is not tenant-schedule allowlisted.', $name));
        }

        $arguments = $this->stringList($payload['arguments'] ?? []);
        $options = $this->options($payload['options'] ?? []);
        $argumentNames = $this->stringList($payload['argumentNames'] ?? []);
        $className = self::TENANT_SAFE_COMMANDS[$name];
        $command = new $className();
        $io = new ConsoleIo(new ConsoleOutput('php://memory'), new ConsoleOutput('php://memory'));
        $io->setInteractive(false);

        $result = $command->execute(new Arguments($arguments, $options, $argumentNames), $io);
        if ($result !== Command::CODE_SUCCESS && $result !== null) {
            throw new RuntimeException(sprintf('Cake command "%s" exited with status %d.', $name, $result));
        }
    }

    /**
     * @param mixed $value Raw value
     * @return list<string>
     */
    private function stringList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        return array_values(array_map(static fn(mixed $item): string => (string)$item, $value));
    }

    /**
     * @param mixed $value Raw value
     * @return array<string, array<string>|string|bool|null>
     */
    private function options(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $options = [];
        foreach ($value as $key => $option) {
            if (is_array($option)) {
                $options[(string)$key] = array_map(static fn(mixed $item): string => (string)$item, $option);
            } elseif (is_bool($option) || is_string($option) || $option === null) {
                $options[(string)$key] = $option;
            } else {
                $options[(string)$key] = (string)$option;
            }
        }

        return $options;
    }
}
