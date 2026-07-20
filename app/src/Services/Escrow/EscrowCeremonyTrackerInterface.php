<?php
declare(strict_types=1);

namespace App\Services\Escrow;

interface EscrowCeremonyTrackerInterface
{
    /**
     * Record a sealed-envelope escrow ceremony without storing plaintext shares.
     *
     * @param \App\Services\Escrow\EscrowCeremonyRequest $request Ceremony metadata
     * @return array<string, mixed> Persisted ceremony record
     */
    public function recordCeremony(EscrowCeremonyRequest $request): array;
}
