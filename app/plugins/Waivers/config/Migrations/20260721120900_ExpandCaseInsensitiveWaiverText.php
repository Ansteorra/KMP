<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class ExpandCaseInsensitiveWaiverText extends BaseMigration
{
    private const COLUMNS = [
        'waivers_waiver_types' => [
            'description' => 'text',
        ],
        'waivers_gathering_waivers' => [
            'status' => 'varchar(50)',
            'exemption_reason' => 'varchar(500)',
            'decline_reason' => 'text',
            'notes' => 'text',
        ],
    ];

    /**
     * Convert human-facing waiver and lifecycle text to citext.
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
     * Restore original waiver varchar and text types.
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
