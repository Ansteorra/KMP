<?php
declare(strict_types=1);

namespace Awards\Policy;

use App\KMP\KmpIdentityInterface;
use App\Policy\BasePolicy;

/**
 * Table-level authorization policy for BestowalStates.
 *
 * @method bool canAdd(\App\KMP\KmpIdentityInterface $user, \Cake\ORM\Table $table, mixed ...$optionalArgs)
 * @method bool canIndex(\App\KMP\KmpIdentityInterface $user, \Cake\ORM\Table $table, mixed ...$optionalArgs)
 */
class BestowalStatesTablePolicy extends BasePolicy
{
    /**
     * @param \App\KMP\KmpIdentityInterface $user User
     * @param mixed $query Query
     * @return mixed
     */
    public function scopeGridData(KmpIdentityInterface $user, mixed $query): mixed
    {
        return $this->scopeIndex($user, $query);
    }
}
