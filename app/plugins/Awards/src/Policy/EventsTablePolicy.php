<?php

declare(strict_types=1);

namespace Awards\Policy;

use App\Policy\BasePolicy;

/**
 * Table-level authorization policy for Events in the Awards plugin.
 *
 * Manages access control for event queries, bulk operations, and temporal data access.
 * All authorization methods inherited from BasePolicy.
 *
 * @see \App\Policy\BasePolicy Base table authorization functionality
 * @see \Awards\Model\Table\EventsTable Event data management
 * @see /docs/5.2.10-awards-events-table-policy.md Full documentation
 */
class EventsTablePolicy extends BasePolicy {}
