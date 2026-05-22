<?php
declare(strict_types=1);

namespace App\Services\Platform;

use App\Services\Secrets\SensitiveString;
use DateTimeImmutable;

final class PlatformEmergencyLoginResult
{
    /**
     * Constructor.
     */
    public function __construct(
        public readonly string $platformUserId,
        public readonly string $sessionId,
        public readonly SensitiveString $sessionToken,
        public readonly DateTimeImmutable $expiresAt,
    ) {
    }
}
