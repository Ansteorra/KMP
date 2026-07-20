<?php
declare(strict_types=1);

namespace App\KMP;

/**
 * Stable DOM ids for Dataverse grid rows (Turbo Stream replace/remove targets).
 */
final class GridRowDomId
{
    /**
     * Build row id from table turbo-frame id (e.g. recommendations-grid-table → recommendations-grid-row-42).
     */
    public static function fromTableFrameId(string $tableFrameId, string|int $rowId): string
    {
        $prefix = preg_replace('/-table$/', '', $tableFrameId) ?? $tableFrameId;

        return $prefix . '-row-' . $rowId;
    }
}
