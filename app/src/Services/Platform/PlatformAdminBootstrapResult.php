<?php
declare(strict_types=1);

namespace App\Services\Platform;

use App\Services\Secrets\SensitiveString;

final class PlatformAdminBootstrapResult
{
    /**
     * @param list<\App\Services\Secrets\SensitiveString> $recoveryCodes
     */
    public function __construct(
        public readonly string $platformUserId,
        public readonly string $email,
        public readonly string $totpSecretRef,
        public readonly SensitiveString $initialPassword,
        public readonly SensitiveString $totpSecret,
        public readonly array $recoveryCodes,
    ) {
    }
}
