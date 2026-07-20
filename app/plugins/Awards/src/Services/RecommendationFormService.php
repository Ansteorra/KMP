<?php
declare(strict_types=1);

namespace Awards\Services;

use Awards\Model\Entity\Recommendation;
use Cake\ORM\Table;

/**
 * Prepares lookup data for recommendation edit forms.
 *
 * Centralises dropdown preparation used by the Turbo Frame templates.
 *
 * @see \Awards\Controller\RecommendationsController::turboEditForm()
 */
class RecommendationFormService
{
    private RecommendationBestowalStatePolicyService $statePolicyService;

    /**
     * @param \Awards\Services\RecommendationBestowalStatePolicyService|null $statePolicyService Optional state policy.
     */
    public function __construct(
        ?RecommendationBestowalStatePolicyService $statePolicyService = null,
    ) {
        $this->statePolicyService = $statePolicyService ?? new RecommendationBestowalStatePolicyService();
    }

    /**
     * Build the status list formatted for dropdown display.
     *
     * Converts the entity's state/status map into a grouped array where each
     * status key maps to an associative array of state => state.
     *
     * @return array<string, array<string, string>> Grouped status list.
     */
    public function buildStatusList(?string $currentState = null): array
    {
        $statusList = Recommendation::getStatuses();
        foreach ($statusList as $key => $value) {
            $states = $value;
            $statusList[$key] = [];
            foreach ($states as $state) {
                $statusList[$key][$state] = $state;
            }
        }

        return $this->statePolicyService->filterUserTargetStatusList($statusList, $currentState);
    }

    /**
     * Build the branches dropdown list.
     *
     * @param \Cake\ORM\Table $awardsTable The Awards ORM table (must have Branches association).
     * @return array Branches list keyed by "id|can_have_members".
     */
    public function buildBranchesList(Table $awardsTable): array
    {
        return $awardsTable->Branches
            ->find('list', keyPath: function ($entity) {
                return $entity->id . '|' . ($entity->can_have_members == 1 ? 'true' : 'false');
            })
            ->where(['can_have_members' => true])
            ->orderBy(['name' => 'ASC'])
            ->toArray();
    }

    /**
     * Prepare all view variables for a single-recommendation edit form (full or quick).
     *
     * Loads the lookup data needed by workflow-centric recommendation edit forms.
     *
     * @param \Cake\ORM\Table $recommendationsTable The Recommendations ORM table.
     * @param \Awards\Model\Entity\Recommendation $recommendation The loaded recommendation entity (with contains).
     * @return array<string, mixed> View variables expected by the edit templates.
     */
    public function prepareEditFormData(
        Table $recommendationsTable,
        Recommendation $recommendation,
    ): array {
        $recommendation->domain_id = $recommendation->award->domain_id;

        // Lookup data
        $awardsTable = $recommendationsTable->Awards->getTarget();
        $awardsDomains = $awardsTable->Domains->find('list', limit: 200)->all();
        $awardsLevels = $awardsTable->Levels->find('list', limit: 200)->all();

        $branches = $this->buildBranchesList($awardsTable);

        $awards = $awardsTable->find('selectable', [
                'domain_id' => $recommendation->domain_id,
                'current_award_id' => $recommendation->award_id,
            ])
            ->select(['id', 'name', 'specialties', 'approval_process_id'])
            ->limit(200)
            ->all();
        $currentApprovalProcessId = $recommendation->current_approval_run?->approval_process_id !== null
            ? (int)$recommendation->current_approval_run->approval_process_id
            : null;

        return compact(
            'recommendation',
            'branches',
            'awards',
            'awardsDomains',
            'awardsLevels',
            'currentApprovalProcessId',
        );
    }
}
