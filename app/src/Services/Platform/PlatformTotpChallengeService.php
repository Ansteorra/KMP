<?php
declare(strict_types=1);

namespace App\Services\Platform;

use App\Services\Security\RequestRateLimiter;
use Cake\Database\Connection;

/** Consume each TOTP moving factor once across login, recovery and sensitive actions. */
final class PlatformTotpChallengeService
{
    /** Bind the challenge verifier and shared platform database. */
    public function __construct(
        private readonly Connection $connection,
        private readonly PlatformTotpVerifierInterface $verifier,
    ) {
    }

    /** Reserve an attempt and atomically consume the matched TOTP counter. */
    public function consume(string $userId, ?string $secretRef, string $code): bool
    {
        $limiter = new RequestRateLimiter($this->connection);
        $limit = $limiter->attempt(RequestRateLimiter::BUCKET_PLATFORM_STEP_UP, $userId);
        if (!$limit->allowed) {
            return false;
        }
        $counter = $this->verifier->matchingCounter($secretRef, $code);
        if ($counter === null || $secretRef === null) {
            return false;
        }

        $accepted = $this->connection->execute(
            'UPDATE platform_users SET last_accepted_totp_counter = :counter WHERE id = :id ' .
            "AND totp_secret_ref = :secret AND status IN ('active', 'pending_enrollment') " .
            'AND (last_accepted_totp_counter IS NULL OR last_accepted_totp_counter < :counter)',
            ['counter' => $counter, 'id' => $userId, 'secret' => $secretRef],
        )->rowCount() === 1;
        if ($accepted) {
            $limiter->releaseSuccessfulAttempt($limit);
        }

        return $accepted;
    }
}
