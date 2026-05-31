<?php
declare(strict_types=1);

namespace App\Services\Security;

/**
 * Outcome of a rate-limit check.
 */
final class RateLimitResult
{
    /**
     * @param bool $allowed Whether the request is within the limit
     * @param int $remaining Remaining attempts in the current window
     * @param int $retryAfterSeconds Seconds until the window resets when blocked
     */
    public function __construct(
        public readonly bool $allowed,
        public readonly int $remaining,
        public readonly int $retryAfterSeconds,
    ) {
    }
}
