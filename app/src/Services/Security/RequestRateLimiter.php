<?php
declare(strict_types=1);

namespace App\Services\Security;

use App\Services\Cache\TenantAwareCache;
use InvalidArgumentException;

/**
 * Fixed-window request rate limiter backed by tenant-aware cache.
 */
class RequestRateLimiter
{
    public const BUCKET_EMAIL_TAKEN = 'members.email_taken';

    public const BUCKET_SEARCH_MEMBERS = 'members.search_members';

    public const BUCKET_GITHUB_ISSUE = 'github.issue_submit';

    /** @var array<string, array{max: int, window: int}> */
    private const LIMITS = [
        self::BUCKET_EMAIL_TAKEN => [
            'max' => 10,
            'window' => 900,
        ],
        self::BUCKET_SEARCH_MEMBERS => [
            'max' => 15,
            'window' => 900,
        ],
        self::BUCKET_GITHUB_ISSUE => [
            'max' => 5,
            'window' => 3600,
        ],
    ];

    /**
     * @param \App\Services\Cache\TenantAwareCache $cache Tenant-scoped cache
     * @param string $cacheConfig Cake cache config name
     */
    public function __construct(
        private readonly TenantAwareCache $cache = new TenantAwareCache(),
        private readonly string $cacheConfig = 'default',
    ) {
    }

    /**
     * Record an attempt and return whether it is allowed.
     *
     * Limits are sized for anonymous form helpers: a few valid checks plus
     * roughly five user mistakes within a fifteen-minute window.
     *
     * @param string $bucket Rate-limit bucket identifier
     * @param string $clientKey Client identifier (typically IP address)
     * @return \App\Services\Security\RateLimitResult
     */
    public function attempt(string $bucket, string $clientKey): RateLimitResult
    {
        $limits = self::LIMITS[$bucket] ?? throw new InvalidArgumentException("Unknown rate limit bucket: {$bucket}");
        $maxAttempts = $limits['max'];
        $windowSeconds = $limits['window'];
        $now = time();

        $cacheKey = sprintf('rate_limit:%s:%s', $bucket, $this->normalizeClientKey($clientKey));
        $state = $this->cache->read($cacheKey, $this->cacheConfig);

        if (!is_array($state) || !isset($state['count'], $state['reset_at']) || (int)$state['reset_at'] <= $now) {
            $this->cache->write($cacheKey, [
                'count' => 1,
                'reset_at' => $now + $windowSeconds,
            ], $this->cacheConfig);

            return new RateLimitResult(true, $maxAttempts - 1, 0);
        }

        $count = (int)$state['count'];
        $resetAt = (int)$state['reset_at'];
        $retryAfter = max(1, $resetAt - $now);

        if ($count >= $maxAttempts) {
            return new RateLimitResult(false, 0, $retryAfter);
        }

        $this->cache->write($cacheKey, [
            'count' => $count + 1,
            'reset_at' => $resetAt,
        ], $this->cacheConfig);

        return new RateLimitResult(true, $maxAttempts - $count - 1, 0);
    }

    /**
     * Sanitize client key for cache storage.
     *
     * @param string $clientKey Raw client key
     * @return string
     */
    private function normalizeClientKey(string $clientKey): string
    {
        $clientKey = trim($clientKey);
        if ($clientKey === '') {
            return 'unknown';
        }

        return preg_replace('/[^A-Za-z0-9_.:-]+/', '_', $clientKey) ?? 'unknown';
    }
}
