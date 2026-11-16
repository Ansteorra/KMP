<?php
declare(strict_types=1);

namespace App\Services\Cache;

use Cake\Database\Connection;

/** Preserve locally installed cache triggers across logical schema reset, never trust archive SQL. */
final class ReadModelCacheRestore
{
    /** @return list<array{table_name: string, definition: string}> */
    public static function capture(Connection $connection): array
    {
        return $connection->execute(
            'SELECT c.relname AS table_name, pg_get_triggerdef(t.oid) AS definition'
            . ' FROM pg_trigger t JOIN pg_class c ON c.oid = t.tgrelid'
            . ' JOIN pg_namespace n ON n.oid = c.relnamespace'
            . " WHERE t.tgname = 'kmp_read_cache_revision' AND n.nspname = 'public'",
        )->fetchAll('assoc');
    }

    /** @param list<array{table_name: string, definition: string}> $triggers Trusted live-database definitions. */
    public static function restore(Connection $connection, array $triggers): void
    {
        $tables = $connection->getSchemaCollection()->listTables();
        if (!in_array('application_read_cache_revision', $tables, true)) {
            return;
        }
        // A restored database must never reuse any pre-restore cache generation.
        $connection->execute('INSERT INTO public.application_read_cache_revision (id, revision)'
            . ' VALUES (1, gen_random_uuid()) ON CONFLICT (id) DO UPDATE SET revision = EXCLUDED.revision');
        foreach ($triggers as $trigger) {
            if (in_array($trigger['table_name'], $tables, true)) {
                $connection->execute($trigger['definition']);
            }
        }
    }
}
