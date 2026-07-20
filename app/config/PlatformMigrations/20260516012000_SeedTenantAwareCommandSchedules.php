<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

// phpcs:disable PSR1.Classes.ClassDeclaration.MissingNamespace
// phpcs:disable Squiz.Classes.ClassFileName.NoMatch

class SeedTenantAwareCommandSchedules extends AbstractMigration
{
    /**
     * Seed platform schedules that fan out tenant-affecting legacy commands.
     *
     * @return void
     */
    public function up(): void
    {
        $now = date('Y-m-d H:i:s');
        $rows = [
            $this->schedule(
                '66666666-6666-4666-8666-666666666666',
                'platform-admin-job-runner',
                '* * * * *',
                'platform_jobs_run',
                $now,
                'platform',
                'platform:run-platform-jobs',
                ['limit' => 10],
                ['fail_fast' => true],
            ),
            $this->schedule(
                '11111111-1111-4111-8111-111111111111',
                'workflow-scheduler',
                '* * * * *',
                'workflow_scheduler',
                $now,
            ),
            $this->schedule(
                '22222222-2222-4222-8222-222222222222',
                'active-window-sync',
                '*/15 * * * *',
                'sync_active_window_statuses',
                $now,
            ),
            $this->schedule(
                '33333333-3333-4333-8333-333333333333',
                'member-warrantable-sync',
                '10 0 * * *',
                'sync_member_warrantable_statuses',
                $now,
            ),
            $this->schedule(
                '44444444-4444-4444-8444-444444444444',
                'age-up-members',
                '20 0 * * *',
                'age_up_members',
                $now,
            ),
            $this->schedule(
                '55555555-5555-4555-8555-555555555555',
                'backup-check',
                '0 3 * * *',
                'backup_check',
                $now,
            ),
        ];

        $this->table('platform_schedules')->insert($rows)->saveData();
    }

    /**
     * Remove seeded schedules.
     *
     * @return void
     */
    public function down(): void
    {
        $this->execute(
            "DELETE FROM platform_schedules WHERE name IN (
                'workflow-scheduler',
                'active-window-sync',
                'member-warrantable-sync',
                'age-up-members',
                'backup-check',
                'platform-admin-job-runner'
            )",
        );
    }

    /**
     * Build a seeded schedule row.
     *
     * @return array<string, mixed>
     */
    private function schedule(
        string $id,
        string $name,
        string $cron,
        string $commandName,
        string $now,
        string $tenantScope = 'all_active_tenants',
        string $command = 'platform:run-cake-command',
        array $payload = [],
        array $options = ['requires_tenant_connection' => true],
    ): array {
        if ($payload === []) {
            $payload = ['command' => $commandName];
        }

        return [
            'id' => $id,
            'name' => $name,
            'cron_expression' => $cron,
            'command' => $command,
            'enabled' => true,
            'tenant_scope' => $tenantScope,
            'tenant_id' => null,
            'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
            'options' => json_encode($options, JSON_THROW_ON_ERROR),
            'status' => 'idle',
            'last_run_at' => null,
            'next_run_at' => null,
            'last_success_at' => null,
            'last_failure_at' => null,
            'last_error' => null,
            'created_at' => $now,
            'modified_at' => null,
        ];
    }
}
