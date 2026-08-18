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
use RuntimeException;
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

    private const SYNC_FAILURE_REASON =
        'Bestowal to-do synchronization failed. Review server logs for details.';

    private ActionItemService $actionItemService;

    /**
     * @var array<string, \Cake\Datasource\EntityInterface|null>
     */
    private array $ancestorBranchMemo = [];

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

        $connection = $this->fetchTable('Awards.Bestowals')->getConnection();
        $savePointsWereEnabled = $connection->isSavePointsEnabled();
        if (!$savePointsWereEnabled) {
            $connection->enableSavePoints();
        }

        try {
            return $connection->transactional(function () use ($bestowal, $bestowalId): ServiceResult {
                $bestowal = $this->lockPersistedBestowal($bestowal);
                if (!$bestowal instanceof Bestowal || !$bestowal->allowsActionItemMutations()) {
                    return new ServiceResult(true, 'Only open bestowals receive new to-do lists.', []);
                }
                $contextResult = $this->resolveTemplateContext($bestowal);
                if (!$contextResult->success) {
                    return $contextResult;
                }
                if ($contextResult->data['skipped']) {
                    return new ServiceResult(true, $contextResult->reason, []);
                }

                return $this->actionItemService->materializeFor(
                    Bestowal::ACTION_ITEM_ENTITY_TYPE,
                    $bestowalId,
                    $contextResult->data['definitions'],
                    $contextResult->data['branchId'],
                );
            });
        } finally {
            if (!$savePointsWereEnabled) {
                $connection->disableSavePoints();
            }
        }
    }

    /**
     * Reconcile one open bestowal's to-dos with its award's current template.
     *
     * @param \Awards\Model\Entity\Bestowal $bestowal Saved bestowal entity.
     * @param int|null $actorId Member initiating the synchronization, if any.
     * @return \App\Services\ServiceResult Data contains item mutation counts and skip metadata.
     */
    public function syncForBestowal(Bestowal $bestowal, ?int $actorId = null): ServiceResult
    {
        $bestowalId = (int)$bestowal->id;
        if ($bestowalId <= 0) {
            return new ServiceResult(false, 'A saved bestowal is required to synchronize to-dos.');
        }
        $connection = $this->fetchTable('Awards.Bestowals')->getConnection();
        $savePointsWereEnabled = $connection->isSavePointsEnabled();
        if (!$savePointsWereEnabled) {
            $connection->enableSavePoints();
        }
        $failureReason = null;

        try {
            return $connection->transactional(function () use ($bestowal, $actorId, &$failureReason): ServiceResult {
                $bestowal = $this->lockPersistedBestowal($bestowal);
                if (!$bestowal instanceof Bestowal || !$bestowal->allowsActionItemMutations()) {
                    return new ServiceResult(
                        true,
                        'Only open bestowals are synchronized.',
                        $this->emptySyncSummary(true),
                    );
                }
                $result = $this->syncForBestowalInTransaction($bestowal, $actorId);
                if (!$result->success) {
                    $failureReason = $result->reason ?? 'The bestowal to-do list could not be synchronized.';
                    throw new RuntimeException('Bestowal to-do synchronization returned a failed result.');
                }

                return $result;
            });
        } catch (Throwable $exception) {
            Log::error(sprintf(
                'Bestowal to-do sync failed for bestowal %d: %s',
                $bestowalId,
                $failureReason ?? $exception->getMessage(),
            ));

            return new ServiceResult(false, self::SYNC_FAILURE_REASON);
        } finally {
            if (!$savePointsWereEnabled) {
                $connection->disableSavePoints();
            }
        }
    }

    /**
     * Reconcile one bestowal inside the caller's transaction.
     *
     * @param \Awards\Model\Entity\Bestowal $bestowal Saved bestowal entity.
     * @param int|null $actorId Member initiating the synchronization, if any.
     * @return \App\Services\ServiceResult
     */
    private function syncForBestowalInTransaction(Bestowal $bestowal, ?int $actorId): ServiceResult
    {
        $bestowalId = (int)$bestowal->id;

        $contextResult = $this->resolveTemplateContext($bestowal);
        if (!$contextResult->success) {
            return $contextResult;
        }
        if ($contextResult->data['skipped']) {
            return new ServiceResult(
                true,
                $contextResult->reason,
                $this->emptySyncSummary(true, $contextResult->data['templateId']),
            );
        }

        $result = $this->actionItemService->synchronizeFor(
            Bestowal::ACTION_ITEM_ENTITY_TYPE,
            $bestowalId,
            $contextResult->data['definitions'],
            $contextResult->data['branchId'],
            $actorId,
        );
        if (!$result->success) {
            return $result;
        }

        $remainingPasses = count($contextResult->data['definitions']);
        $transitionCount = (int)($result->data['requiredCompletedCount'] ?? 0)
            + (int)($result->data['requiredReopenedCount'] ?? 0);
        while ($transitionCount > 0 && $remainingPasses-- > 0) {
            $requiredResult = $this->actionItemService->syncRequiredFieldCompletionStates(
                Bestowal::ACTION_ITEM_ENTITY_TYPE,
                $bestowalId,
            );
            if (!$requiredResult->success) {
                return $requiredResult;
            }

            $completedCount = (int)($requiredResult->data['completedCount'] ?? 0);
            $reopenedCount = (int)($requiredResult->data['reopenedCount'] ?? 0);
            $result->data['requiredCompletedCount'] += $completedCount;
            $result->data['requiredReopenedCount'] += $reopenedCount;
            $result->data['requiredSkippedCount'] += (int)($requiredResult->data['skippedCount'] ?? 0);
            $transitionCount = $completedCount + $reopenedCount;
        }

        $result->data['skipped'] = false;
        $result->data['templateId'] = $contextResult->data['templateId'];

        return $result;
    }

    /**
     * Serialize checklist work on the persisted bestowal when it exists.
     *
     * A missing row means the supplied entity is stale or was never saved; in
     * either case checklist writes must not proceed without a lockable owner.
     *
     * @param \Awards\Model\Entity\Bestowal $bestowal Bestowal context.
     * @return \Awards\Model\Entity\Bestowal|null
     */
    private function lockPersistedBestowal(Bestowal $bestowal): ?Bestowal
    {
        $bestowals = $this->fetchTable('Awards.Bestowals');
        $query = $bestowals->hasBehavior('Trash')
            ? $bestowals->find('withTrashed')
            : $bestowals->find();
        $persisted = $query
            ->where(['Bestowals.id' => (int)$bestowal->id])
            ->epilog('FOR UPDATE')
            ->first();

        return $persisted instanceof Bestowal ? $persisted : null;
    }

    /**
     * Reconcile every currently open bestowal with its award's current template.
     *
     * Each bestowal is synchronized independently so one malformed template
     * does not roll back successful work for other bestowals.
     *
     * @param int|null $actorId Member initiating the synchronization, if any.
     * @return \App\Services\ServiceResult Data contains per-bestowal and aggregate mutation counts.
     */
    public function syncOpenBestowals(?int $actorId = null): ServiceResult
    {
        $bestowalIds = $this->fetchTable('Awards.Bestowals')->find()
            ->select(['id'])
            ->where([
                'Bestowals.deleted IS' => null,
                'OR' => [
                    'Bestowals.lifecycle_status IS' => null,
                    'Bestowals.lifecycle_status' => Bestowal::LIFECYCLE_OPEN,
                ],
            ])
            ->orderBy(['Bestowals.id' => 'ASC'])
            ->all()
            ->extract('id')
            ->map(static fn($id): int => (int)$id)
            ->toList();

        $summary = [
            'processedCount' => 0,
            'changedCount' => 0,
            'unchangedCount' => 0,
            'skippedCount' => 0,
            'failedCount' => 0,
            'failures' => [],
            'skips' => [],
            'createdCount' => 0,
            'updatedCount' => 0,
            'cancelledCount' => 0,
            'reopenedCount' => 0,
            'requiredCompletedCount' => 0,
            'requiredReopenedCount' => 0,
            'requiredSkippedCount' => 0,
        ];
        $mutationKeys = [
            'createdCount',
            'updatedCount',
            'cancelledCount',
            'reopenedCount',
            'requiredCompletedCount',
            'requiredReopenedCount',
        ];
        $aggregateKeys = array_merge($mutationKeys, ['requiredSkippedCount']);

        foreach ($bestowalIds as $bestowalId) {
            $summary['processedCount']++;
            $result = $this->syncPersistedOpenBestowal($bestowalId, $actorId);
            if (!$result->success) {
                $summary['failedCount']++;
                $summary['failures'][] = [
                    'bestowalId' => $bestowalId,
                    'reason' => $result->reason ?? 'Unknown synchronization failure.',
                ];

                continue;
            }
            if (!empty($result->data['skipped'])) {
                $summary['skippedCount']++;
                $summary['skips'][] = [
                    'bestowalId' => $bestowalId,
                    'templateId' => $result->data['templateId'] ?? null,
                    'reason' => $result->reason ?? 'Synchronization was skipped.',
                ];

                continue;
            }

            $changed = false;
            foreach ($aggregateKeys as $key) {
                $count = (int)($result->data[$key] ?? 0);
                $summary[$key] += $count;
                if (in_array($key, $mutationKeys, true) && $count > 0) {
                    $changed = true;
                }
            }
            $summary[$changed ? 'changedCount' : 'unchangedCount']++;
        }

        $success = $summary['failedCount'] === 0;
        $reason = $success ? null : sprintf(
            '%d open bestowal(s) could not be synchronized.',
            $summary['failedCount'],
        );

        return new ServiceResult($success, $reason, $summary);
    }

    /**
     * Lock one open bestowal so concurrent bulk requests remain idempotent.
     *
     * @param int $bestowalId Bestowal ID selected by the bulk scan.
     * @param int|null $actorId Member initiating the synchronization, if any.
     * @return \App\Services\ServiceResult
     */
    private function syncPersistedOpenBestowal(int $bestowalId, ?int $actorId): ServiceResult
    {
        $bestowals = $this->fetchTable('Awards.Bestowals');
        $connection = $bestowals->getConnection();
        $savePointsWereEnabled = $connection->isSavePointsEnabled();
        if (!$savePointsWereEnabled) {
            $connection->enableSavePoints();
        }

        try {
            return $connection->transactional(function () use ($bestowals, $bestowalId, $actorId): ServiceResult {
                $bestowal = $bestowals->find()
                    ->where([
                        'Bestowals.id' => $bestowalId,
                        'Bestowals.deleted IS' => null,
                        'OR' => [
                            'Bestowals.lifecycle_status IS' => null,
                            'Bestowals.lifecycle_status' => Bestowal::LIFECYCLE_OPEN,
                        ],
                    ])
                    ->epilog('FOR UPDATE')
                    ->first();
                if (!$bestowal instanceof Bestowal) {
                    return new ServiceResult(
                        true,
                        'The bestowal is no longer open.',
                        $this->emptySyncSummary(true),
                    );
                }

                return $this->syncForBestowal($bestowal, $actorId);
            });
        } catch (Throwable $exception) {
            Log::error(sprintf(
                'Bestowal to-do sync failed for bestowal %d: %s',
                $bestowalId,
                $exception->getMessage(),
            ));

            return new ServiceResult(false, self::SYNC_FAILURE_REASON);
        } finally {
            if (!$savePointsWereEnabled) {
                $connection->disableSavePoints();
            }
        }
    }

    /**
     * Resolve the assigned template and convert its items to action definitions.
     *
     * Inactive templates remain authoritative when they are still assigned to
     * an award. Missing templates are safe no-ops; a deliberately empty
     * assigned template is an authoritative zero-item definition.
     *
     * @param \Awards\Model\Entity\Bestowal $bestowal Saved bestowal entity.
     * @return \App\Services\ServiceResult
     */
    private function resolveTemplateContext(Bestowal $bestowal): ServiceResult
    {
        $awardId = $bestowal->award_id !== null ? (int)$bestowal->award_id : 0;
        if ($awardId <= 0) {
            return new ServiceResult(
                true,
                'Bestowal has no award; no to-do template applied.',
                $this->emptyTemplateContext(),
            );
        }

        $award = $this->fetchTable('Awards.Awards')->find()
            ->where(['Awards.id' => $awardId])
            ->select(['Awards.id', 'Awards.branch_id', 'Awards.bestowal_todo_template_id'])
            ->first();
        if ($award === null || $award->get('bestowal_todo_template_id') === null) {
            return new ServiceResult(
                true,
                'No bestowal to-do template assigned to this award.',
                $this->emptyTemplateContext(),
            );
        }

        $templateId = (int)$award->get('bestowal_todo_template_id');
        $template = $this->loadTemplate($templateId);
        if ($template === null) {
            return new ServiceResult(
                true,
                'Assigned bestowal to-do template is missing.',
                $this->emptyTemplateContext($templateId),
            );
        }

        $awardBranchId = $award->get('branch_id') !== null ? (int)$award->get('branch_id') : null;
        if (empty($template->bestowal_todo_template_items)) {
            return new ServiceResult(true, null, [
                'skipped' => false,
                'templateId' => $templateId,
                'branchId' => $awardBranchId,
                'definitions' => [],
            ]);
        }

        $definitions = [];
        $sourceRefs = [];
        foreach ($template->bestowal_todo_template_items as $item) {
            $definition = $this->buildDefinition($item, $awardBranchId);
            $sourceRef = trim((string)$definition['source_ref']);
            if ($sourceRef === '') {
                return new ServiceResult(false, sprintf(
                    'Bestowal to-do template %d contains an item without a stable key.',
                    $templateId,
                ));
            }
            if (isset($sourceRefs[$sourceRef])) {
                return new ServiceResult(false, sprintf(
                    'Bestowal to-do template %d contains duplicate item key "%s".',
                    $templateId,
                    $sourceRef,
                ));
            }
            $sourceRefs[$sourceRef] = true;
            $definitions[] = $definition;
        }

        return new ServiceResult(true, null, [
            'skipped' => false,
            'templateId' => $templateId,
            'branchId' => $awardBranchId,
            'definitions' => $definitions,
        ]);
    }

    /**
     * @param int|null $templateId Assigned template ID, when available.
     * @return array<string, mixed>
     */
    private function emptyTemplateContext(?int $templateId = null): array
    {
        return [
            'skipped' => true,
            'templateId' => $templateId,
            'branchId' => null,
            'definitions' => [],
        ];
    }

    /**
     * @param bool $skipped Whether synchronization was intentionally skipped.
     * @param int|null $templateId Assigned template ID, when available.
     * @return array<string, int|bool|null>
     */
    private function emptySyncSummary(bool $skipped, ?int $templateId = null): array
    {
        return [
            'createdCount' => 0,
            'updatedCount' => 0,
            'cancelledCount' => 0,
            'reopenedCount' => 0,
            'unchangedCount' => 0,
            'requiredCompletedCount' => 0,
            'requiredReopenedCount' => 0,
            'requiredSkippedCount' => 0,
            'skipped' => $skipped,
            'templateId' => $templateId,
        ];
    }

    /**
     * Load an assigned template with its items in display order.
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
        $definition = [
            'title' => (string)$item->label,
            'description' => $item->description,
            'is_gating' => (bool)$item->is_gating,
            'sort_order' => (int)$item->sort_order,
            'source_ref' => trim((string)$item->item_key),
            'completion_config' => $item->getCompletionConfig() ?? ['required_fields' => []],
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
        $memoKey = $branchId . ':' . $branchType;
        if (array_key_exists($memoKey, $this->ancestorBranchMemo)) {
            return $this->ancestorBranchMemo[$memoKey];
        }

        $branches = $this->fetchTable('Branches');
        $candidateIds = array_values(array_unique(array_merge(
            [$branchId],
            $branches->getAllParents($branchId),
        )));
        $rows = $branches->find()
            ->select(['id', 'type'])
            ->where(['id IN' => $candidateIds])
            ->all()
            ->combine('id', static fn(EntityInterface $branch): EntityInterface => $branch)
            ->toArray();

        foreach ($candidateIds as $candidateId) {
            $branch = $rows[$candidateId] ?? null;
            if ($branch !== null && (string)$branch->get('type') === $branchType) {
                return $this->ancestorBranchMemo[$memoKey] = $branch;
            }
        }

        return $this->ancestorBranchMemo[$memoKey] = null;
    }
}
