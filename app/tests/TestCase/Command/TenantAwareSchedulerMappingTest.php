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

        $this->assertStringContainsString("'tenant_scope' => 'all_active_tenants'", $migration);
        $this->assertStringContainsString("'requires_tenant_connection' => true", $migration);
    }

    private function readAppFile(string $relativePath): string
    {
        $path = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . $relativePath;
        $contents = file_get_contents($path);
        $this->assertIsString($contents);

        return $contents;
    }
}
