<?php
declare(strict_types=1);

namespace Awards\Services;

use App\KMP\KmpIdentityInterface;
use Awards\Model\Entity\Award;
use Awards\Model\Entity\Bestowal;
use Awards\Model\Entity\CourtAgenda;
use Awards\Policy\BestowalPolicy;
use Awards\Policy\CourtAgendaPolicy;
use Cake\ORM\Locator\LocatorAwareTrait;

/** Resolves live, native grantor authority without following delegated grants. */
class BestowalDelegationAccessService
{
    use LocatorAwareTrait;

    /** @return list<\Awards\Model\Entity\BestowalDelegation> */
    public function grantsFor(int $delegateId, ?int $gatheringId = null, ?int $branchId = null): array
    {
        $table = $this->fetchTable('Awards.BestowalDelegations');
        $query = $table->find()
            ->where(['delegate_id' => $delegateId, 'revoked_at IS' => null]);
        if ($gatheringId !== null) {
            $query->where(['BestowalDelegations.gathering_id' => $gatheringId]);
        }
        if ($branchId !== null) {
            $query->where(['BestowalDelegations.branch_id' => $branchId]);
        }
        if ($table->getConnection()->inTransaction()) {
            // Revocation waits for authorized writes to finish; never use a newly appeared, unlocked grant.
            $locked = (clone $query)->select(['BestowalDelegations.id'])->epilog('FOR SHARE')
                ->all()->extract('id')->toList();
            if ($locked === []) {
                return [];
            }
            $query->where(['BestowalDelegations.id IN' => $locked]);
        }
        $query->contain(['Grantors', 'Delegates', 'Gatherings']);

        return array_values(array_filter($query->all()->toList(), static function ($grant): bool {
            return $grant->grantor !== null && $grant->delegate !== null && $grant->gathering !== null
                && $grant->grantor->status === 'verified' && $grant->delegate->status === 'verified';
        }));
    }

    /** Build explicit event and award ownership context for native permission checks. */
    public function probe(int $gatheringId, int $branchId): Bestowal
    {
        return new Bestowal([
            'gathering_id' => $gatheringId,
            'award' => new Award(['branch_id' => $branchId]),
        ]);
    }

    /** @return list<array{gathering_id: int, branch_id: int}> */
    public function scopesFor(KmpIdentityInterface $user, string $method = 'canView'): array
    {
        $policy = new BestowalPolicy();
        $scopes = [];
        foreach ($this->grantsFor((int)$user->getIdentifier()) as $grant) {
            $probe = $this->probe((int)$grant->gathering_id, (int)$grant->branch_id);
            if (
                $policy->hasNativePermission($grant->grantor, 'canView', $probe)
                && $policy->hasNativePermission($grant->grantor, $method, $probe)
            ) {
                $scopes[] = ['gathering_id' => (int)$grant->gathering_id, 'branch_id' => (int)$grant->branch_id];
            }
        }

        return $scopes;
    }

    /** Match only grants for this record's current event and exact awarding group. */
    public function allowsBestowal(KmpIdentityInterface $user, string $method, Bestowal $bestowal): bool
    {
        $gatheringId = (int)$bestowal->gathering_id;
        $branchId = $bestowal->getBranchId();
        // Only list/context checks may use an empty placeholder; edits require an owning award.
        $contextMethods = ['canIndex', 'canViewGatheringBestowals'];
        if ((int)$bestowal->id <= 0 && $bestowal->award_id === null) {
            $contextMethods = array_merge($contextMethods, ['canAccessHeraldNotes', 'canAccessCrownFields']);
        }
        if (($gatheringId <= 0 || $branchId === null) && !in_array($method, $contextMethods, true)) {
            return false;
        }
        foreach ($this->scopesFor($user, $method) as $scope) {
            if (
                ($gatheringId <= 0 || $scope['gathering_id'] === $gatheringId)
                && ($branchId === null || $scope['branch_id'] === $branchId)
            ) {
                return true;
            }
        }

        return false;
    }

    /** Resolve an explicit event context without authorizing an award-less mutation. */
    public function allowsContext(KmpIdentityInterface $user, string $method, int $gatheringId): bool
    {
        foreach ($this->scopesFor($user, $method) as $scope) {
            if ($scope['gathering_id'] === $gatheringId) {
                return true;
            }
        }

        return false;
    }

    /** Court permissions follow the grantor's native authority in the delegated awarding group. */
    public function allowsAgenda(KmpIdentityInterface $user, string $method, CourtAgenda $agenda): bool
    {
        $bestowalPolicy = new BestowalPolicy();
        $agendaPolicy = new CourtAgendaPolicy();
        foreach ($this->grantsFor((int)$user->getIdentifier(), (int)$agenda->gathering_id) as $grant) {
            $probe = $this->probe((int)$grant->gathering_id, (int)$grant->branch_id);
            if (
                $bestowalPolicy->hasNativePermission($grant->grantor, 'canView', $probe)
                && $agendaPolicy->hasNativePermission($grant->grantor, $method, $agenda, (int)$grant->branch_id)
            ) {
                return true;
            }
        }

        return false;
    }

    /** SQL row conditions, always paired with current native grantor view permission. */
    public function bestowalConditions(KmpIdentityInterface $user, string $alias = 'Bestowals'): array
    {
        $conditions = [];
        foreach ($this->scopesFor($user) as $scope) {
            $conditions[] = [
                $alias . '.gathering_id' => $scope['gathering_id'],
                $alias . '.award_id IN' => $this->fetchTable('Awards.Awards')->find()
                    ->select(['id'])->where(['Awards.branch_id' => $scope['branch_id']]),
            ];
        }

        return $conditions;
    }
}
