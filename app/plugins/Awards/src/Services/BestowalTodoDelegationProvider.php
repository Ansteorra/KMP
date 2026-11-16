<?php
declare(strict_types=1);

namespace Awards\Services;

use App\Model\Entity\ActionItem;
use App\Services\ActionItems\ActionItemAssigneeResolver;
use App\Services\ActionItems\ActionItemDelegationProviderInterface;
use Awards\Model\Entity\Bestowal;
use Awards\Policy\BestowalPolicy;
use Cake\ORM\Table;
use Cake\ORM\TableRegistry;

/** Extends every assignment kind using the grantor's native assignment eligibility. */
class BestowalTodoDelegationProvider implements ActionItemDelegationProviderInterface
{
    /** A registered provider must resolve the current tenant locator on every call. */
    private function fetchTable(string $alias): Table
    {
        return TableRegistry::getTableLocator()->get($alias);
    }

    /** @inheritDoc */
    public function isMemberEligible(ActionItem $item, int $memberId, ActionItemAssigneeResolver $resolver): bool
    {
        $bestowal = $this->bestowalFor($item);
        if ($bestowal === null || (int)$bestowal->gathering_id <= 0 || $bestowal->getBranchId() === null) {
            return false;
        }
        $access = new BestowalDelegationAccessService();
        foreach ($access->grantsFor($memberId, (int)$bestowal->gathering_id, $bestowal->getBranchId()) as $grant) {
            if (
                (new BestowalPolicy())->hasNativePermission($grant->grantor, 'canView', $bestowal)
                && $resolver->isMemberDirectlyEligible($item, (int)$grant->grantor_id)
            ) {
                return true;
            }
        }

        return false;
    }

    /** @inheritDoc */
    public function getEligibleMemberIds(ActionItem $item, ActionItemAssigneeResolver $resolver): array
    {
        $bestowal = $this->bestowalFor($item);
        if ($bestowal === null || (int)$bestowal->gathering_id <= 0 || $bestowal->getBranchId() === null) {
            return [];
        }
        $recipients = $this->fetchTable('Awards.BestowalDelegations')->find()->where([
            'gathering_id' => $bestowal->gathering_id, 'branch_id' => $bestowal->getBranchId(),
            'revoked_at IS' => null,
        ])->all()->extract('delegate_id')->toList();

        return array_values(array_filter(
            array_unique(array_map('intval', $recipients)),
            fn(int $id): bool => $this->isMemberEligible($item, $id, $resolver),
        ));
    }

    /** @inheritDoc */
    public function candidateConditions(int $memberId): array
    {
        $member = $this->fetchTable('Members')->find()->where(['Members.id' => $memberId])->first();
        if ($member === null) {
            return [];
        }
        $conditions = (new BestowalDelegationAccessService())->bestowalConditions($member);
        if ($conditions === []) {
            return [];
        }

        return [[
            'ActionItems.entity_type' => Bestowal::ACTION_ITEM_ENTITY_TYPE,
            'ActionItems.entity_id IN' => $this->fetchTable('Awards.Bestowals')->find()
                ->select(['Bestowals.id'])->where(['OR' => $conditions]),
        ]];
    }

    /** Resolve persisted ownership, never request-provided event context. */
    private function bestowalFor(ActionItem $item): ?Bestowal
    {
        if ($item->entity_type !== Bestowal::ACTION_ITEM_ENTITY_TYPE || (int)$item->entity_id <= 0) {
            return null;
        }

        return $this->fetchTable('Awards.Bestowals')->find()
            ->where(['Bestowals.id' => $item->entity_id])->contain(['Awards'])->first();
    }
}
