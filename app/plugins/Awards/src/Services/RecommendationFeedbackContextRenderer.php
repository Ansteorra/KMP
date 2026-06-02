<?php
declare(strict_types=1);

namespace Awards\Services;

use App\Model\Entity\WorkflowInstance;
use App\Services\ApprovalContext\ApprovalContext;
use App\Services\ApprovalContext\ApprovalContextRendererInterface;
use Cake\ORM\TableRegistry;
use Cake\Routing\Exception\MissingRouteException;
use Cake\Routing\Router;

class RecommendationFeedbackContextRenderer implements ApprovalContextRendererInterface
{
    /**
     * Check whether this renderer supports the workflow instance.
     */
    public function canRender(WorkflowInstance $instance): bool
    {
        return $instance->entity_type === 'Awards.RecommendationFeedbackRequests';
    }

    /**
     * Render feedback request context from snapshots only.
     */
    public function render(WorkflowInstance $instance): ApprovalContext
    {
        $request = TableRegistry::getTableLocator()
            ->get('Awards.RecommendationFeedbackRequests')
            ->find()
            ->contain(['Requesters', 'Items'])
            ->where(['RecommendationFeedbackRequests.id' => $instance->entity_id])
            ->first();

        if (!$request) {
            return new ApprovalContext(
                title: __('Recommendation Feedback'),
                description: __('Feedback requested for an award recommendation.'),
                icon: 'bi-chat-left-text',
            );
        }

        $items = $request->items ?? [];
        $primary = $items[0]->snapshot ?? [];
        $isGroup = count($items) > 1;
        $title = $isGroup
            ? __('Feedback: {0} recommendation group', $primary['memberScaName'] ?? __('Unknown'))
            : __('Feedback: {0}', $primary['memberScaName'] ?? __('Unknown'));

        $fields = [
            ['label' => __('Recommended For'), 'value' => $primary['memberScaName'] ?? __('Unknown')],
        ];

        if ($isGroup) {
            $fields[] = ['label' => __('Grouped Recommendations'), 'value' => (string)count($items)];
            foreach (array_values($items) as $index => $item) {
                $snapshot = $item->snapshot ?? [];
                $fields[] = [
                    'label' => __('Recommendation {0}', $index + 1),
                    'value' => $this->groupRecommendationDetails($snapshot),
                ];
            }
        } else {
            $fields[] = ['label' => __('Submitted By'), 'value' => $primary['requesterScaName'] ?? __('Unknown')];
            $fields[] = ['label' => __('Branch'), 'value' => $primary['branchName'] ?? __('Unknown')];
            $fields[] = ['label' => __('Award'), 'value' => $this->awardLabel($primary)];
            $fields[] = ['label' => __('Reason'), 'value' => $primary['reason'] ?? ''];

            if (!empty($primary['gatherings'])) {
                $fields[] = ['label' => __('Gatherings'), 'value' => implode(', ', (array)$primary['gatherings'])];
            }
        }
        if ($request->message) {
            $fields[] = ['label' => __('Requester Message'), 'value' => $request->message];
        }

        return new ApprovalContext(
            title: $title,
            description: __(
                '{0} requested your feedback on an award recommendation.',
                $request->requester->sca_name ?? __('Nobility'),
            ),
            fields: $fields,
            entityUrl: $this->recommendationUrl($primary),
            icon: 'bi-chat-left-text',
            requester: $request->requester->sca_name ?? null,
        );
    }

    /**
     * Build an award label from the safe snapshot fields.
     */
    private function awardLabel(array $snapshot): string
    {
        $parts = array_filter([
            $snapshot['awardDomain'] ?? null,
            $snapshot['awardLevel'] ?? null,
            $snapshot['awardName'] ?? $snapshot['awardAbbreviation'] ?? null,
        ]);

        return $parts === [] ? __('Unknown') : implode(' / ', $parts);
    }

    /**
     * Build one plain-text summary for a grouped recommendation snapshot.
     */
    private function groupRecommendationDetails(array $snapshot): string
    {
        $parts = [
            __('Award: {0}', $this->awardLabel($snapshot)),
            __('Submitted by: {0}', $snapshot['requesterScaName'] ?? __('Unknown')),
            __('Branch: {0}', $snapshot['branchName'] ?? __('Unknown')),
            __('Reason: {0}', $snapshot['reason'] ?? ''),
        ];

        if (!empty($snapshot['gatherings'])) {
            $parts[] = __('Gatherings: {0}', implode(', ', (array)$snapshot['gatherings']));
        }

        return implode('; ', $parts);
    }

    /**
     * Build a recommendation link when routes are available.
     */
    private function recommendationUrl(array $snapshot): ?string
    {
        if (empty($snapshot['recommendationId'])) {
            return null;
        }

        try {
            return Router::url([
                'plugin' => 'Awards',
                'controller' => 'Recommendations',
                'action' => 'view',
                $snapshot['recommendationId'],
            ]);
        } catch (MissingRouteException) {
            return null;
        }
    }
}
