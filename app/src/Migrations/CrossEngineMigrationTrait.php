<?php
declare(strict_types=1);

namespace App\Migrations;

/** PostgreSQL SQL literals; retain the trait name used by applied migrations. */
trait CrossEngineMigrationTrait
{
    /** Escape a PostgreSQL string literal without altering literal backslashes. */
    protected function sqlEscape(string $value): string
    {
        return str_replace("'", "''", $value);
    }

    /** Return a PostgreSQL boolean literal. */
    protected function sqlBool(bool $value): string
    {
        return $value ? 'TRUE' : 'FALSE';
    }

    /**
     * Return a stable text expression for JSON marker comparisons.
     *
     * PostgreSQL JSONB inserts a space after structural colons when cast to
     * text. Historical migrations compare compact json_encode() fragments, so
     * normalize that formatting while preserving their existing predicates.
     */
    protected function jsonAsText(string $column): string
    {
        return "REPLACE(CAST({$column} AS TEXT), ': ', ':')";
    }

    /**
     * Check whether a table exists in the current connection. Useful for
     * data-backfill migrations that depend on tables created by plugin
     * migrations (which may not have run yet on a fresh install).
     */
    protected function tableExistsInDb(string $tableName): bool
    {
        return $this->hasTable($tableName);
    }
}
