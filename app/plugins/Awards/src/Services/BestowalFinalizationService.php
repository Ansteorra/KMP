<?php
declare(strict_types=1);

namespace Awards\Services;

use App\Model\Entity\ActionItem;
use App\Services\ActionItems\ActionItemCompletionFormRegistry;
use App\Services\ActionItems\ActionItemService;
use App\Services\ServiceResult;
use Awards\Model\Entity\Bestowal;
use Cake\I18n\DateTime;
use Cake\ORM\Locator\LocatorAwareTrait;
use Cake\ORM\Table;
use DateTimeInterface;
use RuntimeException;
use Throwable;

/**
 * BestowalFinalizationService - shared "Mark Given" finalization for bestowals.
 *
 * Terminal completion finalizes inside the ActionItem transaction and audit-closes
 * unfinished siblings, including required tasks. Without a terminal snapshot,
 * all gating items must be completed. Linked recommendations synchronize in
 * the same transaction; completed checklist history remains unchanged.
 */
class BestowalFinalizationService
{
    use LocatorAwareTrait;

    private const FINALIZATION_SKIP_NOTE =
        'Bestowal marked given; remaining to-do is not applicable.';

    /**
     * @var \Cake\ORM\Table Bestowals table.
     */
    protected Table $bestowals;

    /**
     * @var \App\Services\ActionItems\ActionItemService To-do lifecycle service.
     */
    protected ActionItemService $actionItemService;

    /**
     * @var \Awards\Services\BestowalRecommendationSyncService Recommendation sync service.
     */
    protected BestowalRecommendationSyncService $syncService;

    /**
     * @param \App\Services\ActionItems\ActionItemService|null $actionItemService To-do service.
     * @param \Awards\Services\BestowalRecommendationSyncService|null $syncService Recommendation sync service.
     * @param \Cake\ORM\Table|null $bestowals Bestowals table.
     */
    public function __construct(
        ?ActionItemService $actionItemService = null,
        ?BestowalRecommendationSyncService $syncService = null,
        ?Table $bestowals = null,
    ) {
        $this->actionItemService = $actionItemService ?? new ActionItemService();
        $this->syncService = $syncService ?? new BestowalRecommendationSyncService();
        $this->bestowals = $bestowals ?? $this->fetchTable('Awards.Bestowals');
    }

    /**
     * Explicitly finalize a bestowal (the user-driven "Mark Given" action).
     *
     * Strict: surfaces a user-facing failure reason when the bestowal is not
     * ready, missing, or cancelled.
     *
     * @param int $bestowalId Bestowal id.
     * @param int $actorId Member performing the action.
     * @param \DateTimeInterface|null $bestowedAt Optional bestowed timestamp (defaults to now).
     * @return \App\Services\ServiceResult Success carries the saved bestowal.
     */
    public function markGiven(int $bestowalId, int $actorId, ?DateTimeInterface $bestowedAt = null): ServiceResult
    {
        if ($bestowalId <= 0) {
            return new ServiceResult(false, 'Bestowal ID is required.');
        }

        $terminal = $this->terminalItem($bestowalId);
        if ($terminal !== null) {
            $actor = $this->fetchTable('Members')->get($actorId);
            if (!$actor->isSuperUser() && !$this->actionItemService->isMemberEligible($terminal, $actorId)) {
                return new ServiceResult(false, 'You are not assigned to the terminal to-do.');
            }
            if (!$terminal->isCompleted()) {
                return $this->actionItemService->complete(
                    (int)$terminal->id,
                    $actorId,
                    null,
                    !$actor->isSuperUser(),
                    $bestowedAt === null ? [] : ['bestowed_at' => $bestowedAt],
                    $actor,
                );
            }

            return $this->finalizeWithLockedRecheck($bestowalId, $actorId, $bestowedAt, true, true);
        }

        return $this->finalizeWithLockedRecheck($bestowalId, $actorId, $bestowedAt, true);
    }

    /** Find the terminal snapshot, not the live template definition. */
    public function terminalItem(int $bestowalId): ?ActionItem
    {
        return $this->fetchTable('ActionItems')->find()->where([
            'entity_type' => Bestowal::ACTION_ITEM_ENTITY_TYPE,
            'entity_id' => $bestowalId, 'is_terminal' => true,
            'status !=' => ActionItem::STATUS_CANCELLED,
        ])->first();
    }

    /** Called inside the owner-locked ActionItem transaction. */
    public function finalizeTerminalCompletion(
        ActionItem $item,
        int $actorId,
        ?DateTimeInterface $bestowedAt = null,
    ): ServiceResult {
        return $this->finalizeWithLockedRecheck((int)$item->entity_id, $actorId, $bestowedAt, true, true);
    }

    /**
     * Auto-finalize a bestowal because its gating to-do(s) just completed.
     *
     * Lenient: benign states (gating still incomplete, bestowal missing, or
     * already given/cancelled) return success no-ops so the best-effort listener
     * stays quiet. Only a genuine save failure returns a failure result.
     *
     * @param int $bestowalId Bestowal id.
     * @param int $actorId Member who completed the gating to-do.
     * @return \App\Services\ServiceResult
     */
    public function finalizeFromGatingCompletion(int $bestowalId, int $actorId): ServiceResult
    {
        if ($bestowalId <= 0) {
            return new ServiceResult(true, 'No bestowal to finalize.');
        }

        return $this->finalizeWithLockedRecheck($bestowalId, $actorId, null, false);
    }

    /**
     * Serialize finalization with workflow synchronization and recheck readiness.
     *
     * @param int $bestowalId Bestowal id.
     * @param int $actorId Member performing or causing the action.
     * @param \DateTimeInterface|null $bestowedAt Optional bestowed timestamp.
     * @param bool $strict Whether readiness failures should be surfaced.
     * @return \App\Services\ServiceResult
     */
    private function finalizeWithLockedRecheck(
        int $bestowalId,
        int $actorId,
        ?DateTimeInterface $bestowedAt,
        bool $strict,
        bool $terminalAction = false,
    ): ServiceResult {
        $connection = $this->bestowals->getConnection();
        $savePointsWereEnabled = $connection->isSavePointsEnabled();
        if (!$savePointsWereEnabled) {
            $connection->enableSavePoints();
        }

        try {
            return $connection->transactional(function () use (
                $bestowalId,
                $actorId,
                $bestowedAt,
                $strict,
                $terminalAction,
            ): ServiceResult {
                $bestowal = $this->loadBestowal($bestowalId, true);
                if ($bestowal === null) {
                    return new ServiceResult(
                        !$strict,
                        $strict ? 'Bestowal not found.' : 'Bestowal not found; no change.',
                    );
                }
                if ($bestowal->lifecycle_status === Bestowal::LIFECYCLE_GIVEN) {
                    return new ServiceResult(true, 'Bestowal already given.', $bestowal);
                }
                if ($bestowal->lifecycle_status === Bestowal::LIFECYCLE_CANCELLED) {
                    return new ServiceResult(
                        !$strict,
                        $strict
                            ? 'A cancelled bestowal cannot be marked given.'
                            : 'Bestowal is not open; no change.',
                        $strict ? null : $bestowal,
                    );
                }
                $terminal = $this->terminalItem($bestowalId);
                if ($terminal !== null) {
                    if (!$terminalAction || !$terminal->isCompleted()) {
                        return new ServiceResult(!$strict, 'Complete the terminal to-do to mark this bestowal Given.');
                    }

                    $provider = ActionItemCompletionFormRegistry::providerFor($terminal);
                    $requirements = $provider?->validateCompletion($terminal);
                    if ($requirements === null || !$requirements->success) {
                        return $requirements ?? new ServiceResult(
                            false,
                            'Terminal completion provider is unavailable.',
                        );
                    }

                    return $this->applyGiven($bestowal, $actorId, $bestowedAt);
                }
                $gatingComplete = $this->actionItemService->allGatingComplete(
                    Bestowal::ACTION_ITEM_ENTITY_TYPE,
                    $bestowalId,
                );
                $configuredWithoutGates = !$this->actionItemService->hasActiveGatingItems(
                    Bestowal::ACTION_ITEM_ENTITY_TYPE,
                    $bestowalId,
                ) && $this->assignedTemplateHasNoGatingItems($bestowal);
                if (!$gatingComplete && !$configuredWithoutGates) {
                    return new ServiceResult(
                        !$strict,
                        $strict
                            ? 'All required checks must be completed before the bestowal can be marked given.'
                            : 'Gating checks are not all complete; no change.',
                    );
                }

                return $this->applyGiven($bestowal, $actorId, $bestowedAt);
            });
        } catch (Throwable $exception) {
            return new ServiceResult(false, $exception->getMessage());
        } finally {
            if (!$savePointsWereEnabled) {
                $connection->disableSavePoints();
            }
        }
    }

    /**
     * Apply the lifecycle flip + recommendation sync for an open bestowal.
     *
     * @param \Awards\Model\Entity\Bestowal $bestowal Open bestowal to finalize.
     * @param int $actorId Member performing the action.
     * @param \DateTimeInterface|null $bestowedAt Optional bestowed timestamp.
     * @return \App\Services\ServiceResult
     */
    protected function applyGiven(Bestowal $bestowal, int $actorId, ?DateTimeInterface $bestowedAt): ServiceResult
    {
        try {
            $connection = $this->bestowals->getConnection();
            $connection->enableSavePoints();
            $savedBestowal = $connection->transactional(function () use (
                $bestowal,
                $actorId,
                $bestowedAt,
            ): Bestowal {
                $this->closeRemainingTodos((int)$bestowal->id, $actorId);

                $bestowal->lifecycle_status = Bestowal::LIFECYCLE_GIVEN;
                $bestowal->bestowed_at = $bestowedAt ?? DateTime::now();
                $bestowal->modified_by = $actorId;

                if (!$this->bestowals->save($bestowal)) {
                    throw new RuntimeException('The bestowal could not be marked given.');
                }

                $syncResult = $this->syncService->syncFromBestowal((int)$bestowal->id, $actorId);
                if (empty($syncResult['success'])) {
                    throw new RuntimeException(
                        (string)($syncResult['error'] ?? 'Linked recommendations could not be synchronized.'),
                    );
                }

                return $bestowal;
            });
        } catch (Throwable $e) {
            return new ServiceResult(false, $e->getMessage());
        }

        return new ServiceResult(true, null, $savedBestowal);
    }

    /**
     * Audit-close unfinished work that no longer applies after finalization.
     *
     * The owner is still open and locked when this runs, allowing the shared
     * ActionItem transition service to preserve one log per skipped to-do.
     * The surrounding transaction rolls every cancellation back if marking the
     * bestowal Given or synchronizing its recommendations fails.
     *
     * @param int $bestowalId Bestowal being finalized.
     * @param int $actorId Member performing or causing finalization.
     * @return void
     */
    private function closeRemainingTodos(int $bestowalId, int $actorId): void
    {
        $items = $this->actionItemService->getItemsForEntity(
            Bestowal::ACTION_ITEM_ENTITY_TYPE,
            $bestowalId,
        );
        foreach ($items as $item) {
            if (!$item->isOpen()) {
                continue;
            }

            $result = $this->actionItemService->cancel(
                (int)$item->id,
                $actorId,
                ($terminal = $this->terminalItem($bestowalId)) !== null
                    ? sprintf('Terminal to-do "%s" completed; remaining to-do is not applicable.', $terminal->title)
                    : self::FINALIZATION_SKIP_NOTE,
                false,
            );
            if (!$result->isSuccess()) {
                throw new RuntimeException('Remaining bestowal to-dos could not be closed.');
            }
        }
    }

    /**
     * Load a bestowal by id, returning null when it does not exist.
     *
     * @param int $bestowalId Bestowal id.
     * @param bool $forUpdate Whether to acquire a row lock.
     * @return \Awards\Model\Entity\Bestowal|null
     */
    protected function loadBestowal(int $bestowalId, bool $forUpdate = false): ?Bestowal
    {
        $query = $this->bestowals->find()->where(['Bestowals.id' => $bestowalId]);
        if ($forUpdate) {
            $query->epilog('FOR UPDATE');
        }
        /** @var \Awards\Model\Entity\Bestowal|null $bestowal */
        $bestowal = $query->first();

        return $bestowal;
    }

    /**
     * Whether the assigned, existing template intentionally defines no gates.
     *
     * No assigned template (or a deleted/missing template) remains not ready so
     * a bestowal cannot bypass a checklist that was never configured.
     *
     * @param \Awards\Model\Entity\Bestowal $bestowal Bestowal being finalized.
     * @return bool
     */
    private function assignedTemplateHasNoGatingItems(Bestowal $bestowal): bool
    {
        if ($bestowal->award_id === null) {
            return false;
        }
        $award = $this->fetchTable('Awards.Awards')->find()
            ->select(['bestowal_todo_template_id'])
            ->where(['Awards.id' => (int)$bestowal->award_id])
            ->first();
        $templateId = $award?->get('bestowal_todo_template_id');
        if ($templateId === null) {
            return false;
        }
        $templateExists = $this->fetchTable('Awards.BestowalTodoTemplates')->exists([
            'BestowalTodoTemplates.id' => (int)$templateId,
        ]);
        if (!$templateExists) {
            return false;
        }

        return !$this->fetchTable('Awards.BestowalTodoTemplateItems')->exists([
            'BestowalTodoTemplateItems.template_id' => (int)$templateId,
            'BestowalTodoTemplateItems.is_gating' => true,
        ]);
    }
}
