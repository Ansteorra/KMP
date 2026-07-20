<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class EnableCaseInsensitiveWaiverTypeNames extends BaseMigration
{
    /**
     * Convert waiver type names after checking unique collisions.
     */
    public function up(): void
    {
        if (!$this->isPostgres()) {
            return;
        }

        $result = $this->fetchRow(
            'SELECT COUNT(*) AS conflict_groups FROM (' .
            'SELECT LOWER(name) FROM waivers_waiver_types GROUP BY LOWER(name) HAVING COUNT(*) > 1' .
            ') conflicts',
        );
        if ((int)($result['conflict_groups'] ?? 0) > 0) {
            throw new RuntimeException(
                'Cannot make waivers_waiver_types.name case-insensitive: normalized duplicates exist.',
            );
        }

        $this->execute(
            'ALTER TABLE waivers_waiver_types ALTER COLUMN name TYPE citext USING name::citext',
        );
    }

    /**
     * Restore waiver type names to varchar.
     */
    public function down(): void
    {
        if (!$this->isPostgres()) {
            return;
        }

        $this->execute(
            'ALTER TABLE waivers_waiver_types ALTER COLUMN name TYPE varchar(255) USING name::text',
        );
    }

    /**
     * Check whether the active migration adapter is PostgreSQL.
     */
    private function isPostgres(): bool
    {
        return $this->getAdapter()->getAdapterType() === 'pgsql';
    }
}
