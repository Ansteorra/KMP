<?php
declare(strict_types=1);

namespace Awards\Event;

use App\Model\Entity\ActionItem;
use Awards\Model\Entity\Bestowal;
use Awards\Model\Entity\BestowalTodoTemplateItem;
use Awards\Services\BestowalFinalizationService;
use Awards\Services\BestowalRecommendationSyncService;
use Cake\Event\EventInterface;
use Cake\Event\EventListenerInterface;
use Cake\Log\Log;

/**
 * Synchronizes bestowal projections and finalizes completed bestowals.
 *
 * The core ActionItem subsystem stays plugin-agnostic and merely announces an
 * `ActionItem.completed` event. The Awards plugin listens for it here, syncs a
 * newly scheduled bestowal after the surrounding to-do transaction commits,
 * and flips the lifecycle to "given" once all gating checks are complete.
 *
 * Best-effort: any failure is logged but never disrupts the to-do transition.
 * One-directional by design — reopening a completed gating item does not revert
 * a bestowal back to "open".
 */
class BestowalTodoCompletionListener implements EventListenerInterface
{
    private BestowalRecommendationSyncService $syncService;

    private BestowalFinalizationService $finalizationService;

    /**
     * @param \Awards\Services\BestowalRecommendationSyncService|null $syncService Recommendation projection sync.
     * @param \Awards\Services\BestowalFinalizationService|null $finalizationService Bestowal finalization service.
     */
    public function __construct(
        ?BestowalRecommendationSyncService $syncService = null,
        ?BestowalFinalizationService $finalizationService = null,
    ) {
        $this->syncService = $syncService ?? new BestowalRecommendationSyncService();
        $this->finalizationService = $finalizationService ?? new BestowalFinalizationService();
    }

    /**
     * @return array<string, string>
     */
    public function implementedEvents(): array
    {
        return [
            'ActionItem.completed' => 'handleCompletedTodo',
        ];
    }

    /**
     * Synchronize or finalize the owning bestowal when a gating to-do completes.
     *
     * @param \Cake\Event\EventInterface $event The ActionItem.completed event.
     * @return void
     */
    public function handleCompletedTodo(EventInterface $event): void
    {
        $item = $event->getData('item');
        if (!$item instanceof ActionItem) {
            return;
        }

        if ($item->entity_type !== Bestowal::ACTION_ITEM_ENTITY_TYPE || !$item->is_gating) {
            return;
        }

        $actorId = $event->getData('actorId');
        if ($actorId === null || (int)$actorId <= 0) {
            return;
        }

        $bestowalId = (int)$item->entity_id;
        $actorId = (int)$actorId;
        if ((string)$item->source_ref === BestowalTodoTemplateItem::ITEM_KEY_EVENT_SCHEDULED) {
            $syncResult = $this->syncService->syncFromBestowal($bestowalId, $actorId);
            if (!($syncResult['success'] ?? false)) {
                Log::error(sprintf(
                    'Recommendation sync for bestowal %d after to-do %d completion failed: %s',
                    $bestowalId,
                    (int)$item->id,
                    (string)($syncResult['error'] ?? 'unknown error'),
                ));
            }
        }

        $result = $this->finalizationService->finalizeFromGatingCompletion($bestowalId, $actorId);

        if (!$result->isSuccess()) {
            Log::error(sprintf(
                'Auto-finalize of bestowal %d after to-do %d completion failed: %s',
                (int)$item->entity_id,
                (int)$item->id,
                $result->getError() ?? 'unknown error',
            ));
        }
    }
}
