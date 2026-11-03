<?php
declare(strict_types=1);

namespace Awards\Policy;

use App\KMP\KmpIdentityInterface;
use App\Model\Entity\BaseEntity;
use App\Policy\BasePolicy;
use Cake\ORM\TableRegistry;

/**
 * Authorization policy for Awards court agendas.
 */
class CourtAgendaPolicy extends BasePolicy
{
    /**
     * @param \App\KMP\KmpIdentityInterface $user User.
     * @param \App\Model\Entity\BaseEntity $entity Agenda entity.
     * @param mixed ...$optionalArgs Context.
     * @return bool
     */
    public function canGathering(KmpIdentityInterface $user, BaseEntity $entity, ...$optionalArgs): bool
    {
        return $this->hasScopedAgendaPolicy($user, __FUNCTION__, $entity, ...$optionalArgs);
    }

    /**
     * @param \App\KMP\KmpIdentityInterface $user User.
     * @param \App\Model\Entity\BaseEntity $entity Agenda entity.
     * @param mixed ...$optionalArgs Context.
     * @return bool
     */
    public function canPrintAgenda(KmpIdentityInterface $user, BaseEntity $entity, ...$optionalArgs): bool
    {
        return $this->hasScopedAgendaPolicy($user, __FUNCTION__, $entity, ...$optionalArgs);
    }

    /**
     * @param \App\KMP\KmpIdentityInterface $user User.
     * @param \App\Model\Entity\BaseEntity $entity Agenda entity.
     * @param mixed ...$optionalArgs Context.
     * @return bool
     */
    public function canImport(KmpIdentityInterface $user, BaseEntity $entity, ...$optionalArgs): bool
    {
        return $this->canEdit($user, $entity, ...$optionalArgs);
    }

    /**
     * @param \App\KMP\KmpIdentityInterface $user User.
     * @param \App\Model\Entity\BaseEntity $entity Agenda entity.
     * @param mixed ...$optionalArgs Context.
     * @return bool
     */
    public function canEdit(KmpIdentityInterface $user, BaseEntity $entity, ...$optionalArgs): bool
    {
        return $this->hasScopedAgendaPolicy($user, __FUNCTION__, $entity, ...$optionalArgs);
    }

    /**
     * @param \App\KMP\KmpIdentityInterface $user User.
     * @param \App\Model\Entity\BaseEntity $entity Agenda entity.
     * @param mixed ...$optionalArgs Context.
     * @return bool
     */
    public function canAddSegment(KmpIdentityInterface $user, BaseEntity $entity, ...$optionalArgs): bool
    {
        return $this->canEdit($user, $entity, ...$optionalArgs);
    }

    /**
     * @param \App\KMP\KmpIdentityInterface $user User.
     * @param \App\Model\Entity\BaseEntity $entity Agenda entity.
     * @param mixed ...$optionalArgs Context.
     * @return bool
     */
    public function canAddBlock(KmpIdentityInterface $user, BaseEntity $entity, ...$optionalArgs): bool
    {
        return $this->canEdit($user, $entity, ...$optionalArgs);
    }

    /**
     * @param \App\KMP\KmpIdentityInterface $user User.
     * @param \App\Model\Entity\BaseEntity $entity Agenda entity.
     * @param mixed ...$optionalArgs Context.
     * @return bool
     */
    public function canAddBestowal(KmpIdentityInterface $user, BaseEntity $entity, ...$optionalArgs): bool
    {
        return $this->canEdit($user, $entity, ...$optionalArgs);
    }

    /**
     * @param \App\KMP\KmpIdentityInterface $user User.
     * @param \App\Model\Entity\BaseEntity $entity Agenda entity.
     * @param mixed ...$optionalArgs Context.
     * @return bool
     */
    public function canMoveToRoaming(KmpIdentityInterface $user, BaseEntity $entity, ...$optionalArgs): bool
    {
        return $this->canEdit($user, $entity, ...$optionalArgs);
    }

    /**
     * @param \App\KMP\KmpIdentityInterface $user User.
     * @param \App\Model\Entity\BaseEntity $entity Agenda entity.
     * @param mixed ...$optionalArgs Context.
     * @return bool
     */
    public function canUpdateItem(KmpIdentityInterface $user, BaseEntity $entity, ...$optionalArgs): bool
    {
        return $this->canEdit($user, $entity, ...$optionalArgs);
    }

    /**
     * @param \App\KMP\KmpIdentityInterface $user User.
     * @param \App\Model\Entity\BaseEntity $entity Agenda entity.
     * @param mixed ...$optionalArgs Context.
     * @return bool
     */
    public function canMoveItem(KmpIdentityInterface $user, BaseEntity $entity, ...$optionalArgs): bool
    {
        return $this->canEdit($user, $entity, ...$optionalArgs);
    }

    /**
     * @param \App\KMP\KmpIdentityInterface $user User.
     * @param \App\Model\Entity\BaseEntity $entity Agenda entity.
     * @param mixed ...$optionalArgs Context.
     * @return bool
     */
    public function canRemoveItem(KmpIdentityInterface $user, BaseEntity $entity, ...$optionalArgs): bool
    {
        return $this->canEdit($user, $entity, ...$optionalArgs);
    }

    /**
     * Authorize by host branch, assigned awards, or awards supported by scheduled sessions.
     *
     * Court agenda staff follow award ownership scope, while gatherings may be hosted
     * by a different branch. Eligible scheduled sessions allow planning before any
     * bestowals exist without widening permission to unrelated gatherings.
     *
     * @param \App\KMP\KmpIdentityInterface $user User.
     * @param string $policyMethod Policy method.
     * @param \App\Model\Entity\BaseEntity $entity Agenda entity.
     * @param mixed ...$optionalArgs Context.
     * @return bool
     */
    private function hasScopedAgendaPolicy(
        KmpIdentityInterface $user,
        string $policyMethod,
        BaseEntity $entity,
        ...$optionalArgs,
    ): bool {
        if ($this->_hasPolicy($user, $policyMethod, $entity, ...$optionalArgs)) {
            return true;
        }

        $branchIds = $this->_getBranchIdsForPolicy($user, $policyMethod);
        if ($branchIds === null) {
            return true;
        }
        if ($branchIds === [] || in_array(-10000000, $branchIds, true)) {
            return false;
        }

        $gatheringId = (int)($entity->get('gathering_id') ?? 0);
        if ($gatheringId <= 0) {
            return false;
        }

        $bestowals = TableRegistry::getTableLocator()->get('Awards.Bestowals');
        $query = $bestowals->find()->where(['Bestowals.gathering_id' => $gatheringId]);
        $query = $bestowals->addBranchScopeQuery($query, $branchIds);

        if ($query->first() !== null) {
            return true;
        }

        $scheduledActivityTypes = TableRegistry::getTableLocator()->get('GatheringScheduledActivities')->find()
            ->select(['GatheringScheduledActivities.gathering_activity_id'])
            ->where(['GatheringScheduledActivities.gathering_id' => $gatheringId]);

        return TableRegistry::getTableLocator()->get('Awards.AwardGatheringActivities')->find()
            ->innerJoinWith('Awards')
            ->where([
                'AwardGatheringActivities.gathering_activity_id IN' => $scheduledActivityTypes,
                'Awards.branch_id IN' => $branchIds,
            ])
            ->first() !== null;
    }
}
