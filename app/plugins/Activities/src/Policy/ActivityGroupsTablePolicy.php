<?php

declare(strict_types=1);

namespace Activities\Policy;

use Activities\Model\Table\ActivityGroupsTable;
use App\KMP\KmpIdentityInterface;
use App\Policy\BasePolicy;

/**
 * Table-level authorization policy for ActivityGroups.
 *
 * Provides authorization scoping for bulk operations and DataTables grid filtering.
 * Extends BasePolicy for RBAC integration.
 *
 * @package Activities\Policy
 * @see \App\Policy\BasePolicy For inherited RBAC functionality
 * @see /docs/5.6.5-activity-security-patterns.md For table policy patterns
 */
class ActivityGroupsTablePolicy extends BasePolicy
{
    /**
     * Scope grid data using the standard index authorization.
     *
     * @param \App\KMP\KmpIdentityInterface $user Authenticated user
     * @param mixed $query Query to scope
     * @return mixed Scoped query
     */
    public function scopeGridData(KmpIdentityInterface $user, mixed $query): mixed
    {
        return $this->scopeIndex($user, $query);
    }
}
