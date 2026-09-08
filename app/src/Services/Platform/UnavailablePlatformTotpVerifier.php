<?php
declare(strict_types=1);

namespace App\Services\Platform;

class UnavailablePlatformTotpVerifier implements PlatformTotpVerifierInterface
{
    /**
     * @inheritDoc
     */
    public function isAvailable(): bool
    {
        return false;
    }

    /**
     * @inheritDoc
     */
    public function matchingCounter(?string $totpSecretRef, string $totpCode): ?int
    {
        return null;
    }

    /** @inheritDoc */
    public function verify(string $platformUserId, ?string $totpSecretRef, string $totpCode): bool
    {
        return false;
    }
}
