<?php

declare(strict_types=1);

namespace Officers\Controller;

use App\Controller\DataverseGridTrait;
use App\Services\ActiveWindowManager\ActiveWindowManagerInterface;
use App\Services\CsvExportService;
use Officers\Services\OfficerManagerInterface;
use App\Services\WarrantManager\WarrantManagerInterface;
use App\Services\ServiceResult;
use App\Services\WarrantManager\WarrantRequest;
use App\Model\Entity\Warrant;
use Cake\I18n\DateTime;
use Cake\I18n\Date;
use Officers\Model\Entity\Officer;
use App\Model\Entity\Member;

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

    /**
     * Initialize controller with authentication and authorization settings.
     *
     * @return void
     */
    public function initialize(): void
    {
        parent::initialize();
        $this->Authentication->addUnauthenticatedActions(['api']);
        $this->Authorization->authorizeModel('index', 'gridData');
    }

    /**
     * Assign an officer to an office position.
     *
     * @param \Officers\Services\OfficerManagerInterface $oManager Officer business logic service
     * @return \Cake\Http\Response|null|void Redirects on completion or error
     */
    public function assign(OfficerManagerInterface $oManager)
    {
        if ($this->request->is('post')) {
            $officer = $this->Officers->newEmptyEntity();
            $user = $this->Authentication->getIdentity();
            $branchId = (int)$this->request->getData('branch_id');
            $this->Authorization->authorize($officer);
            $user = $this->Authentication->getIdentity();
            //begin transaction

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
            if ($this->request->getData('end_on') !== null && $this->request->getData('end_on') !== "") {
                $endOn = new DateTime($this->request->getData('end_on'));
            } else {
                $endOn = null;
            }
            $approverId = (int)$user->id;
            $deputyDescription = $this->request->getData('deputy_description');
            $this->Officers->getConnection()->begin();
            $omResult = $oManager->assign($officeId, $memberId, $branchId, $startOn, $endOn, $deputyDescription, $approverId, $emailAddress);
            if (!$omResult->success) {
                $this->Officers->getConnection()->rollback();
                $this->Flash->error(__($omResult->reason));
                $this->redirect($this->referer());
                return;
            }
            //commit transaction
            $this->Officers->getConnection()->commit();
            $this->Flash->success(__('The officer has been saved.'));
            $this->redirect($this->referer());
        }
    }

    /**
     * Release an officer from their assignment.
     *
     * @param \Officers\Services\OfficerManagerInterface $oManager Officer business logic service
     * @return \Cake\Http\Response|null|void Redirects on completion or error
     * @throws \Cake\Http\Exception\NotFoundException When officer not found
     */
    public function release(OfficerManagerInterface $oManager)
    {
        $officer = $this->Officers->get($this->request->getData('id'));
        if (!$officer) {
            throw new \Cake\Http\Exception\NotFoundException();
        }
        $this->Authorization->authorize($officer);
        if ($this->request->is('post')) {
            $revokeReason = $this->request->getData('revoked_reason');
            $revokeDate = new DateTime($this->request->getData('revoked_on'));
            $revokerId = $this->Authentication->getIdentity()->getIdentifier();

            //begin transaction
            $this->Officers->getConnection()->begin();
            $omResult = $oManager->release($officer->id, $revokerId, $revokeDate, $revokeReason);
            if (!$omResult->success) {
                $this->Officers->getConnection()->rollback();
                $this->Flash->error(__('The officer could not be released. Please, try again.'));
                $this->redirect($this->referer());
            }
            //commit transaction
            $this->Officers->getConnection()->commit();
            $this->Flash->success(__('The officer has been released.'));
            $this->redirect($this->referer());
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
        $this->request->allowMethod(["post"]);
        $officer = $this->Officers->get($this->request->getData('id'));
        if (!$officer) {
            throw new \Cake\Http\Exception\NotFoundException();
        }
        $this->Authorization->authorize($officer);
        $officer->deputy_description = $this->request->getData('deputy_description');
        $officer->email_address = $this->request->getData('email_address');
        if ($this->Officers->save($officer)) {
            $this->Flash->success(__('The officer has been saved.'));
        } else {
            $this->Flash->error(__('The officer could not be saved. Please, try again.'));
        }
        $this->redirect($this->referer());
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
        $officer = $this->Officers->find()->where(['Officers.id' => $id])->contain(["Offices", "Branches", "Members"])->first();
        $userid = $this->Authentication->getIdentity()->getIdentifier();
        if (!$officer) {
            throw new \Cake\Http\Exception\NotFoundException();
        }
        $this->Authorization->authorize($officer);
        if ($this->request->is('post')) {
            $officeName = $officer->office->name;
            if ($officer->deputy_description != null && $officer->deputy_description != "") {
                $officeName = $officeName . " (" . $officer->deputy_description . ")";
            }
            $branchName = $officer->branch->name;
            $warrantRequest = new WarrantRequest("Manual Request Warrant: $branchName - $officeName", 'Officers.Officers', $officer->id, $userid, $officer->member_id, $officer->start_on, $officer->expires_on, $officer->granted_member_role_id);
            $memberName = $officer->member->sca_name;
            $wmResult = $wManager->request("$officeName : $memberName", "", [$warrantRequest]);
            if (!$wmResult->success) {
                $this->Flash->error("Could not request Warrant: " . __($wmResult->reason));
                $this->redirect($this->referer());
                return;
            }
            $this->Flash->success(__('The warrant request has been sent.'));
            $this->redirect($this->referer());
            return;
        }
    }

    /**
     * Display officer assignments for a specific member.
     *
     * @param int $id Member ID
     * @param string $state Assignment state filter (current, upcoming, previous)
     * @return void
     */
    public function memberOfficers($id, $state)
    {
        $newOfficer = $this->Officers->newEmptyEntity();
        $newOfficer->member_id = $id;
        $this->Authorization->authorize($newOfficer);

        $officersQuery = $this->Officers->find()

            ->contain(['Offices' => ["Departments"], 'Members', 'Branches'])
            ->orderBY(["Officers.id" => "ASC"]);


        switch ($state) {
            case 'current':
                $officersQuery = $this->Officers->addDisplayConditionsAndFields($officersQuery->find('current')->where(['Officers.member_id' => $id]), 'current');
                break;
            case 'upcoming':
                $officersQuery = $this->Officers->addDisplayConditionsAndFields($officersQuery->find('upcoming')->where(['Officers.member_id' => $id]), 'upcoming');
                break;
            case 'previous':
                $officersQuery = $this->Officers->addDisplayConditionsAndFields($officersQuery->find('previous')->where(['Officers.member_id' => $id]), 'previous');
                break;
        }

        $page = $this->request->getQuery("page");
        $limit = $this->request->getQuery("limit");
        $paginate = [];
        if ($page) {
            $paginate['page'] = $page;
        }
        if ($limit) {
            $paginate['limit'] = $limit;
        }
        //$paginate["limit"] = 5;
        $officers = $this->paginate($officersQuery, $paginate);
        $turboFrameId = $state;

        $this->set(compact('officers', 'id', 'state'));
    }

    /**
     * Display officer assignments for a specific branch with search capability.
     *
     * Supports Þ/th character conversion for SCA name searches.
     *
     * @param int $id Branch ID
     * @param string $state Assignment state filter (current, upcoming, previous)
     * @return void
     */
    public function branchOfficers($id, $state)
    {
        $newOfficer = $this->Officers->newEmptyEntity();
        $this->Authorization->authorize($newOfficer);

        $officersQuery = $this->Officers->find()

            ->contain(['Offices' => ["Departments"], 'Members', 'Branches'])->where(['Branches.id' => $id])
            ->orderBY(["Officers.id" => "ASC"]);

        $search = $this->request->getQuery("search");
        $search = $search ? trim($search) : null;

        if ($search) {
            //detect th and replace with Þ
            $nsearch = $search;
            if (preg_match("/th/", $search)) {
                $nsearch = str_replace("th", "Þ", $search);
            }
            //detect Þ and replace with th
            $usearch = $search;
            if (preg_match("/Þ/", $search)) {
                $usearch = str_replace("Þ", "th", $search);
            }
            $officersQuery = $officersQuery->where([
                "OR" => [
                    ["Members.sca_name LIKE" => "%" . $search . "%"],
                    ["Members.sca_name LIKE" => "%" . $nsearch . "%"],
                    ["Members.sca_name LIKE" => "%" . $usearch . "%"],
                    ["Offices.name LIKE" => "%" . $search . "%"],
                    ["Offices.name LIKE" => "%" . $nsearch . "%"],
                    ["Offices.name LIKE" => "%" . $usearch . "%"],
                    ["Departments.name LIKE" => "%" . $search . "%"],
                    ["Departments.name LIKE" => "%" . $nsearch . "%"],
                    ["Departments.name LIKE" => "%" . $usearch . "%"],

                ],
            ]);
        }

        switch ($state) {
            case 'current':
                $officersQuery = $this->Officers->addDisplayConditionsAndFields($officersQuery->find('current')->where(['Officers.branch_id' => $id]), 'current');
                break;
            case 'upcoming':
                $officersQuery = $this->Officers->addDisplayConditionsAndFields($officersQuery->find('upcoming')->where(['Officers.branch_id' => $id]), 'upcoming');
                break;
            case 'previous':
                $officersQuery = $this->Officers->addDisplayConditionsAndFields($officersQuery->find('previous')->where(['Officers.branch_id' => $id]), 'previous');
                break;
        }

        $page = $this->request->getQuery("page");
        $limit = $this->request->getQuery("limit");
        $paginate = [];
        if ($page) {
            $paginate['page'] = $page;
        }
        if ($limit) {
            $paginate['limit'] = $limit;
        }
        //$paginate["limit"] = 5;
        $officers = $this->paginate($officersQuery, $paginate);
        $turboFrameId = $state;

        $this->set(compact('officers', 'newOfficer', 'id', 'state'));
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
        $q = $this->request->getQuery("q");
        //detect th and replace with Þ
        $nq = $q;
        if (preg_match("/th/", $q)) {
            $nq = str_replace("th", "Þ", $q);
        }
        //detect Þ and replace with th
        $uq = $q;
        if (preg_match("/Þ/", $q)) {
            $uq = str_replace("Þ", "th", $q);
        }
        $office = $this->Officers->Offices->get($officeId);
        $this->Authorization->skipAuthorization();
        $this->request->allowMethod(["get"]);
        $this->viewBuilder()->setClassName("Ajax");
        $query = $memberTbl
            ->find("all")
            ->where([
                'status <>' => Member::STATUS_DEACTIVATED,
                'OR' => [["sca_name LIKE" => "%$q%"], ["sca_name LIKE" => "%$nq%"], ["sca_name LIKE" => "%$uq%"]]
            ])
            ->select(["id", "sca_name", "warrantable", "status"])
            ->limit(50);
        $this->set(compact("query", "q", "nq", "uq", "office"));
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
     * @param CsvExportService $csvExportService CSV export service
     * @return \Cake\Http\Response|null|void
     */
    public function gridData(CsvExportService $csvExportService)
    {
        // Determine context from query parameters
        $memberId = $this->request->getQuery('member_id');
        $branchId = $this->request->getQuery('branch_id');
        $search = $this->request->getQuery('search');

        // Authorization: check context-specific permissions
        $newOfficer = $this->Officers->newEmptyEntity();
        $context = null;
        if ($memberId) {
            $newOfficer->member_id = (int)$memberId;
            $this->Authorization->authorize($newOfficer, 'memberOfficers');
            $context = 'member';
        } elseif ($branchId) {
            $newOfficer->branch_id = (int)$branchId;
            $this->Authorization->authorize($newOfficer, 'branchOfficers');
            $context = 'branch';
        } else {
            $this->Authorization->skipAuthorization();
        }

        // Get system views for temporal/warrant filtering with context-specific columns
        $systemViews = $this->getOfficerSystemViews($context);

        // Build base query with required associations
        $baseQuery = $this->Officers->find()
            ->contain([
                'Members' => function ($q) {
                    return $q->select(['id', 'sca_name']);
                },
                'Offices' => function ($q) {
                    return $q->select(['id', 'name', 'requires_warrant', 'deputy_to_id']);
                },
                'Offices.Departments' => function ($q) {
                    return $q->select(['id', 'name']);
                },
                'Branches' => function ($q) {
                    return $q->select(['id', 'name']);
                },
                'CurrentWarrants' => function ($q) {
                    return $q->select(['id', 'start_on', 'expires_on', 'entity_id']);
                },
                'PendingWarrants' => function ($q) {
                    return $q->select(['id', 'start_on', 'expires_on', 'entity_id']);
                },
                'RevokedBy' => function ($q) {
                    return $q->select(['id', 'sca_name']);
                },
            ]);

        // Apply context filters
        if ($memberId) {
            $baseQuery->where(['Officers.member_id' => (int)$memberId]);
        }
        if ($branchId) {
            $baseQuery->where(['Officers.branch_id' => (int)$branchId]);
        }

        // Apply special character search (Þ/th handling for SCA names)
        if (!empty($search)) {
            $nsearch = str_replace("Þ", "th", $search);
            $nsearch = str_replace("þ", "th", $nsearch);
            $usearch = str_replace("th", "Þ", $search);
            $usearch = str_replace("TH", "Þ", $usearch);
            $usearch = str_replace("Th", "Þ", $usearch);

            $baseQuery->where([
                'OR' => [
                    ['Members.sca_name LIKE' => '%' . $search . '%'],
                    ['Members.sca_name LIKE' => '%' . $nsearch . '%'],
                    ['Members.sca_name LIKE' => '%' . $usearch . '%'],
                    ['Offices.name LIKE' => '%' . $search . '%'],
                    ['Offices.name LIKE' => '%' . $nsearch . '%'],
                    ['Offices.name LIKE' => '%' . $usearch . '%'],
                    ['Departments.name LIKE' => '%' . $search . '%'],
                    ['Departments.name LIKE' => '%' . $nsearch . '%'],
                    ['Departments.name LIKE' => '%' . $usearch . '%'],
                ],
            ]);
        }

        // Build query callback for system view processing
        $queryCallback = $this->buildOfficerQueryCallback();

        // Determine frame ID based on context
        $frameId = 'officers-grid';
        if ($memberId) {
            $frameId = 'member-officers-grid';
        } elseif ($branchId) {
            $frameId = 'branch-officers-grid';
        }

        // Process using DataverseGridTrait
        $result = $this->processDataverseGrid([
            'gridKey' => 'Officers.Officers.index.main',
            'gridColumnsClass' => \Officers\KMP\GridColumns\OfficersGridColumns::class,
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
            'canExportCsv' => true,
        ]);

        // Handle CSV export
        if (!empty($result['isCsvExport'])) {
            return $this->handleCsvExport($result, $csvExportService, 'officers');
        }

        // Get row actions from grid columns
        $rowActions = \Officers\KMP\GridColumns\OfficersGridColumns::getRowActions();

        // Set view variables
        $this->set([
            'officers' => $result['data'],
            'gridState' => $result['gridState'],
            'columns' => $result['columnsMetadata'],
            'visibleColumns' => $result['visibleColumns'],
            'searchableColumns' => \Officers\KMP\GridColumns\OfficersGridColumns::getSearchableColumns(),
            'dropdownFilterColumns' => $result['dropdownFilterColumns'],
            'filterOptions' => $result['filterOptions'],
            'currentFilters' => $result['currentFilters'],
            'currentSearch' => $result['currentSearch'],
            'currentView' => $result['currentView'],
            'availableViews' => $result['availableViews'],
            'gridKey' => $result['gridKey'],
            'currentSort' => $result['currentSort'],
            'currentMember' => $result['currentMember'],
            'memberId' => $memberId,
            'branchId' => $branchId,
            'rowActions' => $rowActions,
        ]);

        // Determine which template to render based on Turbo-Frame header
        $turboFrame = $this->request->getHeaderLine('Turbo-Frame');

        // Use main app's element templates (not plugin templates)
        $this->viewBuilder()->setPlugin(null);

        if ($turboFrame === $frameId . '-table') {
            // Inner frame request - render table data only
            $this->set('data', $result['data']);
            $this->set('tableFrameId', $frameId . '-table');
            $this->viewBuilder()->disableAutoLayout();
            $this->viewBuilder()->setTemplatePath('element');
            $this->viewBuilder()->setTemplate('dv_grid_table');
        } else {
            // Outer frame request (or no frame) - render toolbar + table frame
            $this->set('data', $result['data']);
            $this->set('frameId', $frameId);
            $this->viewBuilder()->disableAutoLayout();
            $this->viewBuilder()->setTemplatePath('element');
            $this->viewBuilder()->setTemplate('dv_grid_content');
        }
    }

    /**
     * Get system views for officer temporal filtering.
     *
     * @param string|null $context Context type ('member', 'branch', or null)
     * @return array<string, array<string, mixed>>
     */
    protected function getOfficerSystemViews(?string $context = null): array
    {
        $today = Date::today();
        $todayString = $today->format('Y-m-d');

        // Define column configurations based on context
        // Current/Upcoming: Office, Branch, Contact, Warrant, Start Date, End Date, Reports To
        // Previous: Office, Branch, Start Date, End Date, Reason
        $currentUpcomingColumns = match ($context) {
            'member' => ['office_name', 'branch_name', 'email_address', 'warrant_state', 'start_on', 'expires_on', 'reports_to_list'],
            'branch' => ['member_sca_name', 'office_name', 'email_address', 'warrant_state', 'start_on', 'expires_on', 'reports_to_list'],
            default => ['member_sca_name', 'office_name', 'branch_name', 'email_address', 'warrant_state', 'start_on', 'expires_on', 'status'],
        };

        $previousColumns = match ($context) {
            'member' => ['office_name', 'branch_name', 'start_on', 'expires_on', 'revoked_reason'],
            'branch' => ['member_sca_name', 'office_name', 'start_on', 'expires_on', 'revoked_reason'],
            default => ['member_sca_name', 'office_name', 'branch_name', 'start_on', 'expires_on', 'revoked_reason', 'status'],
        };

        return [
            'sys-officers-current' => [
                'id' => 'sys-officers-current',
                'name' => __('Current'),
                'description' => __('Active officer assignments'),
                'canManage' => false,
                'config' => [
                    'filters' => [
                        ['field' => 'status', 'operator' => 'eq', 'value' => Officer::CURRENT_STATUS],
                    ],
                    'columns' => $currentUpcomingColumns,
                ],
            ],
            'sys-officers-upcoming' => [
                'id' => 'sys-officers-upcoming',
                'name' => __('Upcoming'),
                'description' => __('Future officer assignments'),
                'canManage' => false,
                'config' => [
                    'filters' => [
                        ['field' => 'status', 'operator' => 'eq', 'value' => Officer::UPCOMING_STATUS],
                    ],
                    'columns' => $currentUpcomingColumns,
                ],
            ],
            'sys-officers-previous' => [
                'id' => 'sys-officers-previous',
                'name' => __('Previous'),
                'description' => __('Past officer assignments'),
                'canManage' => false,
                'config' => [
                    'filters' => [
                        ['field' => 'status', 'operator' => 'in', 'value' => [
                            Officer::EXPIRED_STATUS,
                            Officer::DEACTIVATED_STATUS,
                            Officer::RELEASED_STATUS,
                            Officer::REPLACED_STATUS,
                        ]],
                    ],
                    'columns' => $previousColumns,
                ],
            ],
        ];
    }

    /**
     * Build query callback for officer system view processing.
     *
     * @return callable
     */
    protected function buildOfficerQueryCallback(): callable
    {
        return function ($query, $selectedSystemView) {
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
            if ($type === 'current' || $type === 'upcoming') {
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
            throw new \Cake\Http\Exception\NotFoundException();
        }
        //$securityOfficer = $this->Officers->newEmptyEntity();
        $this->Authorization->skipAuthorization();


        $membersTable = $this->fetchTable('Members');
        $warrantsTable = $this->fetchTable('Warrants');

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
                ['Offices.id = Officers.office_id']
            )
            ->innerJoin(
                ['Branches' => 'branches'],
                ['Branches.id = Officers.branch_id']
            )
            ->innerJoin(
                ['Members' => 'members'],
                ['Members.id = Officers.member_id']
            )
            ->join([
                'table' => 'members',
                'alias' => 'revoker',
                'type' => 'LEFT',
                'conditions' => 'revoker.id = Officers.revoker_id',
            ])
            ->leftJoin(
                ['Warrants' => 'warrants'],
                ['Members.id = Warrants.member_id AND Officers.id = Warrants.entity_id']
            )
            ->order(['sca_name' => 'ASC'])
            ->order(['office_name' => 'ASC']);

        $today = new DateTime();
        switch ($state) {
            case 'current':
                $officersQuery = $officersQuery->where(['Warrants.expires_on >=' => $today, 'Warrants.start_on <=' => $today, 'Warrants.status' => Warrant::CURRENT_STATUS]);
                break;
            case 'unwarranted':
                $officersQuery = $officersQuery->where("Warrants.id IS NULL");

                break;
            case 'pending':
                $officersQuery = $officersQuery->where(['Warrants.status' => Warrant::PENDING_STATUS]);
                break;
            case 'previous':
                $officersQuery = $officersQuery->where(["OR" => ['Warrants.expires_on <' => $today, 'Warrants.status IN ' => [Warrant::DEACTIVATED_STATUS, Warrant::EXPIRED_STATUS]]]);
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
     * @return void Outputs CSV file directly
     */
    public function api()
    {
        $this->Authorization->skipAuthorization();
        $this->autoRender = false;

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=officers-' . date("Y-m-d-h-i-s") . '.csv');
        $output = fopen('php://output', 'w');

        $status = $this->request->getQuery('status');
        $endsIn = $this->request->getQuery('endsIn');

        $officers = $this->Officers->find()
            ->contain(['Offices' => ["Departments"], 'Members', 'Branches']);
        if ($status !== null) {
            $officers = $officers->where(["Officers.status" => $status]);
        }
        if ($endsIn !== null) {
            $endDate = new DateTime('+' . $endsIn . ' days');

            $officers = $officers->where([
                "Officers.expires_on >=" => DateTime::now(),
                "Officers.expires_on <=" => $endDate
            ]);
        }
        fputcsv($output, array('Office', 'Name', 'email', 'Branch', 'Department', 'Start', 'End'));

        $officers = $officers->toArray();

        if (count($officers) > 0) {
            foreach ($officers as $officer) {

                //DateTime::createFromFormat('yyyy-mm-dd hh:mm:ss', $officer['start_on']);
                $memberData = $officer['member']->publicData();
                $officeName = $officer['office']['name'];
                if ($officer['deputy_description'] != null && $officer['deputy_description'] != "") {
                    $officeName = $officeName . " (" . $officer['deputy_description'] . ")";
                }
                $officer_row = [
                    $officeName,
                    $memberData['sca_name'],
                    $officer['email_address'],
                    $officer['branch']['name'],
                    $officer['office']['department']['name'],
                    $officer['start_on']->i18nFormat('MM-dd-yyyy'),
                    $officer['expires_on']->i18nFormat('MM-dd-yyyy'),


                ];

                fputcsv($output, $officer_row);
            }
        }
        //return ($officers);
    }
}
