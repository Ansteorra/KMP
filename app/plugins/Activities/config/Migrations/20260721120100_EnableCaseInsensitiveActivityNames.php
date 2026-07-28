<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class EnableCaseInsensitiveActivityNames extends BaseMigration
{
    private const TABLES = [
        'activities_activity_groups',
        'activities_activities',
    ];

    /**
     * Convert activity names after checking unique collisions.
     */
    public function up(): void
    {
        if (!$this->isPostgres()) {
            return;
        }

        foreach (self::TABLES as $table) {
            $result = $this->fetchRow(sprintf(
                'SELECT COUNT(*) AS conflict_groups FROM (' .
                'SELECT LOWER(name) FROM "%s" GROUP BY LOWER(name) HAVING COUNT(*) > 1' .
                ') conflicts',
                $table,
            ));
            if ((int)($result['conflict_groups'] ?? 0) > 0) {
                throw new RuntimeException(sprintf(
                    'Cannot make %s.name case-insensitive: normalized duplicates exist.',
                    $table,
                ));
            }
        }

        foreach (self::TABLES as $table) {
            $this->execute(sprintf(
                'ALTER TABLE "%s" ALTER COLUMN name TYPE citext USING name::citext',
                $table,
            ));
        }
    }

    /**
     * Restore activity names to varchar.
     */
    public function down(): void
    {
        if (!$this->isPostgres()) {
            return;
        }

        foreach (self::TABLES as $table) {
            $this->execute(sprintf(
                'ALTER TABLE "%s" ALTER COLUMN name TYPE varchar(255) USING name::text',
                $table,
            ));
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
