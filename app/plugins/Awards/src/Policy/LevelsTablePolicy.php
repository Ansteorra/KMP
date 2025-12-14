<?php

declare(strict_types=1);

namespace Awards\Policy;

use App\KMP\KmpIdentityInterface;
use App\Policy\BasePolicy;

/**
 * Table-level authorization policy for Levels in the Awards plugin.
 *
 * Manages access control for level queries, bulk operations, and hierarchical data access.
 * Includes Dataverse grid support via scopeGridData().
 *
 * @see \App\Policy\BasePolicy Base table authorization functionality
 * @see \Awards\Model\Table\LevelsTable Level data management
 * @see /docs/5.2.12-awards-levels-table-policy.md Full documentation
 */
class LevelsTablePolicy extends BasePolicy
{
    /**
     * Scope query for Dataverse grid data endpoint.
     *
     * Delegates to scopeIndex() for consistent authorization.
     *
     * @param \App\KMP\KmpIdentityInterface $user User requesting access
     * @param mixed $query Query to scope
     * @return mixed Scoped query
     */
    public function scopeGridData(KmpIdentityInterface $user, mixed $query): mixed
    {
        return $this->scopeIndex($user, $query);
    }
}
