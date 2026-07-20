<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class EnableCaseInsensitiveAwardNames extends BaseMigration
{
    private const COLUMNS = [
        'awards_domains' => ['name' => 255],
        'awards_levels' => ['name' => 255],
        'awards_awards' => ['name' => 255, 'abbreviation' => 20],
        'awards_events' => ['name' => 255],
        'awards_recommendations' => [
            'requester_sca_name' => 255,
            'member_sca_name' => 255,
            'contact_email' => 255,
            'person_to_notify' => 255,
        ],
        'awards_bestowals' => ['member_sca_name' => 255],
    ];

    /**
     * Convert award-facing names after checking unique collisions.
     */
    public function up(): void
    {
        if (!$this->isPostgres()) {
            return;
        }

        foreach (['awards_domains', 'awards_levels', 'awards_awards'] as $table) {
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
     * Restore award-facing names to varchar.
     */
    public function down(): void
    {
        if (!$this->isPostgres()) {
            return;
        }

        foreach (self::COLUMNS as $table => $columns) {
            foreach ($columns as $column => $limit) {
                $this->execute(sprintf(
                    'ALTER TABLE "%s" ALTER COLUMN "%s" TYPE varchar(%d) USING "%s"::text',
                    $table,
                    $column,
                    $limit,
                    $column,
                ));
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
