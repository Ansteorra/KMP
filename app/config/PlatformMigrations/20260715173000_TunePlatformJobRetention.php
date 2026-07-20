<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

// phpcs:disable PSR1.Classes.ClassDeclaration.MissingNamespace
// phpcs:disable Squiz.Classes.ClassFileName.NoMatch

class TunePlatformJobRetention extends AbstractMigration
{
    private const ACTIVITY_ONLY_SCHEDULES = [
        'platform-admin-job-runner',
        'tenant-queue-drain',
        'workflow-scheduler',
    ];

    /**
     * Enable activity-only logging and schedule retention.
     */
    public function up(): void
    {
        $this->setEmptyRunRetention(false);

        $this->table('platform_jobs')
            ->addIndex(['status', 'finished_at'], ['name' => 'idx_platform_jobs_status_finished'])
            ->update();

        $now = date('Y-m-d H:i:s');
        $this->table('platform_schedules')->insert([[
            'id' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
            'name' => 'platform-job-retention',
            'cron_expression' => '0 4 * * *',
            'command' => 'platform:run-cake-command',
            'enabled' => true,
            'tenant_scope' => 'platform',
            'tenant_id' => null,
            'payload' => json_encode([
                'command' => 'platform_jobs_prune',
                'options' => [
                    'schedule-days' => '14',
                    'completed-days' => '90',
                    'failed-days' => '180',
                    'limit' => '5000',
                ],
            ], JSON_THROW_ON_ERROR),
            'options' => json_encode(['fail_fast' => true], JSON_THROW_ON_ERROR),
            'status' => 'idle',
            'last_run_at' => null,
            'next_run_at' => null,
            'last_success_at' => null,
            'last_failure_at' => null,
            'last_error' => null,
            'created_at' => $now,
            'modified_at' => null,
        ]])->saveData();
    }

    /**
     * Remove the retention schedule and restore default logging.
     */
    public function down(): void
    {
        $this->execute("DELETE FROM platform_schedules WHERE name = 'platform-job-retention'");
        $this->table('platform_jobs')->removeIndexByName('idx_platform_jobs_status_finished')->update();
        $this->setEmptyRunRetention(true);
    }

    /**
     * Add or remove the activity-only option without replacing other schedule options.
     */
    private function setEmptyRunRetention(bool $remove): void
    {
        $quotedNames = implode(', ', array_map(
            static fn(string $name): string => "'" . str_replace("'", "''", $name) . "'",
            self::ACTIVITY_ONLY_SCHEDULES,
        ));
        $rows = $this->fetchAll(sprintf(
            'SELECT id, options FROM platform_schedules WHERE name IN (%s)',
            $quotedNames,
        ));
        foreach ($rows as $row) {
            $options = json_decode((string)($row['options'] ?? ''), true);
            if (!is_array($options)) {
                $options = [];
            }
            if ($remove) {
                unset($options['record_empty_runs']);
            } else {
                $options['record_empty_runs'] = false;
            }
            $this->execute(
                'UPDATE platform_schedules SET options = :options WHERE id = :id',
                [
                    'options' => json_encode($options, JSON_THROW_ON_ERROR),
                    'id' => $row['id'],
                ],
            );
        }
    }
}
