<?php
declare(strict_types=1);

namespace App\Services\Security;

/**
 * Builds session ini settings while keeping cookies host-only by default.
 */
final class SessionCookieConfig
{
    /**
     * Apply the optional cookie domain override; empty means no Domain attribute.
     *
     * @param array<string, mixed> $ini Session ini settings
     * @return array<string, mixed>
     */
    public static function withDomainOverride(array $ini): array
    {
        $domain = trim((string)env('KMP_SESSION_COOKIE_DOMAIN', env('SESSION_COOKIE_DOMAIN', '')));
        if ($domain === '') {
            unset($ini['session.cookie_domain']);

            return $ini;
        }

        $ini['session.cookie_domain'] = $domain;

        return $ini;
    }
}
