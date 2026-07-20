<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

// phpcs:disable PSR1.Classes.ClassDeclaration.MissingNamespace
// phpcs:disable Squiz.Classes.ClassFileName.NoMatch

class ReplaceBackupCheckWithFleetBackupSchedule extends AbstractMigration
{
    /**
     * Retire the per-tenant `backup_check` schedule (legacy self-service
     * backups driven by tenant AppSettings) in favor of a single
     * platform-scoped fleet schedule that enqueues managed tenant backups
     * per the global backup policy.
     *
     * @return void
     */
    public function up(): void
    {
        $this->execute("DELETE FROM platform_schedules WHERE name = 'backup-check'");

        $this->table('platform_schedules')->insert([
            [
                'id' => 'aaaaaaa1-aaaa-4aaa-8aaa-aaaaaaaaaaa1',
                'name' => 'tenant-backup-fleet',
                'cron_expression' => '15 3 * * *',
                'command' => 'platform:run-cake-command',
                'enabled' => true,
                'tenant_scope' => 'platform',
                'tenant_id' => null,
                'payload' => json_encode([
                    'command' => 'tenant_backups_enqueue',
                ], JSON_THROW_ON_ERROR),
                'options' => json_encode(['fail_fast' => true], JSON_THROW_ON_ERROR),
                'status' => 'idle',
                'last_run_at' => null,
                'next_run_at' => null,
                'last_success_at' => null,
                'last_failure_at' => null,
                'last_error' => null,
                'created_at' => date('Y-m-d H:i:s'),
                'modified_at' => null,
            ],
        ])->saveData();
    }

    /**
     * Restore the legacy backup-check schedule.
     *
     * @return void
     */
    public function down(): void
    {
        $this->execute("DELETE FROM platform_schedules WHERE name = 'tenant-backup-fleet'");

        $this->table('platform_schedules')->insert([
            [
                'id' => '55555555-5555-4555-8555-555555555555',
                'name' => 'backup-check',
                'cron_expression' => '0 3 * * *',
                'command' => 'platform:run-cake-command',
                'enabled' => true,
                'tenant_scope' => 'all_active_tenants',
                'tenant_id' => null,
                'payload' => json_encode(['command' => 'backup_check'], JSON_THROW_ON_ERROR),
                'options' => json_encode(['requires_tenant_connection' => true], JSON_THROW_ON_ERROR),
                'status' => 'idle',
                'last_run_at' => null,
                'next_run_at' => null,
                'last_success_at' => null,
                'last_failure_at' => null,
                'last_error' => null,
                'created_at' => date('Y-m-d H:i:s'),
                'modified_at' => null,
            ],
        ])->saveData();
    }
}
