<?php
declare(strict_types=1);

namespace App\Services\Backup;

use Cake\I18n\DateTime;

/**
 * Applies ordered object-level upgrades to decoded backup payloads.
 */
class BackupPayloadUpgradeService
{
    /**
     * @param array<int, \App\Services\Backup\BackupPayloadMigratorInterface>|null $migrators
     */
    public function __construct(private ?array $migrators = null)
    {
    }

    /**
     * @param array<string, mixed> $payload Decoded backup payload.
     * @param callable(array<string, mixed>):void|null $progressReporter
     * @return array{payload: array<string, mixed>, stats: array<string, mixed>}
     */
    public function upgrade(array $payload, ?callable $progressReporter = null): array
    {
        $stats = [
            'source_version' => $this->detectSourceVersion($payload),
            'target_version' => 'feature-workflow-engine-20260622',
            'migrators_applied' => 0,
            'migrators_skipped' => 0,
            'migrators' => [],
        ];

        $this->reportProgress(
            $progressReporter,
            'upgrading_backup_payload',
            'Upgrading backup payload to the current object model.',
            ['backup_source_version' => $stats['source_version']],
        );

        foreach ($this->migrators() as $migrator) {
            if (!$migrator->shouldRun($payload)) {
                $stats['migrators_skipped']++;
                continue;
            }

            $result = $migrator->migrate($payload);
            $payload = $result['payload'];
            $stats['migrators_applied']++;
            $stats['migrators'][$migrator->name()] = $result['stats'];
        }

        if ($stats['migrators_applied'] > 0) {
            $payload['meta']['payload_upgraded_at'] = DateTime::now()->toIso8601String();
            $payload['meta']['payload_upgrade_target'] = $stats['target_version'];
            $payload['meta']['payload_upgrades'] = array_merge(
                $payload['meta']['payload_upgrades'] ?? [],
                [[
                    'target' => $stats['target_version'],
                    'applied_at' => $payload['meta']['payload_upgraded_at'],
                    'migrators' => array_keys($stats['migrators']),
                ]],
            );
        }

        $this->reportProgress(
            $progressReporter,
            'upgrading_backup_payload',
            'Backup payload upgrade completed.',
            ['backup_payload_upgrade' => $stats],
        );

        return ['payload' => $payload, 'stats' => $stats];
    }

    /**
     * @return array<int, \App\Services\Backup\BackupPayloadMigratorInterface>
     */
    private function migrators(): array
    {
        if ($this->migrators === null) {
            $this->migrators = [
                new MainToWorkflowEngineBranchBackupMigrator(),
            ];
        }

        return $this->migrators;
    }

    /**
     * @param array<string, mixed> $payload Decoded backup payload.
     */
    private function detectSourceVersion(array $payload): string
    {
        if (!empty($payload['meta']['migration_fingerprint']) && is_array($payload['meta']['migration_fingerprint'])) {
            return 'fingerprinted';
        }

        if (isset($payload['tables']) && is_array($payload['tables'])) {
            foreach (array_keys($payload['tables']) as $tableName) {
                if (is_string($tableName) && preg_match('/(?:^|_)phinxlog$/i', $tableName)) {
                    return 'legacy-phinxlog-payload';
                }
            }
        }

        return 'baseline-v1';
    }

    /**
     * @param callable(array<string, mixed>):void|null $progressReporter
     * @param array<string, mixed> $context
     */
    private function reportProgress(
        ?callable $progressReporter,
        string $phase,
        string $message,
        array $context = [],
    ): void {
        if ($progressReporter === null) {
            return;
        }

        $progressReporter(array_merge($context, [
            'phase' => $phase,
            'message' => $message,
        ]));
    }
}
