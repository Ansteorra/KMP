<?php
declare(strict_types=1);

namespace App\Services\Escrow;

use App\Services\Secrets\SensitiveString;

interface KekEscrowSplitterInterface
{
    /**
     * Split a KEK into escrow shares for sealed-envelope handling.
     *
     * Implementations must never persist plaintext shares or KEKs.
     *
     * @param \App\Services\Secrets\SensitiveString $kek Key-encryption key material
     * @param int $threshold Reassembly threshold
     * @param int $shareCount Total shares
     * @return list<\App\Services\Secrets\SensitiveString>
     */
    public function split(SensitiveString $kek, int $threshold, int $shareCount): array;

    /**
     * Reassemble plaintext shares during a controlled DR drill.
     *
     * @param list<\App\Services\Secrets\SensitiveString> $shares Share material
     * @return \App\Services\Secrets\SensitiveString
     */
    public function reassemble(array $shares): SensitiveString;
}
