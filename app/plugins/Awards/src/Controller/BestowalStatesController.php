<?php
declare(strict_types=1);

namespace Awards\Controller;

use App\Controller\DataverseGridTrait;
use App\Services\CsvExportService;
use Awards\KMP\GridColumns\BestowalStatesGridColumns;
use Awards\Model\Entity\Bestowal;
use Awards\Model\Entity\BestowalStateFieldRule;
use Cake\Http\Exception\NotFoundException;

/**
 * CRUD operations for bestowal state management.
 *
 * @property \Awards\Model\Table\BestowalStatesTable $BestowalStates
 */
class BestowalStatesController extends AppController
{
    use DataverseGridTrait;

    /**
     * @return void
     */
    public function initialize(): void
    {
        parent::initialize();
        $this->Authorization->authorizeModel('index', 'add', 'gridData');
    }

    /**
     * List all bestowal states.
     *
     * @return void
     */
    public function index(): void
    {
        $this->set('user', $this->request->getAttribute('identity'));
    }

    /**
     * Grid data endpoint for states listing.
     *
     * @param \App\Services\CsvExportService $csvExportService Injected CSV export service
     * @return \Cake\Http\Response|null|void
     */
    public function gridData(CsvExportService $csvExportService)
    {
        $baseQuery = $this->BestowalStates->find()
            ->contain([
                'BestowalStatuses',
                'SyncRecommendationState',
                'UnwindRecommendationState',
            ]);

        $result = $this->processDataverseGrid([
            'gridKey' => 'Awards.BestowalStates.index.main',
            'gridColumnsClass' => BestowalStatesGridColumns::class,
            'baseQuery' => $baseQuery,
            'tableName' => 'BestowalStates',
            'defaultSort' => ['BestowalStates.sort_order' => 'asc'],
            'defaultPageSize' => 25,
            'showAllTab' => false,
            'canAddViews' => false,
            'canFilter' => true,
            'canExportCsv' => false,
        ]);

        if (!empty($result['isCsvExport'])) {
            return $this->handleCsvExport($result, $csvExportService, 'bestowal-states');
        }

        $this->set([
            'states' => $result['data'],
            'gridState' => $result['gridState'],
            'columns' => $result['columnsMetadata'],
            'visibleColumns' => $result['visibleColumns'],
            'searchableColumns' => BestowalStatesGridColumns::getSearchableColumns(),
            'dropdownFilterColumns' => $result['dropdownFilterColumns'],
            'filterOptions' => $result['filterOptions'],
            'currentFilters' => $result['currentFilters'],
            'currentSearch' => $result['currentSearch'],
            'currentView' => $result['currentView'],
            'availableViews' => $result['availableViews'],
            'gridKey' => $result['gridKey'],
            'currentSort' => $result['currentSort'],
            'currentMember' => $result['currentMember'],
        ]);

        $turboFrame = $this->request->getHeaderLine('Turbo-Frame');
        $this->viewBuilder()->setPlugin(null);

        if ($turboFrame === 'bestowal-states-grid-table') {
            $this->set('data', $result['data']);
            $this->set('tableFrameId', 'bestowal-states-grid-table');
            $this->viewBuilder()->disableAutoLayout();
            $this->viewBuilder()->setTemplatePath('element');
            $this->viewBuilder()->setTemplate('dv_grid_table');
        } else {
            $this->set('data', $result['data']);
            $this->set('frameId', 'bestowal-states-grid');
            $this->viewBuilder()->disableAutoLayout();
            $this->viewBuilder()->setTemplatePath('element');
            $this->viewBuilder()->setTemplate('dv_grid_content');
        }
    }

    /**
     * View state details with field rules and transitions.
     *
     * @param string|null $id State ID
     * @return void
     */
    public function view($id = null): void
    {
        $state = $this->BestowalStates->get($id, contain: [
            'BestowalStatuses',
            'SyncRecommendationState',
            'UnwindRecommendationState',
            'BestowalStateFieldRules',
            'OutgoingTransitions' => ['ToStates'],
        ]);
        if (!$state) {
            throw new NotFoundException();
        }
        $this->Authorization->authorize($state);

        $bestowalsTable = $this->fetchTable('Awards.Bestowals');
        $bestowalCount = $bestowalsTable->find()
            ->where(['state' => $state->name])
            ->count();

        $allStates = $this->BestowalStates->find()
            ->contain(['BestowalStatuses'])
            ->orderBy(['BestowalStatuses.sort_order' => 'ASC', 'BestowalStates.sort_order' => 'ASC'])
            ->all()
            ->toArray();

        $transitionTargetIds = [];
        foreach ($state->outgoing_transitions as $transition) {
            $transitionTargetIds[$transition->to_state_id] = true;
        }

        $this->set(compact('state', 'bestowalCount', 'allStates', 'transitionTargetIds'));
        $this->set('fieldTargetOptions', BestowalStateFieldRule::FIELD_TARGET_OPTIONS);
        $this->set('ruleTypeOptions', BestowalStateFieldRule::RULE_TYPE_OPTIONS);
        $this->set('recommendationStateOptions', $this->getRecommendationStateOptions());
    }

    /**
     * Create a new state.
     *
     * @return \Cake\Http\Response|null|void
     */
    public function add()
    {
        $state = $this->BestowalStates->newEmptyEntity();
        if ($this->request->is('post')) {
            $state = $this->BestowalStates->patchEntity(
                $state,
                $this->request->getData(),
            );
            if ($this->BestowalStates->save($state)) {
                Bestowal::clearCache();
                $this->Flash->success(__('The Bestowal State has been saved.'));

                return $this->redirect(['action' => 'view', $state->id]);
            }
            $this->Flash->error(__('The Bestowal State could not be saved. Please, try again.'));
        }
        $statuses = $this->BestowalStates->BestowalStatuses->find('list', limit: 200)
            ->orderBy(['sort_order' => 'ASC'])
            ->toArray();
        $this->set(compact('state', 'statuses'));
        $this->set('recommendationStateOptions', $this->getRecommendationStateOptions());
    }

    /**
     * Edit an existing state.
     *
     * @param string|null $id State ID
     * @return \Cake\Http\Response|null|void
     */
    public function edit($id = null)
    {
        $state = $this->BestowalStates->get($id, contain: ['BestowalStatuses']);
        if (!$state) {
            throw new NotFoundException();
        }
        $this->Authorization->authorize($state);

        if ($state->is_system) {
            $this->Flash->error(__('System states cannot be edited.'));

            return $this->redirect(['action' => 'view', $state->id]);
        }

        if ($this->request->is(['patch', 'post', 'put'])) {
            $oldName = $state->name;
            $oldStatusId = $state->status_id;
            $state = $this->BestowalStates->patchEntity(
                $state,
                $this->request->getData(),
            );

            if ($this->BestowalStates->save($state)) {
                if ($oldName !== $state->name) {
                    $this->cascadeStateRename($oldName, $state->name);
                }
                if ($oldStatusId !== $state->status_id) {
                    $this->cascadeStateStatusChange($state->name, $state->status_id);
                }
                Bestowal::clearCache();
                $this->Flash->success(__('The Bestowal State has been saved.'));

                return $this->redirect(['action' => 'view', $state->id]);
            }
            $this->Flash->error(__('The Bestowal State could not be saved. Please, try again.'));
        }
        $statuses = $this->BestowalStates->BestowalStatuses->find('list', limit: 200)
            ->orderBy(['sort_order' => 'ASC'])
            ->toArray();
        $this->set(compact('state', 'statuses'));
        $this->set('recommendationStateOptions', $this->getRecommendationStateOptions());
    }

    /**
     * Delete a state.
     *
     * @param string|null $id State ID
     * @return \Cake\Http\Response|null
     */
    public function delete($id = null)
    {
        $this->request->allowMethod(['post', 'delete']);
        $state = $this->BestowalStates->get($id);
        if (!$state) {
            throw new NotFoundException();
        }
        $this->Authorization->authorize($state);

        if ($state->is_system) {
            $this->Flash->error(__('System states cannot be deleted.'));

            return $this->redirect(['action' => 'view', $state->id]);
        }

        $bestowalsTable = $this->fetchTable('Awards.Bestowals');
        $bestowalCount = $bestowalsTable->find()
            ->where(['state' => $state->name])
            ->count();

        if ($bestowalCount > 0) {
            $targetStateId = $this->request->getData('target_state_id');
            if (empty($targetStateId)) {
                $this->Flash->error(__(
                    'Cannot delete this state because {0} bestowal(s) are in it. '
                    . 'Please select a target state to transfer them.',
                    $bestowalCount,
                ));

                return $this->redirect(['action' => 'view', $state->id]);
            }

            $targetState = $this->BestowalStates->get((int)$targetStateId, contain: ['BestowalStatuses']);
            $this->transferBestowals($state->name, $targetState->name, $targetState->bestowal_status->name);
        }

        if ($this->BestowalStates->delete($state)) {
            Bestowal::clearCache();
            $this->Flash->success(__('The Bestowal State has been deleted.'));
        } else {
            $this->Flash->error(__('The Bestowal State could not be deleted. Please, try again.'));

            return $this->redirect(['action' => 'view', $state->id]);
        }

        return $this->redirect(['action' => 'index']);
    }

    /**
     * Add a field rule to a state.
     *
     * @param string $stateId State ID
     * @return \Cake\Http\Response|null|void
     */
    public function addFieldRule(string $stateId)
    {
        $this->request->allowMethod(['post']);
        $state = $this->BestowalStates->get($stateId);
        $this->Authorization->authorize($state, 'edit');

        $rulesTable = $this->fetchTable('Awards.BestowalStateFieldRules');
        $rule = $rulesTable->newEntity($this->request->getData());
        $rule->state_id = (int)$stateId;

        if ($rulesTable->save($rule)) {
            Bestowal::clearCache();
            $this->Flash->success(__('Field rule added.'));
        } else {
            $this->Flash->error(__('Could not add field rule. Please, try again.'));
        }

        return $this->redirect(['action' => 'view', $stateId]);
    }

    /**
     * Edit an existing field rule.
     *
     * @param string $ruleId Rule ID
     * @return \Cake\Http\Response|null|void
     */
    public function editFieldRule(string $ruleId)
    {
        $this->request->allowMethod(['post', 'put', 'patch']);
        $rulesTable = $this->fetchTable('Awards.BestowalStateFieldRules');
        $rule = $rulesTable->get($ruleId, contain: ['BestowalStates']);
        $this->Authorization->authorize($rule->bestowal_state, 'edit');

        $stateId = $rule->state_id;
        $rule = $rulesTable->patchEntity($rule, $this->request->getData());

        if ($rulesTable->save($rule)) {
            Bestowal::clearCache();
            $this->Flash->success(__('Field rule updated.'));
        } else {
            $this->Flash->error(__('Could not update field rule. Please, try again.'));
        }

        return $this->redirect(['action' => 'view', $stateId]);
    }

    /**
     * Delete a field rule from a state.
     *
     * @param string $ruleId Rule ID
     * @return \Cake\Http\Response|null|void
     */
    public function deleteFieldRule(string $ruleId)
    {
        $this->request->allowMethod(['post', 'delete']);
        $rulesTable = $this->fetchTable('Awards.BestowalStateFieldRules');
        $rule = $rulesTable->get($ruleId, contain: ['BestowalStates']);
        $this->Authorization->authorize($rule->bestowal_state, 'edit');

        $stateId = $rule->state_id;
        if ($rulesTable->delete($rule)) {
            Bestowal::clearCache();
            $this->Flash->success(__('Field rule removed.'));
        } else {
            $this->Flash->error(__('Could not remove field rule.'));
        }

        return $this->redirect(['action' => 'view', $stateId]);
    }

    /**
     * Save the transition matrix for a state.
     *
     * @param string $stateId State ID
     * @return \Cake\Http\Response|null|void
     */
    public function saveTransitions(string $stateId)
    {
        $this->request->allowMethod(['post']);
        $state = $this->BestowalStates->get($stateId);
        $this->Authorization->authorize($state, 'edit');

        $transitionsTable = $this->fetchTable('Awards.BestowalStateTransitions');
        $targetIds = $this->request->getData('transition_targets') ?? [];

        $transitionsTable->deleteAll(['from_state_id' => (int)$stateId]);

        foreach ($targetIds as $toStateId) {
            $transition = $transitionsTable->newEntity([
                'from_state_id' => (int)$stateId,
                'to_state_id' => (int)$toStateId,
            ]);
            $transitionsTable->save($transition);
        }

        Bestowal::clearCache();
        $this->Flash->success(__('Transitions updated.'));

        return $this->redirect(['action' => 'view', $stateId]);
    }

    /**
     * Recommendation states for sync/unwind dropdowns (id => label).
     *
     * @return array<int, string>
     */
    private function getRecommendationStateOptions(): array
    {
        $recStatesTable = $this->fetchTable('Awards.RecommendationStates');
        $states = $recStatesTable->find()
            ->contain(['RecommendationStatuses'])
            ->orderBy(['RecommendationStatuses.sort_order' => 'ASC', 'RecommendationStates.sort_order' => 'ASC'])
            ->all();

        $options = [];
        foreach ($states as $recState) {
            $options[$recState->id] = $recState->name . ' (' . $recState->recommendation_status->name . ')';
        }

        return $options;
    }

    /**
     * Cascade a state name change to bestowals and state transition logs.
     *
     * @param string $oldName Previous state name
     * @param string $newName New state name
     * @return void
     */
    private function cascadeStateRename(string $oldName, string $newName): void
    {
        $bestowalsTable = $this->fetchTable('Awards.Bestowals');
        $bestowalsTable->updateAll(
            ['state' => $newName],
            ['state' => $oldName],
        );

        $logsTable = $this->fetchTable('Awards.BestowalsStatesLogs');
        $logsTable->updateAll(
            ['from_state' => $newName],
            ['from_state' => $oldName],
        );
        $logsTable->updateAll(
            ['to_state' => $newName],
            ['to_state' => $oldName],
        );
    }

    /**
     * Update the status column on bestowals when a state moves to a different status.
     *
     * @param string $stateName The state name whose bestowals need updating
     * @param int $newStatusId The new status ID
     * @return void
     */
    private function cascadeStateStatusChange(string $stateName, int $newStatusId): void
    {
        $newStatus = $this->BestowalStates->BestowalStatuses->get($newStatusId);
        $bestowalsTable = $this->fetchTable('Awards.Bestowals');
        $bestowalsTable->updateAll(
            ['status' => $newStatus->name],
            ['state' => $stateName],
        );
    }

    /**
     * Transfer bestowals from one state to another.
     *
     * @param string $fromState Source state name
     * @param string $toState Target state name
     * @param string $toStatus Target status name
     * @return void
     */
    private function transferBestowals(string $fromState, string $toState, string $toStatus): void
    {
        $bestowalsTable = $this->fetchTable('Awards.Bestowals');
        $bestowalsTable->updateAll(
            ['state' => $toState, 'status' => $toStatus],
            ['state' => $fromState],
        );
    }
}
