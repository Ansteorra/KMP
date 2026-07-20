<?php
declare(strict_types=1);

namespace Awards\Services;

use App\Model\Entity\WorkflowInstance;
use App\Services\ApprovalContext\ApprovalContext;
use App\Services\ApprovalContext\ApprovalContextRendererInterface;
use Awards\Model\Entity\Recommendation;
use Awards\Model\Entity\RecommendationApprovalRun;
use Cake\ORM\TableRegistry;
use Cake\Routing\Router;

class RecommendationApprovalContextRenderer implements ApprovalContextRendererInterface
{
    /**
     * Check whether this renderer supports award recommendation approval workflows.
     */
    public function canRender(WorkflowInstance $instance): bool
    {
        if ($instance->entity_type === 'Awards.Recommendations') {
            return true;
        }

        return $this->findRecommendationApprovalRun($instance) instanceof RecommendationApprovalRun;
    }

    /**
     * Render award recommendation details for the unified approvals UI.
     */
    public function render(WorkflowInstance $instance): ApprovalContext
    {
        $recommendation = $this->findRecommendation($instance);
        if (!$recommendation instanceof Recommendation) {
            return new ApprovalContext(
                title: __('Award Recommendation'),
                description: __('Approval requested for an award recommendation.'),
                icon: 'bi-award',
            );
        }

        $awardLabel = $this->awardLabel($recommendation);
        $fields = [
            ['label' => __('Recommended For'), 'value' => (string)$recommendation->member_sca_name],
            ['label' => __('Submitted By'), 'value' => (string)$recommendation->requester_sca_name],
            ['label' => __('Branch'), 'value' => $recommendation->branch->name ?? __('Unknown')],
            ['label' => __('Award'), 'value' => $awardLabel],
            ['label' => __('State'), 'value' => (string)$recommendation->state],
            ['label' => __('Reason'), 'value' => (string)$recommendation->reason],
        ];

        if (!empty($recommendation->specialty)) {
            $fields[] = ['label' => __('Specialty'), 'value' => (string)$recommendation->specialty];
        }
        if (!empty($recommendation->call_into_court)) {
            $fields[] = ['label' => __('Call Into Court'), 'value' => (string)$recommendation->call_into_court];
        }
        if (!empty($recommendation->court_availability)) {
            $fields[] = ['label' => __('Court Availability'), 'value' => (string)$recommendation->court_availability];
        }
        if (!empty($recommendation->person_to_notify)) {
            $fields[] = ['label' => __('Person to Notify'), 'value' => (string)$recommendation->person_to_notify];
        }
        $gatherings = $this->gatheringLabels($recommendation);
        if ($gatherings !== []) {
            $fields[] = ['label' => __('Gatherings'), 'value' => implode(', ', $gatherings)];
        }
        if (!empty($recommendation->group_children)) {
            $fields[] = [
                'label' => __('Grouped Recommendations'),
                'value' => (string)count($recommendation->group_children),
            ];
        }

        return new ApprovalContext(
            title: __('Award Recommendation: {0}', $recommendation->member_sca_name),
            description: __('Review {0} for {1}.', $recommendation->member_sca_name, $awardLabel),
            fields: $fields,
            entityUrl: $this->recommendationUrl((int)$recommendation->id),
            icon: 'bi-award',
            requester: $recommendation->requester_sca_name,
        );
    }

    /**
     * Locate the recommendation referenced by a workflow instance.
     *
     * @param \App\Model\Entity\WorkflowInstance $instance Workflow instance.
     * @return \Awards\Model\Entity\Recommendation|null
     */
    private function findRecommendation(WorkflowInstance $instance): ?Recommendation
    {
        if ($instance->entity_type === 'Awards.Recommendations' && !empty($instance->entity_id)) {
            return $this->findRecommendationById((int)$instance->entity_id);
        }

        $run = $this->findRecommendationApprovalRun($instance);
        if (!$run instanceof RecommendationApprovalRun) {
            return null;
        }

        return $this->findRecommendationById((int)$run->recommendation_id);
    }

    /**
     * Load the recommendation detail graph used by approval detail panels.
     *
     * @param int $recommendationId Recommendation ID.
     * @return \Awards\Model\Entity\Recommendation|null
     */
    private function findRecommendationById(int $recommendationId): ?Recommendation
    {
        return TableRegistry::getTableLocator()
            ->get('Awards.Recommendations')
            ->find()
            ->contain([
                'Requesters',
                'Members',
                'Branches',
                'Awards' => ['Domains', 'Levels'],
                'Gatherings',
                'AssignedGathering',
                'GroupChildren',
            ])
            ->where(['Recommendations.id' => $recommendationId])
            ->first();
    }

    /**
     * Find the recommendation approval run for workflow instances owned by the run.
     *
     * @param \App\Model\Entity\WorkflowInstance $instance Workflow instance.
     * @return \Awards\Model\Entity\RecommendationApprovalRun|null
     */
    private function findRecommendationApprovalRun(WorkflowInstance $instance): ?RecommendationApprovalRun
    {
        if (empty($instance->id)) {
            return null;
        }

        return TableRegistry::getTableLocator()
            ->get('Awards.RecommendationApprovalRuns')
            ->find()
            ->where([
                'RecommendationApprovalRuns.workflow_instance_id' => (int)$instance->id,
                'RecommendationApprovalRuns.deleted IS' => null,
            ])
            ->orderByDesc('RecommendationApprovalRuns.id')
            ->first();
    }

    /**
     * Build a readable award label from the award, domain, level, and specialty.
     *
     * @param \Awards\Model\Entity\Recommendation $recommendation Recommendation.
     * @return string
     */
    private function awardLabel(Recommendation $recommendation): string
    {
        $award = $recommendation->award ?? null;
        if ($award === null) {
            return __('Unknown');
        }

        $parts = array_filter([
            $award->domain->name ?? null,
            $award->level->name ?? null,
            $award->name ?? $award->abbreviation ?? null,
        ]);

        $label = $parts === [] ? __('Unknown') : implode(' / ', $parts);
        if (!empty($recommendation->specialty)) {
            $label .= ' (' . $recommendation->specialty . ')';
        }

        return $label;
    }

    /**
     * @return array<int, string>
     */
    private function gatheringLabels(Recommendation $recommendation): array
    {
        $gatherings = [];
        if (!empty($recommendation->gatherings)) {
            foreach ($recommendation->gatherings as $gathering) {
                $gatherings[] = (string)($gathering->name ?? __('Unknown'));
            }
        }
        if (!empty($recommendation->assigned_gathering)) {
            $gatherings[] = (string)($recommendation->assigned_gathering->name ?? __('Unknown'));
        }

        return array_values(array_unique(array_filter($gatherings)));
    }

    /**
     * Build the source recommendation URL.
     *
     * @param int $recommendationId Recommendation ID.
     * @return string|null
     */
    private function recommendationUrl(int $recommendationId): ?string
    {
        return Router::url([
            'plugin' => 'Awards',
            'controller' => 'Recommendations',
            'action' => 'view',
            $recommendationId,
        ]);
    }
}
