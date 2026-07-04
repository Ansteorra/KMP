<?php
declare(strict_types=1);

namespace App\Controller;

use App\KMP\GridColumns\ApprovalsGridColumns;
use App\KMP\TimezoneHelper;
use App\KMP\WorkflowApprovalDecisionOptions;
use App\Model\Entity\WorkflowApproval;
use App\Model\Entity\WorkflowApprovalResponse;
use App\Model\Entity\WorkflowApprovalTriageState;
use App\Model\Entity\WorkflowInstance;
use App\Model\Table\WorkflowApprovalsTable;
use App\Services\ApprovalContext\ApprovalContextRendererRegistry;
use App\Services\ServiceResult;
use App\Services\WorkflowEngine\WorkflowApprovalManagerInterface;
use App\Services\WorkflowEngine\WorkflowEngineInterface;
use Awards\Model\Entity\Bestowal;
use Awards\Services\BestowalGatheringLookupService;
use Awards\Services\RecommendationApprovalProcessService;
use Awards\Services\RecommendationFeedbackService;
use Cake\Controller\ComponentRegistry;
use Cake\Http\Response;
use Cake\Http\ServerRequest;
use Cake\I18n\DateTime;
use Cake\ORM\TableRegistry;
use Cake\Routing\Router;
use Exception;

/**
 * Approvals Controller
 *
 * Manages user approvals and admin approval management.
 *
 * @property \App\Model\Table\WorkflowApprovalsTable $WorkflowApprovals
 */
class ApprovalsController extends AppController
{
    use DataverseGridTrait;

    private const BESTOWAL_GATHERING_REQUIRED_KEY = 'requires_bestowal_gathering';
    private const BESTOWAL_GATHERING_WORKFLOW_SLUGS = [
        'awards-recommendation-submitted',
        'awards-existing-recommendation-approval',
    ];

    protected ?string $defaultTable = 'WorkflowApprovals';

    private WorkflowEngineInterface $engine;
    private WorkflowApprovalManagerInterface $approvalManager;
    private ?array $bestowalGatheringOptions = null;

    /**
     * Constructor.
     *
     * @param \Cake\Http\ServerRequest $request Request
     * @param \App\Services\WorkflowEngine\WorkflowEngineInterface $engine Workflow engine
     * @param \App\Services\WorkflowEngine\WorkflowApprovalManagerInterface $approvalManager Approval manager
     * @param \Cake\Controller\ComponentRegistry|null $components Component registry
     */
    public function __construct(
        ServerRequest $request,
        WorkflowEngineInterface $engine,
        WorkflowApprovalManagerInterface $approvalManager,
        ?ComponentRegistry $components = null,
    ) {
        parent::__construct($request, null, null, $components);
        $this->engine = $engine;
        $this->approvalManager = $approvalManager;
    }

    /**
     * Initialize controller authorization.
     *
     * @return void
     */
    public function initialize(): void
    {
        parent::initialize();
        $this->Authorization->authorizeModel(
            'approvals',
            'recordApproval',
            'allApprovals',
            'allApprovalsGridData',
            'approvalsKanbanLaneData',
            'reassignApproval',
            'updateTriage',
            'mobileApprovals',
            'mobileApprovalsData',
            'bestowalGatheringsAutoComplete',
        );
    }

    /**
     * Approval dashboard entry point.
     *
     * @return \Cake\Http\Response|null|void
     */
    public function approvals()
    {
        // Page shell only — grid lazy-loads via approvalsGridData
    }

    /**
     * Mobile-optimized approval dashboard.
     *
     * @return \Cake\Http\Response|null|void
     */
    public function mobileApprovals()
    {
        $currentUser = $this->request->getAttribute('identity');
        $pendingCount = $currentUser
            ? WorkflowApprovalsTable::getPendingApprovalCountForMember((int)$currentUser->id)
            : 0;
        if ($pendingCount === 0) {
            $this->Flash->info(__('You have no pending approvals right now.'));

            return $this->redirect(['controller' => 'Members', 'action' => 'viewMobileCard']);
        }

        $this->set('mobileTitle', 'Approvals');
        $this->set('mobileSection', 'approvals');
        $this->set('mobileIcon', 'bi-check2-square');
        $this->set('mobileQueuePerPage', self::MOBILE_QUEUE_DEFAULT_PER_PAGE);

        $this->viewBuilder()->setLayout('mobile_app');
    }

    /**
     * JSON API: Pending approvals with rich context for mobile UI.
     *
     * @return \Cake\Http\Response|null|void
     */
    public function mobileApprovalsData()
    {
        $this->request->allowMethod(['get']);
        $this->Authorization->skipAuthorization();

        $currentUser = $this->request->getAttribute('identity');
        $total = WorkflowApprovalsTable::getPendingApprovalCountForMember((int)$currentUser->id);
        $pagination = $this->mobileQueuePagination($total);
        $eligible = WorkflowApprovalsTable::getPendingApprovalsForMember((int)$currentUser->id, [
            'WorkflowInstances' => ['WorkflowDefinitions'],
        ], null, (int)$pagination['perPage'], (int)$pagination['offset']);
        $triageByApprovalId = $this->getTriagePayloads(
            array_map(static fn($approval): int => (int)$approval->id, $eligible),
            (int)$currentUser->id,
        );

        $approvals = [];
        foreach ($eligible as $approval) {
            $triage = $triageByApprovalId[(int)$approval->id] ?? $this->defaultTriagePayload();
            $instance = $approval->workflow_instance;
            $ctx = $instance
                ? ApprovalContextRendererRegistry::render($instance)
                : null;

            $context = $ctx ? $ctx->toArray() : [
                'title' => __('Unknown Approval'),
                'description' => '',
                'fields' => [],
                'entityUrl' => null,
                'icon' => 'bi-question-circle',
                'requester' => null,
            ];

            $approverConfig = $this->augmentApproverConfigForResponse(
                ApprovalsGridColumns::normalizeApproverConfig($approval->approver_config),
                $approval,
            );
            $isFeedbackResponse = !empty($approverConfig['feedback_response']);
            $decisionOptions = WorkflowApprovalDecisionOptions::normalizeOptions($approverConfig);

            $approvals[] = [
                'id' => $approval->id,
                'title' => $context['title'],
                'description' => $context['description'],
                'icon' => $context['icon'],
                'requester' => $context['requester'] ?? '—',
                'fields' => $context['fields'],
                'entityUrl' => $context['entityUrl'],
                'progress' => [
                    'required' => $approval->required_count,
                    'approved' => $approval->approved_count,
                    'rejected' => $approval->rejected_count,
                ],
                'statusLabel' => __('Pending ({0}/{1})', $approval->approved_count, $approval->required_count),
                'triage' => $triage,
                'approverConfig' => [
                    'serialPickNext' => $approverConfig['serial_pick_next'] ?? false,
                    'feedbackResponse' => $isFeedbackResponse,
                    'hideProgress' => $isFeedbackResponse,
                    'commentWarning' => $approverConfig['comment_warning'] ?? '',
                    'requiresComment' => !empty($approverConfig['requires_comment']),
                    'decisionOptions' => $decisionOptions,
                    'decisionPromptLabel' => $approverConfig['decision_prompt_label'] ?? '',
                    'requiresBestowalGathering' => $this->requiresBestowalGatheringSelection($approverConfig),
                    'bestowalGatheringOptions' => $approverConfig['bestowal_gathering_options'] ?? [],
                ],
                'modified' => $approval->modified ? $approval->modified->toIso8601String() : null,
            ];
        }

        $this->response = $this->response
            ->withType('application/json')
            ->withStringBody(json_encode([
                'approvals' => $approvals,
                'pagination' => $this->mobileQueuePaginationPayload($pagination),
            ]));

        return $this->response;
    }

    /**
     * API: create or update the current member's private approval triage state.
     *
     * @return \Cake\Http\Response|null|void
     */
    public function updateTriage()
    {
        $this->request->allowMethod(['post']);
        $this->Authorization->skipAuthorization();

        $currentUser = $this->request->getAttribute('identity');
        $approvalId = (int)$this->request->getData('approvalId');
        $state = (string)$this->request->getData('state', WorkflowApprovalTriageState::STATE_NEW);
        $note = (string)$this->request->getData('note', '');

        if ($approvalId <= 0) {
            return $this->jsonResponse(['success' => false, 'error' => __('Approval ID is required.')], 422);
        }
        if (!in_array($state, WorkflowApprovalTriageState::states(), true)) {
            return $this->jsonResponse(['success' => false, 'error' => __('Invalid triage state.')], 422);
        }
        if (!$this->isApprovalPendingForMember($approvalId, (int)$currentUser->id)) {
            return $this->jsonResponse(['success' => false, 'error' => __('Approval is not pending for you.')], 403);
        }

        $triageTable = $this->fetchTable('WorkflowApprovalTriageStates');
        $triage = $triageTable->find()
            ->where([
                'workflow_approval_id' => $approvalId,
                'member_id' => (int)$currentUser->id,
            ])
            ->first();
        if (!$triage) {
            $triage = $triageTable->newEntity([
                'workflow_approval_id' => $approvalId,
                'member_id' => (int)$currentUser->id,
                'state' => $state,
                'note' => trim($note) === '' ? null : $note,
            ]);
        } else {
            $triage = $triageTable->patchEntity($triage, [
                'state' => $state,
                'note' => trim($note) === '' ? null : $note,
            ]);
        }
        if (!$triageTable->save($triage)) {
            $errorMessage = $this->formatValidationErrors($triage->getErrors())
                ?: __('Unable to save triage state.');

            return $this->jsonResponse(['success' => false, 'error' => $errorMessage], 422);
        }

        return $this->jsonResponse([
            'success' => true,
            'triage' => $this->formatTriagePayload($triage),
        ]);
    }

    /**
     * Return future gathering autocomplete options for approval-created bestowals.
     *
     * @param \Awards\Services\BestowalGatheringLookupService $lookupService Gathering lookup service.
     * @return void
     */
    public function bestowalGatheringsAutoComplete(BestowalGatheringLookupService $lookupService): void
    {
        $this->request->allowMethod(['get']);
        $this->viewBuilder()->setClassName('Ajax');

        $q = trim((string)$this->request->getQuery('q', ''));
        $selectedId = $this->request->getQuery('selected_id');
        $selectedId = is_numeric((string)$selectedId) ? (int)$selectedId : null;
        $bestowal = new Bestowal();
        $gatheringData = $lookupService->getFilteredGatheringsForBestowal($bestowal, true, $selectedId);
        $gatherings = $gatheringData['gatherings'] ?? [];
        $cancelledGatheringIds = $gatheringData['cancelledGatheringIds'] ?? [];

        if ($q !== '') {
            $gatherings = array_filter(
                $gatherings,
                fn($display) => mb_stripos((string)$display, $q) !== false,
            );
        }

        $this->set(compact('gatherings', 'q', 'cancelledGatheringIds', 'selectedId'));
    }

    /**
     * Token-based deep link for email-based approval access.
     *
     * @param string $token Approval token from email
     * @return \Cake\Http\Response|null|void
     */
    public function approvalByToken(string $token)
    {
        $this->Authorization->skipAuthorization();

        $approvalsTable = $this->fetchTable('WorkflowApprovals');
        $approval = $approvalsTable->find()
            ->contain(['WorkflowInstances' => ['WorkflowDefinitions']])
            ->where(['WorkflowApprovals.approval_token' => $token])
            ->first();

        if (!$approval) {
            $this->Flash->error(__('Invalid or expired approval link.'));

            return $this->redirect(['action' => 'approvals']);
        }

        $this->set('focusedApprovalId', $approval->id);
        $this->set('approvalToken', $token);

        $this->render('approvals');
    }

    /**
     * Grid data endpoint for the My Approvals DataverseGrid.
     *
     * @return \Cake\Http\Response|null|void
     */
    public function approvalsGridData()
    {
        $this->Authorization->skipAuthorization();

        $currentUser = $this->request->getAttribute('identity');
        $approvalsTable = $this->fetchTable('WorkflowApprovals');
        $systemViews = ApprovalsGridColumns::getSystemViews();
        $queryContext = $this->resolveDataverseGridQueryContext([
            'gridKey' => 'Workflows.approvals.main',
            'gridColumnsClass' => ApprovalsGridColumns::class,
            'systemViews' => $systemViews,
            'defaultSystemView' => 'sys-approvals-pending',
            'defaultSort' => ['WorkflowApprovals.modified' => 'desc', 'WorkflowApprovals.id' => 'desc'],
        ]);
        $contain = [
            'WorkflowInstances' => ['WorkflowDefinitions'],
        ];
        if ($queryContext->loadsColumn('current_approver')) {
            $contain['CurrentApprover'] = function ($q) {
                return $q->select(['id', 'sca_name']);
            };
        }

        $baseQuery = $approvalsTable->find()->contain($contain);
        $baseQuery->leftJoinWith('CurrentApprover');

        $queryCallback = function ($query, $systemView) use ($currentUser) {
            if ($systemView === null) {
                $query->where(['WorkflowApprovals.id' => -1]);

                return $query;
            }

            if (in_array($systemView['id'], ['sys-approvals-pending', 'sys-approvals-triage-board'], true)) {
                $eligibleIds = WorkflowApprovalsTable::getPendingApprovalIdsForMember((int)$currentUser->id);

                if (empty($eligibleIds)) {
                    $query->where(['WorkflowApprovals.id' => -1]);
                } else {
                    $query->where(['WorkflowApprovals.id IN' => $eligibleIds]);
                }
            } elseif ($systemView['id'] === 'sys-approvals-decisions') {
                $query->innerJoinWith('WorkflowApprovalResponses', function ($q) use ($currentUser) {
                    return $q->where(['WorkflowApprovalResponses.member_id' => $currentUser->id]);
                })
                    ->where([
                        'NOT EXISTS (
                            SELECT 1
                            FROM awards_recommendation_feedback_request_recipients feedback_recipients
                            WHERE feedback_recipients.workflow_approval_id = WorkflowApprovals.id
                        )',
                    ]);
            }

            return $query;
        };

        $result = $this->processDataverseGrid([
            'gridKey' => 'Workflows.approvals.main',
            'gridColumnsClass' => ApprovalsGridColumns::class,
            'baseQuery' => $baseQuery,
            'tableName' => 'WorkflowApprovals',
            'defaultSort' => ['WorkflowApprovals.modified' => 'desc', 'WorkflowApprovals.id' => 'desc'],
            'defaultPageSize' => 25,
            'systemViews' => $systemViews,
            'defaultSystemView' => 'sys-approvals-pending',
            'queryCallback' => $queryCallback,
            'showAllTab' => false,
            'canAddViews' => false,
            'canFilter' => true,
            'canExportCsv' => false,
            'enableBulkSelection' => true,
            'bulkSelection' => [
                'selectAllLabel' => __('Select all approvals on this page'),
                'rowLabelTemplate' => __('Select approval: {request}'),
                'disabledLabel' => __('Only pending approvals can be selected for bulk response.'),
            ],
            'bulkActions' => [
                [
                    'key' => 'approval-response',
                    'label' => __('Respond'),
                    'icon' => 'bi-reply-fill',
                    'modalTarget' => '#approvalResponseModal',
                ],
            ],
            'bulkSelectionDataFields' => [
                'approval-type-key' => 'bulk_response_type_key',
                'approval-response-payload' => 'bulk_response_payload',
            ],
            'bulkSelectionDisabledField' => 'bulk_response_disabled',
            'lockedFilters' => ['status_label'],
            'showFilterPills' => true,
            'showSearchBox' => true,
        ]);

        $isKanbanView = $this->isApprovalKanbanView($result['gridState']);
        $this->prepareApprovalsForGrid($result['data'], $result['visibleColumns']);

        $rowActions = ApprovalsGridColumns::getRowActions();
        $this->set([
            'data' => $result['data'],
            'gridState' => $result['gridState'],
            'columns' => $result['columnsMetadata'],
            'visibleColumns' => $result['visibleColumns'],
            'searchableColumns' => ApprovalsGridColumns::getSearchableColumns(),
            'dropdownFilterColumns' => $result['dropdownFilterColumns'],
            'filterOptions' => $result['filterOptions'],
            'currentFilters' => $result['currentFilters'],
            'currentSearch' => $result['currentSearch'],
            'currentView' => $result['currentView'],
            'availableViews' => $result['availableViews'],
            'gridKey' => $result['gridKey'],
            'currentSort' => $result['currentSort'],
            'currentMember' => $result['currentMember'],
            'rowActions' => $rowActions,
            'customElement' => $isKanbanView ? 'approvals/kanban_board' : null,
            'customElementOptions' => $isKanbanView ? [
                'lanes' => $this->buildApprovalKanbanLanes(),
                'detailUrl' => Router::url(['controller' => 'Approvals', 'action' => 'approvalDetail']),
                'triageUrl' => Router::url(['controller' => 'Approvals', 'action' => 'updateTriage']),
            ] : [],
        ]);

        $turboFrame = $this->request->getHeaderLine('Turbo-Frame');

        if ($turboFrame === 'approvals-grid-table') {
            $this->set('tableFrameId', 'approvals-grid-table');
            $this->viewBuilder()->disableAutoLayout();
            $this->viewBuilder()->setTemplate('../element/dv_grid_table');
        } else {
            $this->set('frameId', 'approvals-grid');
            $this->viewBuilder()->disableAutoLayout();
            $this->viewBuilder()->setTemplate('../element/dv_grid_content');
        }
    }

    /**
     * Lane frame endpoint for the approval triage Kanban board.
     *
     * @return \Cake\Http\Response|null|void
     */
    public function approvalsKanbanLaneData()
    {
        $this->request->allowMethod(['get']);
        $currentUser = $this->request->getAttribute('identity');
        $state = (string)$this->request->getQuery('triage_state', WorkflowApprovalTriageState::STATE_NEW);
        if (!in_array($state, WorkflowApprovalTriageState::states(), true)) {
            $state = WorkflowApprovalTriageState::STATE_NEW;
        }

        $approvalsTable = $this->fetchTable('WorkflowApprovals');
        $systemViews = ApprovalsGridColumns::getSystemViews();
        $baseQuery = $approvalsTable->find()
            ->contain([
                'WorkflowInstances' => ['WorkflowDefinitions'],
            ])
            ->leftJoinWith('CurrentApprover');

        $queryCallback = function ($query) use ($currentUser, $state) {
            $eligibleIds = WorkflowApprovalsTable::getPendingApprovalIdsForMember((int)$currentUser->id);
            if ($eligibleIds === []) {
                $query->where(['WorkflowApprovals.id' => -1]);

                return $query;
            }

            $query
                ->where(['WorkflowApprovals.id IN' => $eligibleIds])
                ->leftJoinWith('WorkflowApprovalTriageStates', function ($q) use ($currentUser) {
                    return $q->where(['WorkflowApprovalTriageStates.member_id' => (int)$currentUser->id]);
                });

            if ($state === WorkflowApprovalTriageState::STATE_NEW) {
                $query->where(function ($exp) {
                    return $exp->or([
                        'WorkflowApprovalTriageStates.id IS' => null,
                        'WorkflowApprovalTriageStates.state' => WorkflowApprovalTriageState::STATE_NEW,
                    ]);
                });
            } else {
                $query->where(['WorkflowApprovalTriageStates.state' => $state]);
            }

            return $query;
        };

        $result = $this->processDataverseGrid([
            'gridKey' => 'Workflows.approvals.main',
            'gridColumnsClass' => ApprovalsGridColumns::class,
            'baseQuery' => $baseQuery,
            'tableName' => 'WorkflowApprovals',
            'defaultSort' => ['WorkflowApprovals.modified' => 'desc', 'WorkflowApprovals.id' => 'desc'],
            'defaultPageSize' => 20,
            'systemViews' => $systemViews,
            'defaultSystemView' => 'sys-approvals-triage-board',
            'queryCallback' => $queryCallback,
            'showAllTab' => false,
            'canAddViews' => false,
            'canFilter' => true,
            'canExportCsv' => false,
            'lockedFilters' => ['status_label'],
            'showFilterPills' => true,
            'showSearchBox' => true,
            'metadataMode' => 'table',
        ]);

        $this->prepareApprovalsForKanbanCards($result['data'], (int)$currentUser->id);
        $paging = method_exists($result['data'], 'pagingParams')
            ? $result['data']->pagingParams()
            : ($this->request->getAttribute('paging')['WorkflowApprovals'] ?? []);
        $page = (int)($paging['currentPage'] ?? $paging['page'] ?? $this->request->getQuery('page', 1));
        $totalCount = (int)($paging['totalCount'] ?? $paging['count'] ?? count($result['data']));
        $hasNextPage = (bool)($paging['hasNextPage'] ?? false);
        $pageCount = (int)($paging['pageCount'] ?? max($page + ($hasNextPage ? 1 : 0), 1));

        $this->set([
            'lane' => $this->buildApprovalKanbanLane($state),
            'data' => $result['data'],
            'cardActions' => $this->getApprovalKanbanCardActions(),
            'page' => $page,
            'pageCount' => $pageCount,
            'totalCount' => $totalCount,
            'hasNextPage' => $hasNextPage,
            'nextPage' => $page + 1,
        ]);
        $this->viewBuilder()->disableAutoLayout();
        $this->viewBuilder()->setTemplate('../element/approvals/kanban_lane');
    }

    /**
     * Admin view: all approvals across the system.
     *
     * @return \Cake\Http\Response|null|void
     */
    public function allApprovals()
    {
        // Page shell only — grid lazy-loads via allApprovalsGridData
    }

    /**
     * Grid data endpoint for the admin All Approvals DataverseGrid.
     *
     * @return \Cake\Http\Response|null|void
     */
    public function allApprovalsGridData()
    {
        $approvalsTable = $this->fetchTable('WorkflowApprovals');
        $systemViews = ApprovalsGridColumns::getAdminSystemViews();
        $contain = [
            'WorkflowInstances' => ['WorkflowDefinitions'],
        ];
        $contain['CurrentApprover'] = function ($q) {
            return $q->select(['id', 'sca_name']);
        };

        $baseQuery = $approvalsTable->find()
            ->contain($contain)
            ->leftJoinWith('CurrentApprover');

        $queryCallback = function ($query, $systemView) {
            if ($systemView === null) {
                $query->where(['WorkflowApprovals.id' => -1]);

                return $query;
            }

            if ($systemView['id'] === 'sys-admin-pending') {
                $query->where(['WorkflowApprovals.status' => WorkflowApproval::STATUS_PENDING]);
            } elseif ($systemView['id'] === 'sys-admin-approved') {
                $query->where(['WorkflowApprovals.status' => WorkflowApproval::STATUS_APPROVED]);
            } elseif ($systemView['id'] === 'sys-admin-rejected') {
                $query->where(['WorkflowApprovals.status' => WorkflowApproval::STATUS_REJECTED]);
            }

            return $query;
        };

        $result = $this->processDataverseGrid([
            'gridKey' => 'Workflows.allApprovals.main',
            'gridColumnsClass' => ApprovalsGridColumns::class,
            'baseQuery' => $baseQuery,
            'tableName' => 'WorkflowApprovals',
            'defaultSort' => ['WorkflowApprovals.modified' => 'desc'],
            'defaultPageSize' => 25,
            'systemViews' => $systemViews,
            'defaultSystemView' => 'sys-admin-pending',
            'queryCallback' => $queryCallback,
            'showAllTab' => false,
            'canAddViews' => false,
            'canFilter' => true,
            'canExportCsv' => false,
            'lockedFilters' => ['status_label'],
            'showFilterPills' => true,
            'showSearchBox' => true,
        ]);

        if (!in_array('current_approver', $result['gridState']['columns']['visible'])) {
            $result['gridState']['columns']['visible'][] = 'current_approver';
        }
        $this->prepareApprovalsForGrid($result['data'], $result['gridState']['columns']['visible']);

        $rowActions = ApprovalsGridColumns::getAdminRowActions();
        $this->set([
            'data' => $result['data'],
            'gridState' => $result['gridState'],
            'columns' => $result['columnsMetadata'],
            'visibleColumns' => $result['gridState']['columns']['visible'],
            'searchableColumns' => ApprovalsGridColumns::getSearchableColumns(),
            'dropdownFilterColumns' => $result['dropdownFilterColumns'],
            'filterOptions' => $result['filterOptions'],
            'currentFilters' => $result['currentFilters'],
            'currentSearch' => $result['currentSearch'],
            'currentView' => $result['currentView'],
            'availableViews' => $result['availableViews'],
            'gridKey' => $result['gridKey'],
            'currentSort' => $result['currentSort'],
            'currentMember' => $result['currentMember'],
            'rowActions' => $rowActions,
        ]);

        $turboFrame = $this->request->getHeaderLine('Turbo-Frame');

        if ($turboFrame === 'all-approvals-grid-table') {
            $this->set('tableFrameId', 'all-approvals-grid-table');
            $this->viewBuilder()->disableAutoLayout();
            $this->viewBuilder()->setTemplate('../element/dv_grid_table');
        } else {
            $this->set('frameId', 'all-approvals-grid');
            $this->viewBuilder()->disableAutoLayout();
            $this->viewBuilder()->setTemplate('../element/dv_grid_content');
        }
    }

    /**
     * Add computed approval grid fields only when their columns are visible.
     *
     * @param iterable<\App\Model\Entity\WorkflowApproval> $approvals
     * @param array<int,string> $visibleColumns
     */
    private function prepareApprovalsForGrid(iterable $approvals, array $visibleColumns): void
    {
        $includeWorkflowName = in_array('workflow_name', $visibleColumns, true);
        $includeStatusLabel = in_array('status_label', $visibleColumns, true);
        $includeCurrentApprover = in_array('current_approver', $visibleColumns, true);
        $includeRequest = in_array('request', $visibleColumns, true);
        $includeRequester = in_array('requester', $visibleColumns, true);

        foreach ($approvals as $approval) {
            $approverConfig = ApprovalsGridColumns::normalizeApproverConfig($approval->approver_config);
            $approverConfig = $this->augmentApproverConfigForResponse($approverConfig, $approval);
            $approval->approver_config = $approverConfig;
            $isFeedbackResponse = !empty($approverConfig['feedback_response']);
            $approval->is_feedback_response = $isFeedbackResponse;
            $approval->bulk_response_type_key = $this->getApprovalResponseTypeKey($approval);
            $approval->bulk_response_payload = json_encode($this->getApprovalResponseModalPayload($approval));
            $approval->bulk_response_disabled = $approval->status !== WorkflowApproval::STATUS_PENDING;
            if ($includeWorkflowName) {
                $approval->workflow_name = $approval->workflow_instance?->workflow_definition?->name ?? __('Unknown');
            }

            if ($includeStatusLabel) {
                if ($approval->status === WorkflowApproval::STATUS_PENDING) {
                    $approval->status_label = ApprovalsGridColumns::getPendingStatusLabel($approval, $approverConfig);
                } else {
                    $approval->status_label = ucfirst($approval->status);
                }
            }

            if ($includeCurrentApprover) {
                $approval->current_approver = $approval->current_approver?->sca_name ?? '—';
            }

            if ($includeRequest || $includeRequester) {
                $cachedTitle = trim((string)($approval->request_title ?? ''));
                $instance = $approval->workflow_instance;
                if ($includeRequest && $cachedTitle !== '') {
                    $approval->request = $cachedTitle;
                }

                if ($instance && ($includeRequester || ($includeRequest && $cachedTitle === ''))) {
                    $ctx = ApprovalContextRendererRegistry::render($instance);
                    if ($includeRequest && $cachedTitle === '') {
                        $approval->request = $ctx->getTitle();
                    }
                    if ($includeRequester) {
                        $approval->requester = $ctx->getRequester() ?? '—';
                    }
                } else {
                    if ($includeRequest && $cachedTitle === '') {
                        $approval->request = '—';
                    }
                    if ($includeRequester) {
                        $approval->requester = '—';
                    }
                }
            }
        }
    }

    /**
     * Add lightweight card fields for approval Kanban lanes.
     *
     * @param iterable<\App\Model\Entity\WorkflowApproval> $approvals Approvals.
     * @param int $memberId Current member ID.
     * @return void
     */
    private function prepareApprovalsForKanbanCards(iterable $approvals, int $memberId): void
    {
        $approvalList = is_array($approvals) ? $approvals : iterator_to_array($approvals);
        $triageByApprovalId = $this->getTriagePayloads(
            array_map(static fn($approval): int => (int)$approval->id, $approvalList),
            $memberId,
        );

        foreach ($approvalList as $approval) {
            $approverConfig = ApprovalsGridColumns::normalizeApproverConfig($approval->approver_config);
            $approverConfig = $this->augmentApproverConfigForResponse($approverConfig, $approval);
            $approval->approver_config = $approverConfig;
            $isFeedbackResponse = !empty($approverConfig['feedback_response']);
            $approval->is_feedback_response = $isFeedbackResponse;
            $approval->workflow_name = $approval->workflow_instance?->workflow_definition?->name ?? __('Unknown');
            $approval->status_label = ApprovalsGridColumns::getPendingStatusLabel($approval, $approverConfig);
            $approval->triage = $triageByApprovalId[(int)$approval->id] ?? $this->defaultTriagePayload();
            $approval->triage_state = $approval->triage['state'];
            $approval->triage_state_label = $approval->triage['stateLabel'];
            $approval->triage_note = $approval->triage['note'];
            $approval->modified_label = $approval->modified
                ? TimezoneHelper::formatDateTime($approval->modified, TimezoneHelper::DISPLAY_DATETIME_FORMAT)
                : __('Unknown');
            $approval->modified_iso = $approval->modified?->toIso8601String();

            $instance = $approval->workflow_instance;
            $cachedTitle = trim((string)($approval->request_title ?? ''));
            if ($instance) {
                $ctx = ApprovalContextRendererRegistry::render($instance);
                $approval->request = $cachedTitle !== '' ? $cachedTitle : $ctx->getTitle();
                $approval->requester = $ctx->getRequester() ?? '—';
                $approval->source_url = $ctx->getEntityUrl();
                $approval->icon = $ctx->getIcon();
            } else {
                $approval->request = $cachedTitle !== '' ? $cachedTitle : __('Unknown Approval');
                $approval->requester = '—';
                $approval->source_url = null;
                $approval->icon = 'bi-question-circle';
            }
        }
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function getApprovalKanbanCardActions(): array
    {
        $actions = ApprovalsGridColumns::getRowActions();
        unset($actions['detail']);

        return $actions;
    }

    /**
     * @param array<string, mixed> $gridState Grid state.
     * @return bool
     */
    private function isApprovalKanbanView(array $gridState): bool
    {
        return ($gridState['view']['currentId'] ?? null) === 'sys-approvals-triage-board';
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildApprovalKanbanLanes(): array
    {
        return array_map(
            fn(string $state): array => $this->buildApprovalKanbanLane($state),
            WorkflowApprovalTriageState::states(),
        );
    }

    /**
     * @param string $state Triage state.
     * @return array<string, mixed>
     */
    private function buildApprovalKanbanLane(string $state): array
    {
        $labels = WorkflowApprovalTriageState::labels();
        $query = $this->sanitizeApprovalKanbanQueryParams($this->request->getQueryParams());
        $query['triage_state'] = $state;
        $query['view_id'] = 'sys-approvals-triage-board';
        unset($query['page']);

        return [
            'state' => $state,
            'label' => $labels[$state] ?? $state,
            'frameId' => 'approval-kanban-lane-' . str_replace('_', '-', $state),
            'url' => Router::url([
                'controller' => 'Approvals',
                'action' => 'approvalsKanbanLaneData',
                '?' => $query,
            ]),
        ];
    }

    /**
     * Remove query keys produced by escaped ampersands being reused as literal URL params.
     *
     * @param array<string, mixed> $query Query parameters.
     * @return array<string, mixed>
     */
    private function sanitizeApprovalKanbanQueryParams(array $query): array
    {
        foreach (array_keys($query) as $key) {
            if ($this->isEscapedAmpersandQueryKey((string)$key)) {
                unset($query[$key]);
            }
        }

        return $query;
    }

    /**
     * Detect keys like amp;page or amp%3Btriage_state created by repeated HTML entity encoding.
     *
     * @param string $key Query parameter key.
     * @return bool
     */
    private function isEscapedAmpersandQueryKey(string $key): bool
    {
        $decoded = html_entity_decode(rawurldecode($key), ENT_QUOTES | ENT_HTML5);

        return str_starts_with($decoded, 'amp;');
    }

    /**
     * API: Record an approval response and optionally resume workflow.
     *
     * @return \Cake\Http\Response|null|void
     */
    public function recordApproval(RecommendationFeedbackService $feedbackService)
    {
        $this->request->allowMethod(['post']);
        $bulkApprovalIds = $this->parseBulkApprovalIds($this->request->getData('approvalIds'));
        if ($bulkApprovalIds !== []) {
            return $this->recordBulkApprovalResponses($feedbackService, $bulkApprovalIds);
        }

        $approvalId = (int)$this->request->getData('approvalId');
        $decision = $this->request->getData('decision');
        $comment = $this->request->getData('comment');
        $bestowalGatheringId = $this->getPostedBestowalGatheringId();
        $nextApproverId = $this->request->getData('next_approver_id')
            ? (int)$this->request->getData('next_approver_id')
            : null;
        $currentUser = $this->request->getAttribute('identity');
        $approval = $this->fetchTable('WorkflowApprovals')->find()
            ->contain(['WorkflowInstances' => ['WorkflowDefinitions']])
            ->where(['WorkflowApprovals.id' => $approvalId])
            ->first();
        $approverConfig = $approval
            ? ApprovalsGridColumns::normalizeApproverConfig($approval->approver_config)
            : [];
        $isFeedbackResponse = !empty($approverConfig['feedback_response']);
        $decisionOptions = WorkflowApprovalDecisionOptions::normalizeOptions($approverConfig);
        if ($isFeedbackResponse && $decisionOptions === []) {
            $decision = WorkflowApprovalResponse::DECISION_APPROVE;
        }
        $requiresComment = $decision === 'reject' || !empty($approverConfig['requires_comment']);

        if ($requiresComment && empty(trim((string)$comment))) {
            $error = $isFeedbackResponse
                ? __('Feedback comment is required.')
                : __('A comment is required when rejecting an approval.');
            if ($this->request->is('ajax')) {
                $this->set('result', ['success' => false, 'error' => $error]);
                $this->viewBuilder()->setOption('serialize', 'result');
                $this->response = $this->response->withType('application/json');
                $this->viewBuilder()->setClassName('Json');

                return;
            }
            $this->Flash->error($error);

            return $this->redirect(['action' => 'approvals']);
        }

        $gatheringError = $this->validateBestowalGatheringSelection($approval, (string)$decision, $bestowalGatheringId);
        if ($gatheringError !== null) {
            return $this->approvalResponseFailure($gatheringError);
        }

        $result = $this->recordSingleApprovalResponse(
            $approval,
            (int)$currentUser->id,
            (string)$decision,
            $comment !== null ? (string)$comment : null,
            $nextApproverId,
            $feedbackService,
        );

        if ($result->isSuccess()) {
            $this->handleApprovalResponseSideEffects(
                $result,
                (int)$currentUser->id,
                (string)$decision,
                $comment,
                $bestowalGatheringId,
            );
        }

        if ($this->request->is('ajax') && !$this->wantsTurboStreamRequest()) {
            return $this->jsonResponse([
                'success' => $result->isSuccess(),
                'error' => $result->isSuccess() ? null : $result->getError(),
                'reason' => $result->getError(),
                'data' => $result->getData(),
            ]);
        } else {
            if ($result->isSuccess()) {
                $this->Flash->success(__('Approval response recorded.'));
                $stream = $this->tryApprovalsGridTurboResponse($this->getPageContextUrl());
                if ($stream !== null) {
                    return $stream;
                }
            } else {
                $this->Flash->error($result->getError());
            }

            return $this->redirect(['action' => 'approvals']);
        }
    }

    /**
     * Apply one modal response to multiple same-type approvals.
     *
     * @param \Awards\Services\RecommendationFeedbackService $feedbackService Feedback service.
     * @param array<int> $approvalIds Selected approval IDs.
     * @return \Cake\Http\Response|null|void
     */
    private function recordBulkApprovalResponses(
        RecommendationFeedbackService $feedbackService,
        array $approvalIds,
    ) {
        $decision = $this->request->getData('decision');
        $comment = $this->request->getData('comment');
        $bestowalGatheringId = $this->getPostedBestowalGatheringId();
        $nextApproverId = $this->request->getData('next_approver_id')
            ? (int)$this->request->getData('next_approver_id')
            : null;
        $currentUser = $this->request->getAttribute('identity');

        if (count($approvalIds) < 1) {
            $error = __('Select at least one approval for bulk response.');

            return $this->approvalResponseFailure($error);
        }

        $approvals = $this->fetchTable('WorkflowApprovals')->find()
            ->contain(['WorkflowInstances' => ['WorkflowDefinitions']])
            ->where(['WorkflowApprovals.id IN' => $approvalIds])
            ->all()
            ->combine('id', fn(WorkflowApproval $approval): WorkflowApproval => $approval)
            ->toArray();
        if (count($approvals) !== count($approvalIds)) {
            return $this->approvalResponseFailure(__('One or more selected approvals could not be found.'));
        }

        $typeKeys = [];
        foreach ($approvalIds as $approvalId) {
            /** @var \App\Model\Entity\WorkflowApproval $approval */
            $approval = $approvals[$approvalId];
            if (!$this->isApprovalPendingForMember((int)$approval->id, (int)$currentUser->id)) {
                return $this->approvalResponseFailure(__('One or more selected approvals are not pending for you.'));
            }
            $typeKeys[$this->getApprovalResponseTypeKey($approval)] = true;
        }
        if (count($typeKeys) !== 1) {
            return $this->approvalResponseFailure(__('Bulk responses require approvals of the same type.'));
        }

        /** @var \App\Model\Entity\WorkflowApproval $firstApproval */
        $firstApproval = $approvals[$approvalIds[0]];
        $approverConfig = ApprovalsGridColumns::normalizeApproverConfig($firstApproval->approver_config);
        $isFeedbackResponse = !empty($approverConfig['feedback_response']);
        $decisionOptions = WorkflowApprovalDecisionOptions::normalizeOptions($approverConfig);
        if ($isFeedbackResponse && $decisionOptions === []) {
            $decision = WorkflowApprovalResponse::DECISION_APPROVE;
        }
        $requiresComment = $decision === WorkflowApprovalResponse::DECISION_REJECT
            || !empty($approverConfig['requires_comment']);
        if ($requiresComment && empty(trim((string)$comment))) {
            $error = $isFeedbackResponse
                ? __('Feedback comment is required.')
                : __('A comment is required when rejecting an approval.');

            return $this->approvalResponseFailure($error);
        }

        $gatheringError = $this->validateBestowalGatheringSelection(
            $firstApproval,
            (string)$decision,
            $bestowalGatheringId,
        );
        if ($gatheringError !== null) {
            return $this->approvalResponseFailure($gatheringError);
        }

        $successCount = 0;
        $errors = [];
        foreach ($approvalIds as $approvalId) {
            /** @var \App\Model\Entity\WorkflowApproval $approval */
            $approval = $approvals[$approvalId];
            $result = $this->recordSingleApprovalResponse(
                $approval,
                (int)$currentUser->id,
                (string)$decision,
                $comment !== null ? (string)$comment : null,
                $nextApproverId,
                $feedbackService,
            );
            if ($result->isSuccess()) {
                $successCount++;
                $this->handleApprovalResponseSideEffects(
                    $result,
                    (int)$currentUser->id,
                    (string)$decision,
                    $comment,
                    $bestowalGatheringId,
                );
            } else {
                $errors[] = __('Approval {0}: {1}', $approvalId, $result->getError());
            }
        }

        if ($successCount === 0) {
            return $this->approvalResponseFailure(implode(' ', $errors) ?: __('No approval responses were recorded.'));
        }

        if ($errors !== []) {
            $this->Flash->warning(__(
                'Recorded {0} approval response(s); {1} failed.',
                $successCount,
                count($errors),
            ));
        } else {
            $this->Flash->success(__('Recorded {0} approval response(s).', $successCount));
        }

        $stream = $this->tryApprovalsGridTurboResponse($this->getPageContextUrl());
        if ($stream !== null) {
            return $stream;
        }

        return $this->redirect(['action' => 'approvals']);
    }

    /**
     * Record one approval response without applying controller response handling.
     */
    private function recordSingleApprovalResponse(
        ?WorkflowApproval $approval,
        int $memberId,
        string $decision,
        ?string $comment,
        ?int $nextApproverId,
        RecommendationFeedbackService $feedbackService,
    ): ServiceResult {
        if ($approval === null || empty($approval->id)) {
            return new ServiceResult(false, 'Approval not found.');
        }

        $approvalId = (int)$approval->id;
        $approverConfig = ApprovalsGridColumns::normalizeApproverConfig($approval->approver_config);
        $isFeedbackResponse = !empty($approverConfig['feedback_response']);
        $feedbackRecorded = false;

        $result = $this->getApprovalManager()->recordResponse(
            $approvalId,
            $memberId,
            $decision,
            $comment,
            $nextApproverId,
        );

        if (!$result->isSuccess() && $isFeedbackResponse && $result->getError() === 'Approval is no longer pending.') {
            $result = $feedbackService->recordFeedbackFromApproval($approvalId, $memberId, $comment);
            $feedbackRecorded = $result->isSuccess();
        }

        if ($result->isSuccess() && $isFeedbackResponse && !$feedbackRecorded) {
            $feedbackResult = $feedbackService->recordFeedbackFromApproval($approvalId, $memberId, $comment);
            if (!$feedbackResult->isSuccess()) {
                return $feedbackResult;
            }
        }

        return $result;
    }

    /**
     * Apply workflow side effects after a successful approval response.
     */
    private function handleApprovalResponseSideEffects(
        ServiceResult $result,
        int $memberId,
        string $decision,
        mixed $comment,
        ?int $bestowalGatheringId = null,
    ): void {
        $data = $result->getData();
        if (!is_array($data)) {
            return;
        }

        $resumeData = [
            'approval' => $data,
            'approverId' => $memberId,
            'decision' => $decision,
            'comment' => $comment,
        ];
        $intermediateData = [
            'approverId' => $memberId,
            'decision' => $decision,
            'comment' => $comment ?? null,
            'nextApproverId' => $data['nextApproverId'] ?? null,
        ];
        if ($bestowalGatheringId !== null) {
            $resumeData['bestowalGatheringId'] = $bestowalGatheringId;
            $intermediateData['bestowalGatheringId'] = $bestowalGatheringId;
        }

        if (in_array($data['approvalStatus'] ?? '', ['approved', 'rejected'], true)) {
            $outputPort = $data['approvalStatus'] === 'approved' ? 'approved' : 'rejected';
            $this->getWorkflowEngine()->resumeWorkflow(
                $data['instanceId'],
                $data['nodeId'],
                $outputPort,
                $resumeData,
            );
        }

        if (!empty($data['needsMore'])) {
            $this->getWorkflowEngine()->fireIntermediateApprovalActions(
                $data['instanceId'],
                $data['nodeId'],
                $intermediateData,
            );
        }
    }

    /**
     * Return a JSON-safe payload the bulk modal can reuse from the first selected approval.
     *
     * @return array<string, mixed>
     */
    private function getApprovalResponseModalPayload(WorkflowApproval $approval): array
    {
        return [
            'id' => (int)$approval->id,
            'approver_config' => $this->augmentApproverConfigForResponse(
                ApprovalsGridColumns::normalizeApproverConfig($approval->approver_config),
                $approval,
            ),
            'required_count' => (int)$approval->required_count,
            'approved_count' => (int)$approval->approved_count,
            'bulk_type_key' => $this->getApprovalResponseTypeKey($approval),
        ];
    }

    /**
     * Hash the modal-affecting approval configuration so bulk responses cannot mix types.
     */
    private function getApprovalResponseTypeKey(WorkflowApproval $approval): string
    {
        $approverConfig = ApprovalsGridColumns::normalizeApproverConfig($approval->approver_config);
        $isFeedbackResponse = !empty($approverConfig['feedback_response']);
        $typeConfig = [
            'approver_type' => (string)$approval->approver_type,
            'feedback_response' => $isFeedbackResponse,
            'serial_pick_next' => !empty($approverConfig['serial_pick_next']),
            'requires_comment' => !empty($approverConfig['requires_comment']),
            'hide_reject' => $isFeedbackResponse || !empty($approverConfig['hide_reject']),
            'approve_label' => (string)($approverConfig['approve_label'] ?? ''),
            'response_label' => (string)($approverConfig['response_label'] ?? ''),
            'comment_warning' => (string)($approverConfig['comment_warning'] ?? ''),
            'decision_prompt_label' => (string)($approverConfig['decision_prompt_label'] ?? ''),
            'decision_options' => WorkflowApprovalDecisionOptions::normalizeOptions($approverConfig),
            self::BESTOWAL_GATHERING_REQUIRED_KEY => $this->approvalRequiresBestowalGatheringSelection(
                $approval,
                $approverConfig,
            ),
        ];

        return hash('sha256', json_encode($typeConfig, JSON_UNESCAPED_SLASHES));
    }

    /**
     * @param array<string, mixed> $approverConfig Approval config.
     * @return array<string, mixed>
     */
    private function augmentApproverConfigForResponse(array $approverConfig, ?WorkflowApproval $approval = null): array
    {
        if (!$this->approvalRequiresBestowalGatheringSelection($approval, $approverConfig)) {
            unset(
                $approverConfig[self::BESTOWAL_GATHERING_REQUIRED_KEY],
                $approverConfig['requiresBestowalGathering'],
                $approverConfig['bestowal_gathering_options'],
                $approverConfig['bestowalGatheringOptions'],
                $approverConfig['bestowal_gathering_url'],
                $approverConfig['bestowalGatheringUrl'],
            );

            return $approverConfig;
        }

        $approverConfig[self::BESTOWAL_GATHERING_REQUIRED_KEY] = true;
        $approverConfig['bestowal_gathering_options'] = $this->getBestowalGatheringOptions();
        $recommendationId = $this->getAwardsRecommendationIdForApproval($approval);
        if ($recommendationId !== null) {
            $lookupUrl = Router::url([
                'plugin' => 'Awards',
                'controller' => 'Bestowals',
                'action' => 'gatheringsForBestowalAutoComplete',
                '?' => ['recommendation_id' => $recommendationId],
            ]);
            $approverConfig['bestowal_gathering_url'] = $lookupUrl;
            $approverConfig['bestowalGatheringUrl'] = $lookupUrl;
        }

        return $approverConfig;
    }

    /**
     * Resolve the Awards recommendation context for approval responses, when present.
     *
     * @param \App\Model\Entity\WorkflowApproval|null $approval Approval entity.
     * @return int|null
     */
    private function getAwardsRecommendationIdForApproval(?WorkflowApproval $approval): ?int
    {
        if ($approval === null || empty($approval->workflow_instance_id)) {
            return null;
        }

        $run = TableRegistry::getTableLocator()->get('Awards.RecommendationApprovalRuns')->find()
            ->select(['recommendation_id'])
            ->where(['workflow_instance_id' => (int)$approval->workflow_instance_id])
            ->first();
        $recommendationId = (int)($run?->recommendation_id ?? 0);

        return $recommendationId > 0 ? $recommendationId : null;
    }

    /**
     * @param array<string, mixed> $approverConfig Approval config.
     * @return bool
     */
    private function requiresBestowalGatheringSelection(array $approverConfig): bool
    {
        return !empty($approverConfig[self::BESTOWAL_GATHERING_REQUIRED_KEY])
            || !empty($approverConfig['requiresBestowalGathering']);
    }

    /**
     * Determine whether this approval creates an award bestowal that must be scheduled to a gathering.
     *
     * Older pending approvals may have been created before the workflow node included
     * requires_bestowal_gathering, so the workflow slug is the compatibility fallback.
     *
     * @param \App\Model\Entity\WorkflowApproval|null $approval Approval.
     * @param array<string, mixed> $approverConfig Approval config.
     * @return bool
     */
    private function approvalRequiresBestowalGatheringSelection(
        ?WorkflowApproval $approval,
        array $approverConfig,
    ): bool {
        $finalStepState = $approval !== null
            ? $this->awardApprovalFinalStepState($approval, $approverConfig)
            : null;
        if ($finalStepState === false) {
            return false;
        }

        if ($this->requiresBestowalGatheringSelection($approverConfig)) {
            return true;
        }

        $slug = (string)($approval?->workflow_instance?->workflow_definition?->slug ?? '');

        return $finalStepState === true && in_array($slug, self::BESTOWAL_GATHERING_WORKFLOW_SLUGS, true);
    }

    /**
     * @param \App\Model\Entity\WorkflowApproval $approval Approval.
     * @param array<string, mixed> $approverConfig Approval config.
     * @return bool|null
     */
    private function awardApprovalFinalStepState(WorkflowApproval $approval, array $approverConfig): ?bool
    {
        $isAwardRecommendationWorkflow = in_array(
            (string)($approval->workflow_instance?->workflow_definition?->slug ?? ''),
            self::BESTOWAL_GATHERING_WORKFLOW_SLUGS,
            true,
        );
        if (
            !$isAwardRecommendationWorkflow
            && empty($approverConfig['award_approval_run_id'])
            && empty($approverConfig['award_approval_step_key'])
            && !array_key_exists('award_approval_is_final_step', $approverConfig)
        ) {
            return null;
        }

        return (new RecommendationApprovalProcessService())->isFinalApprovalStep($approval, $approverConfig);
    }

    /**
     * Read the optional approval-selected bestowal gathering ID from the posted form data.
     *
     * @return int|null
     */
    private function getPostedBestowalGatheringId(): ?int
    {
        $rawId = $this->request->getData('bestowal_gathering_id') ?? $this->request->getData('gathering_id');
        $gatheringId = (int)$rawId;

        return $gatheringId > 0 ? $gatheringId : null;
    }

    /**
     * Validate the gathering selection for approval types that create scheduled bestowals.
     *
     * @param \App\Model\Entity\WorkflowApproval|null $approval Approval being answered.
     * @param string $decision Submitted decision.
     * @param int|null $gatheringId Selected gathering ID.
     * @return string|null Error message when invalid.
     */
    private function validateBestowalGatheringSelection(
        ?WorkflowApproval $approval,
        string $decision,
        ?int $gatheringId,
    ): ?string {
        if ($approval === null || $decision !== WorkflowApprovalResponse::DECISION_APPROVE) {
            return null;
        }

        $approverConfig = ApprovalsGridColumns::normalizeApproverConfig($approval->approver_config);
        if (!$this->approvalRequiresBestowalGatheringSelection($approval, $approverConfig)) {
            return null;
        }

        if ($gatheringId === null) {
            return null;
        }

        if (!$this->isSelectableBestowalGathering($gatheringId)) {
            return (string)__('Select a valid, future gathering for the bestowal.');
        }

        return null;
    }

    /**
     * Check that a selected bestowal gathering is still available for scheduling.
     *
     * @param int $gatheringId Gathering ID.
     * @return bool
     */
    private function isSelectableBestowalGathering(int $gatheringId): bool
    {
        return $this->fetchTable('Gatherings')->exists([
            'Gatherings.id' => $gatheringId,
            'Gatherings.deleted IS' => null,
            'Gatherings.cancelled_at IS' => null,
            'Gatherings.start_date >' => DateTime::now(),
        ]);
    }

    /**
     * @return array<int, array{id:int,label:string}>
     */
    private function getBestowalGatheringOptions(): array
    {
        if ($this->bestowalGatheringOptions !== null) {
            return $this->bestowalGatheringOptions;
        }

        $gatherings = $this->fetchTable('Gatherings')->find()
            ->select(['id', 'name', 'start_date'])
            ->where([
                'Gatherings.deleted IS' => null,
                'Gatherings.cancelled_at IS' => null,
                'Gatherings.start_date >' => DateTime::now(),
            ])
            ->orderBy(['Gatherings.start_date' => 'ASC', 'Gatherings.name' => 'ASC'])
            ->limit(100)
            ->all();

        $options = [];
        foreach ($gatherings as $gathering) {
            $dateLabel = $gathering->start_date ? TimezoneHelper::formatDate($gathering->start_date) : '';
            $options[] = [
                'id' => (int)$gathering->id,
                'label' => trim((string)$gathering->name . ($dateLabel !== '' ? ' - ' . $dateLabel : '')),
            ];
        }

        $this->bestowalGatheringOptions = $options;

        return $this->bestowalGatheringOptions;
    }

    /**
     * @param mixed $rawApprovalIds Posted approval ID list.
     * @return array<int>
     */
    private function parseBulkApprovalIds(mixed $rawApprovalIds): array
    {
        if (is_string($rawApprovalIds)) {
            $rawApprovalIds = explode(',', $rawApprovalIds);
        }
        if (!is_array($rawApprovalIds)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map('intval', $rawApprovalIds))));
    }

    /**
     * Return a consistent failure response for single and bulk approval modal posts.
     *
     * @return \Cake\Http\Response|null|void
     */
    private function approvalResponseFailure(string $error)
    {
        if ($this->request->is('ajax') && !$this->wantsTurboStreamRequest()) {
            $this->set('result', ['success' => false, 'error' => $error]);
            $this->viewBuilder()->setOption('serialize', 'result');
            $this->response = $this->response->withType('application/json');
            $this->viewBuilder()->setClassName('Json');

            return;
        }

        $this->Flash->error($error);

        return $this->redirect(['action' => 'approvals']);
    }

    /**
     * API: Get approval detail context for the expandable panel.
     *
     * @param int $approvalId Workflow approval ID
     * @return \Cake\Http\Response|null|void
     */
    public function approvalDetail(int $approvalId)
    {
        $this->request->allowMethod(['get']);
        $this->Authorization->skipAuthorization();

        $approvalsTable = $this->fetchTable('WorkflowApprovals');
        $approval = $approvalsTable->find()
            ->contain([
                'WorkflowInstances' => ['WorkflowDefinitions'],
                'WorkflowApprovalResponses' => [
                    'Members',
                    'sort' => ['WorkflowApprovalResponses.responded_at' => 'ASC'],
                ],
            ])
            ->where(['WorkflowApprovals.id' => $approvalId])
            ->first();

        if (!$approval) {
            $this->response = $this->response
                ->withType('application/json')
                ->withStatus(404)
                ->withStringBody(json_encode(['error' => 'Approval not found']));

            return $this->response;
        }

        $instance = $approval->workflow_instance;
        $ctx = $instance
            ? ApprovalContextRendererRegistry::render($instance)
            : null;

        $context = $ctx ? $ctx->toArray() : [
            'title' => __('Unknown Approval'),
            'description' => '',
            'fields' => [],
            'entityUrl' => null,
            'icon' => 'bi-question-circle',
            'requester' => null,
        ];

        $progress = [
            'approvalId' => (int)$approval->id,
            'required' => $approval->required_count,
            'approved' => $approval->approved_count,
            'rejected' => $approval->rejected_count,
            'status' => $approval->status,
        ];
        $approverConfig = ApprovalsGridColumns::normalizeApproverConfig($approval->approver_config);
        $isFeedbackResponse = !empty($approverConfig['feedback_response']);
        $decisionOptions = WorkflowApprovalDecisionOptions::normalizeOptions($approverConfig);
        $currentUser = $this->request->getAttribute('identity');
        $canTriage = $currentUser
            && $approval->status === WorkflowApproval::STATUS_PENDING
            && $this->isApprovalPendingForMember((int)$approval->id, (int)$currentUser->id);

        $responses = [];
        if (!empty($approval->workflow_approval_responses)) {
            foreach ($approval->workflow_approval_responses as $resp) {
                $memberName = $resp->member->sca_name
                    ?? $resp->member->email_address
                    ?? __('Unknown');
                $responses[] = [
                    'memberName' => $memberName,
                    'decision' => $resp->decision,
                    'decisionLabel' => WorkflowApprovalDecisionOptions::labelForDecision(
                        (string)$resp->decision,
                        $approverConfig,
                    ),
                    'comment' => $resp->comment,
                    'respondedAt' => TimezoneHelper::formatDateTime($resp->responded_at),
                ];
            }
        }

        $payload = [
            'approvalId' => (int)$approval->id,
            'context' => $context,
            'progress' => $progress,
            'responses' => $responses,
            'ui' => [
                'feedbackResponse' => $isFeedbackResponse,
                'hideProgress' => $isFeedbackResponse,
                'decisionOptions' => $decisionOptions,
                'canTriage' => $canTriage,
            ],
            'triage' => $canTriage
                ? $this->getTriagePayload((int)$approval->id, (int)$currentUser->id)
                : null,
        ];

        $this->response = $this->response
            ->withType('application/json')
            ->withStringBody(json_encode($payload));

        return $this->response;
    }

    /**
     * @param int $approvalId Workflow approval ID
     * @param int $memberId Member ID
     * @return bool
     */
    private function isApprovalPendingForMember(int $approvalId, int $memberId): bool
    {
        return WorkflowApprovalsTable::isPendingApprovalForMember($approvalId, $memberId);
    }

    /**
     * @param int $approvalId Workflow approval ID
     * @param int $memberId Member ID
     * @return array<string, mixed>
     */
    private function getTriagePayload(int $approvalId, int $memberId): array
    {
        $triage = $this->fetchTable('WorkflowApprovalTriageStates')->find()
            ->where([
                'workflow_approval_id' => $approvalId,
                'member_id' => $memberId,
            ])
            ->first();

        if (!$triage) {
            return $this->defaultTriagePayload();
        }

        return $this->formatTriagePayload($triage);
    }

    /**
     * @param array<int> $approvalIds Workflow approval IDs
     * @param int $memberId Member ID
     * @return array<int, array<string, mixed>>
     */
    private function getTriagePayloads(array $approvalIds, int $memberId): array
    {
        $approvalIds = array_values(array_unique(array_filter(array_map('intval', $approvalIds))));
        if ($approvalIds === []) {
            return [];
        }

        $triageRows = $this->fetchTable('WorkflowApprovalTriageStates')->find()
            ->where([
                'workflow_approval_id IN' => $approvalIds,
                'member_id' => $memberId,
            ])
            ->all();

        $payloads = [];
        foreach ($triageRows as $triage) {
            $payloads[(int)$triage->get('workflow_approval_id')] = $this->formatTriagePayload($triage);
        }

        return $payloads;
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultTriagePayload(): array
    {
        $labels = WorkflowApprovalTriageState::labels();

        return [
            'state' => WorkflowApprovalTriageState::STATE_NEW,
            'stateLabel' => $labels[WorkflowApprovalTriageState::STATE_NEW],
            'note' => '',
            'states' => $labels,
        ];
    }

    /**
     * @param \Cake\Datasource\EntityInterface $triage Triage entity
     * @return array<string, mixed>
     */
    private function formatTriagePayload($triage): array
    {
        $labels = WorkflowApprovalTriageState::labels();
        $state = (string)$triage->get('state');

        return [
            'state' => $state,
            'stateLabel' => $labels[$state] ?? $state,
            'note' => (string)($triage->get('note') ?? ''),
            'states' => $labels,
            'modified' => $triage->get('modified')?->toIso8601String(),
        ];
    }

    /**
     * @param array<string, mixed> $errors Entity validation/rule errors
     * @return string
     */
    private function formatValidationErrors(array $errors): string
    {
        $messages = [];
        foreach ($errors as $field => $fieldErrors) {
            foreach ((array)$fieldErrors as $error) {
                if (is_array($error)) {
                    foreach ($error as $nestedError) {
                        $messages[] = sprintf('%s: %s', $field, (string)$nestedError);
                    }
                    continue;
                }
                $messages[] = sprintf('%s: %s', $field, (string)$error);
            }
        }

        return implode(', ', $messages);
    }

    /**
     * @param array<string, mixed> $payload JSON payload
     * @param int $status HTTP status
     * @return \Cake\Http\Response
     */
    private function jsonResponse(array $payload, int $status = 200): Response
    {
        return $this->response
            ->withType('application/json')
            ->withStatus($status)
            ->withStringBody(json_encode($payload));
    }

    /**
     * API: Return eligible approvers for a serial-pick-next workflow approval.
     *
     * @param int $approvalId Workflow approval ID
     * @return \Cake\Http\Response|null|void
     */
    public function eligibleApprovers(int $approvalId)
    {
        $this->request->allowMethod(['get']);
        $this->Authorization->skipAuthorization();

        $q = $this->request->getQuery('q') ?? '';
        $context = $this->request->getQuery('context') ?? '';
        $currentUser = $this->request->getAttribute('identity');

        $approvalManager = $this->getApprovalManager();

        if ($context === 'reassign') {
            $eligibleMembers = $approvalManager->getEligibleApprovers($approvalId);
            $eligible = array_map(fn($m) => $m->id, $eligibleMembers);
        } else {
            $eligible = $approvalManager->getNextApproverCandidates($approvalId, $currentUser?->id);
        }

        $html = '';
        if (!empty($eligible)) {
            $membersTable = $this->fetchTable('Members');
            $query = $membersTable->find()
                ->contain(['Branches'])
                ->where(['Members.id IN' => $eligible])
                ->orderBy(['Branches.name', 'Members.sca_name']);

            if ($q !== '') {
                $query->where(['Members.sca_name LIKE' => "%{$q}%"]);
            }

            foreach ($query->all() as $member) {
                $branchName = $member->branch->name ?? '';
                $displayName = $branchName ? $branchName . ': ' . $member->sca_name : $member->sca_name;
                $highlighted = $q !== ''
                    ? preg_replace(
                        '/(' . preg_quote($q, '/') . ')/i',
                        '<span class="text-primary">$1</span>',
                        h($displayName),
                    )
                    : h($displayName);
                $html .= '<li class="list-group-item" role="option" data-ac-value="' . h($member->id) . '">'
                    . $highlighted
                    . '</li>';
            }
        }

        $this->response = $this->response
            ->withType('text/html')
            ->withStringBody($html);

        return $this->response;
    }

    /**
     * Admin API: Reassign a pending approval to a different eligible member.
     *
     * @return \Cake\Http\Response|null|void
     */
    public function reassignApproval()
    {
        $this->request->allowMethod(['post']);
        $approvalId = (int)$this->request->getData('approvalId');
        $newApproverId = (int)$this->request->getData('newApproverId');
        $reason = $this->request->getData('reason');
        $currentUser = $this->request->getAttribute('identity');

        if (!$approvalId || !$newApproverId) {
            $error = __('Approval ID and new approver are required.');
            if ($this->request->is('ajax')) {
                $this->set('result', ['success' => false, 'error' => $error]);
                $this->viewBuilder()->setOption('serialize', 'result');
                $this->response = $this->response->withType('application/json');
                $this->viewBuilder()->setClassName('Json');

                return;
            }
            $this->Flash->error($error);

            return $this->redirect(['action' => 'allApprovals']);
        }

        $approvalManager = $this->getApprovalManager();
        $result = $approvalManager->reassignApproval($approvalId, $newApproverId, $currentUser->id, $reason);

        if ($result->isSuccess() && $result->getData()) {
            $data = $result->getData();
            $engine = $this->getWorkflowEngine();
            $engine->fireIntermediateApprovalActions(
                $data['instanceId'],
                $data['nodeId'],
                [
                    'previousApproverId' => $data['previousApproverId'],
                    'newApproverId' => $data['newApproverId'],
                    'reassignedBy' => $currentUser->id,
                    'reason' => $reason,
                ],
                'on_reassigned',
            );
        }

        if ($this->request->is('ajax')) {
            $this->set('result', $result);
            $this->viewBuilder()->setOption('serialize', 'result');
            $this->response = $this->response->withType('application/json');
            $this->viewBuilder()->setClassName('Json');
        } else {
            if ($result->isSuccess()) {
                $this->Flash->success(__('Approval reassigned successfully.'));
            } else {
                $this->Flash->error($result->getError());
            }

            return $this->redirect(['action' => 'allApprovals']);
        }
    }

    /**
     * Resolve entity details from workflow instance for display.
     */
    private function resolveEntityContext(WorkflowInstance $instance): array
    {
        $details = [
            'entityType' => $instance->entity_type,
            'entityId' => $instance->entity_id,
            'startedBy' => null,
            'entityName' => null,
        ];

        if ($instance->started_by) {
            $member = $this->fetchTable('Members')->find()
                ->where(['id' => $instance->started_by])
                ->first();
            if ($member) {
                $details['startedBy'] = $member->sca_name ?? $member->email_address;
            }
        }

        if ($instance->entity_type && $instance->entity_id) {
            try {
                $table = TableRegistry::getTableLocator()->get($instance->entity_type);
                $entity = $table->find()->where(['id' => $instance->entity_id])->first();
                if ($entity) {
                    $details['entityName'] = $entity->name ?? $entity->sca_name ?? "#{$instance->entity_id}";
                }
            } catch (Exception $e) {
                // Table doesn't exist or other error — skip
            }
        }

        return $details;
    }

    /**
     * Get the injected workflow engine.
     *
     * @return \App\Services\WorkflowEngine\WorkflowEngineInterface
     */
    private function getWorkflowEngine(): WorkflowEngineInterface
    {
        return $this->engine;
    }

    /**
     * Get the injected approval manager.
     *
     * @return \App\Services\WorkflowEngine\WorkflowApprovalManagerInterface
     */
    private function getApprovalManager(): WorkflowApprovalManagerInterface
    {
        return $this->approvalManager;
    }

    /**
     * @return array{tableFrameId: string, gridKey: string}|null
     */
    private function resolveApprovalsGridSyncContext(?string $pageContextUrl): ?array
    {
        if ($pageContextUrl === null) {
            return null;
        }

        if ($this->matchesGridIndexPath($pageContextUrl, '#/approvals/?$#')) {
            return [
                'tableFrameId' => 'approvals-grid-table',
                'gridKey' => 'Workflows.approvals.main',
            ];
        }

        if ($this->matchesGridIndexPath($pageContextUrl, '#/approvals/all/?$#')) {
            return [
                'tableFrameId' => 'all-approvals-grid-table',
                'gridKey' => 'Workflows.approvals.admin',
            ];
        }

        return null;
    }

    /**
     * Render a Turbo Stream refresh for the current approvals grid table.
     */
    private function tryApprovalsGridTurboResponse(?string $pageContext): ?Response
    {
        if (!$this->wantsTurboStreamRequest() || $pageContext === null) {
            return null;
        }

        $syncContext = $this->resolveApprovalsGridSyncContext($pageContext);
        if ($syncContext === null) {
            return null;
        }

        $gridRoute = $syncContext['tableFrameId'] === 'all-approvals-grid-table'
            ? ['controller' => 'Approvals', 'action' => 'allApprovalsGridData']
            : ['controller' => 'Approvals', 'action' => 'approvalsGridData'];

        return $this->renderTurboCloseModal($syncContext['tableFrameId'], $gridRoute, $pageContext);
    }
}
