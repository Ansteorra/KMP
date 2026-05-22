<?php
declare(strict_types=1);

namespace App\Services\Platform;

interface PlatformTotpVerifierInterface
{
    /**
     * Return true when production-grade TOTP verification is available.
     */
    public function isAvailable(): bool;

    /**
     * Verify a TOTP code for the platform user's secret reference.
     *
     * @param string $platformUserId Platform user UUID.
     * @param string|null $totpSecretRef Secret-store reference for the user.
     * @param string $totpCode User-provided TOTP code.
     * @return bool
     */
    public function verify(string $platformUserId, ?string $totpSecretRef, string $totpCode): bool;
}
