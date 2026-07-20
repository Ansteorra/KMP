<?php
declare(strict_types=1);

namespace App\KMP;

/**
 * Tracks the active tenant for the current request, worker message, or CLI scope.
 */
final class TenantContext
{
    /**
     * @var list<\App\KMP\TenantMetadata>
     */
    private static array $stack = [];

    /**
     * Static utility class.
     */
    private function __construct()
    {
    }

    /**
     * Return the active tenant or fail fast if code is not tenant-scoped.
     *
     * @return \App\KMP\TenantMetadata
     */
    public static function current(): TenantMetadata
    {
        $tenant = self::tryCurrent();
        if ($tenant === null) {
            throw new MissingTenantContextException('Tenant context is required but has not been set.');
        }

        return $tenant;
    }

    /**
     * Return the active tenant when one is set.
     *
     * @return \App\KMP\TenantMetadata|null
     */
    public static function tryCurrent(): ?TenantMetadata
    {
        if (self::$stack === []) {
            return null;
        }

        return self::$stack[array_key_last(self::$stack)];
    }

    /**
     * Return the active tenant id.
     *
     * @return string
     */
    public static function id(): string
    {
        return self::current()->id;
    }

    /**
     * Return the active tenant slug.
     *
     * @return string
     */
    public static function slug(): string
    {
        return self::current()->slug;
    }

    /**
     * Run a callback with the supplied tenant context and always restore the previous context.
     *
     * @template TReturn
     * @param \App\KMP\TenantMetadata $tenant Tenant metadata
     * @param callable():TReturn $callback Callback to run
     * @return TReturn
     */
    public static function with(TenantMetadata $tenant, callable $callback): mixed
    {
        self::$stack[] = $tenant;
        try {
            return $callback();
        } finally {
            array_pop(self::$stack);
        }
    }
}
