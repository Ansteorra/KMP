<?php

declare(strict_types=1);

namespace Officers\Controller;

use App\Controller\DataverseGridTrait;
use App\Controller\WorkflowDispatchTrait;
use App\KMP\DataverseGridQueryContext;
use App\KMP\CaseInsensitiveQuery;
use App\KMP\GridRowDomId;
use Cake\Http\Response;
use Cake\ORM\TableRegistry;
use Cake\Routing\Router;
use App\Model\Entity\Member;
use App\Model\Entity\Warrant;
use App\Services\CsvExportService;
use App\Services\ServiceResult;
use App\Services\WarrantManager\WarrantManagerInterface;
use App\Services\WarrantManager\WarrantRequest;
use App\Services\WorkflowEngine\TriggerDispatcher;
use Cake\Http\Exception\ForbiddenException;
use Cake\Http\Exception\NotFoundException;
use Cake\I18n\DateTime;
use Cake\Log\Log;
use Officers\KMP\GridColumns\OfficersGridColumns;
use Officers\Model\Entity\Officer;
use Throwable;

/**
 * Officers Controller
 *
 * Manages officer assignment lifecycle including creation, modification,
 * release, and warrant integration.
 *
 * @property \Officers\Model\Table\OfficersTable $Officers
 */
class OfficersController extends AppController
{
    use DataverseGridTrait;
    use WorkflowDispatchTrait;

    /**
     * Initialize controller with authentication and authorization settings.
     *
     * @return void
     */
    public function initialize(): void
    {
        parent::initialize();
        $this->Authentication->addUnauthenticatedActions(['api']);
        $this->Authorization->authorizeModel('index');
    }

    /**
     * Assign an officer to an office position.
     *
     * @param \App\Services\WorkflowEngine\TriggerDispatcher $dispatcher Workflow trigger dispatcher
     * @return \Cake\Http\Response|null|void Redirects on completion or error
     */
    public function assign(TriggerDispatcher $dispatcher)
    {
        if ($this->request->is('post')) {
            $officer = $this->Officers->newEmptyEntity();
            $user = $this->Authentication->getIdentity();
            $branchId = (int)$this->request->getData('branch_id');
            $this->Authorization->authorize($officer);

            $memberId = (int)$this->request->getData('member_id');
            $officeId = (int)$this->request->getData('office_id');
            $branchId = (int)$this->request->getData('branch_id');
            $canHireOffices = $this->Officers->Offices->officesMemberCanWork($user, $branchId);
            if (!in_array($officeId, $canHireOffices)) {
                $this->Flash->error(__('You do not have permission to assign this officer.'));
                $this->redirect($this->referer());

                return;
            }
            $startOn = new DateTime($this->request->getData('start_on'));
            $emailAddress = $this->request->getData('email_address');
            $endOn = null;
            if ($this->request->getData('end_on') !== null && $this->request->getData('end_on') !== '') {
                $endOn = new DateTime($this->request->getData('end_on'));
            }
            $approverId = (int)$user->id;
            $deputyDescription = $this->request->getData('deputy_description');

            $context = [
                'memberId' => $memberId,
                'officeId' => $officeId,
                'branchId' => $branchId,
                'startOn' => $startOn->toDateTimeString(),
                'expiresOn' => $endOn?->toDateTimeString(),
                'deputyDescription' => $deputyDescription,
                'approverId' => $approverId,
                'emailAddress' => $emailAddress,
                'member_id' => $memberId,
                'office_id' => $officeId,
                'branch_id' => $branchId,
                'start_on' => $startOn->toDateTimeString(),
                'end_on' => $endOn?->toDateTimeString(),
                'deputy_description' => $deputyDescription,
                'approver_id' => $approverId,
                'email_address' => $emailAddress,
            ];

            try {
                $result = $this->dispatchWorkflowOrFail(
                    $dispatcher,
                    'officer-hire',
                    'Officers.HireRequested',
                    $context,
                );
                $workflowError = $this->extractWorkflowDispatchFailure(
                    $result,
                    'The officer assignment workflow could not be completed.',
                );
                if ($workflowError !== null) {
                    $this->Flash->error(__($workflowError));
                } else {
                    $this->Flash->success(__('The officer has been saved.'));
                }
            } catch (Throwable $e) {
                Log::error('Officer hire workflow dispatch failed: ' . $e->getMessage());
                $this->Flash->error(__('The officer assignment workflow is not currently available.'));
            }

            return $this->redirect($this->referer());
        }
    }

    /**
     * Release an officer from their assignment.
     *
     * @param \App\Services\WorkflowEngine\TriggerDispatcher $dispatcher Workflow trigger dispatcher
     * @return \Cake\Http\Response|null|void Redirects on completion or error
     * @throws \Cake\Http\Exception\NotFoundException When officer not found
     */
    public function release(TriggerDispatcher $dispatcher)
    {
        $officer = $this->Officers->get($this->request->getData('id'));
        if (!$officer) {
            throw new NotFoundException();
        }
        $this->Authorization->authorize($officer);
        if ($this->request->is('post')) {
            $revokeReason = $this->request->getData('revoked_reason');
            $revokeDate = new DateTime($this->request->getData('revoked_on'));
            $revokerId = $this->Authentication->getIdentity()->getIdentifier();

            $context = [
                'officerId' => $officer->id,
                'memberId' => $officer->member_id,
                'officeId' => $officer->office_id,
                'releasedById' => $revokerId,
                'reason' => $revokeReason,
                'expiresOn' => $revokeDate->toDateTimeString(),
                'releaseStatus' => Officer::RELEASED_STATUS,
                // Keep legacy-shaped keys during the migration window for older drafts/tests.
                'officer_id' => $officer->id,
                'released_by' => $revokerId,
                'revoked_on' => $revokeDate->toDateTimeString(),
            ];

            try {
                $result = $this->dispatchWorkflowOrFail($dispatcher, 'officers-release', 'Officers.Released', $context);
                $workflowError = $this->extractWorkflowDispatchFailure(
                    $result,
                    'The officer release workflow could not be completed.',
                );
                if ($workflowError !== null) {
                    $this->Flash->error(__($workflowError));
                } else {
                    $this->Flash->success(__('The officer release workflow has been initiated.'));
                }
            } catch (Throwable $e) {
                Log::error('Officer release workflow dispatch failed: ' . $e->getMessage());
                $this->Flash->error(__('The officer release workflow is not currently available.'));
            }

            return $this->redirect($this->referer());
        }
    }

    /**
     * Edit officer assignment details (deputy description, email).
     *
     * @return \Cake\Http\Response|null|void Redirects on completion
     * @throws \Cake\Http\Exception\NotFoundException When officer not found
     */
    public function edit()
    {
        $this->request->allowMethod(['post']);
        $officer = $this->Officers->get($this->request->getData('id'));
        if (!$officer) {
            throw new NotFoundException();
        }
        $this->Authorization->authorize($officer);
        $officer->deputy_description = $this->request->getData('deputy_description');
        $officer->email_address = $this->request->getData('email_address');
        if ($this->Officers->save($officer)) {
            $this->Flash->success(__('The officer has been saved.'));
            $stream = $this->tryOfficersGridTurboResponse(
                $this->getPageContextUrl(),
                (int)$officer->id,
            );
            if ($stream !== null) {
                return $stream;
            }
        } else {
            $this->Flash->error(__('The officer could not be saved. Please, try again.'));
        }

        return $this->redirect($this->referer());
    }

    /**
     * Request a warrant for an officer assignment.
     *
     * @param \App\Services\WarrantManager\WarrantManagerInterface $wManager Warrant management service
     * @param int $id Officer ID for warrant request
     * @return \Cake\Http\Response|null|void Redirects on completion or error
     * @throws \Cake\Http\Exception\NotFoundException When officer not found
     */
    public function requestWarrant(WarrantManagerInterface $wManager, $id)
    {
        $officer = $this->Officers->find()
            ->where(['Officers.id' => $id])
            ->contain(['Offices', 'Branches', 'Members'])
            ->first();
        $userid = $this->Authentication->getIdentity()->getIdentifier();
        if (!$officer) {
            throw new NotFoundException();
        }
        $this->Authorization->authorize($officer);
        if ($this->request->is('post')) {
            $officeName = $officer->office->name;
            if ($officer->deputy_description != null && $officer->deputy_description != '') {
                $officeName = $officeName . ' (' . $officer->deputy_description . ')';
            }
            $branchName = $officer->branch->name;
            $warrantRequest = new WarrantRequest(
                "Manual Request Warrant: $branchName - $officeName",
                'Officers.Officers',
                $officer->id,
                $userid,
                $officer->member_id,
                $officer->start_on,
                $officer->expires_on,
                $officer->granted_member_role_id,
            );
            $memberName = $officer->member->sca_name;

            $wmResult = $wManager->request("$officeName : $memberName", '', [$warrantRequest], (int)$userid);
            if (!$wmResult->success) {
                $this->Flash->error('Could not request Warrant: ' . __($wmResult->reason));

                return;
            }

            $this->Flash->success(__('The warrant request workflow has been initiated.'));
            $this->redirect($this->referer());

            return;
        }
    }

    /**
     * Extract the first workflow dispatch failure message from trigger results.
     *
     * @param array<int, mixed> $results Workflow dispatch results from TriggerDispatcher.
     * @param string $defaultMessage Fallback message when no explicit error is available.
     * @return string|null
     */
    private function extractWorkflowDispatchFailure(array $results, string $defaultMessage): ?string
    {
        if ($results === []) {
            return $defaultMessage;
        }

        foreach ($results as $result) {
            if ($result instanceof ServiceResult) {
                if (!$result->success) {
                    return $result->reason ?? $defaultMessage;
                }

                $workflowResult = is_array($result->data ?? null)
                    ? ($result->data['workflowResult'] ?? null)
                    : null;
                if (
                    is_array($workflowResult)
                    && array_key_exists('success', $workflowResult)
                    && $workflowResult['success'] === false
                ) {
                    return (string)($workflowResult['error'] ?? $workflowResult['reason'] ?? $defaultMessage);
                }

                continue;
            }

            if (
                is_array($result)
                && array_key_exists('success', $result)
                && $result['success'] === false
            ) {
                return (string)($result['error'] ?? $result['reason'] ?? $defaultMessage);
            }
        }

        return null;
    }

    /**
     * AJAX autocomplete for member search during officer assignment.
     *
     * Supports Þ/th character conversion and excludes deactivated members.
     *
     * @param int $officeId Office ID for assignment context
     * @return void
     */
    public function autoComplete($officeId)
    {
        //TODO: Audit for Privacy
        $memberTbl = $this->getTableLocator()->get('Members');
        $q = $this->request->getQuery('q');
        //detect th and replace with Þ
        $nq = $q;
        if (preg_match('/th/', $q)) {
            $nq = str_replace('th', 'Þ', $q);
        }
        //detect Þ and replace with th
        $uq = $q;
        if (preg_match('/Þ/', $q)) {
            $uq = str_replace('Þ', 'th', $q);
        }
        $office = $this->Officers->Offices->get($officeId);
        $this->Authorization->skipAuthorization();
        $this->request->allowMethod(['get']);
        $this->viewBuilder()->setClassName('Ajax');
        $query = $memberTbl
            ->find('all')
            ->where([
                'status <>' => Member::STATUS_DEACTIVATED,
                'OR' => [
                    CaseInsensitiveQuery::contains('sca_name', (string)$q),
                    CaseInsensitiveQuery::contains('sca_name', (string)$nq),
                    CaseInsensitiveQuery::contains('sca_name', (string)$uq),
                ],
            ])
            ->select(['id', 'sca_name', 'warrantable', 'status'])
            ->limit(50);
        $this->set(compact('query', 'q', 'nq', 'uq', 'office'));
    }

    /**
     * Officer management index page.
     *
     * @return void
     */
    public function index()
    {
        $this->Authorization->skipAuthorization();
    }

    /**
     * Provide grid data for officers listing with filtering and export support.
     *
     * @param \App\Services\CsvExportService $csvExportService CSV export service
     * @return \Cake\Http\Response|null|void
     */
    public function gridData(CsvExportService $csvExportService)
    {
        // Determine context from query parameters
        $memberId = $this->request->getQuery('member_id');
        $branchId = $this->request->getQuery('branch_id');

        // Authorization: check context-specific permissions
        $newOfficer = $this->Officers->newEmptyEntity();
        $context = null;
        if ($memberId) {
            $newOfficer->member_id = (int)$memberId;
            $this->Authorization->authorize($newOfficer, 'MemberOfficers');
            $context = 'member';
        } elseif ($branchId) {
            $newOfficer->branch_id = (int)$branchId;
            $this->Authorization->authorize($newOfficer, 'BranchOfficers');
            $context = 'branch';
        } else {
            throw new ForbiddenException();
        }

        // Get system views for temporal/warrant filtering with context-specific columns
        $systemViews = OfficersGridColumns::getSystemViews(['context' => $context]);
        $queryContext = $this->resolveDataverseGridQueryContext([
            'gridKey' => $context === 'member'
                ? 'Officers.Officers.member.main'
                : 'Officers.Officers.branch.main',
            'gridColumnsClass' => OfficersGridColumns::class,
            'systemViews' => $systemViews,
            'defaultSystemView' => 'sys-officers-current',
            'defaultSort' => ['Officers.start_on' => 'DESC'],
        ]);

        // Build base query with context-aware associations.
        $contain = [
            'Members' => function ($q) {
                return $q->select(['id', 'sca_name']);
            },
            'Offices' => function ($q) {
                return $q->select(['id', 'name', 'requires_warrant', 'deputy_to_id']);
            },
            'Offices.Departments' => function ($q) {
                return $q->select(['id', 'name']);
            },
        ];
        if ($queryContext->loadsColumn('warrant_state')) {
            $contain['CurrentWarrants'] = function ($q) {
                return $q->select(['id', 'start_on', 'expires_on', 'entity_id']);
            };
            $contain['PendingWarrants'] = function ($q) {
                return $q->select(['id', 'start_on', 'expires_on', 'entity_id']);
            };
        }
        if ($queryContext->loadsColumn('branch_name') || $context === 'branch') {
            $contain['Branches'] = function ($q) {
                return $q->select(['id', 'name']);
            };
        }

        $baseQuery = $this->Officers->find()
            ->contain($contain);

        // Apply context filters
        if ($memberId) {
            $baseQuery->where(['Officers.member_id' => (int)$memberId]);
        }
        if ($branchId) {
            $baseQuery->where(['Officers.branch_id' => (int)$branchId]);
        }

        // Build query callback for system view processing
        $queryCallback = $this->buildOfficerQueryCallback($queryContext);

        // Determine frame ID based on context
        $frameId = 'officers-grid';
        $gridKey = 'Officers.Officers.index.main';
        if ($memberId) {
            $frameId = 'member-officers-grid';
            $gridKey = 'Officers.Officers.member.main';
        } elseif ($branchId) {
            $frameId = 'branch-officers-grid';
            $gridKey = 'Officers.Officers.branch.main';
        }
        $gridConfig = [
            'gridKey' => $gridKey,
            'gridColumnsClass' => OfficersGridColumns::class,
            'baseQuery' => $baseQuery,
            'tableName' => 'Officers',
            'defaultSort' => ['Officers.start_on' => 'DESC'],
            'defaultPageSize' => 25,
            'systemViews' => $systemViews,
            'defaultSystemView' => 'sys-officers-current',
            'queryCallback' => $queryCallback,
            'showAllTab' => false,
            'canAddViews' => false,
            'canFilter' => true,
            'canExportCsv' => false,
            'showFilterPills' => false,
        ];
        if ($context === 'member') {
            $gridConfig['canExportCsv'] = false;
            $gridConfig['canFilter'] = true;
            $gridConfig['lockedFilters'] = ['status'];
            $gridConfig['enableColumnPicker'] = false;
        }

        // Process using DataverseGridTrait
        $result = $this->processDataverseGrid($gridConfig);

        // Handle CSV export
        if (!empty($result['isCsvExport'])) {
            return $this->handleCsvExport($result, $csvExportService, 'officers');
        }

        // Get row actions from grid columns
        $rowActions = OfficersGridColumns::getRowActions();

        $this->renderDataverseGridResponse(
            result: $result,
            frameId: $frameId,
            collectionVar: 'officers',
            extraViewVars: [
                'searchableColumns' => OfficersGridColumns::getSearchableColumns(),
                'memberId' => $memberId,
                'branchId' => $branchId,
                'rowActions' => $rowActions,
            ],
        );
    }

    /**
     * Build query callback for officer system view processing.
     *
     * @return callable
     */
    protected function buildOfficerQueryCallback(?DataverseGridQueryContext $queryContext = null): callable
    {
        return function ($query, $selectedSystemView) use ($queryContext) {
            // Determine the display type based on the selected view
            $viewId = $selectedSystemView['id'] ?? 'sys-officers-current';

            if ($viewId === 'sys-officers-previous') {
                $type = 'previous';
            } elseif ($viewId === 'sys-officers-upcoming') {
                $type = 'upcoming';
            } else {
                $type = 'current';
            }

            // Add reporting relationships for current/upcoming views
            if (
                ($type === 'current' || $type === 'upcoming')
                && ($queryContext === null || $queryContext->loadsColumn('reports_to_list'))
            ) {
                $query->contain([
                    'ReportsToCurrently' => function ($q) {
                        return $q
                            ->contain([
                                'Members' => function ($q) {
                                    return $q->select(['id', 'sca_name']);
                                },
                                'Offices' => function ($q) {
                                    return $q->select(['id', 'name']);
                                },
                            ])
                            ->select(['id', 'office_id', 'branch_id', 'member_id', 'email_address']);
                    },
                    'DeputyToCurrently' => function ($q) {
                        return $q
                            ->contain([
                                'Members' => function ($q) {
                                    return $q->select(['id', 'sca_name']);
                                },
                                'Offices' => function ($q) {
                                    return $q->select(['id', 'name']);
                                },
                            ])
                            ->select(['id', 'office_id', 'branch_id', 'member_id', 'email_address']);
                    },
                ]);
            }

            return $query;
        };
    }

    /**
     * Display officers filtered by warrant status.
     *
     * @param string $state Warrant status filter (current, unwarranted, pending, previous)
     * @return void
     * @throws \Cake\Http\Exception\NotFoundException When invalid state provided
     */
    public function officersByWarrantStatus($state)
    {
        if ($state != 'current' && $state == 'pending' && $state == 'previous') {
            throw new NotFoundException();
        }
        //$securityOfficer = $this->Officers->newEmptyEntity();
        $this->Authorization->skipAuthorization();

        $officersQuery = $this->Officers->find()
            ->select([
                'revoked_reason',
                'sca_name' => 'Members.sca_name',
                'branch_name' => 'Branches.name',
                'office_name' => 'Offices.name',
                'deputy_description' => 'Officers.deputy_description',
                'start_on',
                'expires_on',
                'warrant_status' => 'Warrants.status',
                'status' => 'Officers.status',
                'revoker_id',
                'revoked_by' => 'revoker.sca_name',
            ])
            ->innerJoin(
                ['Offices' => 'officers_offices'],
                ['Offices.id = Officers.office_id'],
            )
            ->innerJoin(
                ['Branches' => 'branches'],
                ['Branches.id = Officers.branch_id'],
            )
            ->innerJoin(
                ['Members' => 'members'],
                ['Members.id = Officers.member_id'],
            )
            ->join([
                'table' => 'members',
                'alias' => 'revoker',
                'type' => 'LEFT',
                'conditions' => 'revoker.id = Officers.revoker_id',
            ])
            ->leftJoin(
                ['Warrants' => 'warrants'],
                [
                    'Members.id = Warrants.member_id',
                    'Officers.id = Warrants.entity_id',
                ],
            )
            ->order(['sca_name' => 'ASC'])
            ->order(['office_name' => 'ASC']);

        $today = new DateTime();
        switch ($state) {
            case 'current':
                $officersQuery = $officersQuery->where([
                    'Warrants.expires_on >=' => $today,
                    'Warrants.start_on <=' => $today,
                    'Warrants.status' => Warrant::CURRENT_STATUS,
                ]);
                break;
            case 'unwarranted':
                $officersQuery = $officersQuery->where('Warrants.id IS NULL');

                break;
            case 'pending':
                $officersQuery = $officersQuery->where(['Warrants.status' => Warrant::PENDING_STATUS]);
                break;
            case 'previous':
                $officersQuery = $officersQuery->where([
                    'OR' => [
                        'Warrants.expires_on <' => $today,
                        'Warrants.status IN ' => [Warrant::DEACTIVATED_STATUS, Warrant::EXPIRED_STATUS],
                    ],
                ]);
                break;
        }
        //$officersQuery = $this->addConditions($officersQuery);
        $officers = $this->paginate($officersQuery);
        $this->set(compact('officers', 'state'));
    }

    /**
     * Export officer data as CSV.
     *
     * Supports filtering by status and expiration timeframe via query parameters.
     *
     * @param \App\Services\CsvExportService $csvExportService CSV export service
     * @return \Cake\Http\Response CSV download response
     */
    public function api(CsvExportService $csvExportService): Response
    {
        $this->Authorization->skipAuthorization();
        $this->autoRender = false;

        $status = $this->request->getQuery('status');
        $endsIn = $this->request->getQuery('endsIn');

        $officers = $this->Officers->find()
            ->contain(['Offices' => ['Departments'], 'Members', 'Branches']);
        if ($status !== null) {
            $officers = $officers->where(
                CaseInsensitiveQuery::equals('Officers.status', (string)$status),
            );
        }
        if ($endsIn !== null) {
            $endDate = new DateTime('+' . $endsIn . ' days');

            // Include officers that either have no expiry (landed nobility)
            // or whose expiry falls within the requested window.
            $officers = $officers->where(function ($exp, $q) use ($endDate) {
                return $exp->or_([
                    $exp->isNull('Officers.expires_on'),
                    ['Officers.expires_on >=' => DateTime::now(), 'Officers.expires_on <=' => $endDate],
                ]);
            });
        }

        $rows = [];
        foreach ($officers as $officer) {
            $memberData = $officer->member->publicData();
            $officeName = $officer->office->name;
            if ($officer->deputy_description !== null && $officer->deputy_description !== '') {
                $officeName .= ' (' . $officer->deputy_description . ')';
            }
            $rows[] = [
                'Office' => $officeName,
                'Name' => $memberData['sca_name'],
                'email' => $officer->email_address,
                'Branch' => $officer->branch->name,
                'Department' => $officer->office->department->name,
                'Start' => $officer->start_on?->i18nFormat('MM-dd-yyyy') ?? '',
                'End' => $officer->expires_on?->i18nFormat('MM-dd-yyyy') ?? '',
            ];
        }

        return $csvExportService->outputCsv(
            $rows,
            'officers-' . date('Y-m-d-H-i-s') . '.csv',
            ['Office', 'Name', 'email', 'Branch', 'Department', 'Start', 'End'],
        );
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
     * @return array{contextKey: string, tableFrameId: string, gridKey: string, memberId?: int, branchId?: int}|null
     */
    private function resolveOfficersGridSyncContext(?string $pageContextUrl): ?array
    {
        if ($pageContextUrl === null) {
            return null;
        }

        $path = parse_url($pageContextUrl, PHP_URL_PATH) ?? $pageContextUrl;
        $tab = $this->pageContextQueryTab($pageContextUrl);

        if ($this->matchesGridIndexPath($pageContextUrl, '#/officers/officers/?$#')) {
            return [
                'contextKey' => 'index',
                'tableFrameId' => 'officers-grid-table',
                'gridKey' => 'Officers.Officers.index.main',
            ];
        }

        if (preg_match('#/members/profile/?$#', $path)) {
            $memberId = (int)$this->request->getAttribute('identity')->id;
            if ($tab !== null && $tab !== 'member-officers') {
                return null;
            }

            return [
                'contextKey' => 'member',
                'tableFrameId' => 'member-officers-grid-table',
                'gridKey' => 'Officers.Officers.member.main',
                'memberId' => $memberId,
            ];
        }

        if (preg_match('#/members/view/(\d+)/?$#', $path, $matches)) {
            if ($tab !== null && $tab !== 'member-officers') {
                return null;
            }

            $memberId = (int)$matches[1];

            return [
                'contextKey' => 'member',
                'tableFrameId' => 'member-officers-grid-table',
                'gridKey' => 'Officers.Officers.member.main',
                'memberId' => $memberId,
            ];
        }

        if (preg_match('#/branches/view/([^/]+)/?$#', $path, $matches)) {
            if ($tab !== null && $tab !== 'branch-officers') {
                return null;
            }

            $branchesTable = TableRegistry::getTableLocator()->get('Branches');
            try {
                $branch = $branchesTable->find('byPublicId', [$matches[1]])->firstOrFail();
            } catch (\Cake\Datasource\Exception\RecordNotFoundException) {
                return null;
            }

            return [
                'contextKey' => 'branch',
                'tableFrameId' => 'branch-officers-grid-table',
                'gridKey' => 'Officers.Officers.branch.main',
                'branchId' => (int)$branch->id,
            ];
        }

        return null;
    }

    /**
     * @return array{action: string, rowDomId: string, rowHtml?: string}|null
     */
    private function resolveOfficerGridRowSync(int $officerId, ?string $pageContextUrl): ?array
    {
        $syncContext = $this->resolveOfficersGridSyncContext($pageContextUrl);
        if ($syncContext === null) {
            return null;
        }

        $tableFrameId = $syncContext['tableFrameId'];
        $rowDomId = GridRowDomId::fromTableFrameId($tableFrameId, $officerId);

        return $this->withPageContextQuery($pageContextUrl, function () use (
            $officerId,
            $rowDomId,
            $tableFrameId,
            $syncContext,
        ): ?array {
            $newOfficer = $this->Officers->newEmptyEntity();
            $context = $syncContext['contextKey'];
            if ($context === 'member') {
                $newOfficer->member_id = $syncContext['memberId'];
                $this->Authorization->authorize($newOfficer, 'MemberOfficers');
            } elseif ($context === 'branch') {
                $newOfficer->branch_id = $syncContext['branchId'];
                $this->Authorization->authorize($newOfficer, 'BranchOfficers');
            } else {
                $this->Authorization->authorizeModel('index');
            }

            $systemViewContext = match ($context) {
                'member' => 'member',
                'branch' => 'branch',
                default => null,
            };
            $systemViews = OfficersGridColumns::getSystemViews(
                $systemViewContext !== null ? ['context' => $systemViewContext] : [],
            );
            $queryContext = $this->resolveDataverseGridQueryContext([
                'gridKey' => $syncContext['gridKey'],
                'gridColumnsClass' => OfficersGridColumns::class,
                'systemViews' => $systemViews,
                'defaultSystemView' => 'sys-officers-current',
                'defaultSort' => ['Officers.start_on' => 'DESC'],
            ]);
            $contain = [
                'Members' => fn($q) => $q->select(['id', 'sca_name']),
                'Offices' => fn($q) => $q->select(['id', 'name', 'requires_warrant', 'deputy_to_id']),
                'Offices.Departments' => fn($q) => $q->select(['id', 'name']),
            ];
            if ($queryContext->loadsColumn('branch_name')) {
                $contain['Branches'] = fn($q) => $q->select(['id', 'name']);
            }
            if ($queryContext->loadsColumn('warrant_state')) {
                $contain['CurrentWarrants'] = fn($q) => $q->select(['id', 'start_on', 'expires_on', 'entity_id']);
                $contain['PendingWarrants'] = fn($q) => $q->select(['id', 'start_on', 'expires_on', 'entity_id']);
            }
            $baseQuery = $this->Officers->find()
                ->where(['Officers.id' => $officerId])
                ->contain($contain);

            if ($context === 'member') {
                $baseQuery->where(['Officers.member_id' => $syncContext['memberId']]);
            } elseif ($context === 'branch') {
                $baseQuery->where(['Officers.branch_id' => $syncContext['branchId']]);
            }

            $gridConfig = [
                'gridKey' => $syncContext['gridKey'],
                'gridColumnsClass' => OfficersGridColumns::class,
                'baseQuery' => $baseQuery,
                'tableName' => 'Officers',
                'defaultSort' => ['Officers.start_on' => 'DESC'],
                'defaultPageSize' => 25,
                'systemViews' => $systemViews,
                'defaultSystemView' => 'sys-officers-current',
                'queryCallback' => $this->buildOfficerQueryCallback($queryContext),
                'showAllTab' => false,
                'canAddViews' => false,
                'canFilter' => true,
                'canExportCsv' => false,
                'showFilterPills' => false,
            ];
            if ($context === 'member') {
                $gridConfig['lockedFilters'] = ['status'];
                $gridConfig['enableColumnPicker'] = false;
            }

            $directOfficer = (clone $baseQuery)->first();
            $result = $this->processDataverseGrid($gridConfig);

            $gridData = $result['data'];
            if (is_array($gridData)) {
                $officers = $gridData;
            } elseif ($gridData instanceof \Traversable) {
                $officers = iterator_to_array($gridData, false);
            } else {
                $officers = [];
            }
            if ($officers === [] && $directOfficer !== null) {
                $officers = [$directOfficer];
            }
            if ($officers === []) {
                return [
                    'action' => 'remove',
                    'rowDomId' => $rowDomId,
                ];
            }

            $rowActions = OfficersGridColumns::getRowActions();
            $gridState = $result['gridState'];
            $visibleColumns = $gridState['columns']['visible'];
            if (!is_array($visibleColumns)) {
                $visibleColumns = array_values($visibleColumns);
            }

            $rowHtml = $this->renderDataverseTableRowElement([
                'row' => $officers[0],
                'columns' => $gridState['columns']['all'],
                'visibleColumns' => $visibleColumns,
                'controllerName' => 'grid-view',
                'primaryKey' => $gridState['config']['primaryKey'],
                'gridKey' => $gridState['config']['gridKey'],
                'rowActions' => $rowActions,
                'user' => $this->request->getAttribute('identity'),
                'enableBulkSelection' => false,
                'rowDomIdPrefix' => preg_replace('/-table$/', '', $tableFrameId),
                'showActionsColumn' => $rowActions !== [],
            ]);

            return [
                'action' => 'replace',
                'rowDomId' => $rowDomId,
                'rowHtml' => $rowHtml,
            ];
        });
    }

    private function tryOfficersGridTurboResponse(?string $pageContext, int $officerId): ?Response
    {
        if (!$this->wantsTurboStreamRequest() || $pageContext === null) {
            return null;
        }

        $syncContext = $this->resolveOfficersGridSyncContext($pageContext);
        if ($syncContext === null) {
            return null;
        }

        $sync = $this->resolveOfficerGridRowSync($officerId, $pageContext);
        if ($sync === null) {
            return null;
        }

        if ($sync['action'] === 'remove') {
            return $this->renderTurboRemoveGridRow($sync['rowDomId']);
        }

        return $this->renderTurboReplaceGridRow(
            $sync['rowDomId'],
            $sync['rowHtml'] ?? '',
        );
    }
}
