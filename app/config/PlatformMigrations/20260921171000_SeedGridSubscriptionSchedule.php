<?php
declare(strict_types=1);

use Migrations\BaseMigration;

// phpcs:disable PSR1.Classes.ClassDeclaration.MissingNamespace
// phpcs:disable Squiz.Classes.ClassFileName.NoMatch

class SeedGridSubscriptionSchedule extends BaseMigration
{
    /** Check subscriptions in each active tenant through the existing bounded worker. */
    public function up(): void
    {
        $this->table('platform_schedules')->insert([[
            'id' => 'ab67fc61-6ec8-44f0-8a64-c28d4a4dfec1',
            'name' => 'grid-subscriptions',
            'cron_expression' => '*/15 * * * *',
            'command' => 'platform:run-cake-command',
            'enabled' => true,
            'tenant_scope' => 'all_active_tenants',
            'tenant_id' => null,
            'payload' => json_encode(['command' => 'grid_subscriptions_enqueue'], JSON_THROW_ON_ERROR),
            'options' => json_encode([
                'requires_tenant_connection' => true, 'fail_fast' => false, 'record_empty_runs' => false,
            ], JSON_THROW_ON_ERROR),
            'status' => 'idle',
            'created_at' => date('Y-m-d H:i:s'),
        ]])->saveData();
    }

    /** Remove the recurring subscription schedule. */
    public function down(): void
    {
        $this->execute("DELETE FROM platform_schedules WHERE name = 'grid-subscriptions'");
    }
}
