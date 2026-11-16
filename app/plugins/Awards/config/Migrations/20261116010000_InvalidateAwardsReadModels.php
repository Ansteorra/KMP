<?php
declare(strict_types=1);

use Migrations\BaseMigration;

/** Keep plugin capability facts coherent with committed database changes. */
class InvalidateAwardsReadModels extends BaseMigration
{
    /** Install plugin-owned dependency triggers. */
    public function up(): void
    {
        $this->execute('DROP TRIGGER IF EXISTS kmp_read_cache_revision ON public.awards_bestowal_delegations');
        $this->execute("CREATE TRIGGER kmp_read_cache_revision
            AFTER INSERT OR UPDATE OR DELETE OR TRUNCATE ON public.awards_bestowal_delegations
            FOR EACH STATEMENT EXECUTE FUNCTION public.kmp_touch_read_cache_revision()");
    }

    /** Remove plugin-owned dependency triggers. */
    public function down(): void
    {
        $this->execute('DROP TRIGGER IF EXISTS kmp_read_cache_revision ON public.awards_bestowal_delegations');
    }
}
