<?php
declare(strict_types=1);

namespace Officers\Controller;

use App\Controller\DataverseGridTrait;
use App\Controller\WorkflowDispatchTrait;
use App\KMP\CaseInsensitiveQuery;
use App\KMP\DataverseGridQueryContext;
use App\KMP\GridRowDomId;
use App\Model\Entity\Member;
use App\Model\Entity\Warrant;
use App\Services\CsvExportService;
use App\Services\ServiceResult;
use App\Services\WarrantManager\WarrantManagerInterface;
use App\Services\WarrantManager\WarrantRequest;
use App\Services\WorkflowEngine\TriggerDispatcher;
use Cake\Datasource\Exception\RecordNotFoundException;
use Cake\Http\Exception\ForbiddenException;
use Cake\Http\Exception\NotFoundException;
use Cake\Http\Response;
use Cake\I18n\Date;
use Cake\I18n\DateTime;
use Cake\Log\Log;
use Cake\ORM\TableRegistry;
use Officers\KMP\GridColumns\OfficersGridColumns;
use Officers\Model\Entity\Officer;
use Throwable;
use Traversable;

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
     * Request an update to an officer assignment.
     *
     * @param \App\Services\WorkflowEngine\TriggerDispatcher $dispatcher Workflow trigger dispatcher
     * @return \Cake\Http\Response|null|void Redirects on completion
     * @throws \Cake\Http\Exception\NotFoundException When officer not found
     */
    public function edit(TriggerDispatcher $dispatcher)
    {
        $this->request->allowMethod(['post']);
        $officer = $this->Officers->get($this->request->getData('id'));
        if (!$officer) {
            throw new NotFoundException();
        }
        $this->Authorization->authorize($officer);
        if (!in_array($officer->status, [Officer::CURRENT_STATUS, Officer::UPCOMING_STATUS], true)) {
            $this->Flash->error(__('Only current or upcoming officer assignments can be updated.'));

            return $this->redirect($this->referer());
        }

        $startOn = $this->normalizeOfficerTermDate($this->request->getData('start_on'));
        if ($startOn === null) {
            $this->Flash->error(__('Enter a valid start date.'));

            return $this->redirect($this->referer());
        }

        $expiresOnInput = $this->request->getData('expires_on');
        $expiresOn = $this->normalizeOfficerTermDate($expiresOnInput);
        if ($expiresOn === null && $expiresOnInput !== null && trim((string)$expiresOnInput) !== '') {
            $this->Flash->error(__('Enter a valid end date.'));

            return $this->redirect($this->referer());
        }
        if ($expiresOn !== null && $expiresOn < $startOn) {
            $this->Flash->error(__('The officer term end date cannot be before the start date.'));

            return $this->redirect($this->referer());
        }

        $emailAddress = trim((string)$this->request->getData('email_address', ''));
        $emailAddress = $emailAddress === '' ? null : $emailAddress;
        if ($emailAddress !== null && filter_var($emailAddress, FILTER_VALIDATE_EMAIL) === false) {
            $this->Flash->error(__('Enter a valid email address.'));

            return $this->redirect($this->referer());
        }

        $termNote = trim((string)$this->request->getData('term_note', ''));
        $currentStartOn = $officer->start_on?->format('Y-m-d');
        $currentExpiresOn = $officer->expires_on?->format('Y-m-d');
        $termChanged = $startOn !== $currentStartOn || $expiresOn !== $currentExpiresOn;
        if ($termChanged && $termNote === '') {
            $this->Flash->error(__('A note is required when changing the officer term dates.'));

            return $this->redirect($this->referer());
        }

        $identity = $this->Authentication->getIdentity();
        $deputyDescription = trim((string)$this->request->getData('deputy_description', ''));
        $deputyDescription = $deputyDescription === '' ? null : $deputyDescription;
        $context = [
            'officerId' => (int)$officer->id,
            'actorId' => (int)$identity->getIdentifier(),
            'memberId' => (int)$officer->member_id,
            'officeId' => (int)$officer->office_id,
            'branchId' => (int)$officer->branch_id,
            'startOn' => $startOn,
            'expiresOn' => $expiresOn,
            'emailAddress' => $emailAddress,
            'deputyDescription' => $deputyDescription,
            'termNote' => $termNote,
        ];

        try {
            $result = $this->dispatchWorkflowOrFail(
                $dispatcher,
                'officer-assignment-update',
                'Officers.AssignmentUpdateRequested',
                $context,
            );
            $workflowError = $this->extractWorkflowDispatchFailure(
                $result,
                'The officer assignment update workflow could not be completed.',
            );
            if ($workflowError !== null) {
                $this->Flash->error(__($workflowError));

                return $this->redirect($this->referer());
            }

            $workflowWarning = $this->extractWorkflowDispatchWarning($result);
            if ($workflowWarning !== null) {
                $this->Flash->warning(__(
                    'The officer assignment was saved, but follow-up work needs attention: {0} '
                    . 'Do not submit this assignment update again; complete the follow-up separately.',
                    $workflowWarning,
                ));
            } else {
                $this->Flash->success(__('The officer assignment has been updated.'));
            }
            $stream = $this->tryOfficersGridTurboResponse(
                $this->getPageContextUrl(),
                (int)$officer->id,
            );
            if ($stream !== null) {
                return $stream;
            }
        } catch (Throwable $e) {
            Log::error('Officer assignment update workflow dispatch failed: ' . $e->getMessage());
            $this->Flash->error(__('The officer assignment update workflow is not currently available.'));
        }

        return $this->redirect($this->referer());
    }

    /**
     * Normalize a submitted officer term date.
     *
     * @param mixed $value Submitted date value
     * @return string|null ISO date, or null when blank or invalid
     */
    private function normalizeOfficerTermDate(mixed $value): ?string
    {
        if (!is_string($value) && !is_int($value)) {
            return null;
        }

        $value = trim((string)$value);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        try {
            $date = Date::createFromFormat('!Y-m-d', $value);
            $errors = Date::getLastErrors();
            if (
                $date->format('Y-m-d') !== $value
                || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
            ) {
                return null;
            }
        } catch (Throwable) {
            return null;
        }

        return $value;
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
     * Extract non-fatal workflow warnings after an assignment update was saved.
     *
     * @param array<int, mixed> $results Workflow dispatch results from TriggerDispatcher.
     * @return string|null Combined warning details, or null when all follow-up work completed.
     */
    private function extractWorkflowDispatchWarning(array $results): ?string
    {
        $warnings = [];

        foreach ($results as $result) {
            if ($result instanceof ServiceResult) {
                $workflowResult = is_array($result->data ?? null)
                    ? ($result->data['workflowResult'] ?? null)
                    : null;
            } elseif (is_array($result)) {
                $workflowResult = isset($result['workflowResult']) && is_array($result['workflowResult'])
                    ? $result['workflowResult']
                    : $result;
            } else {
                continue;
            }

            if (!is_array($workflowResult) || ($workflowResult['updated'] ?? false) !== true) {
                continue;
            }

            $workflowWarnings = $workflowResult['warnings'] ?? $workflowResult['warning'] ?? [];
            if (!is_array($workflowWarnings)) {
                $workflowWarnings = [$workflowWarnings];
            }

            foreach ($workflowWarnings as $warning) {
                if (!is_string($warning) || trim($warning) === '') {
                    continue;
                }

                $warnings[] = trim($warning);
            }
        }

        $warnings = array_values(array_unique($warnings));

        return $warnings === [] ? null : implode(' ', $warnings);
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
            'TermNotes' => function ($q) {
                return $q
                    ->select(['id', 'author_id', 'entity_id', 'subject', 'body', 'created'])
                    ->contain([
                        'Authors' => function ($q) {
                            return $q->select(['id', 'sca_name']);
                        },
                    ]);
            },
        ];
        if (
            $queryContext->loadsColumn('warrant_state')
            || $queryContext->loadsColumn('member_warrant_summary')
        ) {
            $contain['CurrentWarrants'] = function ($q) {
                return $q->select(['id', 'start_on', 'expires_on', 'entity_id']);
            };
            $contain['PendingWarrants'] = function ($q) {
                return $q->select(['id', 'start_on', 'expires_on', 'entity_id']);
            };
        }
        if ($queryContext->loadsColumn('member_warrant_summary')) {
            $contain['Warrants'] = function ($q) {
                return $q
                    ->select(['id', 'status', 'start_on', 'expires_on', 'entity_id'])
                    ->orderByAsc($q->expr()->isNull('Warrants.expires_on'))
                    ->orderBy([
                        'Warrants.expires_on' => 'DESC',
                        'Warrants.start_on' => 'DESC',
                        'Warrants.id' => 'DESC',
                    ]);
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
     * Render the warrant history fragment for a member-profile officer row.
     *
     * @param int $id Officer assignment ID
     * @return void
     */
    public function warrantHistory(int $id): void
    {
        $officer = $this->Officers->find()
            ->where(['Officers.id' => $id])
            ->contain([
                'Offices' => function ($q) {
                    return $q->select(['id', 'name']);
                },
                'Branches' => function ($q) {
                    return $q->select(['id', 'name']);
                },
                'Warrants' => function ($q) {
                    return $q
                        ->select([
                            'id',
                            'status',
                            'start_on',
                            'expires_on',
                            'revoked_reason',
                            'entity_id',
                        ])
                        ->orderBy(['Warrants.start_on' => 'DESC', 'Warrants.id' => 'DESC']);
                },
            ])
            ->firstOrFail();

        $this->Authorization->authorize($officer, 'MemberOfficers');

        $this->set([
            'officer' => $officer,
            'warrants' => $officer->warrants,
        ]);
        $this->viewBuilder()->disableAutoLayout();
        $this->viewBuilder()->setTemplate('warrant_history');
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
            } catch (RecordNotFoundException) {
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
                'TermNotes' => fn($q) => $q
                    ->select(['id', 'author_id', 'entity_id', 'subject', 'body', 'created'])
                    ->contain([
                        'Authors' => fn($q) => $q->select(['id', 'sca_name']),
                    ]),
            ];
            if ($queryContext->loadsColumn('branch_name')) {
                $contain['Branches'] = fn($q) => $q->select(['id', 'name']);
            }
            if (
                $queryContext->loadsColumn('warrant_state')
                || $queryContext->loadsColumn('member_warrant_summary')
            ) {
                $contain['CurrentWarrants'] = fn($q) => $q->select(['id', 'start_on', 'expires_on', 'entity_id']);
                $contain['PendingWarrants'] = fn($q) => $q->select(['id', 'start_on', 'expires_on', 'entity_id']);
            }
            if ($queryContext->loadsColumn('member_warrant_summary')) {
                $contain['Warrants'] = fn($q) => $q
                    ->select(['id', 'status', 'start_on', 'expires_on', 'entity_id'])
                    ->orderByAsc($q->expr()->isNull('Warrants.expires_on'))
                    ->orderBy([
                        'Warrants.expires_on' => 'DESC',
                        'Warrants.start_on' => 'DESC',
                        'Warrants.id' => 'DESC',
                    ]);
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
            } elseif ($gridData instanceof Traversable) {
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

    /**
     * Return a Turbo Stream row update for an officer grid request when possible.
     *
     * @param string|null $pageContext Source page URL
     * @param int $officerId Officer assignment ID
     * @return \Cake\Http\Response|null
     */
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
