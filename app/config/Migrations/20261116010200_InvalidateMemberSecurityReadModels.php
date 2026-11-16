<?php
declare(strict_types=1);

use Migrations\BaseMigration;

/** RBAC changes participate in the same committed read-model revision. */
class InvalidateMemberSecurityReadModels extends BaseMigration
{
    /** @var list<string> */
    private const TABLES = [
        'roles',
        'permissions',
        'roles_permissions',
        'permission_policies',
        'member_roles',
        'warrants',
        'app_settings',
    ];

    /** Install RBAC dependency triggers, including direct SQL writes. */
    public function up(): void
    {
        foreach (self::TABLES as $table) {
            $this->execute("DROP TRIGGER IF EXISTS kmp_read_cache_revision ON public.{$table}");
            $this->execute("CREATE TRIGGER kmp_read_cache_revision
                AFTER INSERT OR UPDATE OR DELETE OR TRUNCATE ON public.{$table}
                FOR EACH STATEMENT EXECUTE FUNCTION public.kmp_touch_read_cache_revision()");
        }
    }

    /** Remove only this migration's triggers. */
    public function down(): void
    {
        foreach (self::TABLES as $table) {
            $this->execute("DROP TRIGGER IF EXISTS kmp_read_cache_revision ON public.{$table}");
        }
    }
}
