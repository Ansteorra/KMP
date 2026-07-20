<?php
declare(strict_types=1);

namespace App\Test\TestCase\Command;

use Cake\TestSuite\TestCase;

class TenantAwareSchedulerMappingTest extends TestCase
{
    public function testPlatformSchedulesUseTenantAwareCakeCommandDispatcher(): void
    {
        $migration = $this->readAppFile('config/PlatformMigrations/20260516012000_SeedTenantAwareCommandSchedules.php');

        $this->assertStringContainsString('platform:run-cake-command', $migration);
        $this->assertStringContainsString('workflow-scheduler', $migration);
        $this->assertStringContainsString('active-window-sync', $migration);
        $this->assertStringContainsString('member-warrantable-sync', $migration);
        $this->assertStringContainsString('backup-check', $migration);
    }

    public function testPlatformSchedulesRequireTenantConnections(): void
    {
        $migration = $this->readAppFile('config/PlatformMigrations/20260516012000_SeedTenantAwareCommandSchedules.php');

        // Schedules default to the all-active-tenants scope and bind that scope into
        // each seeded row (the literal is now expressed via a parameterized helper).
        $this->assertStringContainsString("\$tenantScope = 'all_active_tenants'", $migration);
        $this->assertStringContainsString("'tenant_scope' => \$tenantScope", $migration);
        $this->assertStringContainsString("'requires_tenant_connection' => true", $migration);
    }

    public function testQueueSchedulesAreConsolidatedIntoFleetWorker(): void
    {
        $migration = $this->readAppFile(
            'config/PlatformMigrations/20260718171500_ConsolidatePlatformQueueExecution.php',
        );

        $this->assertStringContainsString('platform-admin-job-runner', $migration);
        $this->assertStringContainsString('tenant-queue-drain', $migration);
        $this->assertStringContainsString('SET enabled = FALSE', $migration);
    }

    private function readAppFile(string $relativePath): string
    {
        $path = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . $relativePath;
        $contents = file_get_contents($path);
        $this->assertIsString($contents);

        return $contents;
    }
}
