<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class ExpandCaseInsensitiveActivityText extends BaseMigration
{
    private const COLUMNS = [
        'activities_authorizations' => [
            'status' => 'varchar(20)',
            'revoked_reason' => 'varchar(255)',
        ],
        'activities_authorization_approvals' => [
            'approver_notes' => 'varchar(255)',
        ],
    ];

    /**
     * Convert human-facing activity and lifecycle text to citext.
     */
    public function up(): void
    {
        $this->convertColumns('citext');
    }

    /**
     * Restore original activity varchar types.
     */
    public function down(): void
    {
        $this->convertColumnsToOriginalTypes();
    }

    /**
     * Convert all configured columns to the requested PostgreSQL type.
     */
    private function convertColumns(string $type): void
    {
        if (!$this->isPostgres()) {
            return;
        }

        foreach (self::COLUMNS as $table => $columns) {
            foreach (array_keys($columns) as $column) {
                $this->execute(sprintf(
                    'ALTER TABLE "%s" ALTER COLUMN "%s" TYPE %s USING "%s"::%s',
                    $table,
                    $column,
                    $type,
                    $column,
                    $type,
                ));
            }
        }
    }

    /**
     * Restore each configured column's original PostgreSQL type.
     */
    private function convertColumnsToOriginalTypes(): void
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
