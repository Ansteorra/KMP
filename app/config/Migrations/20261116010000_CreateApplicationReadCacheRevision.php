<?php
declare(strict_types=1);

use Migrations\BaseMigration;

/** Transactional, cross-process invalidation for cached catalogues and capability facts. */
class CreateApplicationReadCacheRevision extends BaseMigration
{
    /** @var list<string> */
    private const TABLES = [
        'branches',
        'gathering_types',
        'gathering_activities',
        'gathering_type_gathering_activities',
        'gathering_activity_assignment_permissions',
        'gathering_staff',
        'members',
        'gatherings',
    ];

    /** Install the revision and dependency triggers without changing existing data. */
    public function up(): void
    {
        $this->execute("CREATE TABLE IF NOT EXISTS public.application_read_cache_revision (
            id integer PRIMARY KEY CHECK (id = 1), revision uuid NOT NULL DEFAULT gen_random_uuid()
        )");
        $this->execute('INSERT INTO public.application_read_cache_revision (id) VALUES (1)'
            . ' ON CONFLICT (id) DO NOTHING');
        $this->execute(<<<'SQL'
CREATE OR REPLACE FUNCTION public.kmp_touch_read_cache_revision() RETURNS trigger
LANGUAGE plpgsql SET search_path = pg_catalog AS $function$
BEGIN
    UPDATE public.application_read_cache_revision SET revision = gen_random_uuid() WHERE id = 1;
    RETURN NULL;
END;
$function$
SQL);
        foreach (self::TABLES as $table) {
            $this->execute("DROP TRIGGER IF EXISTS kmp_read_cache_revision ON public.{$table}");
            $this->execute("CREATE TRIGGER kmp_read_cache_revision
                AFTER INSERT OR UPDATE OR DELETE OR TRUNCATE ON public.{$table}
                FOR EACH STATEMENT EXECUTE FUNCTION public.kmp_touch_read_cache_revision()");
        }
    }

    /** Remove only this cache's infrastructure. */
    public function down(): void
    {
        foreach (self::TABLES as $table) {
            $this->execute("DROP TRIGGER IF EXISTS kmp_read_cache_revision ON public.{$table}");
        }
        $this->execute('DROP FUNCTION public.kmp_touch_read_cache_revision()');
        $this->execute('DROP TABLE public.application_read_cache_revision');
    }
}
