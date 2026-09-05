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
     * Find the moving factor for the supplied code.
     *
     * @param string|null $totpSecretRef Secret-store reference for the user.
     * @param string $totpCode User-provided TOTP code.
     * @return int|null
     */
    public function matchingCounter(?string $totpSecretRef, string $totpCode): ?int;

    /** Verify only the code; authentication callers must consume the counter. */
    public function verify(string $platformUserId, ?string $totpSecretRef, string $totpCode): bool;
}
