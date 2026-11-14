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
        return (int)$activity->id > 0 && $this->canAssignAny($user, [$activity], $gathering);
    }

    /** @inheritDoc */
    public function canAssignAny(KmpIdentityInterface $user, array $activities, Gathering $gathering): bool
    {
        if ((int)$gathering->id <= 0 || $activities === []) {
            return false;
        }
        $activityIds = array_map(static fn(GatheringActivity $activity): int => (int)$activity->id, $activities);
        $links = TableRegistry::getTableLocator()->get('Awards.AwardGatheringActivities')->find()
            ->select(['gathering_activity_id', 'branch_id' => 'Awards.branch_id'])->innerJoinWith('Awards')
            ->where(['AwardGatheringActivities.gathering_activity_id IN' => $activityIds,
                'Awards.branch_id IS NOT' => null])->distinct(['gathering_activity_id', 'Awards.branch_id'])->all();
        $branchesByActivity = [];
        foreach ($links as $link) {
            $branchesByActivity[(int)$link->gathering_activity_id][] = (int)$link->branch_id;
        }
        $access = new BestowalDelegationAccessService();
        $policy = new GatheringActivityPolicy();
        $bestowalPolicy = new BestowalPolicy();
        $grants = null;
        $nativeContexts = [];
        foreach ($activities as $activity) {
            $branches = $branchesByActivity[(int)$activity->id] ?? [];
            foreach ($branches as $branchId) {
                if (
                    $bestowalPolicy->hasNativePermission(
                        $user,
                        'canView',
                        $access->probe((int)$gathering->id, $branchId),
                    )
                    && $policy->canAssignNatively($user, $activity, $gathering, $branchId)
                ) {
                    $nativeContexts[$branchId] ??= $this->hasNativeAwardContext($user, $gathering, $branchId);
                    if ($nativeContexts[$branchId]) {
                        return true;
                    }
                }
            }
            if ($branches === []) {
                continue;
            }
            $grants ??= $access->grantsFor((int)$user->getIdentifier(), (int)$gathering->id);
            foreach ($grants as $grant) {
                $branchId = (int)$grant->branch_id;
                if (
                    in_array($branchId, $branches, true)
                    && $bestowalPolicy->hasNativePermission(
                        $grant->grantor,
                        'canView',
                        $access->probe((int)$gathering->id, $branchId),
                    )
                    && $policy->canAssignNatively($grant->grantor, $activity, $gathering, $branchId)
                ) {
                    return true;
                }
            }
        }

        return false;
    }

    /** Persisted award business or an outgoing grant supplies native event context. */
    private function hasNativeAwardContext(KmpIdentityInterface $user, Gathering $gathering, int $branchId): bool
    {
        $tables = TableRegistry::getTableLocator();
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

        return false;
    }
}
