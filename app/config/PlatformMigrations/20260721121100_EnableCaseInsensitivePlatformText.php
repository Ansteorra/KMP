<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

// phpcs:disable PSR1.Classes.ClassDeclaration.MissingNamespace
// phpcs:disable Squiz.Classes.ClassFileName.NoMatch

class EnableCaseInsensitivePlatformText extends AbstractMigration
{
    private const COLUMNS = [
        'audit_events' => [
            'action' => 'varchar(120)',
            'ip_address' => 'varchar(64)',
            'reason' => 'text',
        ],
        'escrow_ceremonies' => [
            'notes' => 'text',
            'status' => 'varchar(32)',
        ],
        'escrow_share_envelopes' => [
            'status' => 'varchar(32)',
        ],
        'escrow_verifications' => [
            'notes' => 'text',
            'status' => 'varchar(32)',
        ],
        'platform_auth_sessions' => [
            'ip_address' => 'varchar(64)',
        ],
        'platform_database_backups' => [
            'backup_type' => 'varchar(32)',
            'error_summary' => 'text',
            'status' => 'varchar(32)',
        ],
        'platform_job_events' => [
            'event_level' => 'varchar(16)',
            'message' => 'varchar(500)',
        ],
        'platform_jobs' => [
            'last_error' => 'text',
            'status' => 'varchar(32)',
        ],
        'platform_schedules' => [
            'last_error' => 'text',
            'name' => 'varchar(120)',
            'status' => 'varchar(32)',
            'tenant_scope' => 'varchar(40)',
        ],
        'platform_secret_keks' => [
            'status' => 'varchar(32)',
        ],
        'platform_secret_values' => [
            'status' => 'varchar(32)',
        ],
        'platform_users' => [
            'email' => 'varchar(255)',
            'status' => 'varchar(32)',
        ],
        'tenant_backups' => [
            'backup_type' => 'varchar(32)',
            'error_summary' => 'text',
            'recovery_key_exported_by' => 'varchar(160)',
            'status' => 'varchar(32)',
        ],
        'tenant_hosts' => [
            'host_normalized' => 'varchar(255)',
            'host' => 'varchar(255)',
            'status' => 'varchar(32)',
        ],
        'tenants' => [
            'display_name' => 'varchar(255)',
            'primary_host' => 'varchar(255)',
            'region' => 'varchar(64)',
            'status' => 'varchar(32)',
        ],
    ];

    private const UNIQUE_COLUMNS = [
        'platform_schedules' => ['name'],
        'platform_users' => ['email'],
        'tenant_hosts' => ['host_normalized'],
    ];

    /**
     * Convert human-facing platform and lifecycle text to citext.
     */
    public function up(): void
    {
        if (!$this->isPostgres()) {
            return;
        }

        $this->assertNoCaseInsensitiveCollisions();
        $this->execute('CREATE EXTENSION IF NOT EXISTS citext');
        foreach (self::COLUMNS as $table => $columns) {
            foreach (array_keys($columns) as $column) {
                $this->execute(sprintf(
                    'ALTER TABLE "%s" ALTER COLUMN "%s" TYPE citext USING "%s"::citext',
                    $table,
                    $column,
                    $column,
                ));
            }
        }
    }

    /**
     * Restore original platform varchar and text types.
     */
    public function down(): void
    {
        if (!$this->isPostgres()) {
            return;
        }

        foreach (self::COLUMNS as $table => $columns) {
            foreach ($columns as $column => $type) {
                $this->execute(sprintf(
                    'ALTER TABLE "%s" ALTER COLUMN "%s" TYPE %s USING "%s"::text',
                    $table,
                    $column,
                    $type,
                    $column,
                ));
            }
        }
    }

    /**
     * Stop before DDL when a converted unique value would collide.
     */
    private function assertNoCaseInsensitiveCollisions(): void
    {
        foreach (self::UNIQUE_COLUMNS as $table => $columns) {
            foreach ($columns as $column) {
                $result = $this->fetchRow(sprintf(
                    'SELECT COUNT(*) AS conflict_groups FROM (' .
                    'SELECT LOWER("%s") FROM "%s" GROUP BY LOWER("%s") HAVING COUNT(*) > 1' .
                    ') conflicts',
                    $column,
                    $table,
                    $column,
                ));
                if ((int)($result['conflict_groups'] ?? 0) > 0) {
                    throw new RuntimeException(sprintf(
                        'Cannot make %s.%s case-insensitive: normalized duplicates exist.',
                        $table,
                        $column,
                    ));
                }
            }
        }
    }

    /**
     * Check whether the active migration adapter is PostgreSQL.
     */
    private function isPostgres(): bool
    {
        return $this->getAdapter()->getAdapterType() === 'pgsql';
    }
}
