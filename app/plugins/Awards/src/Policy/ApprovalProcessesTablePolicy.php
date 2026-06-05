<?php
declare(strict_types=1);

namespace Awards\Policy;

use App\KMP\KmpIdentityInterface;
use App\Policy\BasePolicy;

class ApprovalProcessesTablePolicy extends BasePolicy
{
    /**
     * Apply approval process grid scope.
     *
     * @param \App\KMP\KmpIdentityInterface $user Current identity
     * @param mixed $query Query
     * @return mixed
     */
    public function scopeGridData(KmpIdentityInterface $user, mixed $query): mixed
    {
        return $this->scopeIndex($user, $query);
    }
}
