<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

// phpcs:disable PSR1.Classes.ClassDeclaration.MissingNamespace
// phpcs:disable Squiz.Classes.ClassFileName.NoMatch

class SeedTenantQueueDrainSchedule extends AbstractMigration
{
    /**
     * Seed bounded queue processing for every active tenant.
     *
     * @return void
     */
    public function up(): void
    {
        $now = date('Y-m-d H:i:s');
        $this->table('platform_schedules')->insert([[
            'id' => '99999999-9999-4999-8999-999999999999',
            'name' => 'tenant-queue-drain',
            'cron_expression' => '* * * * *',
            'command' => 'platform:shared-queue-fanout',
            'enabled' => true,
            'tenant_scope' => 'all_active_tenants',
            'tenant_id' => null,
            'payload' => json_encode([
                'max_jobs' => 25,
                'max_runtime' => 45,
            ], JSON_THROW_ON_ERROR),
            'options' => json_encode([
                'requires_tenant_connection' => true,
                'fail_fast' => false,
            ], JSON_THROW_ON_ERROR),
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
     * Remove the tenant queue schedule.
     *
     * @return void
     */
    public function down(): void
    {
        $this->execute("DELETE FROM platform_schedules WHERE name = 'tenant-queue-drain'");
    }
}
