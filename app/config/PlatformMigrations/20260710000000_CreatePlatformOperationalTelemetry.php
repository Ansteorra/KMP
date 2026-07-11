<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

// phpcs:disable PSR1.Classes.ClassDeclaration.MissingNamespace
// phpcs:disable Squiz.Classes.ClassFileName.NoMatch

class CreatePlatformOperationalTelemetry extends AbstractMigration
{
    /**
     * Create operational telemetry tables and retention schedules.
     *
     * @return void
     */
    public function up(): void
    {
        $this->table('tenant_request_metrics_hourly', ['id' => false, 'primary_key' => ['id']])
            ->addColumn('id', 'uuid')
            ->addColumn('tenant_id', 'uuid')
            ->addColumn('metric_hour', 'datetime')
            ->addColumn('route_name', 'string', ['limit' => 160])
            ->addColumn('request_count', 'biginteger', ['default' => 0])
            ->addColumn('error_count', 'biginteger', ['default' => 0])
            ->addColumn('server_error_count', 'biginteger', ['default' => 0])
            ->addColumn('slow_request_count', 'biginteger', ['default' => 0])
            ->addColumn('duration_total_ms', 'biginteger', ['default' => 0])
            ->addColumn('duration_max_ms', 'integer', ['default' => 0])
            ->addColumn('created_at', 'datetime')
            ->addColumn('modified_at', 'datetime', ['null' => true])
            ->addIndex(['tenant_id', 'metric_hour', 'route_name'], ['unique' => true])
            ->addIndex(['metric_hour'])
            ->addIndex(['tenant_id', 'metric_hour'])
            ->addForeignKey('tenant_id', 'tenants', 'id', ['delete' => 'CASCADE'])
            ->create();

        $this->table('platform_job_events', ['id' => false, 'primary_key' => ['id']])
            ->addColumn('id', 'uuid')
            ->addColumn('platform_job_id', 'uuid')
            ->addColumn('sequence_number', 'integer')
            ->addColumn('event_level', 'string', ['limit' => 16])
            ->addColumn('event_code', 'string', ['limit' => 80])
            ->addColumn('message', 'string', ['limit' => 500])
            ->addColumn('created_at', 'datetime')
            ->addIndex(['platform_job_id', 'created_at'])
            ->addIndex(['platform_job_id', 'sequence_number'], ['unique' => true])
            ->addForeignKey('platform_job_id', 'platform_jobs', 'id', ['delete' => 'CASCADE'])
            ->create();

        $now = date('Y-m-d H:i:s');
        $this->table('platform_schedules')->insert([
            [
                'id' => '77777777-7777-4777-8777-777777777777',
                'name' => 'backup-retention',
                'cron_expression' => '30 3 * * *',
                'command' => 'platform:run-cake-command',
                'enabled' => true,
                'tenant_scope' => 'platform',
                'tenant_id' => null,
                'payload' => json_encode([
                    'command' => 'platform_backups_prune',
                    'options' => ['limit' => '500'],
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
            ],
            [
                'id' => '88888888-8888-4888-8888-888888888888',
                'name' => 'tenant-metrics-retention',
                'cron_expression' => '45 3 * * *',
                'command' => 'platform:run-cake-command',
                'enabled' => true,
                'tenant_scope' => 'platform',
                'tenant_id' => null,
                'payload' => json_encode([
                    'command' => 'platform_metrics_prune',
                    'options' => ['retention-days' => '90'],
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
            ],
        ])->saveData();
    }

    /**
     * Remove operational telemetry tables and schedules.
     *
     * @return void
     */
    public function down(): void
    {
        $this->execute(
            "DELETE FROM platform_schedules
              WHERE name IN ('backup-retention', 'tenant-metrics-retention')",
        );
        $this->table('platform_job_events')->drop()->save();
        $this->table('tenant_request_metrics_hourly')->drop()->save();
    }
}
