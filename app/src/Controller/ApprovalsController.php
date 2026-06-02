<?php

declare(strict_types=1);

namespace App\Controller;

use App\KMP\GridColumns\ApprovalsGridColumns;
use App\KMP\TimezoneHelper;
use App\Model\Entity\WorkflowApproval;
use App\Model\Entity\WorkflowInstance;
use App\Services\ApprovalContext\ApprovalContextRendererRegistry;
use App\Services\WorkflowEngine\WorkflowApprovalManagerInterface;
use App\Services\WorkflowEngine\WorkflowEngineInterface;
use Awards\Services\RecommendationFeedbackService;
use Cake\Controller\ComponentRegistry;
use Cake\Http\Response;
use Cake\Http\ServerRequest;

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

    protected ?string $defaultTable = 'WorkflowApprovals';

    private WorkflowEngineInterface $engine;
    private WorkflowApprovalManagerInterface $approvalManager;

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

    public function initialize(): void
    {
        parent::initialize();
        $this->Authorization->authorizeModel(
            'approvals',
            'recordApproval',
            'allApprovals',
            'allApprovalsGridData',
            'reassignApproval',
            'mobileApprovals',
            'mobileApprovalsData',
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
        $this->set('mobileTitle', 'Approvals');
        $this->set('mobileSection', 'approvals');
        $this->set('mobileIcon', 'bi-check2-square');

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
        $approvalManager = $this->getApprovalManager();
        $eligible = $approvalManager->getPendingApprovalsForMember($currentUser->id);

        $approvals = [];
        foreach ($eligible as $approval) {
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

            $approverConfig = is_string($approval->approver_config)
                ? json_decode($approval->approver_config, true)
                : ($approval->approver_config ?? []);
            $isFeedbackResponse = !empty($approverConfig['feedback_response']);

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
                'approverConfig' => [
                    'serialPickNext' => $approverConfig['serial_pick_next'] ?? false,
                    'feedbackResponse' => $isFeedbackResponse,
                    'hideProgress' => $isFeedbackResponse,
                    'commentWarning' => $approverConfig['comment_warning'] ?? '',
                ],
                'modified' => $approval->modified ? $approval->modified->toIso8601String() : null,
            ];
        }

        $this->response = $this->response
            ->withType('application/json')
            ->withStringBody(json_encode(['approvals' => $approvals]));

        return $this->response;
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
        $approvalManager = $this->getApprovalManager();
        $approvalsTable = $this->fetchTable('WorkflowApprovals');
        $systemViews = ApprovalsGridColumns::getSystemViews();
        $queryContext = $this->resolveDataverseGridQueryContext([
            'gridKey' => 'Workflows.approvals.main',
            'gridColumnsClass' => ApprovalsGridColumns::class,
            'systemViews' => $systemViews,
            'defaultSystemView' => 'sys-approvals-pending',
            'defaultSort' => ['WorkflowApprovals.modified' => 'desc'],
        ]);
        $contain = [];
        if ($queryContext->loadsAny(['workflow_name', 'request', 'requester'])) {
            $contain['WorkflowInstances'] = ['WorkflowDefinitions'];
        }
        if ($queryContext->loadsColumn('current_approver')) {
            $contain['CurrentApprover'] = function ($q) {
                return $q->select(['id', 'sca_name']);
            };
        }

        $baseQuery = $approvalsTable->find()->contain($contain);
        if ($queryContext->loadsColumn('current_approver')) {
            $baseQuery->leftJoinWith('CurrentApprover');
        }

        $queryCallback = function ($query, $systemView) use ($currentUser, $approvalManager) {
            if ($systemView === null) {
                $query->where(['WorkflowApprovals.id' => -1]);
                return $query;
            }

            if ($systemView['id'] === 'sys-approvals-pending') {
                $eligible = $approvalManager->getPendingApprovalsForMember($currentUser->id);
                $eligibleIds = array_map(fn($a) => $a->id, $eligible);

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
            'defaultSort' => ['WorkflowApprovals.modified' => 'desc'],
            'defaultPageSize' => 25,
            'systemViews' => $systemViews,
            'defaultSystemView' => 'sys-approvals-pending',
            'queryCallback' => $queryCallback,
            'showAllTab' => false,
            'canAddViews' => false,
            'canFilter' => true,
            'canExportCsv' => false,
            'lockedFilters' => ['status_label'],
            'showFilterPills' => true,
            'showSearchBox' => true,
        ]);

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
        $queryContext = $this->resolveDataverseGridQueryContext([
            'gridKey' => 'Workflows.allApprovals.main',
            'gridColumnsClass' => ApprovalsGridColumns::class,
            'systemViews' => $systemViews,
            'defaultSystemView' => 'sys-admin-pending',
            'defaultSort' => ['WorkflowApprovals.modified' => 'desc'],
        ]);
        $contain = [];
        if ($queryContext->loadsAny(['workflow_name', 'request', 'requester'])) {
            $contain['WorkflowInstances'] = ['WorkflowDefinitions'];
        }
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
            $isFeedbackResponse = !empty($approverConfig['feedback_response']);
            $approval->is_feedback_response = $isFeedbackResponse;
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
                $instance = $approval->workflow_instance;
                if ($instance) {
                    $ctx = ApprovalContextRendererRegistry::render($instance);
                    if ($includeRequest) {
                        $approval->request = $ctx->getTitle();
                    }
                    if ($includeRequester) {
                        $approval->requester = $ctx->getRequester() ?? '—';
                    }
                } else {
                    if ($includeRequest) {
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
     * API: Record an approval response and optionally resume workflow.
     *
     * @return \Cake\Http\Response|null|void
     */
    public function recordApproval(RecommendationFeedbackService $feedbackService)
    {
        $this->request->allowMethod(['post']);
        $approvalId = (int)$this->request->getData('approvalId');
        $decision = $this->request->getData('decision');
        $comment = $this->request->getData('comment');
        $nextApproverId = $this->request->getData('next_approver_id') ? (int)$this->request->getData('next_approver_id') : null;
        $currentUser = $this->request->getAttribute('identity');
        $approval = $this->fetchTable('WorkflowApprovals')->find()
            ->where(['WorkflowApprovals.id' => $approvalId])
            ->first();
        $approverConfig = $approval?->approver_config ?? [];
        $isFeedbackResponse = !empty($approverConfig['feedback_response']);
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

        $approvalManager = $this->getApprovalManager();
        $result = $approvalManager->recordResponse(
            $approvalId,
            $currentUser->id,
            $decision,
            $comment,
            $nextApproverId,
        );
        $feedbackRecorded = false;

        if (!$result->isSuccess() && $isFeedbackResponse && $result->getError() === 'Approval is no longer pending.') {
            $result = $feedbackService->recordFeedbackFromApproval(
                $approvalId,
                (int)$currentUser->id,
                (string)$comment,
            );
            $feedbackRecorded = $result->isSuccess();
        }

        if ($result->isSuccess() && $isFeedbackResponse && !$feedbackRecorded) {
            $feedbackResult = $feedbackService->recordFeedbackFromApproval(
                $approvalId,
                (int)$currentUser->id,
                (string)$comment,
            );
            if (!$feedbackResult->isSuccess()) {
                $result = $feedbackResult;
            }
        } elseif ($result->isSuccess() && $result->getData()) {
            $data = $result->getData();
            if (in_array($data['approvalStatus'] ?? '', ['approved', 'rejected'])) {
                $engine = $this->getWorkflowEngine();
                $outputPort = $data['approvalStatus'] === 'approved' ? 'approved' : 'rejected';
                $engine->resumeWorkflow(
                    $data['instanceId'],
                    $data['nodeId'],
                    $outputPort,
                    [
                        'approval' => $data,
                        'approverId' => $currentUser->id,
                        'decision' => $decision,
                        'comment' => $comment,
                    ]
                );
            }

            if (!empty($data['needsMore'])) {
                $engine = $this->getWorkflowEngine();
                $engine->fireIntermediateApprovalActions(
                    $data['instanceId'],
                    $data['nodeId'],
                    [
                        'approverId' => $currentUser->id,
                        'decision' => $decision,
                        'comment' => $comment ?? null,
                        'nextApproverId' => $data['nextApproverId'] ?? null,
                    ]
                );
            }
        }

        if ($this->request->is('ajax') && !$this->wantsTurboStreamRequest()) {
            $this->set('result', $result);
            $this->viewBuilder()->setOption('serialize', 'result');
            $this->response = $this->response->withType('application/json');
            $this->viewBuilder()->setClassName('Json');
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
            'required' => $approval->required_count,
            'approved' => $approval->approved_count,
            'rejected' => $approval->rejected_count,
            'status' => $approval->status,
        ];
        $approverConfig = ApprovalsGridColumns::normalizeApproverConfig($approval->approver_config);
        $isFeedbackResponse = !empty($approverConfig['feedback_response']);

        $responses = [];
        if (!empty($approval->workflow_approval_responses)) {
            foreach ($approval->workflow_approval_responses as $resp) {
                $memberName = $resp->member->sca_name
                    ?? $resp->member->email_address
                    ?? __('Unknown');
                $responses[] = [
                    'memberName' => $memberName,
                    'decision' => $resp->decision,
                    'comment' => $resp->comment,
                    'respondedAt' => TimezoneHelper::formatDateTime($resp->responded_at),
                ];
            }
        }

        $payload = [
            'context' => $context,
            'progress' => $progress,
            'responses' => $responses,
            'ui' => [
                'feedbackResponse' => $isFeedbackResponse,
                'hideProgress' => $isFeedbackResponse,
            ],
        ];

        $this->response = $this->response
            ->withType('application/json')
            ->withStringBody(json_encode($payload));

        return $this->response;
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
                $highlighted = $q !== '' ?
                    preg_replace('/(' . preg_quote($q, '/') . ')/i', '<span class="text-primary">$1</span>', h($displayName)) :
                    h($displayName);
                $html .= '<li class="list-group-item" role="option" data-ac-value="' . h($member->id) . '">' . $highlighted . '</li>';
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
                'on_reassigned'
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
                $table = \Cake\ORM\TableRegistry::getTableLocator()->get($instance->entity_type);
                $entity = $table->find()->where(['id' => $instance->entity_id])->first();
                if ($entity) {
                    $details['entityName'] = $entity->name ?? $entity->sca_name ?? "#{$instance->entity_id}";
                }
            } catch (\Exception $e) {
                // Table doesn't exist or other error — skip
            }
        }

        return $details;
    }

    private function getWorkflowEngine(): WorkflowEngineInterface
    {
        return $this->engine;
    }

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
