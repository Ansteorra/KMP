<?php
declare(strict_types=1);

namespace Awards\Event;

use App\Model\Entity\WorkflowApproval;
use Awards\Services\RecommendationFeedbackService;
use Cake\Event\EventInterface;
use Cake\Event\EventListenerInterface;
use Cake\Log\Log;

/**
 * Keeps recommendation feedback state synchronized with workflow approval lifecycle events.
 */
class RecommendationFeedbackApprovalListener implements EventListenerInterface
{
    /**
     * @return array<string, string>
     */
    public function implementedEvents(): array
    {
        return [
            'Workflow.Approval.Expired' => 'expireFeedbackRecipient',
        ];
    }

    /**
     * Expire the feedback recipient row linked to the expired workflow approval.
     */
    public function expireFeedbackRecipient(EventInterface $event): void
    {
        $approval = $event->getData('approval');
        if (!$approval instanceof WorkflowApproval || !$approval->id) {
            return;
        }

        $result = (new RecommendationFeedbackService())->expireFeedbackForApproval((int)$approval->id);
        if (!$result->isSuccess()) {
            Log::error(sprintf(
                'Recommendation feedback approval expiration sync failed for approval %d: %s',
                (int)$approval->id,
                $result->getError() ?? 'unknown error',
            ));
        }
    }
}
