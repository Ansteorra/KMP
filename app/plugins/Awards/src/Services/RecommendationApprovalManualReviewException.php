<?php
declare(strict_types=1);

namespace Awards\Services;

use RuntimeException;
use Throwable;

/**
 * Identifies an approval migration failure that safely degrades to manual review.
 */
class RecommendationApprovalManualReviewException extends RuntimeException
{
    public const REASON_NO_ELIGIBLE_APPROVERS = 'no_eligible_approvers';
    public const REASON_GATE_MISSING = 'approval_gate_missing';
    public const REASON_OWNERSHIP_AMBIGUOUS = 'approval_ownership_ambiguous';

    /**
     * @param string $reason Stable machine-readable reason.
     * @param string $message Diagnostic message for logs.
     * @param \Throwable|null $previous Previous exception.
     */
    public function __construct(
        private readonly string $reason,
        string $message,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    /**
     * Return the stable manual-review reason.
     *
     * @return string
     */
    public function getReason(): string
    {
        return $this->reason;
    }
}
