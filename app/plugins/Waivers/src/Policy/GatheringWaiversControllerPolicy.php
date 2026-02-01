<?php

declare(strict_types=1);

namespace Waivers\Policy;

use App\KMP\KmpIdentityInterface;
use App\Policy\BasePolicy;
use Authorization\Policy\ResultInterface;
use Cake\ORM\TableRegistry;

/**
 * GatheringWaivers Controller Authorization Policy
 *
 * Provides URL-based authorization for GatheringWaiversController actions.
 * Includes support for stewards who can access waiver upload and pending waiver
 * actions for gatherings they manage.
 *
 * @see /docs/5.7-waivers-plugin.md
 */
class GatheringWaiversControllerPolicy extends BasePolicy
{
    /**
     * Check if user can access needingWaivers action
     *
     * Determines if the user can view the list of gatherings that need waivers.
     * This action shows gatherings where the user has permission to upload waivers
     * and required waivers are missing.
     * 
     * Stewards can access this action if they are a steward for at least one
     * non-closed gathering.
     *
     * @param \App\KMP\KmpIdentityInterface $user User identity
     * @param array $urlProps URL properties for the action
     * @return \Authorization\Policy\ResultInterface|bool Authorization result
     */
    public function canNeedingWaivers(
        KmpIdentityInterface $user,
        array $urlProps,
    ): ResultInterface|bool {
        // Check standard permission first
        if ($this->_hasPolicyForUrl($user, __FUNCTION__, $urlProps)) {
            return true;
        }

        // Check if user is a steward for any non-closed gathering
        return $this->_isAnySteward($user, checkClosure: true);
    }

    /**
     * Check if user can access upload action
     *
     * @param \App\KMP\KmpIdentityInterface $user User identity
     * @param array $urlProps URL properties for the action
     * @return \Authorization\Policy\ResultInterface|bool Authorization result
     */
    public function canUpload(
        KmpIdentityInterface $user,
        array $urlProps,
    ): ResultInterface|bool {
        // Check standard permission first
        if ($this->_hasPolicyForUrl($user, __FUNCTION__, $urlProps)) {
            return true;
        }

        // Check if user is a steward for any non-closed gathering
        return $this->_isAnySteward($user, checkClosure: true);
    }

    /**
     * Check if user can access changeWaiverType action
     *
     * @param \App\KMP\KmpIdentityInterface $user User identity
     * @param array $urlProps URL properties for the action
     * @return \Authorization\Policy\ResultInterface|bool Authorization result
     */
    public function canChangeWaiverType(
        KmpIdentityInterface $user,
        array $urlProps,
    ): ResultInterface|bool {
        $method = __FUNCTION__;

        return $this->_hasPolicyForUrl($user, $method, $urlProps);
    }

    /**
     * Check if user can access changeActivities action
     *
     * @param \App\KMP\KmpIdentityInterface $user User identity
     * @param array $urlProps URL properties for the action
     * @return \Authorization\Policy\ResultInterface|bool Authorization result
     */
    public function canChangeActivities(
        KmpIdentityInterface $user,
        array $urlProps,
    ): ResultInterface|bool {
        $method = __FUNCTION__;

        return $this->_hasPolicyForUrl($user, $method, $urlProps);
    }

    /**
     * Check if user can access dashboard action
     *
     * Determines if the user can view the comprehensive waiver secretary dashboard.
     * This provides access to waiver statistics, compliance overview, and administrative
     * tools for managing waivers across all accessible branches.
     *
     * @param \App\KMP\KmpIdentityInterface $user User identity
     * @param array $urlProps URL properties for the action
     * @return \Authorization\Policy\ResultInterface|bool Authorization result
     */
    public function canDashboard(
        KmpIdentityInterface $user,
        array $urlProps,
    ): ResultInterface|bool {
        $method = __FUNCTION__;

        return $this->_hasPolicyForUrl($user, $method, $urlProps);
    }

    /**
     * Check if user can access mobileSelectGathering action
     *
     * Stewards can access mobile gathering selection for waiver uploads.
     *
     * @param \App\KMP\KmpIdentityInterface $user User identity
     * @param array $urlProps URL properties for the action
     * @return \Authorization\Policy\ResultInterface|bool Authorization result
     */
    public function canMobileSelectGathering(
        KmpIdentityInterface $user,
        array $urlProps,
    ): ResultInterface|bool {
        // Check standard permission first
        if ($this->_hasPolicyForUrl($user, __FUNCTION__, $urlProps)) {
            return true;
        }

        // Check if user is a steward for any non-closed gathering
        return $this->_isAnySteward($user, checkClosure: true);
    }

    /**
     * Check if user can access mobileUpload action
     *
     * Stewards can access mobile waiver upload for their gatherings.
     *
     * @param \App\KMP\KmpIdentityInterface $user User identity
     * @param array $urlProps URL properties for the action
     * @return \Authorization\Policy\ResultInterface|bool Authorization result
     */
    public function canMobileUpload(
        KmpIdentityInterface $user,
        array $urlProps,
    ): ResultInterface|bool {
        // Check standard permission first
        if ($this->_hasPolicyForUrl($user, __FUNCTION__, $urlProps)) {
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
