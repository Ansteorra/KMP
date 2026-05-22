<?php
declare(strict_types=1);

namespace App\Services\Platform\Audit;

class NullWormAuditSink implements WormAuditSinkInterface
{
    /**
     * @inheritDoc
     */
    public function append(array $event): void
    {
    }
}
