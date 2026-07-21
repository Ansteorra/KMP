<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class ExpandCaseInsensitiveOfficerText extends BaseMigration
{
    private const COLUMNS = [
        'officers_departments' => [
            'domain' => 'varchar(255)',
        ],
        'officers_offices' => [
            'default_contact_address' => 'varchar(255)',
        ],
        'officers_officers' => [
            'email_address' => 'varchar(255)',
            'status' => 'varchar(20)',
            'revoked_reason' => 'varchar(255)',
        ],
    ];

    /**
     * Convert human-facing officer and lifecycle text to citext.
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
     * Restore original officer varchar types.
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
