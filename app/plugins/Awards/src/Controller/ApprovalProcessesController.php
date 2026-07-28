<?php
declare(strict_types=1);

namespace Awards\Controller;

use App\Controller\DataverseGridTrait;
use App\KMP\CaseInsensitiveQuery;
use App\Services\CsvExportService;
use Awards\KMP\GridColumns\ApprovalProcessesGridColumns;
use Awards\Model\Entity\ApprovalProcessStep;
use Awards\Services\AwardApprovalResolverService;
use Cake\Http\Exception\NotFoundException;
use Cake\Http\Response;

/**
 * Admin UI for configuring award recommendation approval processes.
 *
 * @property \Awards\Model\Table\ApprovalProcessesTable $ApprovalProcesses
 */
class ApprovalProcessesController extends AppController
{
    use DataverseGridTrait;

    /**
     * Initialize controller dependencies and authorization.
     *
     * @return void
     */
    public function initialize(): void
    {
        parent::initialize();
        $this->Authorization->authorizeModel('index', 'add', 'gridData');
    }

    /**
     * Approval process index.
     *
     * @return void
     */
    public function index(): void
    {
        $this->set('user', $this->request->getAttribute('identity'));
    }

    /**
     * Approval process grid data endpoint.
     *
     * @param \App\Services\CsvExportService $csvExportService CSV export service
     * @return \Cake\Http\Response|null
     */
    public function gridData(CsvExportService $csvExportService)
    {
        $baseQuery = $this->ApprovalProcesses->find()
            ->contain(['ApprovalProcessSteps']);

        $result = $this->processDataverseGrid([
            'gridKey' => 'Awards.ApprovalProcesses.index.main',
            'gridColumnsClass' => ApprovalProcessesGridColumns::class,
            'baseQuery' => $baseQuery,
            'tableName' => 'ApprovalProcesses',
            'defaultSort' => ['ApprovalProcesses.name' => 'asc'],
            'defaultPageSize' => 25,
            'showAllTab' => false,
            'canAddViews' => false,
            'canFilter' => true,
            'canExportCsv' => false,
        ]);

        if (!empty($result['isCsvExport'])) {
            return $this->handleCsvExport($result, $csvExportService, 'approval-processes');
        }

        $this->set([
            'approvalProcesses' => $result['data'],
            'data' => $result['data'],
            'gridState' => $result['gridState'],
            'columns' => $result['columnsMetadata'],
            'visibleColumns' => $result['visibleColumns'],
            'searchableColumns' => ApprovalProcessesGridColumns::getSearchableColumns(),
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

        if ($turboFrame === 'approval-processes-grid-table') {
            $this->set('tableFrameId', 'approval-processes-grid-table');
            $this->viewBuilder()->disableAutoLayout();
            $this->viewBuilder()->setTemplatePath('element');
            $this->viewBuilder()->setTemplate('dv_grid_table');

            return;
        }

        $this->set('frameId', 'approval-processes-grid');
        $this->viewBuilder()->disableAutoLayout();
        $this->viewBuilder()->setTemplatePath('element');
        $this->viewBuilder()->setTemplate('dv_grid_content');
    }

    /**
     * Add an approval process.
     *
     * @return \Cake\Http\Response|null
     */
    public function add()
    {
        $approvalProcess = $this->ApprovalProcesses->newEmptyEntity();

        if ($this->request->is('post')) {
            $approvalProcess = $this->ApprovalProcesses->patchEntity($approvalProcess, $this->request->getData());
            if ($this->ApprovalProcesses->save($approvalProcess)) {
                $this->Flash->success(__('The approval process has been saved.'));

                return $this->redirect(['action' => 'view', $approvalProcess->id]);
            }
            $this->Flash->error(__('The approval process could not be saved. Please, try again.'));
        }

        $this->set(compact('approvalProcess'));
    }

    /**
     * View an approval process and optional award resolver preview.
     *
     * @param string|int|null $id Approval process ID
     * @param \Awards\Services\AwardApprovalResolverService|null $resolver Resolver service
     * @return void
     */
    public function view($id = null, ?AwardApprovalResolverService $resolver = null): void
    {
        $resolver ??= new AwardApprovalResolverService();
        $approvalProcess = $this->ApprovalProcesses->get($id, contain: [
            'ApprovalProcessSteps',
            'Awards' => ['Branches'],
        ]);
        if (!$approvalProcess) {
            throw new NotFoundException();
        }

        $this->Authorization->authorize($approvalProcess);

        $previewAwardId = $this->request->getQuery('preview_award_id');
        $preview = null;
        if ($previewAwardId) {
            $award = $this->ApprovalProcesses->Awards->find()
                ->contain(['Branches'])
                ->where([
                    'Awards.id' => $previewAwardId,
                    'Awards.approval_process_id' => $approvalProcess->id,
                ])
                ->first();
            if (!$award) {
                throw new NotFoundException(__('The selected award is not assigned to this approval process.'));
            }
            $preview = $resolver->previewProcess($approvalProcess, $award);
        }

        $previewFrameId = 'approval-process-approver-preview';
        $this->set(compact('approvalProcess', 'previewAwardId', 'preview', 'previewFrameId'));
        $this->setFormOptions($approvalProcess->id);

        if ($this->request->getHeaderLine('Turbo-Frame') === $previewFrameId) {
            $this->viewBuilder()->disableAutoLayout();
            $this->viewBuilder()->setTemplate('preview_approvers');
        }
    }

    /**
     * Edit an approval process.
     *
     * @param string|int|null $id Approval process ID
     * @return \Cake\Http\Response|null
     */
    public function edit($id = null)
    {
        $approvalProcess = $this->ApprovalProcesses->get($id);
        if (!$approvalProcess) {
            throw new NotFoundException();
        }
        $this->Authorization->authorize($approvalProcess);

        if ($this->request->is(['patch', 'post', 'put'])) {
            $approvalProcess = $this->ApprovalProcesses->patchEntity($approvalProcess, $this->request->getData());
            if ($this->ApprovalProcesses->save($approvalProcess)) {
                $this->Flash->success(__('The approval process has been saved.'));

                return $this->redirect(['action' => 'view', $approvalProcess->id]);
            }
            $this->Flash->error(__('The approval process could not be saved. Please, try again.'));
        }

        $this->set(compact('approvalProcess'));
    }

    /**
     * Delete an approval process.
     *
     * @param string|int|null $id Approval process ID
     * @return \Cake\Http\Response
     */
    public function delete($id = null)
    {
        $this->request->allowMethod(['post', 'delete']);
        $approvalProcess = $this->ApprovalProcesses->get($id, contain: ['Awards']);
        if (!$approvalProcess) {
            throw new NotFoundException();
        }
        $this->Authorization->authorize($approvalProcess);

        if (!empty($approvalProcess->awards)) {
            $this->Flash->error(__('The approval process is assigned to awards and cannot be deleted.'));

            return $this->redirect(['action' => 'view', $approvalProcess->id]);
        }

        if ($this->ApprovalProcesses->delete($approvalProcess)) {
            $this->Flash->success(__('The approval process has been deleted.'));

            return $this->redirect(['action' => 'index']);
        }

        $this->Flash->error(__('The approval process could not be deleted. Please, try again.'));

        return $this->redirect(['action' => 'view', $approvalProcess->id]);
    }

    /**
     * Add a step to an approval process.
     *
     * @param string|int|null $id Approval process ID
     * @return \Cake\Http\Response
     */
    public function addStep($id = null)
    {
        $this->request->allowMethod(['post']);
        $approvalProcess = $this->ApprovalProcesses->get($id);
        $this->Authorization->authorize($approvalProcess, 'edit');

        $stepsTable = $this->fetchTable('Awards.ApprovalProcessSteps');
        $data = $this->normalizeStepData($this->request->getData() + [
            'approval_process_id' => $approvalProcess->id,
            'step_type' => ApprovalProcessStep::STEP_TYPE_APPROVAL,
        ]);
        $step = $stepsTable->newEntity($data);

        if ($stepsTable->save($step)) {
            $this->Flash->success(__('The approval step has been added.'));
        } else {
            $this->Flash->error(
                $this->formatStepSaveError(
                    __('The approval step could not be added.'),
                    $step->getErrors(),
                ),
                ['escape' => false],
            );
        }

        return $this->redirect(['action' => 'view', $approvalProcess->id]);
    }

    /**
     * Edit one approval process step.
     *
     * @param string|int|null $stepId Approval process step ID
     * @return \Cake\Http\Response
     */
    public function editStep($stepId = null)
    {
        $this->request->allowMethod(['post', 'put', 'patch']);
        $stepsTable = $this->fetchTable('Awards.ApprovalProcessSteps');
        $step = $stepsTable->get($stepId, contain: ['ApprovalProcesses']);
        $this->Authorization->authorize($step->approval_process, 'edit');

        $step = $stepsTable->patchEntity($step, $this->normalizeStepData($this->request->getData()));
        if ($stepsTable->save($step)) {
            $this->Flash->success(__('The approval step has been saved.'));
        } else {
            $this->Flash->error(
                $this->formatStepSaveError(
                    __('The approval step could not be saved.'),
                    $step->getErrors(),
                ),
                ['escape' => false],
            );
        }

        return $this->redirect(['action' => 'view', $step->approval_process_id]);
    }

    /**
     * Delete one approval process step.
     *
     * @param string|int|null $stepId Approval process step ID
     * @return \Cake\Http\Response
     */
    public function deleteStep($stepId = null)
    {
        $this->request->allowMethod(['post', 'delete']);
        $stepsTable = $this->fetchTable('Awards.ApprovalProcessSteps');
        $step = $stepsTable->get($stepId, contain: ['ApprovalProcesses']);
        $this->Authorization->authorize($step->approval_process, 'edit');

        if ($stepsTable->delete($step)) {
            $this->Flash->success(__('The approval step has been deleted.'));
        } else {
            $this->Flash->error(__('The approval step could not be deleted. Please, try again.'));
        }

        return $this->redirect(['action' => 'view', $step->approval_process_id]);
    }

    /**
     * Member lookup for approval-step member-source autocomplete.
     *
     * @return \Cake\Http\Response|null|void
     */
    public function memberSourceAutoComplete(): ?Response
    {
        $this->request->allowMethod(['get']);
        $this->Authorization->authorize($this->ApprovalProcesses->newEmptyEntity(), 'index');

        $q = trim((string)$this->request->getQuery('q', ''));
        $members = $this->fetchTable('Members')->find()
            ->select(['id', 'sca_name', 'branch_id'])
            ->contain(['Branches' => fn($query) => $query->select(['id', 'name'])])
            ->orderBy(['Members.sca_name' => 'ASC'])
            ->limit(20);

        if ($q !== '') {
            $members->where(CaseInsensitiveQuery::contains('Members.sca_name', $q));
        }

        $this->set(compact('members', 'q'));
        $this->viewBuilder()
            ->setClassName('Ajax')
            ->setTemplate('member_source_auto_complete');

        return null;
    }

    /**
     * Normalize typed form controls into persisted step fields.
     *
     * @param array $data Submitted step data
     * @return array
     */
    protected function normalizeStepData(array $data): array
    {
        $sourceIdByType = [
            ApprovalProcessStep::APPROVER_TYPE_ROLE => 'role_source_id',
            ApprovalProcessStep::APPROVER_TYPE_PERMISSION => 'permission_source_id',
            ApprovalProcessStep::APPROVER_TYPE_OFFICE => 'office_source_id',
            ApprovalProcessStep::APPROVER_TYPE_MEMBER => 'member_source_id',
        ];

        $sourceField = $sourceIdByType[$data['approver_type'] ?? ''] ?? null;
        if ($sourceField !== null) {
            $data['approver_source_id'] = $data[$sourceField] ?? null;
        }

        if (($data['approver_type'] ?? null) === ApprovalProcessStep::APPROVER_TYPE_DYNAMIC) {
            $data['approver_source_id'] = null;
        } else {
            $data['approver_source_key'] = null;
        }

        if (($data['branch_mode'] ?? null) !== ApprovalProcessStep::BRANCH_MODE_ANCESTOR_TYPE) {
            $data['branch_type'] = null;
        }

        if (($data['threshold_mode'] ?? null) !== ApprovalProcessStep::THRESHOLD_COUNT) {
            $data['required_count'] = null;
        }

        unset(
            $data['role_source_id'],
            $data['permission_source_id'],
            $data['office_source_id'],
            $data['member_source_id'],
        );

        return $data;
    }

    /**
     * Format approval-step validation errors for flash output.
     *
     * @param string $prefix Error summary
     * @param array<string, mixed> $errors Cake validation/rules errors
     * @return string
     */
    private function formatStepSaveError(string $prefix, array $errors): string
    {
        $messages = $this->flattenStepErrors($errors);
        if (empty($messages)) {
            return h($prefix . ' ' . __('Please, check the fields and try again.'));
        }

        $items = array_map(
            fn(string $message): string => '<li>' . h($message) . '</li>',
            $messages,
        );

        return h($prefix) . '<ul class="mb-0"><li>' .
            __('Please, check the fields and try again.') .
            '</li>' . implode('', $items) . '</ul>';
    }

    /**
     * Flatten Cake validation errors.
     *
     * @param array<string, mixed> $errors Cake validation/rules errors
     * @return array<int, string>
     */
    private function flattenStepErrors(array $errors): array
    {
        $messages = [];
        array_walk_recursive(
            $errors,
            function (mixed $message, string|int $field) use (&$messages): void {
                if (!is_string($message) || $message === '') {
                    return;
                }

                $messages[] = (string)__('{0}: {1}', $field, $message);
            },
        );

        return array_values(array_unique($messages));
    }

    /**
     * Set form option lists used by process and step editors.
     *
     * @param string|int|null $approvalProcessId Approval process used to filter preview awards
     * @return void
     */
    protected function setFormOptions(int|string|null $approvalProcessId = null): void
    {
        $roles = $this->fetchTable('Roles')->find('list', limit: 500)
            ->orderBy(['name' => 'ASC'])
            ->toArray();
        $permissions = $this->fetchTable('Permissions')->find('list', limit: 500)
            ->orderBy(['name' => 'ASC'])
            ->toArray();
        $offices = $this->fetchTable('Officers.Offices')->find('list', limit: 500)
            ->orderBy(['name' => 'ASC'])
            ->toArray();
        $members = $this->fetchTable('Members')->find('list', limit: 500)
            ->orderBy(['sca_name' => 'ASC'])
            ->toArray();
        $awardsQuery = $this->ApprovalProcesses->Awards->find('list', limit: 500)
            ->orderBy(['name' => 'ASC']);
        if ($approvalProcessId !== null) {
            $awardsQuery->where(['Awards.approval_process_id' => $approvalProcessId]);
        }
        $awards = $awardsQuery->toArray();
        $branchTypes = $this->fetchTable('Branches')->find()
            ->select(['type'])
            ->where(['type IS NOT' => null])
            ->distinct(['type'])
            ->orderBy(['type' => 'ASC'])
            ->disableHydration()
            ->all()
            ->combine('type', 'type')
            ->toArray();

        $this->set([
            'roles' => $roles,
            'permissions' => $permissions,
            'offices' => $offices,
            'members' => $members,
            'awards' => $awards,
            'branchTypes' => $branchTypes,
            'approverTypeOptions' => ApprovalProcessStep::APPROVER_TYPE_OPTIONS,
            'branchModeOptions' => ApprovalProcessStep::BRANCH_MODE_OPTIONS,
            'thresholdModeOptions' => ApprovalProcessStep::THRESHOLD_MODE_OPTIONS,
            'actionOptions' => ApprovalProcessStep::ACTION_OPTIONS,
        ]);
    }
}
