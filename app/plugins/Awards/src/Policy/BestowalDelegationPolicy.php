<?php
declare(strict_types=1);

namespace Awards\Policy;

use App\KMP\KmpIdentityInterface;
use App\Policy\BasePolicy;
use Awards\Model\Entity\BestowalDelegation;
use Awards\Services\BestowalDelegationAccessService;
use Cake\ORM\TableRegistry;

/** Delegated authority never authorizes another grant. */
class BestowalDelegationPolicy extends BasePolicy
{
    /** Only native award read authority can be shared; inherited authority is excluded. */
    public function canGrant(KmpIdentityInterface $user, BestowalDelegation $grant): bool
    {
        if ((int)$grant->gathering_id <= 0 || (int)$grant->branch_id <= 0) {
            return false;
        }

        return (new BestowalPolicy())->hasNativePermission(
            $user,
            'canView',
            (new BestowalDelegationAccessService())->probe((int)$grant->gathering_id, (int)$grant->branch_id),
        );
    }

    /** Grantors may revoke even after their native award authority expires. */
    public function canRevoke(KmpIdentityInterface $user, BestowalDelegation $grant): bool
    {
        return (int)$grant->grantor_id === (int)$user->getIdentifier();
    }

    /** Participants may read their history; native award readers may start a grant. */
    public function canGathering(KmpIdentityInterface $user, BestowalDelegation $grant): bool
    {
        if ((int)$grant->gathering_id <= 0) {
            return false;
        }
        $isParticipant = TableRegistry::getTableLocator()->get('Awards.BestowalDelegations')->exists([
            'gathering_id' => $grant->gathering_id,
            'OR' => ['grantor_id' => (int)$user->getIdentifier(), 'delegate_id' => (int)$user->getIdentifier()],
        ]);
        if ($isParticipant) {
            return true;
        }
        $branches = TableRegistry::getTableLocator()->get('Awards.Awards')->find()
            ->select(['branch_id'])->where(['branch_id IS NOT' => null])->distinct(['branch_id'])->all();
        foreach ($branches as $award) {
            $probe = new BestowalDelegation([
                'gathering_id' => $grant->gathering_id, 'branch_id' => $award->branch_id,
            ]);
            if ($this->canGrant($user, $probe)) {
                return true;
            }
        }

        return false;
    }
}
