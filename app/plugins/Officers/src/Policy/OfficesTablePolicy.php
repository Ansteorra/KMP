<?php

declare(strict_types=1);

namespace Officers\Policy;

use App\Model\Entity\DepartmentsTable;
use App\KMP\KmpIdentityInterface;
use App\Policy\BasePolicy;

use Cake\ORM\Query\SelectQuery;

/**
 * Offices Table Authorization Policy
 *
 * Provides table-level authorization control for Offices table operations.
 * Implements query scoping via scopeGridData() which delegates to scopeIndex().
 *
 * @see /docs/5.1-officers-plugin.md
 */
class OfficesTablePolicy extends BasePolicy
{
    /**
     * Apply authorization scope to grid data queries.
     *
     * @param KmpIdentityInterface $user The current user
     * @param SelectQuery $query The query to scope
     * @return SelectQuery
     */
    public function scopeGridData(KmpIdentityInterface $user, SelectQuery $query): SelectQuery
    {
        return $this->scopeIndex($user, $query);
    }
}
