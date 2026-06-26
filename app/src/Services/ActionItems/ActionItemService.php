<?php
declare(strict_types=1);

namespace App\Services\ActionItems;

use App\KMP\PermissionsLoader;
use App\Model\Entity\ActionItem;
use App\Services\ServiceResult;
use Cake\Event\Event;
use Cake\Event\EventManager;
use Cake\I18n\DateTime;
use Cake\ORM\Query\SelectQuery;
use Cake\ORM\TableRegistry;

/**
 * ActionItemService - lifecycle operations for the reusable to-do subsystem.
 *
 * Owns materialization of action items from template-style definitions and the
 * gated complete/reopen/cancel transitions (each writing an audit log row). All
 * eligibility decisions delegate to ActionItemAssigneeResolver so "who can flip
 * a check" is enforced consistently.
 */
class ActionItemService
{
    /**
     * @var \App\Services\ActionItems\ActionItemAssigneeResolver
     */
    protected ActionItemAssigneeResolver $resolver;

    /**
     * @var \App\Model\Table\ActionItemsTable
     */
    protected $ActionItems;

    /**
     * @var \App\Model\Table\ActionItemLogsTable
     */
    protected $ActionItemLogs;

    /**
     * @param \App\Services\ActionItems\ActionItemAssigneeResolver|null $resolver Eligibility resolver
     */
    public function __construct(?ActionItemAssigneeResolver $resolver = null)
    {
        $this->resolver = $resolver ?? new ActionItemAssigneeResolver();
        $this->ActionItems = TableRegistry::getTableLocator()->get('ActionItems');
        $this->ActionItemLogs = TableRegistry::getTableLocator()->get('ActionItemLogs');
    }

    /**
     * Create action items for an owner entity from a list of definitions.
     *
     * Each definition is an associative array with keys: title, description,
     * assignee_type, assignee_config, is_gating, sort_order, source_ref,
     * branch_id. Missing keys fall back to sensible defaults. Materialization is
     * idempotent on (entity_type, entity_id, source_ref): an existing item with
     * the same source_ref is skipped.
     *
     * @param string $entityType Polymorphic owner type (e.g. Awards.Bestowals)
     * @param int $entityId Owner primary key
     * @param array<int, array<string, mixed>> $definitions Item definitions
     * @param int|null $branchId Default branch scope for all items
     * @return \App\Services\ServiceResult Data is the array of created ActionItem entities
     */
    public function materializeFor(
        string $entityType,
        int $entityId,
        array $definitions,
        ?int $branchId = null,
    ): ServiceResult {
        if ($entityId <= 0) {
            return new ServiceResult(false, 'A valid entity id is required to materialize to-dos.');
        }

        $existingRefs = $this->ActionItems->find()
            ->where([
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'source_ref IS NOT' => null,
            ])
            ->all()
            ->extract('source_ref')
            ->toList();
        $existingRefs = array_filter($existingRefs, fn($ref): bool => $ref !== null && $ref !== '');

        $created = [];
        foreach ($definitions as $index => $definition) {
            $sourceRef = $definition['source_ref'] ?? null;
            if ($sourceRef !== null && in_array($sourceRef, $existingRefs, true)) {
                continue;
            }

            $entity = $this->ActionItems->newEntity([
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'title' => $definition['title'] ?? 'To-Do',
                'description' => $definition['description'] ?? null,
                'assignee_type' => $definition['assignee_type'] ?? ActionItem::ASSIGNEE_TYPE_PERMISSION,
                'assignee_config' => $definition['assignee_config'] ?? null,
                'branch_id' => $definition['branch_id'] ?? $branchId,
                'status' => ActionItem::STATUS_OPEN,
                'is_gating' => array_key_exists('is_gating', $definition) ? (bool)$definition['is_gating'] : true,
                'sort_order' => $definition['sort_order'] ?? $index,
                'source_ref' => $sourceRef,
            ]);

            if (!$this->ActionItems->save($entity)) {
                return new ServiceResult(false, 'Failed to create one or more to-do items.', $created);
            }
            $created[] = $entity;
        }

        return new ServiceResult(true, null, $created);
    }

    /**
     * Mark an action item completed, enforcing assignee eligibility.
     *
     * @param int $actionItemId The item to complete
     * @param int $actorId The acting member id
     * @param string|null $note Optional audit note
     * @param bool $enforceEligibility When false, skips the eligibility gate (admin/backfill use)
     * @return \App\Services\ServiceResult
     */
    public function complete(
        int $actionItemId,
        int $actorId,
        ?string $note = null,
        bool $enforceEligibility = true,
    ): ServiceResult {
        return $this->transition($actionItemId, $actorId, ActionItem::STATUS_COMPLETED, $note, $enforceEligibility);
    }

    /**
     * Reopen a completed/cancelled action item, enforcing assignee eligibility.
     *
     * @param int $actionItemId The item to reopen
     * @param int $actorId The acting member id
     * @param string|null $note Optional audit note
     * @param bool $enforceEligibility When false, skips the eligibility gate
     * @return \App\Services\ServiceResult
     */
    public function reopen(
        int $actionItemId,
        int $actorId,
        ?string $note = null,
        bool $enforceEligibility = true,
    ): ServiceResult {
        return $this->transition($actionItemId, $actorId, ActionItem::STATUS_OPEN, $note, $enforceEligibility);
    }

    /**
     * Cancel an action item (e.g. when its owner entity is cancelled).
     *
     * @param int $actionItemId The item to cancel
     * @param int $actorId The acting member id
     * @param string|null $note Optional audit note
     * @param bool $enforceEligibility When false, skips the eligibility gate
     * @return \App\Services\ServiceResult
     */
    public function cancel(
        int $actionItemId,
        int $actorId,
        ?string $note = null,
        bool $enforceEligibility = true,
    ): ServiceResult {
        return $this->transition($actionItemId, $actorId, ActionItem::STATUS_CANCELLED, $note, $enforceEligibility);
    }

    /**
     * Shared transition handler: validate, update status, write a log row.
     *
     * @param int $actionItemId The item id
     * @param int $actorId The acting member id
     * @param string $toStatus Target status
     * @param string|null $note Optional audit note
     * @param bool $enforceEligibility Whether to enforce the eligibility gate
     * @return \App\Services\ServiceResult
     */
    protected function transition(
        int $actionItemId,
        int $actorId,
        string $toStatus,
        ?string $note,
        bool $enforceEligibility,
    ): ServiceResult {
        /** @var \App\Model\Entity\ActionItem|null $item */
        $item = $this->ActionItems->find()->where(['ActionItems.id' => $actionItemId])->first();
        if (!$item) {
            return new ServiceResult(false, 'To-do item not found.');
        }

        if ($enforceEligibility && !$this->resolver->isMemberEligible($item, $actorId)) {
            return new ServiceResult(false, 'You are not assigned to this to-do item.');
        }

        $fromStatus = $item->status;
        if ($fromStatus === $toStatus) {
            return new ServiceResult(true, 'No change.', $item);
        }

        $item->status = $toStatus;
        if ($toStatus === ActionItem::STATUS_COMPLETED) {
            $item->completed_at = DateTime::now();
            $item->completed_by = $actorId;
        } else {
            $item->completed_at = null;
            $item->completed_by = null;
        }

        $connection = $this->ActionItems->getConnection();

        $result = $connection->transactional(
            function () use ($item, $fromStatus, $toStatus, $note, $actorId): ServiceResult {
                if (!$this->ActionItems->save($item)) {
                    return new ServiceResult(false, 'Failed to update the to-do item.');
                }

                $log = $this->ActionItemLogs->newEntity([
                    'action_item_id' => $item->id,
                    'from_status' => $fromStatus,
                    'to_status' => $toStatus,
                    'note' => $note,
                    'created_by' => $actorId,
                ]);
                $this->ActionItemLogs->save($log);

                return new ServiceResult(true, null, $item);
            },
        );

        if ($result->success && $toStatus === ActionItem::STATUS_COMPLETED) {
            $this->dispatchCompletedEvent($item, $actorId);
        }

        return $result;
    }

    /**
     * Announce a successful completion so consumers (e.g. the Awards plugin) can
     * react without the core subsystem depending on them.
     *
     * Dispatched on the global EventManager after the status change commits;
     * listeners are best-effort and must not affect the transition result.
     *
     * @param \App\Model\Entity\ActionItem $item The completed item.
     * @param int $actorId Member who completed the item.
     * @return void
     */
    protected function dispatchCompletedEvent(ActionItem $item, int $actorId): void
    {
        EventManager::instance()->dispatch(
            new Event('ActionItem.completed', $this, ['item' => $item, 'actorId' => $actorId]),
        );
    }

    /**
     * List open action items a member is eligible to act on.
     *
     * @param int $memberId The member id
     * @param string|null $entityType Optional owner-type filter
     * @return array<\App\Model\Entity\ActionItem>
     */
    public function getOpenItemsForMember(int $memberId, ?string $entityType = null): array
    {
        if ($memberId <= 0) {
            return [];
        }

        $query = $this->ActionItems->find()
            ->contain(['Branches'])
            ->where(['ActionItems.status' => ActionItem::STATUS_OPEN])
            ->order([
                'ActionItems.entity_type' => 'ASC',
                'ActionItems.entity_id' => 'ASC',
                'ActionItems.sort_order' => 'ASC',
                'ActionItems.id' => 'ASC',
            ]);

        if ($entityType !== null) {
            $query->where(['ActionItems.entity_type' => $entityType]);
        }
        $this->applyMemberCandidateScope($query, $memberId);

        $items = $query->all()->toArray();

        return array_values(array_filter(
            $items,
            fn(ActionItem $item): bool => $this->resolver->isMemberEligible($item, $memberId),
        ));
    }

    /**
     * List the ids of open action items a member is eligible to act on.
     *
     * Mirrors {@see getOpenItemsForMember()} but returns only ids, for use as a
     * pre-resolved `IN (...)` filter when building the My To-Dos grid query.
     *
     * @param int $memberId The member id
     * @return array<int>
     */
    public function getActionableItemIdsForMember(int $memberId): array
    {
        if ($memberId <= 0) {
            return [];
        }

        $query = $this->ActionItems->find()
            ->where(['ActionItems.status' => ActionItem::STATUS_OPEN]);
        $this->applyMemberCandidateScope($query, $memberId);

        $items = $query->all()->toArray();

        $eligible = array_filter(
            $items,
            fn(ActionItem $item): bool => $this->resolver->isMemberEligible($item, $memberId),
        );

        return array_values(array_map(static fn(ActionItem $item): int => (int)$item->id, $eligible));
    }

    /**
     * Count open action items a member is eligible to act on.
     *
     * Uses the same eligibility resolver as the rich list, but avoids branch
     * hydration and presentation ordering for navigation badges.
     *
     * @param int $memberId The member id
     * @return int
     */
    public function countOpenItemsForMember(int $memberId): int
    {
        if ($memberId <= 0) {
            return 0;
        }

        $items = $this->ActionItems->find()
            ->where(['ActionItems.status' => ActionItem::STATUS_OPEN]);
        $this->applyMemberCandidateScope($items, $memberId);
        $items = $items->all()->toArray();

        return count(array_filter(
            $items,
            fn(ActionItem $item): bool => $this->resolver->isMemberEligible($item, $memberId),
        ));
    }

    /**
     * Whether a member may act on (complete/reopen) a specific item.
     *
     * Thin passthrough to the assignee resolver so UI surfaces can decide which
     * per-check controls to render without duplicating eligibility logic.
     *
     * @param \App\Model\Entity\ActionItem $item The action item
     * @param int $memberId The acting member id
     * @return bool
     */
    public function isMemberEligible(ActionItem $item, int $memberId): bool
    {
        if ($memberId <= 0) {
            return false;
        }

        return $this->resolver->isMemberEligible($item, $memberId);
    }

    /**
     * Narrow open-item queries to rows that could match the member's assignment scope.
     *
     * @param \Cake\ORM\Query\SelectQuery $query The query to scope.
     * @param int $memberId The member id.
     * @return void
     */
    private function applyMemberCandidateScope(SelectQuery $query, int $memberId): void
    {
        $roles = PermissionsLoader::getRoles($memberId);
        $permissions = PermissionsLoader::getPermissions($memberId);
        $roleIds = array_map('intval', array_keys($roles));
        $permissionIds = array_map('intval', array_keys($permissions));
        $roleNames = array_values(array_filter(array_map(
            static fn(object $role): string => (string)$role->name,
            $roles,
        )));
        $permissionNames = array_values(array_filter(array_map(
            static fn(object $permission): string => (string)$permission->name,
            $permissions,
        )));
        $officeIds = $this->getCurrentOfficeIdsForMember($memberId);

        $conditions = [
            [
                'ActionItems.assignee_lookup_type' => ActionItem::ASSIGNEE_TYPE_MEMBER,
                'ActionItems.assignee_lookup_id' => $memberId,
            ],
            [
                'ActionItems.assignee_lookup_type' => ActionItem::ASSIGNEE_TYPE_DYNAMIC,
            ],
        ];

        $permissionConditions = [];
        if ($permissionIds !== []) {
            $permissionConditions[] = ['ActionItems.assignee_lookup_id IN' => $permissionIds];
        }
        if ($permissionNames !== []) {
            $permissionConditions[] = ['ActionItems.assignee_lookup_name IN' => $permissionNames];
        }
        if ($permissionConditions !== []) {
            $conditions[] = [
                'ActionItems.assignee_lookup_type' => ActionItem::ASSIGNEE_TYPE_PERMISSION,
                'OR' => $permissionConditions,
            ];
        }

        $roleConditions = [];
        if ($roleIds !== []) {
            $roleConditions[] = ['ActionItems.assignee_lookup_id IN' => $roleIds];
        }
        if ($roleNames !== []) {
            $roleConditions[] = ['ActionItems.assignee_lookup_name IN' => $roleNames];
        }
        if ($roleConditions !== []) {
            $conditions[] = [
                'ActionItems.assignee_lookup_type' => ActionItem::ASSIGNEE_TYPE_ROLE,
                'OR' => $roleConditions,
            ];
        }

        if ($officeIds !== []) {
            $conditions[] = [
                'ActionItems.assignee_lookup_type' => 'office',
                'ActionItems.assignee_lookup_id IN' => $officeIds,
            ];
        }

        $query->where(['OR' => $conditions]);
    }

    /**
     * @param int $memberId Member ID.
     * @return array<int>
     */
    private function getCurrentOfficeIdsForMember(int $memberId): array
    {
        $now = DateTime::now();
        $offices = TableRegistry::getTableLocator()->get('Officers.Officers')->find()
            ->select(['office_id'])
            ->where([
                'Officers.member_id' => $memberId,
                'Officers.status' => 'Current',
                'Officers.start_on <=' => $now,
                'OR' => [
                    'Officers.expires_on IS' => null,
                    'Officers.expires_on >=' => $now,
                ],
            ])
            ->all()
            ->extract('office_id')
            ->map(fn($id): int => (int)$id)
            ->toList();

        return array_values(array_unique(array_filter($offices)));
    }

    /**
     * Whether every gating item for an owner entity is completed.
     *
     * Returns false when there are no gating items at all, so callers do not
     * surface a "ready" action for entities that have no checklist yet.
     *
     * @param string $entityType Polymorphic owner type
     * @param int $entityId Owner primary key
     * @return bool
     */
    public function allGatingComplete(string $entityType, int $entityId): bool
    {
        $gating = $this->ActionItems->find()
            ->where([
                'ActionItems.entity_type' => $entityType,
                'ActionItems.entity_id' => $entityId,
                'ActionItems.is_gating' => true,
                'ActionItems.status !=' => ActionItem::STATUS_CANCELLED,
            ])
            ->all()
            ->toArray();

        if (empty($gating)) {
            return false;
        }

        foreach ($gating as $item) {
            if ($item->status !== ActionItem::STATUS_COMPLETED) {
                return false;
            }
        }

        return true;
    }

    /**
     * Fetch ordered action items for an owner entity.
     *
     * @param string $entityType Polymorphic owner type
     * @param int $entityId Owner primary key
     * @param bool $includeCancelled Whether to include cancelled items
     * @return array<\App\Model\Entity\ActionItem>
     */
    public function getItemsForEntity(string $entityType, int $entityId, bool $includeCancelled = false): array
    {
        $query = $this->ActionItems->find()
            ->where([
                'ActionItems.entity_type' => $entityType,
                'ActionItems.entity_id' => $entityId,
            ])
            ->order(['ActionItems.sort_order' => 'ASC', 'ActionItems.id' => 'ASC']);

        if (!$includeCancelled) {
            $query->where(['ActionItems.status !=' => ActionItem::STATUS_CANCELLED]);
        }

        return $query->all()->toArray();
    }
}
