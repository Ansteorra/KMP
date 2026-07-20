<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class EnableCaseInsensitiveOfficerNames extends BaseMigration
{
    /**
     * Convert officer-facing names after checking unique collisions.
     */
    public function up(): void
    {
        if (!$this->isPostgres()) {
            return;
        }

        foreach (['officers_departments', 'officers_offices'] as $table) {
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

        foreach (['officers_departments', 'officers_offices'] as $table) {
            $this->execute(sprintf(
                'ALTER TABLE "%s" ALTER COLUMN name TYPE citext USING name::citext',
                $table,
            ));
        }
        $this->execute(
            'ALTER TABLE officers_officers ALTER COLUMN deputy_description ' .
            'TYPE citext USING deputy_description::citext',
        );
    }

    /**
     * Restore officer-facing names to varchar.
     */
    public function down(): void
    {
        if (!$this->isPostgres()) {
            return;
        }

        foreach (['officers_departments', 'officers_offices'] as $table) {
            $this->execute(sprintf(
                'ALTER TABLE "%s" ALTER COLUMN name TYPE varchar(255) USING name::text',
                $table,
            ));
        }
        $this->execute(
            'ALTER TABLE officers_officers ALTER COLUMN deputy_description ' .
            'TYPE varchar(255) USING deputy_description::text',
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
