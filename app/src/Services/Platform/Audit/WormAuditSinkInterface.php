<?php
declare(strict_types=1);

namespace App\Services\Platform\Audit;

interface WormAuditSinkInterface
{
    /**
     * Append one immutable mirror record for a platform audit event.
     *
     * @param array<string, mixed> $event Persisted platform audit event
     * @return void
     */
    public function append(array $event): void;
}
