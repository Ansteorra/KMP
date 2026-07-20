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

        if (!$this->actionItemService->allGatingComplete(Bestowal::ACTION_ITEM_ENTITY_TYPE, $bestowalId)) {
            return new ServiceResult(
                false,
                'All required checks must be completed before the bestowal can be marked given.',
            );
        }

        $bestowal = $this->loadBestowal($bestowalId);
        if ($bestowal === null) {
            return new ServiceResult(false, 'Bestowal not found.');
        }

        if ($bestowal->lifecycle_status === Bestowal::LIFECYCLE_GIVEN) {
            return new ServiceResult(true, 'Bestowal already given.', $bestowal);
        }

        if ($bestowal->lifecycle_status === Bestowal::LIFECYCLE_CANCELLED) {
            return new ServiceResult(false, 'A cancelled bestowal cannot be marked given.');
        }

        return $this->applyGiven($bestowal, $actorId, $bestowedAt);
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

        if (!$this->actionItemService->allGatingComplete(Bestowal::ACTION_ITEM_ENTITY_TYPE, $bestowalId)) {
            return new ServiceResult(true, 'Gating checks are not all complete; no change.');
        }

        $bestowal = $this->loadBestowal($bestowalId);
        if ($bestowal === null) {
            return new ServiceResult(true, 'Bestowal not found; no change.');
        }

        if ($bestowal->lifecycle_status !== Bestowal::LIFECYCLE_OPEN) {
            return new ServiceResult(true, 'Bestowal is not open; no change.', $bestowal);
        }

        return $this->applyGiven($bestowal, $actorId, null);
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
     * @return \Awards\Model\Entity\Bestowal|null
     */
    protected function loadBestowal(int $bestowalId): ?Bestowal
    {
        /** @var \Awards\Model\Entity\Bestowal|null $bestowal */
        $bestowal = $this->bestowals->find()->where(['Bestowals.id' => $bestowalId])->first();

        return $bestowal;
    }
}
