<?php
declare(strict_types=1);

namespace Awards\Services;

use App\KMP\KmpIdentityInterface;
use App\Model\Entity\Gathering;
use App\Model\Entity\GatheringActivity;
use App\Policy\GatheringActivityPolicy;
use App\Services\GatheringActivityDelegationProviderInterface;
use Awards\Policy\BestowalPolicy;
use Cake\ORM\TableRegistry;

/** Event delegates may prepare court activities eligible for their granting award group. */
class BestowalGatheringActivityDelegationProvider implements GatheringActivityDelegationProviderInterface
{
    /** @inheritDoc */
    public function canAssign(KmpIdentityInterface $user, GatheringActivity $activity, Gathering $gathering): bool
    {
        if ((int)$gathering->id <= 0 || (int)$activity->id <= 0) {
            return false;
        }
        if ($this->canPrepareOwnAwardCourt($user, $activity, $gathering)) {
            return true;
        }
        $access = new BestowalDelegationAccessService();
        foreach ($access->grantsFor((int)$user->getIdentifier(), (int)$gathering->id) as $grant) {
            $branchId = (int)$grant->branch_id;
            $eligible = TableRegistry::getTableLocator()->get('Awards.AwardGatheringActivities')->find()
                ->innerJoinWith('Awards', fn($query) => $query->where(['Awards.branch_id' => $branchId]))
                ->where(['AwardGatheringActivities.gathering_activity_id' => $activity->id])->count() > 0;
            if (
                $eligible
                && (new BestowalPolicy())->hasNativePermission(
                    $grant->grantor,
                    'canView',
                    $access->probe((int)$gathering->id, $branchId),
                )
                && (new GatheringActivityPolicy())->canAssignNatively(
                    $grant->grantor,
                    $activity,
                    $gathering,
                    $branchId,
                )
            ) {
                return true;
            }
        }

        return false;
    }

    /** Native award staff use the same awarding-group context as their event delegates. */
    private function canPrepareOwnAwardCourt(
        KmpIdentityInterface $user,
        GatheringActivity $activity,
        Gathering $gathering,
    ): bool {
        $tables = TableRegistry::getTableLocator();
        $branches = $tables->get('Awards.AwardGatheringActivities')->find()
            ->select(['branch_id' => 'Awards.branch_id'])->innerJoinWith('Awards')
            ->where(['AwardGatheringActivities.gathering_activity_id' => $activity->id,
                'Awards.branch_id IS NOT' => null])->distinct(['Awards.branch_id'])->all();
        $access = new BestowalDelegationAccessService();
        foreach ($branches as $branch) {
            $branchId = (int)$branch->branch_id;
            if (
                !(new BestowalPolicy())->hasNativePermission(
                    $user,
                    'canView',
                    $access->probe((int)$gathering->id, $branchId),
                )
                || !(new GatheringActivityPolicy())->canAssignNatively($user, $activity, $gathering, $branchId)
            ) {
                continue;
            }
            if (
                $tables->get('Awards.Bestowals')->find()->innerJoinWith('Awards')->where([
                    'Bestowals.gathering_id' => $gathering->id, 'Awards.branch_id' => $branchId,
                ])->first() !== null
            ) {
                return true;
            }
            $scheduled = $tables->get('GatheringScheduledActivities')->find()
                ->select(['gathering_activity_id'])->where(['gathering_id' => $gathering->id]);
            if (
                $tables->get('Awards.AwardGatheringActivities')->find()->innerJoinWith('Awards')->where([
                    'AwardGatheringActivities.gathering_activity_id IN' => $scheduled, 'Awards.branch_id' => $branchId,
                ])->first() !== null
            ) {
                return true;
            }
            // Selecting this event for an outgoing grant also provides explicit event context.
            $grants = $tables->get('Awards.BestowalDelegations');
            $outgoing = $grants->find()->where([
                'gathering_id' => $gathering->id, 'branch_id' => $branchId,
                'grantor_id' => (int)$user->getIdentifier(), 'revoked_at IS' => null,
            ]);
            if ($grants->getConnection()->inTransaction()) {
                $outgoing->epilog('FOR SHARE');
            }
            if ($outgoing->first() !== null) {
                return true;
            }
        }

        return false;
    }
}
