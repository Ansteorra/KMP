<?php
declare(strict_types=1);

namespace App\Test\TestCase\Services\Platform;

use App\Services\Platform\PlatformBackupPolicyService;
use Cake\Database\Connection;
use Cake\Database\Driver\Sqlite;
use Cake\TestSuite\TestCase;
use RuntimeException;

class PlatformBackupPolicyServiceTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connection = new Connection([
            'driver' => new Sqlite(),
            'database' => ':memory:',
        ]);
        $this->connection->execute(
            'CREATE TABLE platform_settings (
                key TEXT PRIMARY KEY,
                value TEXT NULL,
                modified_at TEXT NULL
            )',
        );
        $this->connection->execute(
            'CREATE TABLE platform_schedules (
                id TEXT PRIMARY KEY,
                name TEXT NOT NULL UNIQUE,
                cron_expression TEXT NOT NULL,
                modified_at TEXT NULL
            )',
        );
    }

    public function testDefaultsWhenNoSettingsExist(): void
    {
        $policy = new PlatformBackupPolicyService($this->connection);

        $this->assertSame('daily', $policy->cadence());
        $this->assertSame(30, $policy->retentionDays());
        $this->assertSame(24, $policy->cadenceHours());
        $this->assertSame(24, $policy->warningAfterHours());
        $this->assertSame(72, $policy->criticalAfterHours());
        $this->assertSame('15 3 * * *', $policy->cronExpression());
    }

    public function testWeeklyCadenceDrivesHoursThresholdsAndCron(): void
    {
        $this->connection->insert('platform_settings', ['key' => 'backup.cadence', 'value' => 'weekly']);
        $this->connection->insert('platform_settings', ['key' => 'backup.retention_days', 'value' => '45']);
        $policy = new PlatformBackupPolicyService($this->connection);

        $this->assertSame('weekly', $policy->cadence());
        $this->assertSame(45, $policy->retentionDays());
        $this->assertSame(168, $policy->cadenceHours());
        $this->assertSame(168, $policy->warningAfterHours());
        $this->assertSame(504, $policy->criticalAfterHours());
        $this->assertSame('15 3 * * 0', $policy->cronExpression());
    }

    public function testInvalidStoredValuesFallBackAndClamp(): void
    {
        $this->connection->insert('platform_settings', ['key' => 'backup.cadence', 'value' => 'hourly']);
        $this->connection->insert('platform_settings', ['key' => 'backup.retention_days', 'value' => '9999']);
        $policy = new PlatformBackupPolicyService($this->connection);

        $this->assertSame('daily', $policy->cadence());
        $this->assertSame(365, $policy->retentionDays());
    }

    public function testSavePersistsSettingsAndUpdatesFleetScheduleCron(): void
    {
        $this->connection->insert('platform_schedules', [
            'id' => 'schedule-1',
            'name' => PlatformBackupPolicyService::FLEET_SCHEDULE_NAME,
            'cron_expression' => '15 3 * * *',
        ]);
        $policy = new PlatformBackupPolicyService($this->connection);

        $policy->save('weekly', 60);

        $this->assertSame('weekly', $policy->cadence());
        $this->assertSame(60, $policy->retentionDays());
        $cron = $this->connection->execute(
            'SELECT cron_expression FROM platform_schedules WHERE name = :name',
            ['name' => PlatformBackupPolicyService::FLEET_SCHEDULE_NAME],
        )->fetchColumn(0);
        $this->assertSame('15 3 * * 0', $cron);
    }

    public function testSaveRejectsInvalidCadence(): void
    {
        $policy = new PlatformBackupPolicyService($this->connection);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Backup cadence must be "daily" or "weekly".');
        $policy->save('hourly', 30);
    }

    public function testSaveRejectsOutOfRangeRetention(): void
    {
        $policy = new PlatformBackupPolicyService($this->connection);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Backup retention must be between 1 and 365 days.');
        $policy->save('daily', 0);
    }
}
