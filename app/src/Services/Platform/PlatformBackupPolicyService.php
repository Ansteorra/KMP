<?php
declare(strict_types=1);

namespace App\Services\Platform;

use Cake\Database\Connection;
use Cake\I18n\DateTime;
use RuntimeException;

/**
 * Reads and writes the single global backup policy (cadence + retention).
 *
 * The policy is the one source of truth for how often managed tenant
 * backups are taken, how long they are retained, and — derived from the
 * cadence — when fleet health should flag a tenant's backups as stale.
 */
class PlatformBackupPolicyService
{
    public const CADENCE_DAILY = 'daily';
    public const CADENCE_WEEKLY = 'weekly';
    public const SETTING_CADENCE = 'backup.cadence';
    public const SETTING_RETENTION_DAYS = 'backup.retention_days';
    public const FLEET_SCHEDULE_NAME = 'tenant-backup-fleet';

    /**
     * Constructor.
     */
    public function __construct(private readonly Connection $platformConnection)
    {
    }

    /**
     * The configured backup cadence: daily or weekly.
     */
    public function cadence(): string
    {
        $value = $this->read(self::SETTING_CADENCE);

        return in_array($value, [self::CADENCE_DAILY, self::CADENCE_WEEKLY], true)
            ? $value
            : self::CADENCE_DAILY;
    }

    /**
     * Retention days for new backups, clamped to 1-365.
     */
    public function retentionDays(): int
    {
        $value = (int)($this->read(self::SETTING_RETENTION_DAYS) ?? 30);

        return max(1, min(365, $value));
    }

    /**
     * The cadence window expressed in hours.
     */
    public function cadenceHours(): int
    {
        return $this->cadence() === self::CADENCE_WEEKLY ? 168 : 24;
    }

    /**
     * A tenant's latest backup older than one cadence window deserves attention.
     */
    public function warningAfterHours(): int
    {
        return $this->cadenceHours();
    }

    /**
     * Three missed cadence windows is an incident.
     */
    public function criticalAfterHours(): int
    {
        return $this->cadenceHours() * 3;
    }

    /**
     * Cron expression matching the configured cadence.
     */
    public function cronExpression(): string
    {
        return $this->cadence() === self::CADENCE_WEEKLY ? '15 3 * * 0' : '15 3 * * *';
    }

    /**
     * Persist the policy and keep the fleet backup schedule's cron in step.
     */
    public function save(string $cadence, int $retentionDays): void
    {
        $cadence = strtolower(trim($cadence));
        if (!in_array($cadence, [self::CADENCE_DAILY, self::CADENCE_WEEKLY], true)) {
            throw new RuntimeException('Backup cadence must be "daily" or "weekly".');
        }
        if ($retentionDays < 1 || $retentionDays > 365) {
            throw new RuntimeException('Backup retention must be between 1 and 365 days.');
        }

        $this->platformConnection->transactional(function (Connection $connection) use (
            $cadence,
            $retentionDays,
        ): void {
            $now = DateTime::now('UTC');
            $this->write($connection, self::SETTING_CADENCE, $cadence, $now);
            $this->write($connection, self::SETTING_RETENTION_DAYS, (string)$retentionDays, $now);
            $cron = $cadence === self::CADENCE_WEEKLY ? '15 3 * * 0' : '15 3 * * *';
            $connection->execute(
                'UPDATE platform_schedules SET cron_expression = :cron, modified_at = :now WHERE name = :name',
                ['cron' => $cron, 'now' => $now, 'name' => self::FLEET_SCHEDULE_NAME],
                ['now' => 'datetime'],
            );
        });
    }

    /**
     * Read one policy setting value.
     */
    private function read(string $key): ?string
    {
        $row = $this->platformConnection->execute(
            'SELECT value FROM platform_settings WHERE key = :key LIMIT 1',
            ['key' => $key],
        )->fetch('assoc');

        return is_array($row) ? (string)$row['value'] : null;
    }

    /**
     * Upsert one policy setting value.
     */
    private function write(Connection $connection, string $key, string $value, DateTime $now): void
    {
        $updated = $connection->execute(
            'UPDATE platform_settings SET value = :value, modified_at = :now WHERE key = :key',
            ['value' => $value, 'now' => $now, 'key' => $key],
            ['now' => 'datetime'],
        )->rowCount();
        if ($updated === 0) {
            $connection->insert('platform_settings', [
                'key' => $key,
                'value' => $value,
                'modified_at' => $now,
            ]);
        }
    }
}
