<?php

declare(strict_types=1);

namespace Awards\Policy;

use App\Policy\BasePolicy;

/**
 * Table-level authorization policy for RecommendationsStatesLogs in the Awards plugin.
 *
 * Manages access control for audit trail queries and compliance reporting.
 * All authorization methods inherited from BasePolicy.
 *
 * @see \App\Policy\BasePolicy Base table authorization functionality
 * @see \Awards\Model\Table\RecommendationsStatesLogsTable Audit trail data management
 * @see /docs/5.2.15-awards-recommendations-states-log-table-policy.md Full documentation
 */
class RecommendationsStatesLogTablePolicy extends BasePolicy {}
