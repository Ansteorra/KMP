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
    /**
     * Check if user can download.
     *
     * @param KmpIdentityInterface $user
     * @param BaseEntity $entity
     * @return bool
     */
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

    /**
     * Check if user can preview.
     *
     * @param KmpIdentityInterface $user
     * @param BaseEntity $entity
     * @return bool
     */
    public function canPreview(KmpIdentityInterface $user, BaseEntity $entity, ...$optionalArgs): bool
    {
        $method = __FUNCTION__;
        return $this->_hasPolicy($user, $method, $entity);
    }

    /**
     * Check if user can change waiver type.
     *
     * @param KmpIdentityInterface $user
     * @param BaseEntity $entity
     * @return bool
     */
    public function canChangeWaiverType(KmpIdentityInterface $user, BaseEntity $entity, ...$optionalArgs): bool
    {
        $method = __FUNCTION__;
        return $this->_hasPolicy($user, $method, $entity);
    }

    /**
     * Check if user can view gathering waivers.
     *
     * @param KmpIdentityInterface $user
     * @param BaseEntity $entity
     * @return bool
     */
    public function canViewGatheringWaivers(KmpIdentityInterface $user, BaseEntity $entity, ...$optionalArgs): bool
    {
        // Check standard permission first
        if ($this->_hasPolicy($user, __FUNCTION__, $entity)) {
            return true;
        }

        // Check if user is a steward for this gathering (stewards can view waivers)
        return $this->_isGatheringStewardForWaiver($user, $entity);
    }

    /**
     * Check if user can needing waivers.
     *
     * @param KmpIdentityInterface $user
     * @param BaseEntity $entity
     * @return bool
     */
    public function canNeedingWaivers(KmpIdentityInterface $user, BaseEntity $entity, ...$optionalArgs): bool
    {
        // Check standard permission first
        if ($this->_hasPolicy($user, __FUNCTION__, $entity)) {
            return true;
        }

        // Check if user is a steward for any non-closed gathering
        return $this->_isGatheringStewardForWaiver($user, $entity, checkClosure: true);
    }

    /**
     * Check if user can upload waivers.
     *
     * @param KmpIdentityInterface $user
     * @param BaseEntity $entity
     * @return bool
     */
    public function canUploadWaivers(KmpIdentityInterface $user, BaseEntity $entity, ...$optionalArgs): bool
    {
        // Check standard permission first
        if ($this->_hasPolicy($user, __FUNCTION__, $entity)) {
            return true;
        }

        // Check if user is a steward for this gathering AND gathering is not closed
        return $this->_isGatheringStewardForWaiver($user, $entity, checkClosure: true);
    }

    /**
     * Check if user can close waivers.
     *
     * @param KmpIdentityInterface $user
     * @param BaseEntity $entity
     * @return bool
     */
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
     * Check if user can remove a gathering activity after waivers have been submitted.
     *
     * This is reserved for waiver managers and still blocks removal when it would
     * orphan already-submitted waivers by removing the last activity that requires
     * a submitted waiver type.
     *
     * @param \App\KMP\KmpIdentityInterface $user The user.
     * @param \App\Model\Entity\BaseEntity $entity A GatheringWaiver entity with gathering_id.
     * @param mixed ...$optionalArgs Optional args, first item must be activity id.
     * @return bool
     */
    public function canRemoveGatheringActivity(KmpIdentityInterface $user, BaseEntity $entity, ...$optionalArgs): bool
    {
        $activityId = isset($optionalArgs[0]) ? (int)$optionalArgs[0] : 0;
        if ($activityId <= 0 || empty($entity->gathering_id)) {
            return false;
        }

        if (!$this->_canManageSubmittedWaivers($user, $entity)) {
            return false;
        }

        return !$this->_wouldOrphanSubmittedWaivers((int)$entity->gathering_id, $activityId);
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

    /**
     * Determine if user can manage submitted waivers as a waiver secretary role.
     *
     * @param \App\KMP\KmpIdentityInterface $user The user.
     * @param \App\Model\Entity\BaseEntity $entity The waiver entity context.
     * @return bool
     */
    protected function _canManageSubmittedWaivers(KmpIdentityInterface $user, BaseEntity $entity): bool
    {
        $controllerPolicy = new GatheringWaiversControllerPolicy();

        if ($controllerPolicy->canChangeActivities($user, [
            'plugin' => 'Waivers',
            'controller' => 'GatheringWaivers',
            'action' => 'changeActivities',
        ])) {
            return true;
        }

        if ($controllerPolicy->canDashboard($user, [
            'plugin' => 'Waivers',
            'controller' => 'GatheringWaivers',
            'action' => 'dashboard',
        ])) {
            return true;
        }

        return $this->_hasPolicy($user, 'canRemoveGatheringActivity', $entity);
    }

    /**
     * Check if removing an activity would orphan submitted waivers for the gathering.
     *
     * @param int $gatheringId Gathering id.
     * @param int $activityId Activity id being removed.
     * @return bool True when submitted waivers would become unsupported.
     */
    protected function _wouldOrphanSubmittedWaivers(int $gatheringId, int $activityId): bool
    {
        $GatheringActivityWaivers = TableRegistry::getTableLocator()->get('Waivers.GatheringActivityWaivers');

        $activityWaiverTypeIds = $GatheringActivityWaivers->find()
            ->select(['waiver_type_id'])
            ->where([
                'GatheringActivityWaivers.gathering_activity_id' => $activityId,
                'GatheringActivityWaivers.deleted IS' => null,
            ])
            ->distinct(['waiver_type_id'])
            ->all()
            ->extract('waiver_type_id')
            ->map(fn ($waiverTypeId) => (int)$waiverTypeId)
            ->toList();

        if (empty($activityWaiverTypeIds)) {
            return false;
        }

        $GatheringWaivers = TableRegistry::getTableLocator()->get('Waivers.GatheringWaivers');
        $submittedWaiverTypeIds = $GatheringWaivers->find()
            ->select(['waiver_type_id'])
            ->where([
                'GatheringWaivers.gathering_id' => $gatheringId,
                'GatheringWaivers.waiver_type_id IN' => $activityWaiverTypeIds,
            ])
            ->distinct(['waiver_type_id'])
            ->all()
            ->extract('waiver_type_id')
            ->map(fn ($waiverTypeId) => (int)$waiverTypeId)
            ->toList();

        if (empty($submittedWaiverTypeIds)) {
            return false;
        }

        $remainingWaiverTypeIds = $GatheringActivityWaivers->find()
            ->select(['waiver_type_id'])
            ->innerJoinWith('GatheringActivities.Gatherings', function ($q) use ($gatheringId, $activityId) {
                return $q->where([
                    'Gatherings.id' => $gatheringId,
                    'GatheringActivities.id !=' => $activityId,
                ]);
            })
            ->where(['GatheringActivityWaivers.deleted IS' => null])
            ->distinct(['waiver_type_id'])
            ->all()
            ->extract('waiver_type_id')
            ->map(fn ($waiverTypeId) => (int)$waiverTypeId)
            ->toList();

        return !empty(array_diff($submittedWaiverTypeIds, $remainingWaiverTypeIds));
    }
}
