<?php
declare(strict_types=1);

namespace App\Services\Escrow;

interface EscrowVerificationRecorderInterface
{
    /**
     * Record a KEK escrow verification ceremony without persisting secret material.
     *
     * @param \App\Services\Escrow\EscrowVerificationRequest $request Verification metadata
     * @return array<string, mixed> Persisted record
     */
    public function recordVerification(EscrowVerificationRequest $request): array;
}
