<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class EnableCaseInsensitiveQueueStatusText extends BaseMigration
{
    private const COLUMNS = [
        'queued_jobs' => [
            'status' => 'varchar(190)',
            'failure_message' => 'text',
        ],
    ];

    /**
     * Convert queue lifecycle and error text to citext.
     */
    public function up(): void
    {
        if (!$this->isPostgres()) {
            return;
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
     * Restore original queue varchar and text types.
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
     * Check whether the active migration adapter is PostgreSQL.
     */
    private function isPostgres(): bool
    {
        return $this->getAdapter()->getAdapterType() === 'pgsql';
    }
}
