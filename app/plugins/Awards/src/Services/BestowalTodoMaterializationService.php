<?php
declare(strict_types=1);

namespace Awards\Services;

use App\Model\Entity\ActionItem;
use App\Services\ActionItems\ActionItemService;
use App\Services\ServiceResult;
use Awards\Model\Entity\Bestowal;
use Awards\Model\Entity\BestowalTodoTemplate;
use Awards\Model\Entity\BestowalTodoTemplateItem;
use Cake\Datasource\EntityInterface;
use Cake\Log\Log;
use Cake\ORM\Locator\LocatorAwareTrait;
use Throwable;

/**
 * Builds a bestowal's parallel to-do checklist from its award's assigned
 * bestowal to-do template and materializes them as core ActionItems.
 *
 * This is the bridge between the Awards-specific template configuration and the
 * reusable action-item subsystem. Each template item becomes an ActionItem:
 *   - member items map to the core `member` assignee type (no branch needed);
 *   - role / permission / office items map to the core `dynamic` type backed by
 *     {@see BestowalTodoAssigneeResolver}, with the branch scope resolved here
 *     (award branch, or an ancestor branch of a configured type) and stored on
 *     the ActionItem so eligibility is recomputed live.
 *
 * Materialization is idempotent on the template item key, so re-running for an
 * existing bestowal will not duplicate checklist items.
 */
class BestowalTodoMaterializationService
{
    use LocatorAwareTrait;

    private ActionItemService $actionItemService;

    /**
     * @param \App\Services\ActionItems\ActionItemService|null $actionItemService Optional injected service.
     */
    public function __construct(?ActionItemService $actionItemService = null)
    {
        $this->actionItemService = $actionItemService ?? new ActionItemService();
    }

    /**
     * Materialize the to-do checklist for a saved bestowal.
     *
     * Returns a successful (no-op) result when the bestowal's award has no
     * assigned template, so callers can invoke this unconditionally.
     *
     * @param \Awards\Model\Entity\Bestowal $bestowal Saved bestowal entity.
     * @return \App\Services\ServiceResult Data is the array of created ActionItem entities.
     */
    public function materializeForBestowal(Bestowal $bestowal): ServiceResult
    {
        $bestowalId = (int)$bestowal->id;
        if ($bestowalId <= 0) {
            return new ServiceResult(false, 'A saved bestowal is required to materialize to-dos.');
        }

        $awardId = $bestowal->award_id !== null ? (int)$bestowal->award_id : 0;
        if ($awardId <= 0) {
            return new ServiceResult(true, 'Bestowal has no award; no to-do template applied.', []);
        }

        $award = $this->fetchTable('Awards.Awards')->find()
            ->where(['Awards.id' => $awardId])
            ->select(['Awards.id', 'Awards.branch_id', 'Awards.bestowal_todo_template_id'])
            ->first();
        if ($award === null || $award->get('bestowal_todo_template_id') === null) {
            return new ServiceResult(true, 'No bestowal to-do template assigned to this award.', []);
        }

        $template = $this->loadTemplate((int)$award->get('bestowal_todo_template_id'));
        if ($template === null || empty($template->bestowal_todo_template_items)) {
            return new ServiceResult(true, 'Assigned bestowal to-do template has no items.', []);
        }

        $awardBranchId = $award->get('branch_id') !== null ? (int)$award->get('branch_id') : null;
        $definitions = [];
        foreach ($template->bestowal_todo_template_items as $item) {
            $definitions[] = $this->buildDefinition($item, $awardBranchId);
        }

        return $this->actionItemService->materializeFor(
            Bestowal::ACTION_ITEM_ENTITY_TYPE,
            $bestowalId,
            $definitions,
            $awardBranchId,
        );
    }

    /**
     * Load an active template with its active items in display order.
     *
     * @param int $templateId Template ID.
     * @return \Awards\Model\Entity\BestowalTodoTemplate|null
     */
    private function loadTemplate(int $templateId): ?BestowalTodoTemplate
    {
        /** @var \Awards\Model\Entity\BestowalTodoTemplate|null $template */
        $template = $this->fetchTable('Awards.BestowalTodoTemplates')->find()
            ->where(['BestowalTodoTemplates.id' => $templateId])
            ->contain(['BestowalTodoTemplateItems' => function ($query) {
                return $query->orderBy(['BestowalTodoTemplateItems.sort_order' => 'ASC']);
            }])
            ->first();

        return $template;
    }

    /**
     * Convert a template item into an ActionItemService definition.
     *
     * @param \Awards\Model\Entity\BestowalTodoTemplateItem $item Template item.
     * @param int|null $awardBranchId The award's branch ID.
     * @return array<string, mixed>
     */
    private function buildDefinition(BestowalTodoTemplateItem $item, ?int $awardBranchId): array
    {
        $sourceRef = $item->item_key !== null && $item->item_key !== ''
            ? $item->item_key
            : 'item-' . (int)$item->id;

        $definition = [
            'title' => (string)$item->label,
            'description' => $item->description,
            'is_gating' => (bool)$item->is_gating,
            'sort_order' => (int)$item->sort_order,
            'source_ref' => $sourceRef,
        ];

        if ($item->assignee_type === BestowalTodoTemplateItem::ASSIGNEE_TYPE_MEMBER) {
            $definition['assignee_type'] = ActionItem::ASSIGNEE_TYPE_MEMBER;
            $definition['assignee_config'] = ['member_id' => (int)$item->assignee_source_id];
            $definition['branch_id'] = null;

            return $definition;
        }

        $definition['assignee_type'] = ActionItem::ASSIGNEE_TYPE_DYNAMIC;
        $definition['assignee_config'] = [
            'service' => BestowalTodoAssigneeResolver::class,
            'method' => 'resolveMemberIds',
            'kind' => $item->assignee_type,
            'source_id' => (int)$item->assignee_source_id,
            'source_key' => $item->assignee_source_key,
        ];
        $definition['branch_id'] = $this->resolveBranchId($item, $awardBranchId);

        return $definition;
    }

    /**
     * Resolve the branch scope for a branch-scoped item.
     *
     * @param \Awards\Model\Entity\BestowalTodoTemplateItem $item Template item.
     * @param int|null $awardBranchId The award's branch ID.
     * @return int|null Concrete branch ID, or null when it cannot be resolved.
     */
    private function resolveBranchId(BestowalTodoTemplateItem $item, ?int $awardBranchId): ?int
    {
        if ($awardBranchId === null) {
            Log::warning(sprintf(
                'Bestowal to-do item "%s" has no award branch to scope against.',
                (string)$item->item_key,
            ));

            return null;
        }

        if ($item->branch_mode !== BestowalTodoTemplateItem::BRANCH_MODE_ANCESTOR_TYPE) {
            return $awardBranchId;
        }

        $branchType = (string)$item->branch_type;
        if ($branchType === '') {
            return $awardBranchId;
        }

        try {
            $ancestor = $this->findAncestorBranchByType($awardBranchId, $branchType);
        } catch (Throwable $exception) {
            Log::warning(sprintf(
                'Bestowal to-do item "%s" could not resolve ancestor branch type "%s": %s',
                (string)$item->item_key,
                $branchType,
                $exception->getMessage(),
            ));

            return null;
        }

        return $ancestor !== null ? (int)$ancestor->get('id') : null;
    }

    /**
     * Walk parent branches until a branch of the requested type is found.
     *
     * @param int $branchId Starting branch ID.
     * @param string $branchType Target branch type.
     * @return \Cake\Datasource\EntityInterface|null
     */
    private function findAncestorBranchByType(int $branchId, string $branchType): ?EntityInterface
    {
        $branches = $this->fetchTable('Branches');
        $current = $branches->get($branchId);

        while ($current !== null) {
            if ((string)$current->get('type') === $branchType) {
                return $current;
            }

            $parentId = $current->get('parent_id');
            if ($parentId === null) {
                return null;
            }

            $current = $branches->get($parentId);
        }

        return null;
    }
}
