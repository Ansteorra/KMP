<?php
declare(strict_types=1);

use Migrations\BaseMigration;

/** Record independent transaction tokens so unrelated business writes never share a cache-row lock. */
class AvoidSharedReadCacheWriteLock extends BaseMigration
{
    /** Append tokens transactionally; compact old tokens under a nonblocking maintenance lock. */
    public function up(): void
    {
        $this->execute("CREATE TABLE IF NOT EXISTS public.application_read_cache_changes (
            transaction_id bigint PRIMARY KEY,
            created_at timestamptz NOT NULL DEFAULT clock_timestamp()
        )");
        $this->execute('CREATE INDEX IF NOT EXISTS read_cache_changes_created'
            . ' ON public.application_read_cache_changes (created_at)');
        $this->execute(<<<'SQL'
CREATE OR REPLACE FUNCTION public.kmp_touch_read_cache_revision() RETURNS trigger
LANGUAGE plpgsql SET search_path = pg_catalog AS $function$
DECLARE
    xid bigint := txid_current();
BEGIN
    INSERT INTO public.application_read_cache_changes (transaction_id) VALUES (xid)
        ON CONFLICT (transaction_id) DO NOTHING;
    IF pg_try_advisory_xact_lock(1146244432, 1) THEN
        DELETE FROM public.application_read_cache_changes WHERE transaction_id IN (
            SELECT transaction_id FROM public.application_read_cache_changes
            WHERE created_at < clock_timestamp() - interval '20 minutes'
            ORDER BY created_at LIMIT 1024
        );
        IF FOUND THEN
            UPDATE public.application_read_cache_revision SET revision = gen_random_uuid() WHERE id = 1;
        END IF;
    END IF;
    RETURN NULL;
END;
$function$
SQL);
    }

    /** Restore the original revision trigger before removing its token table. */
    public function down(): void
    {
        $this->execute(<<<'SQL'
CREATE OR REPLACE FUNCTION public.kmp_touch_read_cache_revision() RETURNS trigger
LANGUAGE plpgsql SET search_path = pg_catalog AS $function$
BEGIN
    UPDATE public.application_read_cache_revision SET revision = gen_random_uuid() WHERE id = 1;
    RETURN NULL;
END;
$function$
SQL);
        $this->execute('DROP TABLE public.application_read_cache_changes');
    }
}
