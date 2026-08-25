<?php
declare(strict_types=1);

namespace Awards\Controller;

use App\Controller\DataverseGridTrait;
use App\KMP\CaseInsensitiveQuery;
use App\Services\CsvExportService;
use Awards\KMP\GridColumns\BestowalTodoTemplatesGridColumns;
use Awards\Model\Entity\BestowalTodoTemplateItem;
use Awards\Services\BestowalTodoMaterializationService;
use Cake\Http\Exception\NotFoundException;
use Cake\Http\Response;

/**
 * Admin UI for configuring bestowal to-do templates.
 *
 * A bestowal to-do template is a reusable, named checklist assigned to awards.
 * Items are worked in parallel (no sequence); each item is completed by the
 * configured assignee (role / permission / office / member / dynamic).
 *
 * @property \Awards\Model\Table\BestowalTodoTemplatesTable $BestowalTodoTemplates
 */
class BestowalTodoTemplatesController extends AppController
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
     * Bestowal to-do template index.
     *
     * @return void
     */
    public function index(): void
    {
        $user = $this->request->getAttribute('identity');
        $this->set('canAddTemplate', $user->can('add', $this->BestowalTodoTemplates));
    }

    /**
     * Synchronize outdated open bestowals assigned to one to-do template.
     *
     * @param string|int|null $id Template ID
     * @param \Awards\Services\BestowalTodoMaterializationService $syncService To-do synchronizer
     * @return \Cake\Http\Response
     */
    public function syncTemplate($id, BestowalTodoMaterializationService $syncService): Response
    {
        $this->request->allowMethod(['post']);

        $template = $this->BestowalTodoTemplates->get($id);
        $this->Authorization->authorize($template, 'syncOpenBestowals');

        $identity = $this->request->getAttribute('identity');
        $result = $syncService->syncOpenBestowalsForTemplate(
            (int)$template->id,
            (int)$identity->getIdentifier(),
        );
        $data = is_array($result->getData()) ? $result->getData() : [];
        $summary = __(
            'Processed {0} outdated open bestowal(s) for {1}: {2} changed, {3} unchanged, {4} skipped, and {5} failed. '
            . 'To-do changes: {6} created, {7} updated, {8} cancelled, and {9} reopened. '
            . 'Required-field checks: {10} completed, {11} reopened, and {12} skipped.',
            (int)($data['processedCount'] ?? 0),
            (string)$template->name,
            (int)($data['changedCount'] ?? 0),
            (int)($data['unchangedCount'] ?? 0),
            (int)($data['skippedCount'] ?? 0),
            (int)($data['failedCount'] ?? 0),
            (int)($data['createdCount'] ?? 0),
            (int)($data['updatedCount'] ?? 0),
            (int)($data['cancelledCount'] ?? 0),
            (int)($data['reopenedCount'] ?? 0),
            (int)($data['requiredCompletedCount'] ?? 0),
            (int)($data['requiredReopenedCount'] ?? 0),
            (int)($data['requiredSkippedCount'] ?? 0),
        );
        $failedBestowalIds = [];
        foreach ($data['failures'] ?? [] as $failure) {
            if (is_array($failure) && !empty($failure['bestowalId'])) {
                $failedBestowalIds[] = (int)$failure['bestowalId'];
            }
        }
        $failedBestowalIds = array_values(array_unique($failedBestowalIds));
        if ($failedBestowalIds !== []) {
            $shownIds = array_slice($failedBestowalIds, 0, 10);
            $summary .= ' ' . __('Bestowals needing attention: #{0}.', implode(', #', $shownIds));
            if (count($failedBestowalIds) > count($shownIds)) {
                $summary .= ' ' . __('{0} more not shown.', count($failedBestowalIds) - count($shownIds));
            }
        }
        $summary .= $this->operationCategorySummary([
            __('Bestowal to-do synchronization error') => (int)($data['failedCount'] ?? 0),
        ], __('Failure categories'));
        $skippedBestowalIds = [];
        foreach ($data['skips'] ?? [] as $skip) {
            if (is_array($skip) && !empty($skip['bestowalId'])) {
                $skippedBestowalIds[] = (int)$skip['bestowalId'];
            }
        }
        $skippedBestowalIds = array_values(array_unique($skippedBestowalIds));
        if ($skippedBestowalIds !== []) {
            $shownIds = array_slice($skippedBestowalIds, 0, 10);
            $summary .= ' ' . __('Skipped bestowals: #{0}.', implode(', #', $shownIds));
            if (count($skippedBestowalIds) > count($shownIds)) {
                $summary .= ' ' . __('{0} more not shown.', count($skippedBestowalIds) - count($shownIds));
            }
        }
        $summary .= $this->operationReasonSummary($data['skips'] ?? [], __('Skip reasons'));

        if (!$result->isSuccess()) {
            $reason = $result->getError();
            $this->Flash->error($reason === null ? $summary : $summary . ' ' . $reason);
        } elseif ((int)($data['failedCount'] ?? 0) > 0) {
            $this->Flash->warning($summary);
        } else {
            $this->Flash->success($summary);
        }

        return $this->redirect(['action' => 'view', $template->id]);
    }

    /**
     * Bestowal to-do template grid data endpoint.
     *
     * @param \App\Services\CsvExportService $csvExportService CSV export service
     * @return \Cake\Http\Response|null
     */
    public function gridData(CsvExportService $csvExportService)
    {
        $baseQuery = $this->BestowalTodoTemplates->find()
            ->contain(['BestowalTodoTemplateItems']);

        $result = $this->processDataverseGrid([
            'gridKey' => 'Awards.BestowalTodoTemplates.index.main',
            'gridColumnsClass' => BestowalTodoTemplatesGridColumns::class,
            'baseQuery' => $baseQuery,
            'tableName' => 'BestowalTodoTemplates',
            'defaultSort' => ['BestowalTodoTemplates.name' => 'asc'],
            'defaultPageSize' => 25,
            'showAllTab' => false,
            'canAddViews' => false,
            'canFilter' => true,
            'canExportCsv' => false,
        ]);

        if (!empty($result['isCsvExport'])) {
            return $this->handleCsvExport($result, $csvExportService, 'bestowal-todo-templates');
        }

        $this->set([
            'bestowalTodoTemplates' => $result['data'],
            'data' => $result['data'],
            'gridState' => $result['gridState'],
            'columns' => $result['columnsMetadata'],
            'visibleColumns' => $result['visibleColumns'],
            'searchableColumns' => BestowalTodoTemplatesGridColumns::getSearchableColumns(),
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

        if ($turboFrame === 'bestowal-todo-templates-grid-table') {
            $this->set('tableFrameId', 'bestowal-todo-templates-grid-table');
            $this->viewBuilder()->disableAutoLayout();
            $this->viewBuilder()->setTemplatePath('element');
            $this->viewBuilder()->setTemplate('dv_grid_table');

            return;
        }

        $this->set('frameId', 'bestowal-todo-templates-grid');
        $this->viewBuilder()->disableAutoLayout();
        $this->viewBuilder()->setTemplatePath('element');
        $this->viewBuilder()->setTemplate('dv_grid_content');
    }

    /**
     * Add a bestowal to-do template.
     *
     * @return \Cake\Http\Response|null
     */
    public function add()
    {
        $template = $this->BestowalTodoTemplates->newEmptyEntity();

        if ($this->request->is('post')) {
            $template = $this->BestowalTodoTemplates->patchEntity($template, $this->request->getData());
            if ($this->BestowalTodoTemplates->save($template)) {
                $this->Flash->success(__('The bestowal to-do template has been saved.'));

                return $this->redirect(['action' => 'view', $template->id]);
            }
            $this->Flash->error(__('The bestowal to-do template could not be saved. Please, try again.'));
        }

        $this->set(compact('template'));
    }

    /**
     * View a bestowal to-do template and its configured items.
     *
     * @param string|int|null $id Template ID
     * @return void
     */
    public function view(
        $id = null,
        ?BestowalTodoMaterializationService $syncService = null,
    ): void {
        $template = $this->BestowalTodoTemplates->get($id, contain: [
            'BestowalTodoTemplateItems',
            'Awards' => ['Branches'],
        ]);
        if (!$template) {
            throw new NotFoundException();
        }

        $this->Authorization->authorize($template);

        $syncService ??= new BestowalTodoMaterializationService();
        $outdatedBestowalCount = $syncService->countOutdatedOpenBestowals((int)$template->id);

        $this->set(compact('template', 'outdatedBestowalCount'));
        $this->setFormOptions();
    }

    /**
     * Edit a bestowal to-do template.
     *
     * @param string|int|null $id Template ID
     * @return \Cake\Http\Response|null
     */
    public function edit($id = null)
    {
        $template = $this->BestowalTodoTemplates->get($id);
        if (!$template) {
            throw new NotFoundException();
        }
        $this->Authorization->authorize($template);

        if ($this->request->is(['patch', 'post', 'put'])) {
            $template = $this->BestowalTodoTemplates->patchEntity($template, $this->request->getData());
            if ($this->BestowalTodoTemplates->save($template)) {
                $this->Flash->success(__('The bestowal to-do template has been saved.'));

                return $this->redirect(['action' => 'view', $template->id]);
            }
            $this->Flash->error(__('The bestowal to-do template could not be saved. Please, try again.'));
        }

        $this->set(compact('template'));
    }

    /**
     * Delete a bestowal to-do template.
     *
     * @param string|int|null $id Template ID
     * @return \Cake\Http\Response
     */
    public function delete($id = null)
    {
        $this->request->allowMethod(['post', 'delete']);
        $template = $this->BestowalTodoTemplates->get($id, contain: ['Awards']);
        if (!$template) {
            throw new NotFoundException();
        }
        $this->Authorization->authorize($template);

        if (!empty($template->awards)) {
            $this->Flash->error(__('The template is assigned to awards and cannot be deleted.'));

            return $this->redirect(['action' => 'view', $template->id]);
        }

        if ($this->BestowalTodoTemplates->delete($template)) {
            $this->Flash->success(__('The bestowal to-do template has been deleted.'));

            return $this->redirect(['action' => 'index']);
        }

        $this->Flash->error(__('The template could not be deleted. Please, try again.'));

        return $this->redirect(['action' => 'view', $template->id]);
    }

    /**
     * Add an item to a bestowal to-do template.
     *
     * @param string|int|null $id Template ID
     * @return \Cake\Http\Response
     */
    public function addItem($id = null)
    {
        $this->request->allowMethod(['post']);
        $template = $this->BestowalTodoTemplates->get($id);
        $this->Authorization->authorize($template, 'edit');

        $itemsTable = $this->fetchTable('Awards.BestowalTodoTemplateItems');
        $data = $this->request->getData();
        $data['template_id'] = (int)$template->id;
        $data = $this->normalizeItemData($data);
        $item = null;
        $itemKey = $data['item_key'] ?? null;
        if (is_string($itemKey) && $itemKey !== '') {
            $item = $itemsTable->find('onlyTrashed')
                ->where([
                    'template_id' => $template->id,
                    'item_key' => $itemKey,
                ])
                ->first();
        }

        if ($item === null) {
            $item = $itemsTable->newEntity($data);
        } else {
            $item = $itemsTable->patchEntity($item, $data);
            $item->set('deleted', null);
        }

        if ($itemsTable->save($item)) {
            $this->Flash->success(__('The to-do item has been added.'));
        } else {
            $this->Flash->error(
                $this->formatItemSaveError(
                    __('The to-do item could not be added.'),
                    $item->getErrors(),
                ),
                ['escape' => false],
            );
        }

        return $this->redirect(['action' => 'view', $template->id]);
    }

    /**
     * Edit one bestowal to-do template item.
     *
     * @param string|int|null $itemId Item ID
     * @return \Cake\Http\Response
     */
    public function editItem($itemId = null)
    {
        $this->request->allowMethod(['post', 'put', 'patch']);
        $itemsTable = $this->fetchTable('Awards.BestowalTodoTemplateItems');
        $item = $itemsTable->get($itemId, contain: ['BestowalTodoTemplates']);
        $this->Authorization->authorize($item->bestowal_todo_template, 'edit');

        $data = $this->request->getData();
        $data['template_id'] = (int)$item->template_id;
        $item = $itemsTable->patchEntity($item, $this->normalizeItemData($data));
        if ($itemsTable->save($item)) {
            $this->Flash->success(__('The to-do item has been saved.'));
        } else {
            $this->Flash->error(
                $this->formatItemSaveError(
                    __('The to-do item could not be saved.'),
                    $item->getErrors(),
                ),
                ['escape' => false],
            );
        }

        return $this->redirect(['action' => 'view', $item->template_id]);
    }

    /**
     * Delete one bestowal to-do template item.
     *
     * @param string|int|null $itemId Item ID
     * @return \Cake\Http\Response
     */
    public function deleteItem($itemId = null)
    {
        $this->request->allowMethod(['post', 'delete']);
        $itemsTable = $this->fetchTable('Awards.BestowalTodoTemplateItems');
        $item = $itemsTable->get($itemId, contain: ['BestowalTodoTemplates']);
        $this->Authorization->authorize($item->bestowal_todo_template, 'edit');

        if ($itemsTable->delete($item)) {
            $this->Flash->success(__('The to-do item has been deleted.'));
        } else {
            $this->Flash->error(__('The to-do item could not be deleted. Please, try again.'));
        }

        return $this->redirect(['action' => 'view', $item->template_id]);
    }

    /**
     * Member lookup for the member-source autocomplete.
     *
     * @return \Cake\Http\Response|null
     */
    public function memberSourceAutoComplete(): ?Response
    {
        $this->request->allowMethod(['get']);
        $this->Authorization->authorize($this->BestowalTodoTemplates->newEmptyEntity(), 'index');

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
     * Normalize typed form controls into persisted item fields.
     *
     * @param array $data Submitted item data
     * @return array
     */
    protected function normalizeItemData(array $data): array
    {
        $sourceIdByType = [
            BestowalTodoTemplateItem::ASSIGNEE_TYPE_ROLE => 'role_source_id',
            BestowalTodoTemplateItem::ASSIGNEE_TYPE_PERMISSION => 'permission_source_id',
            BestowalTodoTemplateItem::ASSIGNEE_TYPE_OFFICE => 'office_source_id',
            BestowalTodoTemplateItem::ASSIGNEE_TYPE_MEMBER => 'member_source_id',
        ];

        $sourceField = $sourceIdByType[$data['assignee_type'] ?? ''] ?? null;
        if ($sourceField !== null) {
            $data['assignee_source_id'] = $data[$sourceField] ?? null;
        }

        if (($data['assignee_type'] ?? null) === BestowalTodoTemplateItem::ASSIGNEE_TYPE_DYNAMIC) {
            $data['assignee_source_id'] = null;
        } else {
            $data['assignee_source_key'] = null;
        }

        if (($data['branch_mode'] ?? null) !== BestowalTodoTemplateItem::BRANCH_MODE_ANCESTOR_TYPE) {
            $data['branch_type'] = null;
        }

        $requiredField = (string)($data['required_field'] ?? '');
        if ($requiredField === '') {
            $data['required_field'] = null;
            $data['required_field_config'] = null;
        } else {
            $data['required_field'] = $requiredField;
            $data['required_field_config'] = [
                'provider' => BestowalTodoTemplateItem::providerForRequiredField($requiredField),
                'field' => $requiredField,
                'label' => BestowalTodoTemplateItem::labelForRequiredField($requiredField),
                'help' => BestowalTodoTemplateItem::helpForRequiredField($requiredField),
                'conditional_complete_on_assign' => !empty($data['conditional_complete_on_assign']),
                'auto_complete_when_satisfied' => !empty($data['auto_complete_when_satisfied']),
            ];
        }

        $data['is_gating'] = !empty($data['is_gating']);

        unset(
            $data['role_source_id'],
            $data['permission_source_id'],
            $data['office_source_id'],
            $data['member_source_id'],
            $data['conditional_complete_on_assign'],
            $data['auto_complete_when_satisfied'],
        );

        return $data;
    }

    /**
     * Format item validation errors for flash output.
     *
     * @param string $prefix Error summary
     * @param array<string, mixed> $errors Cake validation/rules errors
     * @return string
     */
    private function formatItemSaveError(string $prefix, array $errors): string
    {
        $messages = $this->flattenItemErrors($errors);
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
    private function flattenItemErrors(array $errors): array
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
     * Set form option lists used by template and item editors.
     *
     * @return void
     */
    protected function setFormOptions(): void
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
            'branchTypes' => $branchTypes,
            'assigneeTypeOptions' => BestowalTodoTemplateItem::ASSIGNEE_TYPE_OPTIONS,
            'branchModeOptions' => BestowalTodoTemplateItem::BRANCH_MODE_OPTIONS,
            'requiredFieldOptions' => BestowalTodoTemplateItem::REQUIRED_FIELD_OPTIONS,
        ]);
    }
}
