<?php

declare(strict_types=1);

namespace Waivers\Policy;

use App\Policy\BasePolicy;
use App\KMP\KmpIdentityInterface;
use App\Model\Entity\BaseEntity;
use Cake\ORM\TableRegistry;

/**
 * GatheringWaiver Entity Authorization Policy
 *
 * Provides entity-level authorization for gathering waiver operations.
 * Inherits standard CRUD operations from BasePolicy and adds waiver-specific
 * authorization methods.
 * 
 * Stewards (members marked as is_steward=true in gathering_staff) can upload
 * waivers and view pending waivers for gatherings they manage, as long as
 * the gathering has not been closed for waivers.
 *
 * @see /docs/5.7-waivers-plugin.md
 * @method bool canAdd(\App\KMP\KmpIdentityInterface $user, \Cake\ORM\Table $table, mixed ...$optionalArgs)
 * @method bool canIndex(\App\KMP\KmpIdentityInterface $user, \Cake\ORM\Table $table, mixed ...$optionalArgs)
 * @method bool canExport(\App\KMP\KmpIdentityInterface $user, \Cake\ORM\Table $table, mixed ...$optionalArgs)
 */
class GatheringWaiverPolicy extends BasePolicy
{
    public function canDownload(KmpIdentityInterface $user, BaseEntity $entity, ...$optionalArgs): bool
    {
        $method = __FUNCTION__;
        return $this->_hasPolicy($user, $method, $entity);
    }

    /**
     * Authorize inline PDF viewing for a waiver.
     *
     * `@param` \App\KMP\KmpIdentityInterface $user The current user.
     * `@param` \App\Model\Entity\BaseEntity $entity The waiver entity.
     * `@param` mixed ...$optionalArgs Optional arguments.
     * `@return` bool True when authorized.
     */
    public function canInlinePdf(KmpIdentityInterface $user, BaseEntity $entity, ...$optionalArgs): bool
    {
        // Reuse existing download permission so no new permission seed is required.
        return $this->canDownload($user, $entity, ...$optionalArgs);
    }

    public function canPreview(KmpIdentityInterface $user, BaseEntity $entity, ...$optionalArgs): bool
    {
        $method = __FUNCTION__;
        return $this->_hasPolicy($user, $method, $entity);
    }

    public function canChangeWaiverType(KmpIdentityInterface $user, BaseEntity $entity, ...$optionalArgs): bool
    {
        $method = __FUNCTION__;
        return $this->_hasPolicy($user, $method, $entity);
    }

    public function canViewGatheringWaivers(KmpIdentityInterface $user, BaseEntity $entity, ...$optionalArgs): bool
    {
        // Check standard permission first
        if ($this->_hasPolicy($user, __FUNCTION__, $entity)) {
            return true;
        }

        // Check if user is a steward for this gathering (stewards can view waivers)
        return $this->_isGatheringStewardForWaiver($user, $entity);
    }

    public function canNeedingWaivers(KmpIdentityInterface $user, BaseEntity $entity, ...$optionalArgs): bool
    {
        // Check standard permission first
        if ($this->_hasPolicy($user, __FUNCTION__, $entity)) {
            return true;
        }

        // Check if user is a steward for any non-closed gathering
        return $this->_isGatheringStewardForWaiver($user, $entity, checkClosure: true);
    }

    public function canUploadWaivers(KmpIdentityInterface $user, BaseEntity $entity, ...$optionalArgs): bool
    {
        // Check standard permission first
        if ($this->_hasPolicy($user, __FUNCTION__, $entity)) {
            return true;
        }

        // Check if user is a steward for this gathering AND gathering is not closed
        return $this->_isGatheringStewardForWaiver($user, $entity, checkClosure: true);
    }

    public function canCloseWaivers(KmpIdentityInterface $user, BaseEntity $entity, ...$optionalArgs): bool
    {
        $method = __FUNCTION__;
        return $this->_hasPolicy($user, $method, $entity);
    }

    /**
     * Check if user can decline a waiver.
     * Business rules (30-day limit, not already declined) are checked in the controller.
     */
    public function canDecline(KmpIdentityInterface $user, BaseEntity $entity, ...$optionalArgs): bool
    {
        $method = __FUNCTION__;
        return $this->_hasPolicy($user, $method, $entity);
    }

    /**
     * Check if user is a steward for the gathering associated with this waiver
     *
     * Stewards are members assigned to a gathering's staff with is_steward=true.
     * They can upload waivers and see pending waivers for their gatherings.
     *
     * If no gathering is specified (empty entity), checks if user is a steward
     * for ANY non-closed gathering.
     *
     * @param \App\KMP\KmpIdentityInterface $user The user
     * @param \App\Model\Entity\BaseEntity $entity The GatheringWaiver entity (must have gathering or gathering_id)
     * @param bool $checkClosure If true, also verify the gathering is not closed for waivers
     * @return bool True if user is a steward for this gathering (or any non-closed gathering if no specific gathering)
     */
    protected function _isGatheringStewardForWaiver(KmpIdentityInterface $user, BaseEntity $entity, bool $checkClosure = false): bool
    {
        $userId = $user->getIdentifier();
        if (empty($userId)) {
            return false;
        }

        // Get gathering ID from the waiver entity
        $gatheringId = null;
        if (isset($entity->gathering_id)) {
            $gatheringId = $entity->gathering_id;
        } elseif (isset($entity->gathering) && isset($entity->gathering->id)) {
            $gatheringId = $entity->gathering->id;
        }

        $gatheringStaffTable = TableRegistry::getTableLocator()->get('GatheringStaff');

        // If no specific gathering, check if user is a steward for ANY non-closed gathering
        if (empty($gatheringId)) {
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

        // Check if gathering is closed for waivers (if requested)
        if ($checkClosure) {
            $closuresTable = TableRegistry::getTableLocator()->get('Waivers.GatheringWaiverClosures');
            if ($closuresTable->isGatheringClosed((int)$gatheringId)) {
                return false;
            }
        }

        // Check if user is a steward for this specific gathering
        $stewardRecord = $gatheringStaffTable->find()
            ->where([
                'GatheringStaff.gathering_id' => (int)$gatheringId,
                'GatheringStaff.member_id' => $userId,
                'GatheringStaff.is_steward' => true,
            ])
            ->first();

        return $stewardRecord !== null;
    }
}
