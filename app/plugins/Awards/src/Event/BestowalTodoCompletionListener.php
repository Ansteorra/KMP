<?php
declare(strict_types=1);

namespace Awards\Event;

use App\Model\Entity\ActionItem;
use Awards\Model\Entity\Bestowal;
use Awards\Services\BestowalFinalizationService;
use Cake\Event\EventInterface;
use Cake\Event\EventListenerInterface;
use Cake\Log\Log;

/**
 * Auto-finalizes a bestowal when its gating "Given" to-do is completed.
 *
 * The core ActionItem subsystem stays plugin-agnostic and merely announces an
 * `ActionItem.completed` event. The Awards plugin listens for it here and, when
 * the completed item belongs to a bestowal and all gating checks are now done,
 * flips the bestowal's lifecycle to "given" via BestowalFinalizationService.
 *
 * Best-effort: any failure is logged but never disrupts the to-do transition.
 * One-directional by design — reopening a completed gating item does not revert
 * a bestowal back to "open".
 */
class BestowalTodoCompletionListener implements EventListenerInterface
{
    /**
     * @return array<string, string>
     */
    public function implementedEvents(): array
    {
        return [
            'ActionItem.completed' => 'finalizeBestowal',
        ];
    }

    /**
     * Finalize the owning bestowal when a gating to-do completes.
     *
     * @param \Cake\Event\EventInterface $event The ActionItem.completed event.
     * @return void
     */
    public function finalizeBestowal(EventInterface $event): void
    {
        $item = $event->getData('item');
        if (!$item instanceof ActionItem) {
            return;
        }

        if ($item->entity_type !== Bestowal::ACTION_ITEM_ENTITY_TYPE || !$item->is_gating) {
            return;
        }

        $actorId = (int)$event->getData('actorId');
        $result = (new BestowalFinalizationService())
            ->finalizeFromGatingCompletion((int)$item->entity_id, $actorId);

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
