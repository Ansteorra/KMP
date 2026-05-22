<?php
declare(strict_types=1);

namespace App\Test\TestCase\Services\Platform;

use App\Services\Platform\PlatformTotpVerifierInterface;

class AcceptingTotpVerifier implements PlatformTotpVerifierInterface
{
    /**
     * Constructor.
     */
    public function __construct(private readonly string $acceptedCode)
    {
    }

    /**
     * @inheritDoc
     */
    public function isAvailable(): bool
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    public function verify(string $platformUserId, ?string $totpSecretRef, string $totpCode): bool
    {
        return $totpSecretRef !== null && $totpCode === $this->acceptedCode;
    }
}
