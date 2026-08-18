<?php
declare(strict_types=1);

namespace Awards\Services;

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
 * Encapsulates the lifecycle flip to "given" so both the explicit one-click
 * action (BestowalsController::markGiven) and the automatic auto-finalize
 * listener (BestowalTodoCompletionListener) apply identical rules: every gating
 * to-do must be complete, a cancelled bestowal can never be given, and an
 * already-given bestowal is a no-op. Finalizing also syncs linked
 * recommendations to their "Given" state so recommendation notifications fire.
 */
class BestowalFinalizationService
{
    use LocatorAwareTrait;

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

        return $this->finalizeWithLockedRecheck($bestowalId, $actorId, $bestowedAt, true);
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
    ): ServiceResult {
        $connection = $this->bestowals->getConnection();
        $connection->enableSavePoints();

        try {
            return $connection->transactional(function () use (
                $bestowalId,
                $actorId,
                $bestowedAt,
                $strict,
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
     * Load a bestowal by id, returning null when it does not exist.
     *
     * @param int $bestowalId Bestowal id.
     * @param bool $forUpdate Whether to acquire a row lock.
     * @return \Awards\Model\Entity\Bestowal|null
     */
    protected function loadBestowal(int $bestowalId, bool $forUpdate = false): ?Bestowal
    {
        /** @var \Awards\Model\Entity\Bestowal|null $bestowal */
        $query = $this->bestowals->find()->where(['Bestowals.id' => $bestowalId]);
        if ($forUpdate) {
            $query->epilog('FOR UPDATE');
        }
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
