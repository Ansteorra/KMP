<?php
declare(strict_types=1);

namespace App\Services\Escrow;

use InvalidArgumentException;

final class EscrowCeremonyRequest
{
    /**
     * @param array<string, mixed> $metadata Non-sensitive ceremony metadata
     * @param list<array<string, mixed>> $shareEnvelopes Non-sensitive envelope labels/custody metadata
     */
    public function __construct(
        public readonly ?string $tenantId,
        public readonly string $keyName,
        public readonly string $keyVersion,
        public readonly int $threshold,
        public readonly int $shareCount,
        public readonly string $status = 'sealed',
        public readonly array $metadata = [],
        public readonly ?string $notes = null,
        public readonly ?string $createdByPlatformUserId = null,
        public readonly array $shareEnvelopes = [],
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
