<?php
declare(strict_types=1);

namespace Awards\Services;

use App\KMP\StaticHelpers;
use Awards\Model\Entity\Recommendation;
use Cake\ORM\Table;
use Cake\ORM\TableRegistry;

/**
 * Prepares lookup data for recommendation edit forms (full, quick, and bulk).
 *
 * Centralises the dropdown, gathering-list, and status-list preparation that is
 * shared by turboEditForm, turboQuickEditForm, and turboBulkEditForm. Keeps the
 * controller methods thin while preserving the exact same data contracts expected
 * by the Turbo Frame templates.
 *
 * @see \Awards\Controller\RecommendationsController::turboEditForm()
 * @see \Awards\Controller\RecommendationsController::turboQuickEditForm()
 * @see \Awards\Controller\RecommendationsController::turboBulkEditForm()
 */
class RecommendationFormService
{
    /**
     * Build the status list formatted for dropdown display.
     *
     * Converts the entity's state/status map into a grouped array where each
     * status key maps to an associative array of state => state.
     *
     * @return array<string, array<string, string>> Grouped status list.
     */
    public function buildStatusList(): array
    {
        $statusList = Recommendation::getStatuses();
        foreach ($statusList as $key => $value) {
            $states = $value;
            $statusList[$key] = [];
            foreach ($states as $state) {
                $statusList[$key][$state] = $state;
            }
        }

        return $statusList;
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
     * Loads awards-by-domain, domains, levels, branches, gatherings, cancelled-gathering
     * indicators, and state rules. Returns an associative array matching the set of
     * compact() variables expected by the Turbo Frame edit templates.
     *
     * @param \Cake\ORM\Table $recommendationsTable The Recommendations ORM table.
     * @param \Awards\Model\Entity\Recommendation $recommendation The loaded recommendation entity (with contains).
     * @param callable $getFilteredGatherings A callback(awardId, memberId, futureOnly, gatheringId, selectedIds) returning filtered gatherings.
     * @return array<string, mixed> View variables: rules, recommendation, branches, awards, gatheringList, cancelledGatheringIds, awardsDomains, awardsLevels, statusList, assignedGatheringCancelled.
     */
    public function prepareEditFormData(
        Table $recommendationsTable,
        Recommendation $recommendation,
        callable $getFilteredGatherings,
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
            ->select(['id', 'name', 'specialties'])
            ->limit(200)
            ->all();

        // Get filtered gatherings for this award
        // If status is "Given", show all gatherings (past and future) for retroactive entry
        $futureOnly = ($recommendation->status !== 'Given');
        $selectedRecommendationGatheringIds = [];
        foreach (($recommendation->gatherings ?? []) as $selectedGathering) {
            $selectedRecommendationGatheringIds[] = (int)$selectedGathering->id;
        }
        $gatheringData = $getFilteredGatherings(
            $recommendation->award_id,
            $recommendation->member_id,
            $futureOnly,
            $recommendation->gathering_id,
            $selectedRecommendationGatheringIds,
        );
        $gatheringList = $gatheringData['gatherings'];
        $cancelledGatheringIds = $gatheringData['cancelledGatheringIds'];

        // Check if the assigned gathering is cancelled
        $assignedGatheringCancelled = false;
        if ($recommendation->assigned_gathering && $recommendation->assigned_gathering->cancelled_at !== null) {
            $assignedGatheringCancelled = true;
        }

        $statusList = $this->buildStatusList();
        $rules = StaticHelpers::getAppSetting('Awards.RecommendationStateRules');

        return compact(
            'rules',
            'recommendation',
            'branches',
            'awards',
            'gatheringList',
            'cancelledGatheringIds',
            'awardsDomains',
            'awardsLevels',
            'statusList',
            'assignedGatheringCancelled',
        );
    }

    /**
     * Prepare all view variables for the bulk edit form.
     *
     * Loads branches, all gatherings (with cancelled markers), and status/state
     * rules. Returns an associative array matching the compact() variables expected
     * by the bulk edit template.
     *
     * @param \Cake\ORM\Table $recommendationsTable The Recommendations ORM table.
     * @return array<string, mixed> View variables: rules, branches, gatheringList, statusList, cancelledGatheringIds.
     */
    public function prepareBulkEditFormData(Table $recommendationsTable): array
    {
        $branches = $this->buildBranchesList($recommendationsTable->Awards->getTarget());

        // Get gatherings data
        $gatheringsTable = TableRegistry::getTableLocator()->get('Gatherings');
        $gatheringsData = $gatheringsTable->find()
            ->contain(['Branches' => function ($q) {
                return $q->select(['id', 'name']);
            }])
            ->select(['id', 'name', 'start_date', 'end_date', 'cancelled_at', 'Branches.name'])
            ->orderBy(['start_date' => 'ASC'])
            ->all();

        $statusList = $this->buildStatusList();

        // Format gathering list for dropdown, tracking cancelled gatherings
        $gatheringList = [];
        $cancelledGatheringIds = [];
        foreach ($gatheringsData as $gathering) {
            $label = $gathering->name . ' in ' . $gathering->branch->name . ' on '
                . $gathering->start_date->toDateString() . ' - ' . $gathering->end_date->toDateString();
            if ($gathering->cancelled_at !== null) {
                $label = '[CANCELLED] ' . $label;
                $cancelledGatheringIds[] = $gathering->id;
            }
            $gatheringList[$gathering->id] = $label;
        }

        $rules = StaticHelpers::getAppSetting('Awards.RecommendationStateRules');

        return compact('rules', 'branches', 'gatheringList', 'statusList', 'cancelledGatheringIds');
    }
}
