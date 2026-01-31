<?php

declare(strict_types=1);

namespace Waivers\Policy;

use App\Policy\BasePolicy;
use App\KMP\KmpIdentityInterface;
use App\Model\Entity\BaseEntity;
use Cake\ORM\TableRegistry;

/**
 * GatheringWaivers Table Authorization Policy
 *
 * Provides table-level authorization for GatheringWaivers operations.
 * Inherits standard authorization methods from BasePolicy.
 * Includes steward support for waiver-related actions.
 *
 * @see /docs/5.7-waivers-plugin.md
 * @method bool canAdd(\App\KMP\KmpIdentityInterface $user, \Cake\ORM\Table $table, mixed ...$optionalArgs)
 * @method bool canIndex(\App\KMP\KmpIdentityInterface $user, \Cake\ORM\Table $table, mixed ...$optionalArgs)
 * @method bool canExport(\App\KMP\KmpIdentityInterface $user, \Cake\ORM\Table $table, mixed ...$optionalArgs)
 */
class GatheringWaiversTablePolicy extends BasePolicy
{
    public function canNeedingWaivers(KmpIdentityInterface $user, BaseEntity $entity, ...$optionalArgs): bool
    {
        // Check standard permission first
        if ($this->_hasPolicy($user, __FUNCTION__, $entity)) {
            return true;
        }

        // Check if user is a steward for any non-closed gathering
        return $this->_isAnySteward($user, checkClosure: true);
    }

    /**
     * Check if user is a steward for any gathering
     *
     * @param \App\KMP\KmpIdentityInterface $user The user
     * @param bool $checkClosure If true, only counts non-closed gatherings
     * @return bool True if user is a steward for at least one qualifying gathering
     */
    protected function _isAnySteward(KmpIdentityInterface $user, bool $checkClosure = false): bool
    {
        $userId = $user->getIdentifier();
        if (empty($userId)) {
            return false;
        }

        $gatheringStaffTable = TableRegistry::getTableLocator()->get('GatheringStaff');

        // Find all gatherings where user is a steward
        $stewardGatheringIds = $gatheringStaffTable->find()
            ->where([
                'GatheringStaff.member_id' => $userId,
                'GatheringStaff.is_steward' => true,
            ])
            ->select(['gathering_id'])
            ->all()
            ->extract('gathering_id')
            ->toArray();

        if (empty($stewardGatheringIds)) {
            return false;
        }

        // If checking closure, filter to non-closed gatherings
        if ($checkClosure) {
            $closuresTable = TableRegistry::getTableLocator()->get('Waivers.GatheringWaiverClosures');
            $closedGatheringIds = $closuresTable->getClosedGatheringIds($stewardGatheringIds);
            $openGatheringIds = array_diff($stewardGatheringIds, $closedGatheringIds);
            return !empty($openGatheringIds);
        }

        return true;
    }
}
