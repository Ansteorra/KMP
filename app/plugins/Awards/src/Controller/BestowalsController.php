<?php
declare(strict_types=1);

namespace Awards\Controller;

use App\Controller\DataverseGridTrait;
use App\Controller\WorkflowDispatchTrait;
use App\KMP\GridRowDomId;
use App\Model\Entity\ActionItem;
use App\Services\ActionItems\ActionItemService;
use App\Services\CsvExportService;
use App\Services\ServiceResult;
use App\Services\WorkflowEngine\TriggerDispatcher;
use Awards\KMP\GridColumns\BestowalsGridColumns;
use Awards\Model\Entity\Bestowal;
use Awards\Model\Entity\BestowalTodoTemplateItem;
use Awards\Services\BestowalCancellationService;
use Awards\Services\BestowalCourtSlotService;
use Awards\Services\BestowalCreationService;
use Awards\Services\BestowalFinalizationService;
use Awards\Services\BestowalFormService;
use Awards\Services\BestowalGatheringLookupService;
use Awards\Services\BestowalQueryService;
use Awards\Services\BestowalUpdateService;
use Cake\Datasource\Exception\RecordNotFoundException;
use Cake\Http\Exception\BadRequestException;
use Cake\Http\Exception\NotFoundException;
use Cake\Http\Response;
use Cake\I18n\DateTime;
use Cake\Log\Log;
use Cake\ORM\TableRegistry;
use Cake\Routing\Router;
use Throwable;
use Traversable;

/**
 * Bestowals Controller
 *
 * Manages award bestowal workflow UI with Dataverse grids and workflow dispatch.
 *
 * @property \Awards\Model\Table\BestowalsTable $Bestowals
 */
class BestowalsController extends AppController
{
    use DataverseGridTrait;
    use WorkflowDispatchTrait;

    /**
     * @return void
     */
    public function initialize(): void
    {
        parent::initialize();
    }

    /**
     * Bestowals index landing page with lazy-loaded grid.
     *
     * @return \Cake\Http\Response|null|void
     */
    public function index(): ?Response
    {
        $emptyBestowal = $this->Bestowals->newEmptyEntity();
        $this->Authorization->authorize($emptyBestowal, 'index');

        $user = $this->request->getAttribute('identity');
        if ($user->checkCan('edit', $emptyBestowal)) {
            $formService = new BestowalFormService();
            $adHocFormData = $formService->prepareAdHocFormData($user);
            $this->set(compact('adHocFormData'));
        }

        return null;
    }

    /**
     * Grid data for the main bestowals index.
     *
     * @param \App\Services\CsvExportService $csvExportService CSV export service
     * @param \Awards\Services\BestowalQueryService $queryService Bestowal query builder
     * @return \Cake\Http\Response|null|void
     */
    public function gridData(CsvExportService $csvExportService, BestowalQueryService $queryService): ?Response
    {
        $emptyBestowal = $this->Bestowals->newEmptyEntity();
        $this->Authorization->authorize($emptyBestowal, 'index');

        $user = $this->request->getAttribute('identity');
        $canViewHidden = $user->checkCan('ViewHidden', $emptyBestowal);

        $systemViews = BestowalsGridColumns::getSystemViews([]);
        $queryContext = $this->resolveDataverseGridQueryContext([
            'gridKey' => 'Awards.Bestowals.index.main',
            'gridColumnsClass' => BestowalsGridColumns::class,
            'systemViews' => $systemViews,
            'defaultSystemView' => 'sys-bestowals-active',
            'defaultSort' => ['Bestowals.created' => 'desc'],
        ]);
        $built = $queryService->buildIndexQuery(
            $this->Bestowals,
            $user->checkCan('edit', $emptyBestowal),
            $queryContext->queryVisibleColumns(),
        );
        $baseQuery = $built['query'];
        $baseQuery = $queryService->applyHiddenStateVisibility($baseQuery, $canViewHidden);
        $baseQuery = $this->Authorization->applyScope($baseQuery, 'index');
        $built['gridOptions']['baseQuery'] = $baseQuery;
        $built['gridOptions'] = $this->withBestowalsBulkSelectionGridOptions($built['gridOptions']);

        $result = $this->processDataverseGrid($built['gridOptions']);

        if (!empty($result['isCsvExport'])) {
            $exportData = $this->prepareBestowalsForDisplay($result['query']->all(), $result['visibleColumns'], false);

            return $this->handleCsvExport($result, $csvExportService, 'bestowals', 'Awards.Bestowals', $exportData);
        }

        $bestowals = $result['data'];
        $this->prepareBestowalsForDisplay($bestowals, $result['visibleColumns']);

        $this->setGridViewVariables($bestowals, $result, 'bestowals-grid', 'bestowals-grid-table');

        return null;
    }

    /**
     * Grid data for bestowals on a gathering detail tab.
     *
     * @param \App\Services\CsvExportService $csvExportService CSV export service
     * @param \Awards\Services\BestowalQueryService $queryService Bestowal query builder
     * @param int|null $gatheringId Gathering ID
     * @return \Cake\Http\Response|null|void
     */
    public function gatheringBestowalsGridData(
        CsvExportService $csvExportService,
        BestowalQueryService $queryService,
        ?int $gatheringId = null,
    ): ?Response {
        if ($gatheringId === null) {
            throw new BadRequestException(__('Gathering ID is required.'));
        }

        $user = $this->request->getAttribute('identity');
        $gatheringsTable = TableRegistry::getTableLocator()->get('Gatherings');
        $gathering = $gatheringsTable->get($gatheringId);

        $bestowal = $this->Bestowals->newEmptyEntity();
        $bestowal->gathering_id = $gatheringId;
        $bestowal->gathering = $gathering;

        $this->Authorization->authorize($bestowal, 'gatheringBestowalsGridData');
        $canViewHidden = $user->checkCan('ViewHidden', $bestowal);

        $systemViews = BestowalsGridColumns::getSystemViews(['context' => 'gatheringBestowals']);
        $queryContext = $this->resolveDataverseGridQueryContext([
            'gridKey' => 'Awards.Bestowals.gathering',
            'gridColumnsClass' => BestowalsGridColumns::class,
            'systemViews' => $systemViews,
            'defaultSystemView' => 'sys-bestowals-gathering',
            'defaultSort' => ['Bestowals.stack_rank' => 'asc', 'Bestowals.id' => 'asc'],
        ]);
        $built = $queryService->buildGatheringBestowalsQuery(
            $this->Bestowals,
            $gatheringId,
            $user->checkCan('edit', $bestowal),
            $queryContext->queryVisibleColumns(),
        );
        $baseQuery = $built['query'];
        $baseQuery = $this->Authorization->applyScope($baseQuery, 'index');
        $baseQuery = $queryService->applyHiddenStateVisibility($baseQuery, $canViewHidden);
        $built['gridOptions']['baseQuery'] = $baseQuery;

        $result = $this->processDataverseGrid($built['gridOptions']);

        if (!empty($result['isCsvExport'])) {
            $exportData = $this->prepareBestowalsForDisplay($result['query']->all(), $result['visibleColumns'], false);

            return $this->handleCsvExport(
                $result,
                $csvExportService,
                'gathering-bestowals',
                'Awards.Bestowals',
                $exportData,
            );
        }

        $bestowals = $result['data'];
        $this->prepareBestowalsForDisplay($bestowals, $result['visibleColumns']);

        $frameId = 'gathering-bestowals-grid-' . $gatheringId;
        $queryParams = $this->request->getQueryParams();
        $dataUrl = Router::url([
            'plugin' => 'Awards',
            'controller' => 'Bestowals',
            'action' => 'gatheringBestowalsGridData',
            $gatheringId,
        ]);
        $tableDataUrl = $dataUrl;
        if (!empty($queryParams)) {
            $tableDataUrl .= '?' . http_build_query($queryParams);
        }

        $this->set([
            'bestowals' => $bestowals,
            'data' => $bestowals,
            'rowActions' => BestowalsGridColumns::getRowActions(),
            'gridState' => $result['gridState'],
            'columns' => $result['columnsMetadata'],
            'visibleColumns' => $result['visibleColumns'],
            'searchableColumns' => BestowalsGridColumns::getSearchableColumns(),
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
        if ($turboFrame === $frameId . '-table') {
            $this->set('tableFrameId', $frameId . '-table');
            $this->viewBuilder()->setPlugin(null);
            $this->viewBuilder()->disableAutoLayout();
            $this->viewBuilder()->setTemplatePath('element');
            $this->viewBuilder()->setTemplate('dv_grid_table');
        } else {
            $this->set('frameId', $frameId);
            $this->set('dataUrl', $dataUrl);
            $this->set('tableDataUrl', $tableDataUrl);
            $this->viewBuilder()->setPlugin(null);
            $this->viewBuilder()->disableAutoLayout();
            $this->viewBuilder()->setTemplatePath('element');
            $this->viewBuilder()->setTemplate('dv_grid_content');
        }

        return null;
    }

    /**
     * View a single bestowal record.
     *
     * @param string|null $id Bestowal ID
     * @return \Cake\Http\Response|null|void
     */
    public function view(ActionItemService $actionItemService, ?string $id = null): ?Response
    {
        $bestowal = $this->Bestowals->get($id, contain: [
            'Members',
            'Gatherings',
            'GatheringScheduledActivities',
            'Awards' => ['Domains', 'Levels'],
            'PrimaryRecommendation' => ['Awards', 'Awards.Levels'],
            'Recommendations' => ['Awards', 'Awards.Levels', 'Requesters'],
        ]);
        $this->Authorization->authorize($bestowal, 'view');

        $user = $this->request->getAttribute('identity');
        $memberId = (int)$user->getIdentifier();
        $todoContext = $this->buildBestowalTodoContext(
            $bestowal,
            $actionItemService,
            $memberId,
            $user->isSuperUser(),
        );

        $this->set(compact('bestowal'));
        $this->set($todoContext);

        return null;
    }

    /**
     * Render a single bestowal's to-do checklist for the quick To-Dos modal.
     *
     * Returns the parallel preparation checks with per-item eligibility so the
     * grid modal can offer the same gated Complete / Reopen actions as the
     * bestowal view tab, without a full page load.
     *
     * @param \App\Services\ActionItems\ActionItemService $actionItemService To-do service
     * @param string|null $id Bestowal ID
     * @return \Cake\Http\Response|null
     */
    public function bestowalTodos(ActionItemService $actionItemService, ?string $id = null): ?Response
    {
        $bestowal = $this->Bestowals->get($id, contain: ['Members', 'Awards', 'Gatherings']);
        $this->Authorization->authorize($bestowal, 'view');

        $user = $this->request->getAttribute('identity');
        $memberId = (int)$user->getIdentifier();
        $todoContext = $this->buildBestowalTodoContext(
            $bestowal,
            $actionItemService,
            $memberId,
            $user->isSuperUser(),
        );

        $this->set(compact('bestowal'));
        $this->set($todoContext);
        $this->viewBuilder()->disableAutoLayout();

        return null;
    }

    /**
     * Build the shared bestowal to-do view context: items, per-item eligibility,
     * gating counts, progress percentage, the all-gating-complete flag and the
     * current-page return URL used by the gated Complete / Reopen links.
     *
     * @param \Awards\Model\Entity\Bestowal $bestowal Bestowal entity
     * @param \App\Services\ActionItems\ActionItemService $actionItemService To-do service
     * @param int $memberId Current member id
     * @param bool $isSuperUser Whether the current member can bypass to-do assignment
     * @return array<string, mixed>
     */
    protected function buildBestowalTodoContext(
        Bestowal $bestowal,
        ActionItemService $actionItemService,
        int $memberId,
        bool $isSuperUser = false,
    ): array {
        $todoItems = $actionItemService->getItemsForEntity(
            Bestowal::ACTION_ITEM_ENTITY_TYPE,
            (int)$bestowal->id,
        );
        $todoEligibility = [];
        $todoGatingTotal = 0;
        $todoGatingDone = 0;
        foreach ($todoItems as $todoItem) {
            $todoEligibility[$todoItem->id] = $isSuperUser
                || $actionItemService->isMemberEligible($todoItem, $memberId);
            if ($todoItem->is_gating) {
                $todoGatingTotal++;
                if ($todoItem->isCompleted()) {
                    $todoGatingDone++;
                }
            }
        }
        $allGatingComplete = $actionItemService->allGatingComplete(
            Bestowal::ACTION_ITEM_ENTITY_TYPE,
            (int)$bestowal->id,
        );
        $todoRequirementStatus = $this->buildTodoRequirementStatus($todoItems, $bestowal);
        $todoBlockedStatus = $this->buildTodoBlockedStatus($todoItems, $bestowal);
        $gatingPercent = $todoGatingTotal > 0
            ? (int)round($todoGatingDone / $todoGatingTotal * 100)
            : 0;

        return compact(
            'todoItems',
            'todoEligibility',
            'todoRequirementStatus',
            'todoBlockedStatus',
            'todoGatingTotal',
            'todoGatingDone',
            'gatingPercent',
            'allGatingComplete',
        );
    }

    /**
     * Build per-item required-field display status for bestowal checklist surfaces.
     *
     * @param iterable<\App\Model\Entity\ActionItem> $todoItems To-do items.
     * @param \Awards\Model\Entity\Bestowal $bestowal Bestowal owner.
     * @return array<int, array<string, mixed>>
     */
    private function buildTodoRequirementStatus(iterable $todoItems, Bestowal $bestowal): array
    {
        $status = [];
        foreach ($todoItems as $todoItem) {
            $fieldConfigs = $todoItem->getRequiredFieldConfigs();
            $defaultConfig = BestowalTodoTemplateItem::getDefaultRequiredFieldConfigForSourceRef($todoItem->source_ref);
            if ($fieldConfigs === [] && $defaultConfig !== null) {
                $fieldConfigs[] = $defaultConfig;
            }
            foreach ($fieldConfigs as $fieldConfig) {
                $field = $fieldConfig['field'] ?? null;
                if ($field === BestowalTodoTemplateItem::REQUIRED_FIELD_COURT_SLOT) {
                    $courtSlotService = new BestowalCourtSlotService();
                    $courtSlotValue = $courtSlotService->courtSessionSelectValue($bestowal);
                    $courtSlotOptions = $courtSlotService->buildEligibleOptionsForBestowal($bestowal);
                    $status[(int)$todoItem->id] = [
                        'field' => BestowalTodoTemplateItem::REQUIRED_FIELD_COURT_SLOT,
                        'label' => __('Court Assignment'),
                        'satisfied' => $courtSlotService->hasAgendaAssignment($bestowal),
                        'value' => $courtSlotValue,
                        'text' => $courtSlotValue !== null
                            ? (
                                $courtSlotOptions[$courtSlotValue]
                                ?? $courtSlotService->formatCourtSlotDisplay($bestowal)
                            )
                            : null,
                        'options' => $courtSlotOptions,
                    ];

                    continue;
                }

                if ($field !== BestowalTodoTemplateItem::REQUIRED_FIELD_GATHERING) {
                    continue;
                }
                $gatheringId = $bestowal->gathering_id !== null ? (int)$bestowal->gathering_id : null;
                $status[(int)$todoItem->id] = [
                    'field' => 'gathering_id',
                    'label' => __('Bestowal Gathering'),
                    'satisfied' => $gatheringId !== null && $gatheringId > 0,
                    'value' => $gatheringId,
                    'text' => $bestowal->gathering->name ?? null,
                    'lookupUrl' => Router::url([
                        'plugin' => 'Awards',
                        'controller' => 'Bestowals',
                        'action' => 'gatheringsForBestowalAutoComplete',
                        (int)$bestowal->id,
                    ]),
                ];
            }
        }

        return $status;
    }

    /**
     * Build per-item prerequisite display status for bestowal checklist surfaces.
     *
     * @param iterable<\App\Model\Entity\ActionItem> $todoItems To-do items.
     * @param \Awards\Model\Entity\Bestowal $bestowal Bestowal owner.
     * @return array<int, array<string, mixed>>
     */
    private function buildTodoBlockedStatus(iterable $todoItems, Bestowal $bestowal): array
    {
        $items = is_array($todoItems) ? $todoItems : iterator_to_array($todoItems, false);
        $eventScheduled = $this->findTodoBySourceRef($items, BestowalTodoTemplateItem::ITEM_KEY_EVENT_SCHEDULED);
        $eventComplete = $eventScheduled === null || $eventScheduled->isCompleted();
        $hasGathering = $bestowal->gathering_id !== null && (int)$bestowal->gathering_id > 0;
        $status = [];

        foreach ($items as $todoItem) {
            if ((string)$todoItem->source_ref !== BestowalTodoTemplateItem::ITEM_KEY_ADDED_TO_AGENDA) {
                continue;
            }

            if ($eventComplete && $hasGathering) {
                continue;
            }

            $status[(int)$todoItem->id] = [
                'blocked' => true,
                'label' => $eventComplete ? __('Waiting on Gathering') : __('Waiting on Event Scheduled'),
                'message' => $eventComplete
                    ? __('Assign a gathering before Added to Agenda can be completed.')
                    : __('Complete Event Scheduled before Added to Agenda can be completed.'),
            ];
        }

        return $status;
    }

    /**
     * @param iterable<\App\Model\Entity\ActionItem> $todoItems To-do items.
     * @param string $sourceRef Source reference.
     * @return \App\Model\Entity\ActionItem|null
     */
    private function findTodoBySourceRef(iterable $todoItems, string $sourceRef): ?ActionItem
    {
        foreach ($todoItems as $todoItem) {
            if ((string)$todoItem->source_ref === $sourceRef) {
                return $todoItem;
            }
        }

        return null;
    }

    /**
     * Edit a bestowal via workflow dispatch.
     *
     * @param \App\Services\WorkflowEngine\TriggerDispatcher $triggerDispatcher Workflow dispatcher
     * @param string|null $id Bestowal ID
     * @return \Cake\Http\Response|null
     */
    public function edit(TriggerDispatcher $triggerDispatcher, ?string $id = null): ?Response
    {
        $this->request->allowMethod(['patch', 'post', 'put']);

        try {
            $bestowal = $this->Bestowals->get($id);
            $this->Authorization->authorize($bestowal, 'edit');

            $user = $this->request->getAttribute('identity');
            $result = $this->dispatchBestowalMutation(
                $triggerDispatcher,
                'awards-bestowal-update',
                'Awards.BestowalUpdateRequested',
                [
                    'bestowalId' => (int)$bestowal->id,
                    'data' => $this->request->getData(),
                    'actorId' => (int)$user->id,
                ],
            );

            $pageContext = $this->getPageContextUrl();

            if ($result['success']) {
                $stream = $this->tryBestowalsGridTurboResponse(
                    $pageContext,
                    true,
                    null,
                    (int)$bestowal->id,
                );
                if ($stream !== null) {
                    return $stream;
                }
                if (!$this->request->getHeader('Turbo-Frame')) {
                    $this->Flash->success(__('The bestowal has been saved.'));
                }
            } else {
                $this->Flash->error($result['error'] ?? __('The bestowal could not be saved. Please, try again.'));
                $stream = $this->tryBestowalsGridTurboResponse($pageContext, false, (int)$id);
                if ($stream !== null) {
                    return $stream;
                }
            }
        } catch (RecordNotFoundException) {
            throw new NotFoundException(__('Bestowal not found'));
        }

        $redirect = $this->request->getData('current_page');
        if ($redirect) {
            return $this->redirect($redirect);
        }
        if ($pageContext !== null) {
            return $this->redirect($pageContext);
        }

        return $this->redirect(['action' => 'view', $id]);
    }

    /**
     * Render a populated edit form for Turbo Frame partial updates.
     *
     * @param \Awards\Services\BestowalFormService $formService Form preparation service
     * @param string|null $id Bestowal ID
     * @return \Cake\Http\Response|null
     */
    public function turboEditForm(BestowalFormService $formService, ?string $id = null): ?Response
    {
        try {
            $bestowal = $this->Bestowals->get($id, contain: [
                'Members',
                'Gatherings',
                'GatheringScheduledActivities',
                'Awards' => ['Domains', 'Levels'],
                'PrimaryRecommendation' => ['Awards' => ['Domains', 'Levels']],
                'Recommendations' => ['Awards', 'Awards.Levels'],
            ]);
            $this->Authorization->authorize($bestowal, 'turboEditForm');
            $this->set($formService->prepareEditFormData(
                $this->Bestowals,
                $bestowal,
                $this->request->getAttribute('identity'),
            ));

            return null;
        } catch (RecordNotFoundException) {
            throw new NotFoundException(__('Bestowal not found'));
        }
    }

    /**
     * Return court slot options for a gathering as JSON.
     *
     * @param int|null $gatheringId Gathering ID
     * @return \Cake\Http\Response|null
     */
    public function courtSlotsForGathering(?int $gatheringId = null): ?Response
    {
        $this->request->allowMethod(['get']);
        $emptyBestowal = $this->Bestowals->newEmptyEntity();
        $this->Authorization->authorize($emptyBestowal, 'courtSlotsForGathering');

        $member = $this->request->getAttribute('identity');
        $courtSlotService = new BestowalCourtSlotService();
        $courtSlotData = $courtSlotService->buildInitialFormData($gatheringId, null, $member);

        $payload = [
            'enabled' => $courtSlotData['available'],
            'hasScheduledSessions' => $courtSlotData['hasScheduledSessions'],
            'options' => $courtSlotData['options'],
            'optionDates' => $courtSlotData['optionDates'],
            'gatheringStartDate' => $courtSlotData['gatheringStartDate'],
            'helpText' => BestowalCourtSlotService::fieldHelpText(),
            'emptyMessage' => BestowalCourtSlotService::noScheduleMessage(),
        ];

        return $this->response
            ->withType('application/json')
            ->withStringBody((string)json_encode($payload));
    }

    /**
     * Return gathering autocomplete options for a single bestowal edit form.
     *
     * Returns Ajax HTML list items consumed by the shared auto-complete controller.
     *
     * @param \Awards\Services\BestowalGatheringLookupService $lookupService Gathering lookup service
     * @param string|null $bestowalId Bestowal ID
     * @return void
     */
    public function gatheringsForBestowalAutoComplete(
        BestowalGatheringLookupService $lookupService,
        ?string $bestowalId = null,
    ): void {
        $this->request->allowMethod(['get']);
        $emptyBestowal = $this->Bestowals->newEmptyEntity();
        $this->Authorization->authorize($emptyBestowal, 'gatheringsForBestowalAutoComplete');
        $this->viewBuilder()->setClassName('Ajax');
        $this->viewBuilder()->setTemplate('/Recommendations/gatherings_auto_complete');

        $q = trim((string)$this->request->getQuery('q', ''));
        $status = (string)$this->request->getQuery('status', '');
        $includePast = filter_var($this->request->getQuery('include_past', false), FILTER_VALIDATE_BOOLEAN);
        $futureOnly = ($status !== 'Given') && !$includePast;
        $selectedId = $this->request->getQuery('selected_id');
        $selectedId = is_numeric((string)$selectedId) ? (int)$selectedId : null;
        $awardIdOverride = $this->request->getQuery('award_id');
        $awardIdOverride = is_numeric((string)$awardIdOverride) ? (int)$awardIdOverride : null;
        $memberIdOverride = $this->request->getQuery('member_id');
        $memberIdOverride = is_numeric((string)$memberIdOverride) ? (int)$memberIdOverride : null;
        $memberPublicId = trim((string)$this->request->getQuery('member_public_id', ''));
        $recommendationId = $this->request->getQuery('recommendation_id');
        $recommendationId = is_numeric((string)$recommendationId) ? (int)$recommendationId : null;
        $bulkBestowalIds = $this->parseBulkBestowalIds($this->request->getQuery('bestowal_ids'));

        $gatherings = [];
        $rankedGatherings = [];
        $cancelledGatheringIds = [];
        $bestowal = null;

        try {
            if ($bulkBestowalIds !== []) {
                $query = $this->Bestowals->find()
                    ->select(['Bestowals.id'])
                    ->where(['Bestowals.id IN' => $bulkBestowalIds]);
                $query = $this->Authorization->applyScope($query, 'index');
                $scopedIds = array_map('intval', $query->all()->extract('id')->toList());
                $gatheringData = $lookupService->getFilteredGatheringsForBestowalIds(
                    $scopedIds,
                    $futureOnly,
                    $selectedId,
                );
                $gatherings = $gatheringData['gatherings'] ?? [];
                $rankedGatherings = $gatheringData['rankedGatherings'] ?? [];
                $cancelledGatheringIds = $gatheringData['cancelledGatheringIds'] ?? [];
            } elseif ($bestowalId !== null && ctype_digit((string)$bestowalId)) {
                $bestowalQuery = $this->Bestowals->find()
                    ->where(['Bestowals.id' => (int)$bestowalId])
                    ->contain([
                        'Recommendations' => function ($query) {
                            return $query->select(['id', 'award_id', 'member_id', 'bestowal_id']);
                        },
                    ])
                    ->select(['Bestowals.id', 'Bestowals.award_id', 'Bestowals.member_id', 'Bestowals.gathering_id']);
                $bestowal = $this->Authorization->applyScope($bestowalQuery, 'index')->first();
                if ($bestowal !== null) {
                    $includeGatheringId = $selectedId ?? (
                        $bestowal->gathering_id !== null ? (int)$bestowal->gathering_id : null
                    );
                    $gatheringData = $lookupService->getFilteredGatheringsForBestowal(
                        $bestowal,
                        $futureOnly,
                        $includeGatheringId,
                        $awardIdOverride,
                    );
                    $gatherings = $gatheringData['gatherings'] ?? [];
                    $rankedGatherings = $gatheringData['rankedGatherings'] ?? [];
                    $cancelledGatheringIds = $gatheringData['cancelledGatheringIds'] ?? [];
                }
            } elseif ($recommendationId !== null) {
                $recommendation = TableRegistry::getTableLocator()->get('Awards.Recommendations')->find()
                    ->select(['id', 'award_id', 'member_id', 'bestowal_id'])
                    ->where(['id' => $recommendationId])
                    ->first();
                if ($recommendation !== null) {
                    $bestowal = $this->Bestowals->newEmptyEntity();
                    $bestowal->award_id = $recommendation->award_id !== null ? (int)$recommendation->award_id : null;
                    $bestowal->member_id = $recommendation->member_id !== null ? (int)$recommendation->member_id : null;
                    $bestowal->set('recommendations', [$recommendation]);
                    $gatheringData = $lookupService->getFilteredGatheringsForBestowal(
                        $bestowal,
                        $futureOnly,
                        $selectedId,
                        $awardIdOverride,
                    );
                    $gatherings = $gatheringData['gatherings'] ?? [];
                    $rankedGatherings = $gatheringData['rankedGatherings'] ?? [];
                    $cancelledGatheringIds = $gatheringData['cancelledGatheringIds'] ?? [];
                }
            } else {
                $bestowal = $this->Bestowals->newEmptyEntity();
                $bestowal->award_id = $awardIdOverride;
                if ($memberIdOverride !== null) {
                    $bestowal->member_id = $memberIdOverride;
                } elseif ($memberPublicId !== '') {
                    $member = TableRegistry::getTableLocator()->get('Members')->find()
                        ->select(['id'])
                        ->where(['public_id' => $memberPublicId])
                        ->first();
                    if ($member !== null) {
                        $bestowal->member_id = (int)$member->id;
                    }
                }
                $gatheringData = $lookupService->getFilteredGatheringsForBestowal(
                    $bestowal,
                    $futureOnly,
                    $selectedId,
                    $awardIdOverride,
                );
                $gatherings = $gatheringData['gatherings'] ?? [];
                $rankedGatherings = $gatheringData['rankedGatherings'] ?? [];
                $cancelledGatheringIds = $gatheringData['cancelledGatheringIds'] ?? [];
            }

            $stickyGatheringIds = array_values(array_unique(array_filter(array_map('intval', array_merge(
                $selectedId ? [$selectedId] : [],
                $bestowal !== null && $bestowal->gathering_id !== null ? [(int)$bestowal->gathering_id] : [],
            )))));
            $stickyLookup = array_fill_keys($stickyGatheringIds, true);

            if ($q === '') {
                if ($stickyGatheringIds !== []) {
                    $gatherings = array_intersect_key(
                        $gatherings,
                        array_fill_keys($stickyGatheringIds, true),
                    );
                }
            } else {
                $gatherings = array_filter(
                    $gatherings,
                    function ($display, $id) use ($q, $stickyLookup) {
                        return isset($stickyLookup[(int)$id]) || mb_stripos((string)$display, $q) !== false;
                    },
                    ARRAY_FILTER_USE_BOTH,
                );
            }
            $rankedGatherings = $this->filterRankedGatheringsByFlatList($rankedGatherings, $gatherings);
        } catch (Throwable $e) {
            Log::error('Error in gatheringsForBestowalAutoComplete: ' . $e->getMessage());
            $gatherings = [];
            $rankedGatherings = [];
            $cancelledGatheringIds = [];
        }

        $this->set(compact('gatherings', 'rankedGatherings', 'q', 'cancelledGatheringIds', 'selectedId'));
    }

    /**
     * @param array<int, array<string, mixed>> $rankedGatherings Grouped ranked options.
     * @param array<int, string> $gatherings Filtered flat option list keyed by gathering id.
     * @return array<int, array<string, mixed>>
     */
    private function filterRankedGatheringsByFlatList(array $rankedGatherings, array $gatherings): array
    {
        if ($rankedGatherings === []) {
            return [];
        }

        $allowed = array_fill_keys(array_map('intval', array_keys($gatherings)), true);
        $filtered = [];
        foreach ($rankedGatherings as $group) {
            $items = array_values(array_filter(
                $group['items'] ?? [],
                static fn(array $item): bool => isset($allowed[(int)($item['id'] ?? 0)]),
            ));
            if ($items === []) {
                continue;
            }
            $group['items'] = $items;
            $filtered[] = $group;
        }

        return $filtered;
    }

    /**
     * Cancel a bestowal via workflow dispatch.
     *
     * @param \App\Services\WorkflowEngine\TriggerDispatcher $triggerDispatcher Workflow dispatcher
     * @return \Cake\Http\Response|null
     */
    public function cancel(TriggerDispatcher $triggerDispatcher, ?int $id = null): ?Response
    {
        $this->request->allowMethod(['post']);

        $user = $this->request->getAttribute('identity');
        $emptyBestowal = $this->Bestowals->newEmptyEntity();
        $this->Authorization->authorize($emptyBestowal, 'cancel');

        $bestowalId = $this->request->getData('bestowalId')
            ?? $this->request->getData('id')
            ?? $id;
        $closeReason = (string)($this->request->getData('close_reason') ?? 'Cancelled from bestowal view');

        if (empty($bestowalId)) {
            $this->Flash->error(__('Bestowal ID is required.'));
        } else {
            $result = $this->dispatchBestowalMutation(
                $triggerDispatcher,
                'awards-bestowal-cancel',
                'Awards.BestowalCancelRequested',
                [
                    'bestowalId' => (int)$bestowalId,
                    'closeReason' => $closeReason,
                    'close_reason' => $closeReason,
                    'actorId' => (int)$user->id,
                ],
            );

            if ($result['success']) {
                $eventPayload = $result['data']['eventPayload']
                    ?? $result['data']
                    ?? ['bestowalId' => (int)$bestowalId, 'actorId' => (int)$user->id];
                $eventPayload['actorId'] = (int)$user->id;
                $this->dispatchWorkflowEvent(
                    $triggerDispatcher,
                    BestowalCancellationService::EVENT_NAME,
                    $eventPayload,
                );
                $this->Flash->success(__('The bestowal has been cancelled.'));
            } else {
                $this->Flash->error($result['error'] ?? __('The bestowal could not be cancelled.'));
            }
        }

        $redirect = $this->request->getData('current_page');
        if ($redirect) {
            return $this->redirect($redirect);
        }

        if (!empty($bestowalId)) {
            return $this->redirect(['action' => 'view', $bestowalId]);
        }

        return $this->redirect(['action' => 'index']);
    }

    /**
     * Mark a bestowal as Given, gated on completion of all gating to-dos.
     *
     * This is the one-click finalize action: it refuses to finalize until every
     * gating action item for the bestowal is complete, then sets the bestowal's
     * `lifecycle_status` to "given" (recording the bestowed timestamp) and syncs
     * any linked recommendations to their "Given" state so recommendation
     * notifications still fire. Completing the gating "Given" to-do directly also
     * finalizes the bestowal automatically via BestowalTodoCompletionListener;
     * this action covers the explicit button path.
     *
     * @param \Awards\Services\BestowalFinalizationService $finalizationService Bestowal finalize service
     * @return \Cake\Http\Response|null
     */
    public function markGiven(BestowalFinalizationService $finalizationService): ?Response
    {
        $this->request->allowMethod(['post']);

        $user = $this->request->getAttribute('identity');
        $emptyBestowal = $this->Bestowals->newEmptyEntity();
        $this->Authorization->authorize($emptyBestowal, 'updateState');

        $bestowalId = $this->request->getData('bestowalId') ?? $this->request->getData('id');
        $pageContext = $this->getPageContextUrl();

        if (empty($bestowalId)) {
            $this->Flash->error(__('Bestowal ID is required.'));

            return $this->redirectAfterBestowalMutation($pageContext, $bestowalId);
        }

        $bestowedAt = $this->request->getData('bestowed_at') ?? new DateTime();
        $result = $finalizationService->markGiven((int)$bestowalId, (int)$user->id, $bestowedAt);

        if ($result->success) {
            $this->Flash->success(__('The bestowal has been marked given.'));
        } else {
            $this->Flash->error($result->reason ?? __('The bestowal could not be marked given.'));
        }

        return $this->redirectAfterBestowalMutation($pageContext, $bestowalId);
    }

    /**
     * Complete a single named check across many selected bestowals.
     *
     * Drives the grid "Complete Check" bulk action: for each selected bestowal the
     * matching open to-do (by template item key) is completed, but only where the
     * current member is an eligible doer for that check. Bestowals outside the
     * member's authorization scope, without the check open, or where the member is
     * not the assigned doer are skipped and reported in the summary.
     *
     * @param \App\Services\ActionItems\ActionItemService $actionItemService To-do service
     * @return \Cake\Http\Response|null
     */
    public function bulkCompleteTodo(ActionItemService $actionItemService): ?Response
    {
        $this->request->allowMethod(['post']);

        $emptyBestowal = $this->Bestowals->newEmptyEntity();
        $this->Authorization->authorize($emptyBestowal, 'index');

        $user = $this->request->getAttribute('identity');
        $actorId = (int)$user->getIdentifier();
        $pageContext = $this->getPageContextUrl() ?? $this->request->getData('current_page');

        $checkKey = trim((string)$this->request->getData('check_key'));
        $bestowalIds = $this->parseBulkBestowalIds($this->request->getData('bestowal_ids'));
        $completionData = [];
        $gatheringId = $this->positiveIntOrNull($this->request->getData('bestowal_gathering_id'));
        if ($gatheringId !== null) {
            $completionData['bestowal_gathering_id'] = $gatheringId;
        }
        if (filter_var($this->request->getData('include_past', false), FILTER_VALIDATE_BOOLEAN)) {
            $completionData['include_past'] = true;
        }

        if ($checkKey === '' || $bestowalIds === []) {
            $this->Flash->error(__('Select at least one bestowal and a check to complete.'));

            return $this->redirectAfterBestowalMutation($pageContext, null);
        }

        $query = $this->Bestowals->find()
            ->select(['Bestowals.id'])
            ->where(['Bestowals.id IN' => $bestowalIds]);
        $query = $this->Authorization->applyScope($query, 'index');
        $scopedIds = $query->all()->extract('id')->toList();

        $completed = 0;
        $skipped = 0;
        $notApplicable = 0;
        $failureReason = null;

        $itemsByBestowal = $this->loadBestowalActionItems(array_map('intval', $scopedIds));

        foreach ($scopedIds as $bestowalId) {
            $items = $itemsByBestowal[(int)$bestowalId] ?? [];
            $match = null;
            foreach ($items as $item) {
                if ($item->source_ref === $checkKey && $item->isOpen()) {
                    $match = $item;
                    break;
                }
            }

            if ($match === null) {
                $notApplicable++;
                continue;
            }

            $result = $actionItemService->complete(
                (int)$match->id,
                $actorId,
                null,
                !$user->isSuperUser(),
                $completionData,
                $user,
            );
            if ($result->success) {
                $completed++;
            } else {
                $skipped++;
                $failureReason ??= $result->reason !== null ? (string)$result->reason : null;
            }
        }

        $unscoped = count($bestowalIds) - count($scopedIds);
        $notApplicable += $unscoped;

        $this->flashBulkTodoSummary($completed, $skipped, $notApplicable, $failureReason);

        return $this->redirectAfterBestowalMutation($pageContext, null);
    }

    /**
     * Bulk assign selected bestowals to a gathering and optionally complete the matching required-field to-do.
     *
     * @param \App\Services\ActionItems\ActionItemService $actionItemService To-do service
     * @param \Awards\Services\BestowalUpdateService $bestowalUpdateService Bestowal update service
     * @return \Cake\Http\Response|null
     */
    public function bulkAssignGathering(
        ActionItemService $actionItemService,
        BestowalUpdateService $bestowalUpdateService,
    ): ?Response {
        $this->request->allowMethod(['post']);

        $emptyBestowal = $this->Bestowals->newEmptyEntity();
        $this->Authorization->authorize($emptyBestowal, 'bulkAssignGathering');

        $user = $this->request->getAttribute('identity');
        $actorId = (int)$user->getIdentifier();
        $pageContext = $this->getPageContextUrl() ?? $this->request->getData('current_page');
        $bestowalIds = $this->parseBulkBestowalIds($this->request->getData('bestowal_ids'));
        $gatheringId = $this->positiveIntOrNull($this->request->getData('bestowal_gathering_id'));
        $completeRequiredTodo = !empty($this->request->getData('complete_required_todo'));
        $futureOnly = !filter_var($this->request->getData('include_past', false), FILTER_VALIDATE_BOOLEAN);

        if ($bestowalIds === [] || $gatheringId === null) {
            $this->Flash->error(__('Select at least one bestowal and a gathering.'));

            return $this->redirectAfterBestowalMutation($pageContext, null);
        }

        $query = $this->Bestowals->find()
            ->where(['Bestowals.id IN' => $bestowalIds])
            ->contain([
                'Recommendations' => function ($q) {
                    return $q->select(['id', 'award_id', 'member_id', 'bestowal_id']);
                },
            ]);
        $query = $this->Authorization->applyScope($query, 'index');

        $updated = 0;
        $completed = 0;
        $skippedUnauthorized = 0;
        $skippedInvalid = 0;
        $skippedTodo = 0;
        $scopedIds = [];
        $scopedBestowals = [];

        foreach ($query->all() as $bestowal) {
            $scopedIds[] = (int)$bestowal->id;
            $scopedBestowals[] = $bestowal;
        }
        $openItemsByBestowal = $completeRequiredTodo ? $this->loadBestowalActionItems($scopedIds, true) : [];

        foreach ($scopedBestowals as $bestowal) {
            if (!$user->checkCan('manageCourtSchedule', $bestowal)) {
                $skippedUnauthorized++;
                continue;
            }

            $result = $bestowalUpdateService->assignGathering(
                $this->Bestowals,
                (int)$bestowal->id,
                $gatheringId,
                $actorId,
                $futureOnly,
            );
            if (!($result['success'] ?? false)) {
                $skippedInvalid++;
                continue;
            }

            $updated++;
            if (!$completeRequiredTodo) {
                continue;
            }

            $todo = $this->findOpenGatheringRequiredTodoInItems($openItemsByBestowal[(int)$bestowal->id] ?? []);
            if ($todo === null) {
                $skippedTodo++;
                continue;
            }

            $completeResult = $actionItemService->complete((int)$todo->id, $actorId, null, true, [], $user);
            if ($completeResult->success) {
                $completed++;
            } else {
                $skippedTodo++;
            }
        }

        $skippedOutOfScope = count($bestowalIds) - count(array_unique($scopedIds));
        $this->flashBulkGatheringSummary(
            $updated,
            $completed,
            $skippedUnauthorized,
            $skippedInvalid,
            $skippedTodo + $skippedOutOfScope,
        );

        return $this->redirectAfterBestowalMutation($pageContext, null);
    }

    /**
     * Normalize the bulk bestowal id payload (CSV string or array) to unique ints.
     *
     * @param mixed $raw The submitted bestowal_ids value
     * @return list<int>
     */
    private function parseBulkBestowalIds(mixed $raw): array
    {
        if (is_string($raw)) {
            $raw = explode(',', $raw);
        }
        if (!is_array($raw)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map('intval', $raw))));
    }

    /**
     * @param mixed $value Raw value.
     * @return int|null
     */
    private function positiveIntOrNull(mixed $value): ?int
    {
        if (is_string($value)) {
            $value = trim($value);
        }
        if (is_int($value) || (is_string($value) && ctype_digit($value))) {
            $value = (int)$value;

            return $value > 0 ? $value : null;
        }

        return null;
    }

    /**
     * Find the open gathering-required to-do for a bestowal.
     *
     * @param array<int, \App\Model\Entity\ActionItem> $items Bestowal to-do items.
     * @return \App\Model\Entity\ActionItem|null
     */
    private function findOpenGatheringRequiredTodoInItems(array $items): ?ActionItem
    {
        foreach ($items as $item) {
            if (!$item->isOpen()) {
                continue;
            }
            $fieldConfigs = $item->getRequiredFieldConfigs();
            $defaultConfig = BestowalTodoTemplateItem::getDefaultRequiredFieldConfigForSourceRef($item->source_ref);
            if ($fieldConfigs === [] && $defaultConfig !== null) {
                $fieldConfigs[] = $defaultConfig;
            }
            foreach ($fieldConfigs as $fieldConfig) {
                if (
                    ($fieldConfig['field'] ?? null) === 'gathering_id'
                    && ($fieldConfig['conditional_complete_on_assign'] ?? true) !== false
                ) {
                    return $item;
                }
            }
        }

        return null;
    }

    /**
     * Flash a human-readable summary of a bulk gathering assignment.
     *
     * @param int $updated Bestowals updated
     * @param int $completed Required to-dos completed
     * @param int $unauthorized Unauthorized bestowals skipped
     * @param int $invalid Invalid/locked bestowals skipped
     * @param int $notApplicable Out-of-scope or no matching to-do rows
     * @return void
     */
    private function flashBulkGatheringSummary(
        int $updated,
        int $completed,
        int $unauthorized,
        int $invalid,
        int $notApplicable,
    ): void {
        if ($updated > 0) {
            $message = __n(
                'Assigned a gathering to {0} bestowal.',
                'Assigned a gathering to {0} bestowals.',
                $updated,
                $updated,
            );
            if ($completed > 0) {
                $message .= ' ' . __n(
                    'Completed {0} matching to-do.',
                    'Completed {0} matching to-dos.',
                    $completed,
                    $completed,
                );
            }
            $extras = [];
            if ($unauthorized > 0) {
                $extras[] = __n(
                    '{0} skipped (not authorized)',
                    '{0} skipped (not authorized)',
                    $unauthorized,
                    $unauthorized,
                );
            }
            if ($invalid > 0) {
                $extras[] = __n(
                    '{0} skipped (invalid gathering)',
                    '{0} skipped (invalid gathering)',
                    $invalid,
                    $invalid,
                );
            }
            if ($notApplicable > 0) {
                $extras[] = __n(
                    '{0} had no matching to-do',
                    '{0} had no matching to-do',
                    $notApplicable,
                    $notApplicable,
                );
            }
            if ($extras !== []) {
                $message .= ' (' . implode('; ', $extras) . ')';
            }
            $this->Flash->success($message);

            return;
        }

        if ($unauthorized > 0) {
            $this->Flash->error(__('You are not allowed to assign gatherings for the selected bestowals.'));

            return;
        }

        $this->Flash->error(__('No selected bestowals could be assigned to that gathering.'));
    }

    /**
     * Flash a human-readable summary of a bulk check completion.
     *
     * @param int $completed Items completed
     * @param int $skipped Items the actor was not eligible to complete
     * @param int $notApplicable Bestowals without the check open or out of scope
     * @param string|null $failureReason First completion failure reason, when available.
     * @return void
     */
    private function flashBulkTodoSummary(
        int $completed,
        int $skipped,
        int $notApplicable,
        ?string $failureReason = null,
    ): void {
        if ($completed > 0) {
            $message = __n(
                'Completed the check on {0} bestowal.',
                'Completed the check on {0} bestowals.',
                $completed,
                $completed,
            );
            $extras = [];
            if ($skipped > 0) {
                $extras[] = __n('{0} skipped (not your check)', '{0} skipped (not your check)', $skipped, $skipped);
            }
            if ($notApplicable > 0) {
                $extras[] = __n('{0} had no open check', '{0} had no open check', $notApplicable, $notApplicable);
            }
            if ($extras !== []) {
                $message .= ' (' . implode('; ', $extras) . ')';
            }
            $this->Flash->success($message);

            return;
        }

        if ($skipped > 0) {
            $this->Flash->error(
                $failureReason ?? __('You are not assigned to complete that check on the selected bestowals.'),
            );

            return;
        }

        $this->Flash->error(__('None of the selected bestowals have that check open.'));
    }

    /**
     * Record an ad-hoc bestowal via workflow dispatch.
     *
     * @param \App\Services\WorkflowEngine\TriggerDispatcher $triggerDispatcher Workflow dispatcher
     * @return \Cake\Http\Response|null
     */
    public function adHoc(TriggerDispatcher $triggerDispatcher): ?Response
    {
        $this->request->allowMethod(['post']);

        $user = $this->request->getAttribute('identity');
        $emptyBestowal = $this->Bestowals->newEmptyEntity();
        $this->Authorization->authorize($emptyBestowal, 'adHoc');

        $data = $this->request->getData();
        $result = $this->dispatchBestowalMutation(
            $triggerDispatcher,
            'awards-bestowal-ad-hoc',
            'Awards.AdHocBestowalRequested',
            [
                'data' => $data,
                'actorId' => (int)$user->id,
            ],
        );

        if ($result['success']) {
            $eventPayload = $result['data']['eventPayload']
                ?? $result['data']
                ?? ['actorId' => (int)$user->id];
            $eventPayload['actorId'] = (int)$user->id;
            $this->dispatchWorkflowEvent(
                $triggerDispatcher,
                BestowalCreationService::EVENT_NAME,
                $eventPayload,
            );
            $this->Flash->success(__('The ad-hoc bestowal has been recorded.'));
        } else {
            $this->Flash->error($result['error'] ?? __('The ad-hoc bestowal could not be recorded.'));
        }

        $redirect = $this->request->getData('current_page');
        if ($redirect) {
            return $this->redirect($redirect);
        }

        $bestowalId = $result['data']['bestowalId'] ?? null;
        if ($bestowalId) {
            return $this->redirect(['action' => 'view', $bestowalId]);
        }

        return $this->redirect(['action' => 'index']);
    }

    /**
     * Add computed display fields to bestowal entities in place.
     *
     * @param iterable<\Awards\Model\Entity\Bestowal> $bestowals Paginated bestowal entities
     * @param array<int,string>|null $visibleColumns Visible display columns, or null to prepare all
     * @param bool $includeBulkTodoOptions Whether to decorate rows with bulk to-do selection metadata.
     * @return iterable<\Awards\Model\Entity\Bestowal>
     */
    protected function prepareBestowalsForDisplay(
        iterable $bestowals,
        ?array $visibleColumns = null,
        bool $includeBulkTodoOptions = true,
    ): iterable {
        $ids = [];
        foreach ($bestowals as $bestowal) {
            if (!empty($bestowal->id)) {
                $ids[] = (int)$bestowal->id;
            }
        }

        $loadTodoSummary = $this->shouldLoadBestowalDisplayColumn('todos_summary', $visibleColumns);
        $todoSummaryMap = [];
        if ($loadTodoSummary) {
            $todoSummaryMap = $this->loadBestowalTodoSummaries($ids);
        }
        $bulkTodoOptionsMap = $includeBulkTodoOptions ? $this->loadBestowalBulkTodoOptions($ids) : [];

        foreach ($bestowals as $bestowal) {
            if ($includeBulkTodoOptions) {
                $bulkTodoOptions = $bulkTodoOptionsMap[(int)$bestowal->id] ?? '[]';
                $bestowal->bulk_todo_disabled =
                    ($bestowal->lifecycle_status ?? Bestowal::LIFECYCLE_OPEN) === Bestowal::LIFECYCLE_CANCELLED
                    || $bulkTodoOptions === '[]';
                $bestowal->bulk_todo_options = $bulkTodoOptions;
            }
            if ($this->shouldLoadBestowalDisplayColumn('member_sca_name', $visibleColumns)) {
                $bestowal->member_sca_name = $bestowal->member->sca_name ?? $bestowal->member_sca_name ?? '';
            }
            if ($this->shouldLoadBestowalDisplayColumn('awards', $visibleColumns)) {
                $bestowal->awards = $this->buildAwardsHtml($bestowal);
            }
            if ($loadTodoSummary) {
                $bestowal->todos_summary = $this->buildBestowalTodoSummaryHtml(
                    $todoSummaryMap[(int)$bestowal->id] ?? [],
                );
            }
            if ($this->shouldLoadBestowalDisplayColumn('court_slot', $visibleColumns)) {
                $bestowal->court_slot = $this->buildCourtSlotHtml($bestowal);
            }
            if ($this->shouldLoadBestowalDisplayColumn('herald_notes_preview', $visibleColumns)) {
                $bestowal->herald_notes_preview = $this->buildHeraldNotesPreviewHtml($bestowal);
            }
            if ($this->shouldLoadBestowalDisplayColumn('recommendation_reasons', $visibleColumns)) {
                $bestowal->recommendation_reasons = $this->buildRecommendationReasonsHtml($bestowal);
            }
            if ($this->shouldLoadBestowalDisplayColumn('gathering_name', $visibleColumns)) {
                $bestowal->gathering_name = $bestowal->gathering->name ?? '';
            }
        }

        return $bestowals;
    }

    /**
     * Add the bestowals index bulk-selection contract to grid options.
     *
     * @param array<string, mixed> $gridOptions Dataverse grid options.
     * @return array<string, mixed>
     */
    private function withBestowalsBulkSelectionGridOptions(array $gridOptions): array
    {
        $gridOptions['enableBulkSelection'] = true;
        $gridOptions['bulkSelection'] = [
            'selectAllLabel' => __('Select all bestowals on this page'),
            'rowLabelTemplate' => __('Select bestowal: {member_sca_name}'),
            'disabledLabel' => __('This bestowal has no open To-Dos you can complete in bulk.'),
        ];
        $gridOptions['bulkActions'] = [
            [
                'key' => 'bestowal-todo-complete',
                'label' => __('Mass Complete Check'),
                'icon' => 'bi-check2-square',
                'modalTarget' => '#bestowalBulkTodoModal',
            ],
        ];
        $gridOptions['bulkSelectionDataFields'] = [
            'bulk-todo-options' => 'bulk_todo_options',
        ];
        $gridOptions['bulkSelectionDisabledField'] = 'bulk_todo_disabled';
        $gridOptions['bulkSelectionHideDisabledControl'] = true;

        return $gridOptions;
    }

    /**
     * Build per-row bulk completion options for open checks the current user may complete.
     *
     * @param list<int> $bestowalIds Displayed bestowal ids
     * @return array<int, string> JSON-encoded option lists by bestowal id
     */
    protected function loadBestowalBulkTodoOptions(array $bestowalIds): array
    {
        $bestowalIds = array_values(array_unique(array_filter($bestowalIds)));
        if ($bestowalIds === []) {
            return [];
        }

        $user = $this->request->getAttribute('identity');
        if ($user === null) {
            return [];
        }

        $memberId = (int)$user->getIdentifier();
        $actionItemService = new ActionItemService();
        $items = TableRegistry::getTableLocator()->get('ActionItems')
            ->find()
            ->where([
                'ActionItems.entity_type' => Bestowal::ACTION_ITEM_ENTITY_TYPE,
                'ActionItems.entity_id IN' => $bestowalIds,
                'ActionItems.source_ref IS NOT' => null,
            ])
            ->order([
                'ActionItems.entity_id' => 'ASC',
                'ActionItems.sort_order' => 'ASC',
                'ActionItems.id' => 'ASC',
            ])
            ->all();

        $itemsByBestowal = [];
        foreach ($items as $item) {
            $itemsByBestowal[(int)$item->entity_id][] = $item;
        }

        $optionsByBestowal = [];
        foreach ($items as $item) {
            if (!$item->isOpen()) {
                continue;
            }
            if (!$user->isSuperUser() && !$actionItemService->isMemberEligible($item, $memberId)) {
                continue;
            }

            $option = $this->bulkTodoOptionForItem($item, $itemsByBestowal[(int)$item->entity_id] ?? []);
            if ($option === null) {
                continue;
            }

            $bestowalId = (int)$item->entity_id;
            $optionsByBestowal[$bestowalId][$option['key']] = $option;
        }

        $encoded = [];
        foreach ($optionsByBestowal as $bestowalId => $options) {
            $encoded[(int)$bestowalId] = json_encode(array_values($options), JSON_UNESCAPED_SLASHES) ?: '[]';
        }

        return $encoded;
    }

    /**
     * Batch-load to-do action items for selected bestowals.
     *
     * @param list<int> $bestowalIds Bestowal IDs.
     * @param bool $openOnly Whether to include only open items.
     * @return array<int, list<\App\Model\Entity\ActionItem>>
     */
    private function loadBestowalActionItems(array $bestowalIds, bool $openOnly = false): array
    {
        $bestowalIds = array_values(array_unique(array_filter($bestowalIds)));
        if ($bestowalIds === []) {
            return [];
        }

        $query = TableRegistry::getTableLocator()->get('ActionItems')
            ->find()
            ->where([
                'ActionItems.entity_type' => Bestowal::ACTION_ITEM_ENTITY_TYPE,
                'ActionItems.entity_id IN' => $bestowalIds,
                'ActionItems.source_ref IS NOT' => null,
            ])
            ->order([
                'ActionItems.entity_id' => 'ASC',
                'ActionItems.sort_order' => 'ASC',
                'ActionItems.id' => 'ASC',
            ]);
        if ($openOnly) {
            $query->where(['ActionItems.status' => ActionItem::STATUS_OPEN]);
        }

        $itemsByBestowal = [];
        foreach ($query->all() as $item) {
            $itemsByBestowal[(int)$item->entity_id][] = $item;
        }

        return $itemsByBestowal;
    }

    /**
     * @param \App\Model\Entity\ActionItem $item Action item.
     * @param array<int, \App\Model\Entity\ActionItem> $siblingItems To-dos for the same bestowal.
     * @return array<string, mixed>|null
     */
    private function bulkTodoOptionForItem(ActionItem $item, array $siblingItems = []): ?array
    {
        $key = trim((string)$item->source_ref);
        if ($key === '') {
            return null;
        }
        if ($this->isTodoBlockedByPrerequisite($item, $siblingItems)) {
            return null;
        }

        $option = [
            'key' => $key,
            'label' => (string)$item->title,
            'requiresGathering' => false,
            'gatheringLabel' => __('Bestowal Gathering'),
            'gatheringHelp' => __(
                'Choose a future event or court using the same options available on the bestowal edit form.',
            ),
        ];

        $fieldConfigs = $item->getRequiredFieldConfigs();
        $defaultConfig = BestowalTodoTemplateItem::getDefaultRequiredFieldConfigForSourceRef($item->source_ref);
        if ($fieldConfigs === [] && $defaultConfig !== null) {
            $fieldConfigs[] = $defaultConfig;
        }

        foreach ($fieldConfigs as $fieldConfig) {
            if (($fieldConfig['field'] ?? null) !== BestowalTodoTemplateItem::REQUIRED_FIELD_GATHERING) {
                continue;
            }

            $option['requiresGathering'] = true;
            $option['gatheringLabel'] = (string)($fieldConfig['label'] ?? $option['gatheringLabel']);
            $option['gatheringHelp'] = (string)($fieldConfig['help'] ?? $option['gatheringHelp']);

            break;
        }

        return $option;
    }

    /**
     * @param \App\Model\Entity\ActionItem $item Action item.
     * @param array<int, \App\Model\Entity\ActionItem> $siblingItems To-dos for the same bestowal.
     * @return bool
     */
    private function isTodoBlockedByPrerequisite(ActionItem $item, array $siblingItems): bool
    {
        if ((string)$item->source_ref !== BestowalTodoTemplateItem::ITEM_KEY_ADDED_TO_AGENDA) {
            return false;
        }

        $eventScheduled = $this->findTodoBySourceRef($siblingItems, BestowalTodoTemplateItem::ITEM_KEY_EVENT_SCHEDULED);

        return $eventScheduled !== null && !$eventScheduled->isCompleted();
    }

    /**
     * Batch-load preparation-check (to-do) action items for the given bestowal ids,
     * grouped by bestowal id, ordered for checklist display.
     *
     * @param list<int> $bestowalIds Displayed bestowal ids
     * @return array<int, list<\App\Model\Entity\ActionItem>>
     */
    protected function loadBestowalTodoSummaries(array $bestowalIds): array
    {
        $bestowalIds = array_values(array_unique(array_filter($bestowalIds)));
        if ($bestowalIds === []) {
            return [];
        }

        $items = TableRegistry::getTableLocator()->get('ActionItems')->find()
            ->where([
                'ActionItems.entity_type' => Bestowal::ACTION_ITEM_ENTITY_TYPE,
                'ActionItems.entity_id IN' => $bestowalIds,
            ])
            ->orderBy([
                'ActionItems.entity_id' => 'ASC',
                'ActionItems.sort_order' => 'ASC',
                'ActionItems.id' => 'ASC',
            ])
            ->all();

        $map = [];
        foreach ($items as $item) {
            $map[(int)$item->entity_id][] = $item;
        }

        return $map;
    }

    /**
     * Render the compact to-do progress badge with a popover listing each check
     * and its state. The badge's accessible name conveys the gating progress so
     * the information is available without opening the popover.
     *
     * @param list<\App\Model\Entity\ActionItem> $items Action items for one bestowal
     * @return string
     */
    protected function buildBestowalTodoSummaryHtml(array $items): string
    {
        if ($items === []) {
            return '<span class="text-muted small">' . h(__('No checks')) . '</span>';
        }

        $gatingTotal = 0;
        $gatingDone = 0;
        $rows = [];
        foreach ($items as $item) {
            $isGating = (bool)$item->is_gating;
            $completed = $item->isCompleted();
            $cancelled = $item->status === ActionItem::STATUS_CANCELLED;
            if ($isGating) {
                $gatingTotal++;
                if ($completed) {
                    $gatingDone++;
                }
            }

            if ($completed) {
                $icon = 'bi-check-square-fill text-success';
                $statusLabel = __('Completed task:');
            } elseif ($cancelled) {
                $icon = 'bi-dash-square text-muted';
                $statusLabel = __('Cancelled task:');
            } else {
                $icon = 'bi-hourglass-split text-secondary';
                $statusLabel = __('Open task:');
            }
            $required = $isGating
                ? ' <span class="text-danger" aria-hidden="true">*</span>'
                : '';
            $rows[] = '<li class="d-flex align-items-start gap-2 mb-1">'
                . '<i class="bi ' . $icon . '" aria-hidden="true"></i>'
                . '<span class="visually-hidden">' . h($statusLabel) . '</span>'
                . '<span>' . h($item->title) . $required . '</span>'
                . '</li>';
        }

        $allGatingDone = $gatingTotal === 0 || $gatingDone >= $gatingTotal;
        if ($gatingTotal === 0) {
            $badgeClass = 'bg-secondary';
            $progressLabel = (string)count($items);
            $ariaLabel = __n(
                'To-Dos: {0} optional check',
                'To-Dos: {0} optional checks',
                count($items),
                count($items),
            );
        } else {
            $badgeClass = $allGatingDone ? 'bg-success' : 'bg-warning text-dark';
            $progressLabel = $gatingDone . '/' . $gatingTotal;
            $ariaLabel = __('To-Do progress: {0} of {1} required checks complete', $gatingDone, $gatingTotal);
        }

        $content = '<ul class="list-unstyled mb-0 small text-start">' . implode('', $rows) . '</ul>';

        return '<button type="button" class="btn btn-sm p-0 border-0 bg-transparent" '
            . 'data-controller="popover" '
            . 'data-bs-toggle="popover" '
            . 'data-popover-trigger-value="hover focus" '
            . 'data-popover-placement-value="left" '
            . 'data-bs-title="' . h(__('Preparation checks')) . '" '
            . 'data-bs-content="' . h($content) . '" '
            . 'aria-label="' . h($ariaLabel) . '">'
            . '<span class="badge ' . $badgeClass . '">'
            . '<i class="bi bi-check2-square me-1" aria-hidden="true"></i>' . h($progressLabel)
            . '</span>'
            . '</button>';
    }

    /**
     * @param array<int,string>|null $visibleColumns
     */
    private function shouldLoadBestowalDisplayColumn(string $columnKey, ?array $visibleColumns): bool
    {
        return $visibleColumns === null || in_array($columnKey, $visibleColumns, true);
    }

    /**
     * @param \Awards\Model\Entity\Bestowal $bestowal Bestowal entity
     * @return string
     */
    protected function buildAwardsHtml(Bestowal $bestowal): string
    {
        if ($bestowal->hasValue('award')) {
            $label = h($bestowal->award->abbreviation ?? $bestowal->award->name ?? '');
            if ($bestowal->award->hasValue('level') && !empty($bestowal->award->level->name)) {
                $label .= ' (' . h($bestowal->award->level->name) . ')';
            }

            return $label;
        }

        $labels = [];
        foreach ($bestowal->recommendations ?? [] as $recommendation) {
            if (!$recommendation->hasValue('award')) {
                continue;
            }
            $label = h($recommendation->award->abbreviation ?? $recommendation->award->name ?? '');
            if ($recommendation->specialty) {
                $label .= ' (' . h($recommendation->specialty) . ')';
            }
            $labels[] = $label;
        }

        if (
            $labels === []
            && $bestowal->hasValue('primary_recommendation')
            && $bestowal->primary_recommendation->hasValue('award')
        ) {
            $labels[] = h(
                $bestowal->primary_recommendation->award->abbreviation
                ?? $bestowal->primary_recommendation->award->name
                ?? '',
            );
        }

        return implode('<br>', array_unique($labels));
    }

    /**
     * @param \Awards\Model\Entity\Bestowal $bestowal Bestowal entity
     * @return string
     */
    protected function buildCourtSlotHtml(Bestowal $bestowal): string
    {
        return h((new BestowalCourtSlotService())->formatCourtSlotDisplay($bestowal));
    }

    /**
     * @param \Awards\Model\Entity\Bestowal $bestowal Bestowal entity
     * @return string
     */
    protected function buildHeraldNotesPreviewHtml(Bestowal $bestowal): string
    {
        $notes = trim((string)($bestowal->herald_notes ?? ''));
        if ($notes === '') {
            return '';
        }

        $preview = mb_strlen($notes) > 120 ? mb_substr($notes, 0, 117) . '...' : $notes;

        return h($preview);
    }

    /**
     * @param \Awards\Model\Entity\Bestowal $bestowal Bestowal entity
     * @return string
     */
    protected function buildRecommendationReasonsHtml(Bestowal $bestowal): string
    {
        if (empty($bestowal->recommendations)) {
            return '';
        }

        $items = [];
        foreach ($bestowal->recommendations as $recommendation) {
            $reason = trim((string)($recommendation->reason ?? ''));
            if ($reason === '') {
                continue;
            }

            $awardLabel = $recommendation->award->abbreviation
                ?? $recommendation->award->name
                ?? __('Recommendation #{0}', $recommendation->id);
            $memberName = trim((string)($recommendation->member_sca_name ?? ''));
            $requesterName = trim((string)($recommendation->requester_sca_name ?? ''));
            $label = $memberName !== '' ? $awardLabel . ' - ' . $memberName : $awardLabel;

            $items[] = [
                'label' => $label,
                'requester' => $requesterName,
                'reason' => $reason,
            ];
        }

        $count = count($items);
        if ($count === 0) {
            return '';
        }

        $title = $count === 1
            ? __('1 Recommendation Reason')
            : __('{0} Recommendation Reasons', $count);
        $popoverContent = '<div class="popover-header-bar d-flex justify-content-between align-items-center'
            . ' border-bottom pb-2 mb-2">';
        $popoverContent .= '<strong>' . h($title) . '</strong>';
        $popoverContent .= '<button type="button" class="btn-close popover-close-btn" aria-label="'
            . h(__('Close')) . '"></button>';
        $popoverContent .= '</div>';

        foreach ($items as $item) {
            $popoverContent .= '<div class="border-bottom pb-2 mb-2">';
            $popoverContent .= '<div class="fw-bold">' . h($item['label']) . '</div>';
            if ($item['requester'] !== '') {
                $popoverContent .= '<div class="text-muted small">';
                $popoverContent .= h(__('Recommended by {0}', $item['requester']));
                $popoverContent .= '</div>';
            }
            $popoverContent .= '<div>' . nl2br(h($item['reason'])) . '</div>';
            $popoverContent .= '</div>';
        }

        $escapedContent = htmlspecialchars($popoverContent, ENT_QUOTES, 'UTF-8');

        $html = '<button type="button" class="btn btn-link text-primary p-0" ';
        $html .= 'style="font-size: inherit;" ';
        $html .= 'aria-label="' . h(__('Show {0} linked recommendation reasons', $count)) . '" ';
        $html .= 'data-controller="popover" ';
        $html .= 'data-bs-toggle="popover" ';
        $html .= 'data-bs-trigger="click" ';
        $html .= 'data-bs-placement="auto" ';
        $html .= 'data-bs-html="true" ';
        $html .= 'data-bs-custom-class="reason-popover" ';
        $html .= 'data-bs-content="' . $escapedContent . '" ';
        $html .= 'data-turbo="false">';
        $html .= '<span class="badge bg-secondary">' . $count . '</span>';
        $html .= '</button>';

        return $html;
    }

    /**
     * @param iterable<\Awards\Model\Entity\Bestowal> $bestowals Prepared bestowal entities
     * @param array<string, mixed> $result Grid result
     * @param string $frameId Outer turbo frame ID
     * @param string $tableFrameId Inner turbo frame ID
     * @return void
     */
    protected function setGridViewVariables(
        iterable $bestowals,
        array $result,
        string $frameId,
        string $tableFrameId,
    ): void {
        $this->set([
            'bestowals' => $bestowals,
            'data' => $bestowals,
            'rowActions' => BestowalsGridColumns::getRowActions(),
            'gridState' => $result['gridState'],
            'columns' => $result['columnsMetadata'],
            'visibleColumns' => $result['visibleColumns'],
            'searchableColumns' => BestowalsGridColumns::getSearchableColumns(),
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
        if ($turboFrame === $tableFrameId) {
            $this->set('tableFrameId', $tableFrameId);
            $this->viewBuilder()->setPlugin(null);
            $this->viewBuilder()->disableAutoLayout();
            $this->viewBuilder()->setTemplatePath('element');
            $this->viewBuilder()->setTemplate('dv_grid_table');
        } else {
            $this->set('frameId', $frameId);
            $this->viewBuilder()->setPlugin(null);
            $this->viewBuilder()->disableAutoLayout();
            $this->viewBuilder()->setTemplatePath('element');
            $this->viewBuilder()->setTemplate('dv_grid_content');
        }
    }

    /**
     * Resolve the post-mutation redirect target consistently with the other
     * bestowal mutation actions.
     *
     * @param string|null $pageContext Optional grid page-context URL.
     * @param string|int|null $bestowalId Bestowal id for the view fallback.
     * @return \Cake\Http\Response|null
     */
    private function redirectAfterBestowalMutation($pageContext, $bestowalId): ?Response
    {
        $redirect = $this->request->getData('current_page');
        if ($redirect) {
            return $this->redirect($redirect);
        }
        if ($pageContext !== null) {
            return $this->redirect($pageContext);
        }
        if (!empty($bestowalId)) {
            return $this->redirect(['action' => 'view', $bestowalId]);
        }

        return $this->redirect(['action' => 'index']);
    }

    /**
     * @param \App\Services\WorkflowEngine\TriggerDispatcher $triggerDispatcher Workflow dispatcher
     * @param string $slug Workflow definition slug
     * @param string $triggerEvent Workflow trigger event name
     * @param array<string, mixed> $context Workflow context
     * @return array{success: bool, data: array<string, mixed>, error: ?string}
     */
    private function dispatchBestowalMutation(
        TriggerDispatcher $triggerDispatcher,
        string $slug,
        string $triggerEvent,
        array $context,
    ): array {
        try {
            return $this->normalizeBestowalMutationResult(
                $this->dispatchWorkflowOrFail($triggerDispatcher, $slug, $triggerEvent, $context),
            );
        } catch (Throwable $e) {
            Log::error("Bestowal workflow dispatch failed for {$slug}: " . $e->getMessage());

            return [
                'success' => false,
                'data' => [],
                'error' => 'The bestowal workflow is not currently available.',
            ];
        }
    }

    /**
     * @param mixed $result Workflow dispatch result
     * @return array{success: bool, data: array<string, mixed>, error: ?string}
     */
    private function normalizeBestowalMutationResult(mixed $result): array
    {
        if (is_array($result) && $this->isWorkflowDispatchResult($result)) {
            foreach ($result as $dispatchResult) {
                if (!$dispatchResult->isSuccess()) {
                    return [
                        'success' => false,
                        'data' => [],
                        'error' => $dispatchResult->getError(),
                    ];
                }

                $data = $dispatchResult->getData();
                $workflowResult = is_array($data) ? ($data['workflowResult'] ?? null) : null;
                if (is_array($workflowResult)) {
                    return $this->normalizeBestowalMutationResult($workflowResult);
                }
            }

            return [
                'success' => false,
                'data' => [],
                'error' => 'Workflow did not return a result.',
            ];
        }

        if (is_array($result) && array_key_exists('success', $result)) {
            $data = [];
            if (isset($result['output']) && is_array($result['output'])) {
                $data = $result['output'];
            } elseif (isset($result['data']) && is_array($result['data'])) {
                $data = $result['data'];
            }

            return [
                'success' => (bool)$result['success'],
                'data' => $data,
                'error' => $result['error'] ?? $result['message'] ?? null,
            ];
        }

        return [
            'success' => false,
            'data' => [],
            'error' => 'Workflow did not return a result.',
        ];
    }

    /**
     * @param array<int, mixed> $result Workflow dispatch results
     * @return bool
     */
    private function isWorkflowDispatchResult(array $result): bool
    {
        if ($result === []) {
            return false;
        }

        foreach ($result as $item) {
            if (!$item instanceof ServiceResult) {
                return false;
            }
        }

        return true;
    }

    /**
     * Tab query param from page context URL (detail pages).
     */
    private function pageContextQueryTab(?string $pageContextUrl): ?string
    {
        if ($pageContextUrl === null) {
            return null;
        }

        $parsed = parse_url($pageContextUrl);
        if (empty($parsed['query'])) {
            return null;
        }

        $params = [];
        parse_str($parsed['query'], $params);

        $tab = $params['tab'] ?? null;

        return is_string($tab) && $tab !== '' ? $tab : null;
    }

    /**
     * @return array{contextKey: string, tableFrameId: string, gatheringId?: int}|null
     */
    private function resolveBestowalGridSyncContext(?string $pageContextUrl): ?array
    {
        if ($pageContextUrl === null) {
            return null;
        }

        $path = parse_url($pageContextUrl, PHP_URL_PATH) ?? $pageContextUrl;
        $tab = $this->pageContextQueryTab($pageContextUrl);

        if ($this->matchesGridIndexPath($pageContextUrl, '#/awards/bestowals/?$#')) {
            return [
                'contextKey' => 'main',
                'tableFrameId' => 'bestowals-grid-table',
            ];
        }

        if (preg_match('#/gatherings/view/([^/]+)/?$#', $path, $matches)) {
            if ($tab !== null && $tab !== 'gathering-bestowals') {
                return null;
            }

            $gatheringsTable = TableRegistry::getTableLocator()->get('Gatherings');
            try {
                $gathering = $gatheringsTable->find()
                    ->where(['public_id' => $matches[1]])
                    ->firstOrFail();
            } catch (RecordNotFoundException) {
                return null;
            }

            $gatheringId = (int)$gathering->id;

            return [
                'contextKey' => 'gathering',
                'tableFrameId' => 'gathering-bestowals-grid-' . $gatheringId . '-table',
                'gatheringId' => $gatheringId,
            ];
        }

        return null;
    }

    /**
     * Resolve targeted row sync after a single bestowal save.
     *
     * @return array{action: string, rowDomId: string, rowHtml?: string}|null Null → full table refresh
     */
    private function resolveBestowalGridRowSync(
        int $bestowalId,
        ?string $pageContextUrl,
        BestowalQueryService $queryService,
    ): ?array {
        $syncContext = $this->resolveBestowalGridSyncContext($pageContextUrl);
        if ($syncContext === null) {
            return null;
        }

        $tableFrameId = $syncContext['tableFrameId'];
        $rowDomId = GridRowDomId::fromTableFrameId($tableFrameId, $bestowalId);

        return $this->withPageContextQuery($pageContextUrl, function () use (
            $bestowalId,
            $rowDomId,
            $queryService,
            $tableFrameId,
            $syncContext,
        ): ?array {
            $emptyBestowal = $this->Bestowals->newEmptyEntity();
            $user = $this->request->getAttribute('identity');
            $canViewHidden = $user->checkCan('ViewHidden', $emptyBestowal);
            $canEdit = $user->checkCan('edit', $emptyBestowal);

            if ($syncContext['contextKey'] === 'gathering') {
                $systemViews = BestowalsGridColumns::getSystemViews(['context' => 'gatheringBestowals']);
                $queryContext = $this->resolveDataverseGridQueryContext([
                    'gridKey' => 'Awards.Bestowals.gathering',
                    'gridColumnsClass' => BestowalsGridColumns::class,
                    'systemViews' => $systemViews,
                    'defaultSystemView' => 'sys-bestowals-gathering',
                    'defaultSort' => ['Bestowals.stack_rank' => 'asc', 'Bestowals.id' => 'asc'],
                ]);
                $built = $queryService->buildGatheringBestowalsQuery(
                    $this->Bestowals,
                    $syncContext['gatheringId'],
                    $canEdit,
                    $queryContext->queryVisibleColumns(),
                );
                $baseQuery = $built['query'];
                $baseQuery = $this->Authorization->applyScope($baseQuery, 'index');
                $baseQuery = $queryService->applyHiddenStateVisibility($baseQuery, $canViewHidden);
            } else {
                $systemViews = BestowalsGridColumns::getSystemViews([]);
                $queryContext = $this->resolveDataverseGridQueryContext([
                    'gridKey' => 'Awards.Bestowals.index.main',
                    'gridColumnsClass' => BestowalsGridColumns::class,
                    'systemViews' => $systemViews,
                    'defaultSystemView' => 'sys-bestowals-active',
                    'defaultSort' => ['Bestowals.created' => 'desc'],
                ]);
                $built = $queryService->buildIndexQuery(
                    $this->Bestowals,
                    $canEdit,
                    $queryContext->queryVisibleColumns(),
                );
                $built['gridOptions'] = $this->withBestowalsBulkSelectionGridOptions($built['gridOptions']);
                $baseQuery = $built['query'];
                $baseQuery = $queryService->applyHiddenStateVisibility($baseQuery, $canViewHidden);
                $baseQuery = $this->Authorization->applyScope($baseQuery, 'index');
            }

            $baseQuery = $baseQuery->where(['Bestowals.id' => $bestowalId]);
            $built['gridOptions']['baseQuery'] = $baseQuery;

            $result = $this->processDataverseGrid($built['gridOptions']);

            $gridData = $result['data'];
            if (is_array($gridData)) {
                $bestowals = $gridData;
            } elseif ($gridData instanceof Traversable) {
                $bestowals = iterator_to_array($gridData, false);
            } else {
                $bestowals = [];
            }
            if ($bestowals === []) {
                return [
                    'action' => 'remove',
                    'rowDomId' => $rowDomId,
                ];
            }

            $this->prepareBestowalsForDisplay($bestowals, $result['visibleColumns']);
            $bestowal = $bestowals[0];
            $rowActions = BestowalsGridColumns::getRowActions();
            $gridState = $result['gridState'];
            $enableColumnPicker = $gridState['config']['enableColumnPicker'] ?? true;
            $visibleColumns = $gridState['columns']['visible'];
            if (!is_array($visibleColumns)) {
                $visibleColumns = array_values($visibleColumns);
            }

            $rowHtml = $this->renderDataverseTableRowElement([
                'row' => $bestowal,
                'columns' => $gridState['columns']['all'],
                'visibleColumns' => $visibleColumns,
                'controllerName' => 'grid-view',
                'primaryKey' => $gridState['config']['primaryKey'],
                'gridKey' => $gridState['config']['gridKey'],
                'rowActions' => $rowActions,
                'user' => $user,
                'enableBulkSelection' => $gridState['config']['enableBulkSelection'] ?? false,
                'bulkSelectionDataFields' => $gridState['config']['bulkSelectionDataFields'] ?? [],
                'bulkSelectionDisabledField' => $gridState['config']['bulkSelectionDisabledField'] ?? null,
                'bulkSelectionDisabledLabel' => $gridState['config']['bulkSelection']['disabledLabel'] ?? null,
                'bulkSelectionHideDisabledControl' => $gridState['config']['bulkSelectionHideDisabledControl'] ?? false,
                'rowDomIdPrefix' => preg_replace('/-table$/', '', $tableFrameId),
                'showActionsColumn' => $enableColumnPicker || $rowActions !== [],
            ]);

            return [
                'action' => 'replace',
                'rowDomId' => $rowDomId,
                'rowHtml' => $rowHtml,
            ];
        });
    }

    /**
     * Turbo-stream response for grid-origin bestowal saves.
     */
    private function tryBestowalsGridTurboResponse(
        ?string $pageContext,
        bool $success,
        ?int $reloadEditId = null,
        ?int $updatedBestowalId = null,
        ?BestowalQueryService $queryService = null,
    ): ?Response {
        if (!$this->wantsTurboStreamRequest() || $pageContext === null) {
            return null;
        }

        $syncContext = $this->resolveBestowalGridSyncContext($pageContext);
        if (!$this->isGridOriginRequest($pageContext) && $syncContext === null) {
            return null;
        }

        $gridRoute = ['plugin' => 'Awards', 'controller' => 'Bestowals', 'action' => 'gridData'];

        if ($success) {
            $this->Flash->success(__('The bestowal has been saved.'));

            if ($updatedBestowalId !== null) {
                $queryService ??= new BestowalQueryService();
                $sync = $this->resolveBestowalGridRowSync(
                    $updatedBestowalId,
                    $pageContext,
                    $queryService,
                );
                if ($sync !== null) {
                    if ($sync['action'] === 'remove') {
                        return $this->renderTurboRemoveGridRow($sync['rowDomId']);
                    }

                    return $this->renderTurboReplaceGridRow(
                        $sync['rowDomId'],
                        $sync['rowHtml'] ?? '',
                    );
                }
            }

            return $this->renderTurboCloseModal('bestowals-grid-table', $gridRoute, $pageContext);
        }

        if ($reloadEditId !== null) {
            $frameSrc = Router::url([
                'plugin' => 'Awards',
                'controller' => 'Bestowals',
                'action' => 'turboEditForm',
                $reloadEditId,
            ]);

            return $this->renderTurboReloadFrame('editBestowalQuick', $frameSrc)->withStatus(422);
        }

        return null;
    }
}
