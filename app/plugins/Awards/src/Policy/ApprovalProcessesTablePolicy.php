<?php
declare(strict_types=1);

namespace Awards\Policy;

use App\KMP\KmpIdentityInterface;
use App\Model\Entity\BaseEntity;
use App\Policy\BasePolicy;
use Cake\ORM\Table;

class ApprovalProcessesTablePolicy extends BasePolicy
{
    /**
     * Authorize synchronizing open recommendation workflows.
     *
     * @param \App\KMP\KmpIdentityInterface $user Current identity.
     * @param \App\Model\Entity\BaseEntity|\Cake\ORM\Table $entity Authorized table context.
     * @param mixed ...$optionalArgs Additional authorization context.
     * @return bool
     */
    public function canSyncOpenRecommendations(
        KmpIdentityInterface $user,
        BaseEntity|Table $entity,
        ...$optionalArgs,
    ): bool {
        return $this->_hasPolicy($user, __FUNCTION__, $entity, ...$optionalArgs);
    }

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
