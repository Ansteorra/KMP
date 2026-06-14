<?php
declare(strict_types=1);

namespace Awards\Controller;

use App\Controller\DataverseGridTrait;
use App\Controller\WorkflowDispatchTrait;
use App\KMP\GridRowDomId;
use App\Services\CsvExportService;
use App\Services\ServiceResult;
use App\Services\WorkflowEngine\TriggerDispatcher;
use Awards\KMP\GridColumns\BestowalsGridColumns;
use Awards\Model\Entity\Bestowal;
use Awards\Services\BestowalCancellationService;
use Awards\Services\BestowalCourtSlotService;
use Awards\Services\BestowalCreationService;
use Awards\Services\BestowalFormService;
use Awards\Services\BestowalGatheringLookupService;
use Awards\Services\BestowalQueryService;
use Cake\Datasource\Exception\RecordNotFoundException;
use Cake\Http\Exception\BadRequestException;
use Cake\Http\Exception\NotFoundException;
use Cake\Http\Response;
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
            $statusList = $formService->buildStatusList();
            $rules = $formService->buildFormRules();
            $gatheringList = [];
            $adHocFormData = $formService->prepareAdHocFormData($user);
            $this->set(compact('rules', 'statusList', 'gatheringList', 'adHocFormData'));
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

        $result = $this->processDataverseGrid($built['gridOptions']);
        $result = $this->applyStateFilterOptionsToGridResult($result, $canViewHidden);

        if (!empty($result['isCsvExport'])) {
            $exportData = $this->prepareBestowalsForDisplay($result['query']->all(), null);

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
            'gridKey' => 'Awards.Bestowals.gathering.' . $gatheringId,
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
        $result = $this->applyStateFilterOptionsToGridResult($result, $canViewHidden);

        if (!empty($result['isCsvExport'])) {
            $exportData = $this->prepareBestowalsForDisplay($result['query']->all(), null);

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
    public function view(?string $id = null): ?Response
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

        $this->set(compact('bestowal'));

        return null;
    }

    /**
     * Transition a bestowal to a new state via workflow dispatch.
     *
     * @param \App\Services\WorkflowEngine\TriggerDispatcher $triggerDispatcher Workflow dispatcher
     * @return \Cake\Http\Response|null
     */
    public function updateState(TriggerDispatcher $triggerDispatcher): ?Response
    {
        $this->request->allowMethod(['post']);

        $user = $this->request->getAttribute('identity');
        $emptyBestowal = $this->Bestowals->newEmptyEntity();
        $this->Authorization->authorize($emptyBestowal, 'updateState');

        $bestowalId = $this->request->getData('bestowalId') ?? $this->request->getData('id');
        $newState = $this->request->getData('newState') ?? $this->request->getData('targetState');
        $pageContext = $this->getPageContextUrl();

        if (empty($bestowalId) || empty($newState)) {
            $this->Flash->error(__('Bestowal ID and new state are required.'));
        } else {
            $transitionData = [
                'bestowalId' => $bestowalId,
                'targetState' => $newState,
                'newState' => $newState,
                'gathering_id' => $this->request->getData('gathering_id'),
                'gathering_scheduled_activity_id' => $this->request->getData('gathering_scheduled_activity_id'),
                'bestowed_at' => $this->request->getData('bestowed_at'),
                'noble_notes' => $this->request->getData('noble_notes'),
                'herald_notes' => $this->request->getData('herald_notes'),
                'note' => $this->request->getData('note'),
                'close_reason' => $this->request->getData('close_reason'),
            ];

            $result = $this->dispatchBestowalMutation(
                $triggerDispatcher,
                'awards-bestowal-transition',
                'Awards.BestowalTransitionRequested',
                [
                    'bestowalId' => (int)$bestowalId,
                    'targetState' => $newState,
                    'data' => $transitionData,
                    'actorId' => (int)$user->id,
                ],
            );

            if ($result['success']) {
                $this->dispatchWorkflowEvent(
                    $triggerDispatcher,
                    'Awards.BestowalStateChanged',
                    $this->buildBestowalStateChangedPayload($result, (int)$user->id),
                );
                $stream = $this->tryBestowalsGridTurboResponse(
                    $pageContext,
                    true,
                    null,
                    (int)$bestowalId,
                );
                if ($stream !== null) {
                    return $stream;
                }
                $this->Flash->success(__('The bestowal has been updated.'));
            } else {
                $this->Flash->error($result['error'] ?? __('The bestowal could not be updated.'));
            }
        }

        $redirect = $this->request->getData('current_page');
        if ($redirect) {
            return $this->redirect($redirect);
        }
        if ($pageContext !== null) {
            return $this->redirect($pageContext);
        }

        return $this->redirect(['action' => 'view', $bestowalId ?? null]);
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
                $this->dispatchWorkflowEvent(
                    $triggerDispatcher,
                    'Awards.BestowalStateChanged',
                    $this->buildBestowalStateChangedPayload($result, (int)$user->id),
                );
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
     * Bulk update bestowal states via workflow dispatch.
     *
     * @param \App\Services\WorkflowEngine\TriggerDispatcher $triggerDispatcher Workflow dispatcher
     * @return \Cake\Http\Response|null
     */
    public function updateStates(TriggerDispatcher $triggerDispatcher): ?Response
    {
        $this->request->allowMethod(['post']);

        $user = $this->request->getAttribute('identity');
        $emptyBestowal = $this->Bestowals->newEmptyEntity();
        $this->Authorization->authorize($emptyBestowal, 'updateStates');

        $ids = explode(',', (string)$this->request->getData('ids'));
        $ids = array_values(array_filter(array_map('intval', $ids)));
        $newState = $this->request->getData('newState');

        $result = ['success' => false];
        if ($ids === [] || empty($newState)) {
            $this->Flash->error(__('No bestowals selected or new state not specified.'));
        } else {
            $transitionData = [
                'ids' => $ids,
                'bestowalIds' => $ids,
                'newState' => $newState,
                'targetState' => $newState,
                'gathering_id' => $this->request->getData('gathering_id'),
                'bestowed_at' => $this->request->getData('bestowed_at'),
                'note' => $this->request->getData('note'),
                'close_reason' => $this->request->getData('close_reason'),
            ];
            $result = $this->dispatchBestowalMutation(
                $triggerDispatcher,
                'awards-bestowal-bulk-transition',
                'Awards.BestowalBulkTransitionRequested',
                [
                    'bestowalIds' => $ids,
                    'targetState' => $newState,
                    'data' => $transitionData,
                    'actorId' => (int)$user->id,
                ],
            );

            if (!$this->request->getHeader('Turbo-Frame') && !$this->wantsTurboStreamRequest()) {
                if ($result['success']) {
                    $this->Flash->success(__('The bestowals have been updated.'));
                } else {
                    $this->Flash->error(
                        $result['error'] ?? __('The bestowals could not be updated. Please, try again.'),
                    );
                }
            }

            if ($result['success']) {
                foreach ($ids as $bestowalId) {
                    $this->dispatchWorkflowEvent(
                        $triggerDispatcher,
                        'Awards.BestowalStateChanged',
                        [
                            'bestowalId' => (int)$bestowalId,
                            'newState' => $newState,
                            'actorId' => (int)$user->id,
                        ],
                    );
                }
            }
        }

        $pageContext = $this->getPageContextUrl();
        if (
            $ids !== []
            && !empty($newState)
            && ($result['success'] ?? false)
            && $this->wantsTurboStreamRequest()
            && $this->isGridOriginRequest($pageContext)
        ) {
            $this->Flash->success(__('The bestowals have been updated.'));

            return $this->renderTurboCloseModal(
                'bestowals-grid-table',
                ['plugin' => 'Awards', 'controller' => 'Bestowals', 'action' => 'gridData'],
                $pageContext,
            );
        }

        $redirect = $this->request->getData('current_page');
        if ($redirect) {
            return $this->redirect($redirect);
        }
        if ($pageContext !== null) {
            return $this->redirect($pageContext);
        }

        return $this->redirect(['action' => 'index']);
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
     * Render the bulk edit form for Turbo Frame partial updates.
     *
     * @param \Awards\Services\BestowalFormService $formService Form preparation service
     * @return \Cake\Http\Response|null
     */
    public function turboBulkEditForm(BestowalFormService $formService): ?Response
    {
        $emptyBestowal = $this->Bestowals->newEmptyEntity();
        $this->Authorization->authorize($emptyBestowal, 'turboBulkEditForm');
        $this->set($formService->prepareBulkEditFormData());

        return null;
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
        $futureOnly = ($status !== 'Given');
        $selectedId = $this->request->getQuery('selected_id');
        $selectedId = is_numeric((string)$selectedId) ? (int)$selectedId : null;
        $awardIdOverride = $this->request->getQuery('award_id');
        $awardIdOverride = is_numeric((string)$awardIdOverride) ? (int)$awardIdOverride : null;
        $memberIdOverride = $this->request->getQuery('member_id');
        $memberIdOverride = is_numeric((string)$memberIdOverride) ? (int)$memberIdOverride : null;
        $memberPublicId = trim((string)$this->request->getQuery('member_public_id', ''));

        $gatherings = [];
        $cancelledGatheringIds = [];
        $bestowal = null;

        try {
            if ($bestowalId !== null && ctype_digit((string)$bestowalId)) {
                $bestowalQuery = $this->Bestowals->find()
                    ->where(['Bestowals.id' => (int)$bestowalId])
                    ->contain([
                        'Recommendations' => function ($query) {
                            return $query->select(['id', 'award_id', 'member_id', 'bestowal_id']);
                        },
                    ])
                    ->select(['Bestowals.id', 'Bestowals.award_id', 'Bestowals.gathering_id']);
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
        } catch (Throwable $e) {
            Log::error('Error in gatheringsForBestowalAutoComplete: ' . $e->getMessage());
            $gatherings = [];
            $cancelledGatheringIds = [];
        }

        $this->set(compact('gatherings', 'q', 'cancelledGatheringIds', 'selectedId'));
    }

    /**
     * Return gathering autocomplete options for bulk bestowal edit modal.
     *
     * Returns Ajax HTML list items consumed by the shared auto-complete controller.
     *
     * @param \Awards\Services\BestowalGatheringLookupService $lookupService Gathering lookup service
     * @return void
     */
    public function gatheringsForBestowalBulkAutoComplete(
        BestowalGatheringLookupService $lookupService,
    ): void {
        $this->request->allowMethod(['get']);
        $emptyBestowal = $this->Bestowals->newEmptyEntity();
        $this->Authorization->authorize($emptyBestowal, 'gatheringsForBestowalBulkAutoComplete');
        $this->viewBuilder()->setClassName('Ajax');
        $this->viewBuilder()->setTemplate('/Recommendations/gatherings_auto_complete');

        $q = trim((string)$this->request->getQuery('q', ''));
        $status = (string)$this->request->getQuery('status', '');
        $futureOnly = ($status !== 'Given');
        $selectedId = $this->request->getQuery('selected_id');
        $selectedId = is_numeric((string)$selectedId) ? (int)$selectedId : null;

        $idsQuery = $this->request->getQuery('ids', []);
        if (is_string($idsQuery)) {
            $ids = array_filter(array_map('intval', explode(',', $idsQuery)));
        } elseif (is_array($idsQuery)) {
            $ids = array_filter(array_map('intval', $idsQuery));
        } else {
            $ids = [];
        }

        $gatherings = [];
        $cancelledGatheringIds = [];

        try {
            if ($ids !== []) {
                $gatheringData = $lookupService->getFilteredGatheringsForBestowalIds(
                    $ids,
                    $futureOnly,
                    $selectedId,
                );
                $gatherings = $gatheringData['gatherings'] ?? [];
                $cancelledGatheringIds = $gatheringData['cancelledGatheringIds'] ?? [];
            }

            if ($q !== '') {
                $gatherings = array_filter(
                    $gatherings,
                    fn($display) => mb_stripos((string)$display, $q) !== false,
                );
            }
        } catch (Throwable $e) {
            Log::error('Error in gatheringsForBestowalBulkAutoComplete: ' . $e->getMessage());
            $gatherings = [];
            $cancelledGatheringIds = [];
        }

        $this->set(compact('gatherings', 'q', 'cancelledGatheringIds', 'selectedId'));
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
     * Inject state filter options into grid result metadata.
     *
     * @param array<string, mixed> $result Grid result
     * @param bool $canViewHidden Whether hidden states are visible
     * @return array<string, mixed>
     */
    protected function applyStateFilterOptionsToGridResult(array $result, bool $canViewHidden): array
    {
        $stateFilterOptions = BestowalsGridColumns::getStateFilterOptions($canViewHidden);
        $result['filterOptions']['state'] = $stateFilterOptions;

        if (isset($result['columnsMetadata']['state'])) {
            $result['columnsMetadata']['state']['filterOptions'] = $stateFilterOptions;
            $result['gridState']['filters']['available']['state'] = [
                'label' => $result['columnsMetadata']['state']['label'] ?? 'State',
                'options' => $stateFilterOptions,
            ];
        }

        return $result;
    }

    /**
     * Add computed display fields to bestowal entities in place.
     *
     * @param iterable<\Awards\Model\Entity\Bestowal> $bestowals Paginated bestowal entities
     * @param array<int,string>|null $visibleColumns Visible display columns, or null to prepare all
     * @return iterable<\Awards\Model\Entity\Bestowal>
     */
    protected function prepareBestowalsForDisplay(iterable $bestowals, ?array $visibleColumns = null): iterable
    {
        foreach ($bestowals as $bestowal) {
            if ($this->shouldLoadBestowalDisplayColumn('member_sca_name', $visibleColumns)) {
                $bestowal->member_sca_name = $bestowal->member->sca_name ?? '';
            }
            if ($this->shouldLoadBestowalDisplayColumn('awards', $visibleColumns)) {
                $bestowal->awards = $this->buildAwardsHtml($bestowal);
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
     * @param array<string, mixed> $result Normalized mutation result
     * @param int $actorId Current user ID
     * @return array<string, mixed>
     */
    protected function buildBestowalStateChangedPayload(array $result, int $actorId): array
    {
        $data = $result['data'];
        $transition = $data['result'] ?? ($data['results'][0] ?? $data);

        return [
            'bestowalId' => (int)($transition['bestowalId'] ?? $data['bestowalId'] ?? 0),
            'previousState' => $transition['previousState'] ?? null,
            'newState' => $transition['newState'] ?? null,
            'previousStatus' => $transition['previousStatus'] ?? null,
            'newStatus' => $transition['newStatus'] ?? null,
            'actorId' => $actorId,
        ];
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
                    'gridKey' => 'Awards.Bestowals.gathering.' . $syncContext['gatheringId'],
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
                $baseQuery = $built['query'];
                $baseQuery = $queryService->applyHiddenStateVisibility($baseQuery, $canViewHidden);
                $baseQuery = $this->Authorization->applyScope($baseQuery, 'index');
            }

            $baseQuery = $baseQuery->where(['Bestowals.id' => $bestowalId]);
            $built['gridOptions']['baseQuery'] = $baseQuery;

            $result = $this->processDataverseGrid($built['gridOptions']);
            $result = $this->applyStateFilterOptionsToGridResult($result, $canViewHidden);

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
