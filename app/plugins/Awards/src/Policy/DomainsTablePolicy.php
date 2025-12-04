<?php

declare(strict_types=1);

namespace Awards\Policy;

use App\KMP\KmpIdentityInterface;
use App\Policy\BasePolicy;

/**
 * Table-level authorization policy for Domains.
 *
 * Provides administrative access control for domain listing, bulk operations,
 * and organizational management. Includes scopeGridData() for DataverseGrid integration.
 *
 * See /docs/5.2.8-awards-domains-table-policy.md for complete documentation.
 *
 * @method bool canAdd(\App\KMP\KmpIdentityInterface $user, \Cake\ORM\Table $table, mixed ...$optionalArgs)
 * @method bool canIndex(\App\KMP\KmpIdentityInterface $user, \Cake\ORM\Table $table, mixed ...$optionalArgs)
 * @method bool canExport(\App\KMP\KmpIdentityInterface $user, \Cake\ORM\Table $table, mixed ...$optionalArgs)
 */
class DomainsTablePolicy extends BasePolicy
{
    /**
     * Check if user can access gridData scope (Dataverse grid data endpoint)
     * Uses the same authorization scope as the standard index action
     *
     * @param \App\KMP\KmpIdentityInterface $user User
     * @param mixed $query Query
     * @return mixed
     */
    public function scopeGridData(KmpIdentityInterface $user, mixed $query): mixed
    {
        return $this->scopeIndex($user, $query);
    }
}
