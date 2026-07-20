<?php

declare(strict_types=1);

namespace App\Migrations;

/**
 * Helper trait for migrations that embed values directly in raw SQL strings.
 *
 * Phinx's AbstractMigration::execute() does not accept bound parameters, so
 * some migrations interpolate values. MySQL and Postgres differ on string
 * escape syntax:
 *   - MySQL accepts backslash escapes (e.g. \" \' \\) as well as SQL-standard
 *     doubled single-quotes.
 *   - Postgres follows the SQL standard: inside a single-quoted string, only
 *     a doubled single-quote ('') represents a literal quote; backslashes are
 *     taken literally. Backslash-escaped characters embedded in JSON values
 *     therefore corrupt the payload on Postgres.
 *
 * Using {@see self::sqlEscape()} in place of {@see addslashes()} produces a
 * literal that parses identically on both engines.
 */
trait CrossEngineMigrationTrait
{
    /**
     * Escape a value for inclusion inside a single-quoted SQL string literal.
     *
     * Per the SQL standard, only the single-quote character needs escaping
     * (by doubling it). Postgres follows this exactly. MySQL, however, also
     * treats backslash (\) as an escape character inside string literals
     * unless NO_BACKSLASH_ESCAPES mode is set — so embedded `\` in a value
     * (e.g. PHP class names like `App\Policy\Foo` or JSON `\\` sequences)
     * would be eaten. On MySQL we therefore also double the backslashes.
     * On Postgres with the default `standard_conforming_strings = on`
     * backslashes are already literal, so doubling them would corrupt the
     * value — do not escape them there.
     */
    protected function sqlEscape(string $value): string
    {
        $adapter = $this->getAdapter()->getAdapterType();
        if ($adapter === 'mysql') {
            $value = str_replace('\\', '\\\\', $value);
        }

        return str_replace("'", "''", $value);
    }

    /**
     * Return the SQL boolean literal for the target database.
     *
     * Postgres requires TRUE/FALSE keywords for boolean columns and does
     * not auto-cast integers. MySQL accepts TRUE/FALSE as aliases for 1/0,
     * so the keyword form is safe on both engines.
     */
    protected function sqlBool(bool $value): string
    {
        return $value ? 'TRUE' : 'FALSE';
    }

    /**
     * Return a SQL expression that coerces a JSON/JSONB column to text so it
     * can be used with LIKE. MySQL's LIKE works against JSON columns directly,
     * but Postgres rejects JSON types against LIKE and needs an explicit cast.
     */
    protected function jsonAsText(string $column): string
    {
        $adapter = $this->getAdapter()->getAdapterType();
        if (in_array($adapter, ['pgsql', 'postgres'], true)) {
            return "{$column}::text";
        }

        return $column;
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
