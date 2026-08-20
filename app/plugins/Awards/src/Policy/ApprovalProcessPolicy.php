<?php
declare(strict_types=1);

namespace Awards\Policy;

use App\KMP\KmpIdentityInterface;
use App\Model\Entity\BaseEntity;
use App\Policy\BasePolicy;

class ApprovalProcessPolicy extends BasePolicy
{
    /**
     * Authorize synchronizing outdated recommendations assigned to this process.
     */
    public function canSyncOpenRecommendations(
        KmpIdentityInterface $user,
        BaseEntity $entity,
        ...$optionalArgs,
    ): bool {
        return $this->_hasPolicy($user, __FUNCTION__, $entity, ...$optionalArgs);
    }
}
