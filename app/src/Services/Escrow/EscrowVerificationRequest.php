<?php
declare(strict_types=1);

namespace App\Services\Escrow;

use DateTimeImmutable;
use InvalidArgumentException;

final class EscrowVerificationRequest
{
    /**
     * @param array<string, mixed> $metadata Non-sensitive ceremony metadata
     */
    public function __construct(
        public readonly ?string $escrowCeremonyId,
        public readonly ?string $tenantId,
        public readonly string $keyName,
        public readonly string $keyVersion,
        public readonly int $threshold,
        public readonly int $shareCount,
        public readonly DateTimeImmutable $verifiedAt,
        public readonly ?string $verifiedByPlatformUserId,
        public readonly string $status,
        public readonly array $metadata = [],
        public readonly ?string $notes = null,
    ) {
        if ($this->keyName === '' || $this->keyVersion === '' || $this->status === '') {
            throw new InvalidArgumentException('Key name, key version, and status are required.');
        }
        if ($this->threshold < 2) {
            throw new InvalidArgumentException('Threshold must be at least 2.');
        }
        if ($this->shareCount < $this->threshold) {
            throw new InvalidArgumentException('Share count must be greater than or equal to threshold.');
        }
    }
}
