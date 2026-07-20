<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class EnableCaseInsensitiveHumanText extends BaseMigration
{
    private const COLUMNS = [
        'members' => [
            'email_address' => 50,
            'sca_name' => 50,
            'first_name' => 30,
            'middle_name' => 30,
            'last_name' => 30,
            'title' => 255,
            'pronouns' => 50,
            'pronunciation' => 255,
        ],
        'branches' => ['name' => 128],
        'roles' => ['name' => 255],
        'permissions' => ['name' => 255],
        'notes' => ['subject' => 255],
        'gathering_types' => ['name' => 255],
        'gathering_activities' => ['name' => 255],
        'gatherings' => ['name' => 255],
        'gathering_staff' => ['sca_name' => 255, 'role' => 100],
    ];

    private const UNIQUE_COLUMNS = [
        'members' => ['email_address'],
        'branches' => ['name'],
        'roles' => ['name'],
        'permissions' => ['name'],
        'gathering_types' => ['name'],
        'gathering_activities' => ['name'],
    ];

    /**
     * Enable citext and convert curated core columns.
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
     * Restore the original varchar types.
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
     * Stop before DDL if an existing unique value would collide.
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
