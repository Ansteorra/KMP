<?php

declare(strict_types=1);

namespace App\Controller;

use App\Form\ResetPasswordForm;
use App\KMP\GridColumns\MembersGridColumns;
use App\KMP\GridColumns\VerifyQueueGridColumns;
use App\KMP\StaticHelpers;
use App\Mailer\QueuedMailerAwareTrait;
use App\Model\Entity\Member;
use App\Services\CsvExportService;
use App\Services\ImpersonationService;
use Cake\Datasource\Exception\RecordNotFoundException;
use Cake\Event\EventInterface;
use Cake\Http\Exception\BadRequestException;
use Cake\Http\Exception\ForbiddenException;
use Cake\Http\Exception\NotFoundException;
use Cake\I18n\DateTime;
use Cake\Mailer\MailerAwareTrait;
use Cake\ORM\Query\SelectQuery;
use Cake\Routing\Router;

/**
 * Manages member CRUD, authentication, profiles, and member discovery.
 *
 * Handles login/logout, password reset, registration, member search,
 * mobile card display, and verification workflows.
 * Uses DataverseGridTrait for index listing with server-side filtering.
 *
 * @property \App\Model\Table\MembersTable $Members
 */
class MembersController extends AppController
{
    use QueuedMailerAwareTrait;
    use MailerAwareTrait;
    use DataverseGridTrait;

    /** @var array<string> Service injection configuration */
    public static array $inject = [CsvExportService::class];

    /** @var \App\Services\CsvExportService */
    protected CsvExportService $csvExportService;

    /**
     * Configure authorization and authentication filters.
     *
     * @param \Cake\Event\EventInterface $event The beforeFilter event
     * @return void
     */
    public function beforeFilter(EventInterface $event)
    {
        parent::beforeFilter($event);
        $this->Authorization->authorizeModel('index', 'verifyQueue', 'gridData', 'verifyQueueGridData');
        $this->Authentication->allowUnauthenticated([
            'login',
            'approversList',
            'forgotPassword',
            'resetPassword',
            'register',
            'viewMobileCard',
            'viewMobileCardJson',
            'searchMembers',
            'publicProfile',
            'emailTaken',
            'autoComplete',
        ]);
    }

    #region general use calls

    /**
     * Begin impersonating another member (super user only).
     *
     * @param int $memberId Target member ID
     * @param \App\Services\ImpersonationService $impersonationService Session helper
     * @return \Cake\Http\Response|null
     * @throws \Cake\Http\Exception\ForbiddenException When current user is not super user
     * @throws \Cake\Http\Exception\BadRequestException When impersonation already active or invalid target
     */
    public function impersonate(int $memberId, ImpersonationService $impersonationService)
    {
        $this->request->allowMethod(['post']);
        $this->Authorization->skipAuthorization();

        $currentUser = $this->request->getAttribute('identity');
        if (!$currentUser || !$currentUser->isSuperUser()) {
            throw new ForbiddenException(__('Only super users may impersonate other members.'));
        }

        $session = $this->request->getSession();
        if ($impersonationService->isActive($session)) {
            throw new BadRequestException(__('You are already impersonating another member. Stop impersonating before starting a new session.'));
        }

        if ((int)$currentUser->id === $memberId) {
            throw new BadRequestException(__('You cannot impersonate your own account.'));
        }

        $member = $this->Members->find()
            ->where(['Members.id' => $memberId])
            ->first();

        if ($member === null) {
            throw new NotFoundException(__('Member not found.'));
        }

        $impersonationService->start($session, $currentUser, $member);

        $this->Authentication->setIdentity($member);
        $this->request = $this->request->withAttribute('identity', $member);

        $displayName = $member->sca_name ?: ($member->first_name ?? $member->email_address ?? (string)$member->id);
        $this->Flash->success(
            __('You are now impersonating {0}. All actions will use their permissions until you stop impersonating.', $displayName),
        );

        return $this->redirect(
            $this->referer(
                [
                    'controller' => 'Members',
                    'action' => 'view',
                    $member->id,
                ],
                true,
            ),
        );
    }

    /**
     * Stop impersonating and restore original super user identity.
     *
     * @param \App\Services\ImpersonationService $impersonationService Session helper
     * @return \Cake\Http\Response|null
     */
    public function stopImpersonating(ImpersonationService $impersonationService)
    {
        $this->request->allowMethod(['post']);
        $this->Authorization->skipAuthorization();

        $session = $this->request->getSession();
        $state = $impersonationService->getState($session);
        if ($state === null) {
            $this->Flash->info(__('You are not impersonating another member.'));

            return $this->redirect(
                $this->referer(
                    [
                        'controller' => 'Members',
                        'action' => 'index',
                    ],
                    true,
                ),
            );
        }

        $impersonationService->stop($session);

        try {
            $admin = $this->Members->get((int)$state['impersonator_id']);
        } catch (RecordNotFoundException $exception) {
            $this->Authentication->logout();
            $this->Flash->warning(__('Your original account could not be restored. Please log in again.'));

            return $this->redirect(['controller' => 'Members', 'action' => 'login']);
        }

        $this->Authentication->setIdentity($admin);
        $this->request = $this->request->withAttribute('identity', $admin);

        $adminDisplay = $admin->sca_name ?: ($admin->first_name ?? $admin->email_address ?? (string)$admin->id);
        $this->Flash->success(
            __('Impersonation ended. You are signed back in as {0}.', $adminDisplay),
        );

        return $this->redirect(
            $this->referer(
                [
                    'controller' => 'Members',
                    'action' => 'view',
                    $admin->id,
                ],
                true,
            ),
        );
    }

    /**
     * Display member listing with Dataverse grid (saved views, filters, sorting).
     *
     * @return \Cake\Http\Response|null|void
     */
    public function index()
    {
        // Simple index page - just renders the dv_grid element
        // The dv_grid element will lazy-load the actual data via gridData action
    }

    /**
     * Dataverse grid data endpoint for members listing.
     * Handles toolbar+table frame, table-only frame, and CSV export.
     *
     * @param \App\Services\CsvExportService $csvExportService CSV export service
     * @return \Cake\Http\Response|null|void
     */
    public function gridData(CsvExportService $csvExportService)
    {
        $identity = $this->request->getAttribute('identity');
        $canViewPii = $identity ? $identity->checkCan('viewPii', $this->Members->newEmptyEntity()) : false;
        $previousPiiSetting = MembersGridColumns::setIncludePii($canViewPii);

        try {
            $baseQuery = $this->Members->find()->contain(['Branches', 'Parents']);
            $baseQuery = $this->Authorization->applyScope($baseQuery, 'index');
            // Use unified trait for grid processing (saved views mode)
            $result = $this->processDataverseGrid([
                'gridKey' => 'Members.index.main',
                'gridColumnsClass' => MembersGridColumns::class,
                'baseQuery' => $baseQuery,
                'tableName' => 'Members',
                'defaultSort' => ['Members.sca_name' => 'asc'],
                'defaultPageSize' => 25,
                'showAllTab' => true,
                'canAddViews' => true,
                'canFilter' => true,
                'canExportCsv' => false,
            ]);

            // Handle CSV export
            if (!empty($result['isCsvExport'])) {
                return $this->handleCsvExport($result, $csvExportService, 'members');
            }

            // Set view variables
            $this->set([
                'members' => $result['data'],
                'gridState' => $result['gridState'],
                // Legacy variables (kept for backward compatibility during migration)
                'columns' => $result['columnsMetadata'],
                'visibleColumns' => $result['visibleColumns'],
                'searchableColumns' => MembersGridColumns::getSearchableColumns(),
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

            // Determine which template to render based on Turbo-Frame header
            $turboFrame = $this->request->getHeaderLine('Turbo-Frame');

            if ($turboFrame === 'members-grid-table') {
                // Inner frame request - render table data only
                $this->set('data', $result['data']);
                $this->set('tableFrameId', 'members-grid-table');
                $this->viewBuilder()->disableAutoLayout();
                $this->viewBuilder()->setTemplate('../element/dv_grid_table');
            } else {
                // Outer frame request (or no frame) - render toolbar + table frame
                $this->set('data', $result['data']);
                $this->set('frameId', 'members-grid');
                $this->viewBuilder()->disableAutoLayout();
                $this->viewBuilder()->setTemplate('../element/dv_grid_content');
            }
        } finally {
            MembersGridColumns::setIncludePii($previousPiiSetting);
        }
    }

    /**
     * Member roles grid data for Roles tab in member profile.
     *
     * @param int $memberId The member ID
     * @return \Cake\Http\Response|null|void
     */
    public function rolesGridData(int $memberId)
    {
        // Authorization check
        $member = $this->Members->get($memberId);
        $this->Authorization->authorize($member, 'view');

        // Get system views configuration
        $systemViews = \App\KMP\GridColumns\MemberRolesGridColumns::getSystemViews([]);

        // Debug: Log the base query
        $baseQuery = $this->fetchTable('MemberRoles')
            ->find()
            ->where(['MemberRoles.member_id' => $memberId])
            ->contain(['Roles', 'ApprovedBy', 'Branches']);

        \Cake\Log\Log::debug('Member Roles Base Query SQL: ' . $baseQuery->sql());
        \Cake\Log\Log::debug('Member Roles Base Query Params: ' . json_encode($baseQuery->getValueBinder()->bindings()));

        // Use unified trait for grid processing (system views mode)
        $result = $this->processDataverseGrid([
            'gridKey' => "Members.roles.{$memberId}",
            'gridColumnsClass' => \App\KMP\GridColumns\MemberRolesGridColumns::class,
            'baseQuery' => $baseQuery,
            'tableName' => 'MemberRoles',
            'defaultSort' => ['MemberRoles.start_on' => 'DESC'],
            'defaultPageSize' => 25,
            'systemViews' => $systemViews,
            'defaultSystemView' => 'sys-roles-active',
            'showAllTab' => false,
            'canAddViews' => false,
            'canFilter' => false,
            'canExportCsv' => false,
            'lockedFilters' => ['start_on', 'expires_on'],
            'showFilterPills' => false,
            'enableColumnPicker' => false,
        ]);

        \Cake\Log\Log::debug('Member Roles Result Count: ' . count($result['data']));

        // Set view variables
        $this->set([
            'memberRoles' => $result['data'],
            'gridState' => $result['gridState'],
            'member' => $member,
        ]);

        // Build URLs for grid
        $queryParams = $this->request->getQueryParams();
        $dataUrl = Router::url(['action' => 'rolesGridData', $memberId]);
        $tableDataUrl = $dataUrl;
        if (!empty($queryParams)) {
            $tableDataUrl .= '?' . http_build_query($queryParams);
        }

        // Determine which template to render based on Turbo-Frame header
        $turboFrame = $this->request->getHeaderLine('Turbo-Frame');
        $frameId = "member-roles-grid-{$memberId}";

        if ($turboFrame === "{$frameId}-table") {
            // Inner frame request - render table data only
            $this->set('data', $result['data']);
            $this->set('tableFrameId', "{$frameId}-table");
            $this->viewBuilder()->disableAutoLayout();
            $this->viewBuilder()->setTemplate('../element/dv_grid_table');
        } else {
            // Outer frame request - render toolbar + table frame
            $this->set('data', $result['data']);
            $this->set('frameId', $frameId);
            $this->set('dataUrl', $dataUrl);
            $this->set('tableDataUrl', $tableDataUrl);
            $this->viewBuilder()->disableAutoLayout();
            $this->viewBuilder()->setTemplate('../element/dv_grid_content');
        }
    }

    /**
     * Gathering attendances grid data for Gatherings tab in member profile.
     *
     * @param int $memberId The member ID
     * @return \Cake\Http\Response|null|void
     */
    public function gatheringsGridData(int $memberId)
    {
        // Authorization check
        $member = $this->Members->get($memberId);
        $this->Authorization->authorize($member, 'view');

        // Get system views configuration
        $systemViews = \App\KMP\GridColumns\GatheringAttendancesGridColumns::getSystemViews([]);

        // Use unified trait for grid processing (system views mode)
        $result = $this->processDataverseGrid([
            'gridKey' => "Members.gatherings.{$memberId}",
            'gridColumnsClass' => \App\KMP\GridColumns\GatheringAttendancesGridColumns::class,
            'baseQuery' => $this->fetchTable('GatheringAttendances')
                ->find()
                ->where(['GatheringAttendances.member_id' => $memberId])
                ->contain([
                    'Gatherings' => ['Branches', 'GatheringTypes']
                ]),
            'tableName' => 'GatheringAttendances',
            'defaultSort' => ['Gatherings.start_date' => 'DESC'],
            'defaultPageSize' => 25,
            'systemViews' => $systemViews,
            'defaultSystemView' => 'sys-gatherings-upcoming',
            'showAllTab' => false,
            'canAddViews' => false,
            'canFilter' => false,
            'canExportCsv' => false,
            'lockedFilters' => ['start_date', 'end_date'],
            'enableColumnPicker' => false,
            'showFilterPills' => false,
        ]);

        // Get row actions for the grid
        $rowActions = \App\KMP\GridColumns\GatheringAttendancesGridColumns::getRowActions();

        // Set view variables
        $this->set([
            'gatheringAttendances' => $result['data'],
            'gridState' => $result['gridState'],
            'member' => $member,
            'rowActions' => $rowActions,
        ]);

        // Build URLs for grid
        $queryParams = $this->request->getQueryParams();
        $dataUrl = Router::url(['action' => 'gatheringsGridData', $memberId]);
        $tableDataUrl = $dataUrl;
        if (!empty($queryParams)) {
            $tableDataUrl .= '?' . http_build_query($queryParams);
        }

        // Determine which template to render based on Turbo-Frame header
        $turboFrame = $this->request->getHeaderLine('Turbo-Frame');
        $frameId = "member-gatherings-grid-{$memberId}";

        if ($turboFrame === "{$frameId}-table") {
            // Inner frame request - render table data only
            $this->set('data', $result['data']);
            $this->set('tableFrameId', "{$frameId}-table");
            $this->viewBuilder()->disableAutoLayout();
            $this->viewBuilder()->setTemplate('../element/dv_grid_table');
        } else {
            // Outer frame request - render toolbar + table frame
            $this->set('data', $result['data']);
            $this->set('frameId', $frameId);
            $this->set('dataUrl', $dataUrl);
            $this->set('tableDataUrl', $tableDataUrl);
            $this->viewBuilder()->disableAutoLayout();
            $this->viewBuilder()->setTemplate('../element/dv_grid_content');
        }
    }

    /**
     * Return sub-row content for expandable grid rows.
     *
     * @param string|null $id Member ID
     * @param string|null $type Type of sub-row content (e.g., 'warrantreasons')
     * @return void
     * @throws \Cake\Http\Exception\NotFoundException
     */
    public function subRow(?string $id = null, ?string $type = null)
    {
        // This endpoint returns HTML fragments for sub-row content
        $this->viewBuilder()->disableAutoLayout();

        if (!$id || !$type) {
            throw new NotFoundException(__('Invalid request'));
        }

        // Load the member
        $member = $this->Members->get($id);

        // Render different content based on type
        switch ($type) {
            case 'warrantreasons':
                $reasons = $member->getNonWarrantableReasons();
                $this->set('reasons', $reasons);
                $this->render('/element/sub_rows/warrant_reasons');
                break;

            default:
                throw new NotFoundException(__('Unknown sub-row type: {0}', $type));
        }
    }

    /**
     * Legacy paginated member listing (deprecated, use index with Dataverse grid).
     * Supports search with Þ/th character conversion for medieval names.
     */
    private function legacyIndex()
    {
        $search = $this->request->getQuery('search');
        $sort = $this->request->getQuery('sort');
        $direction = $this->request->getQuery('direction');

        $query = $this->Members
            ->find()
            ->contain(['Branches'])
            ->select([
                'Members.id',
                'Members.sca_name',
                'Members.first_name',
                'Members.last_name',
                'Branches.name',
                'Members.status',
                'Members.email_address',
                'Members.last_login',
            ]);
        // if there is a search term, filter the query
        if ($search) {
            //detect th and replace with Þ
            $nsearch = $search;
            if (preg_match('/th/', $search)) {
                $nsearch = str_replace('th', 'Þ', $search);
            }
            //detect Þ and replace with th
            $usearch = $search;
            if (preg_match('/Þ/', $search)) {
                $usearch = str_replace('Þ', 'th', $search);
            }
            $query = $query->where([
                'OR' => [
                    ['Members.membership_number LIKE' => '%' . $search . '%'],
                    ['Members.sca_name LIKE' => '%' . $search . '%'],
                    ['Members.sca_name LIKE' => '%' . $nsearch . '%'],
                    ['Members.sca_name LIKE' => '%' . $usearch . '%'],
                    ['Members.first_name LIKE' => '%' . $search . '%'],
                    ['Members.last_name LIKE' => '%' . $search . '%'],
                    ['Members.email_address LIKE' => '%' . $search . '%'],
                    ['Branches.name LIKE' => '%' . $search . '%'],
                    ['Members.first_name LIKE' => '%' . $nsearch . '%'],
                    ['Members.last_name LIKE' => '%' . $nsearch . '%'],
                    ['Members.email_address LIKE' => '%' . $nsearch . '%'],
                    ['Branches.name LIKE' => '%' . $nsearch . '%'],
                    ['Members.first_name LIKE' => '%' . $usearch . '%'],
                    ['Members.last_name LIKE' => '%' . $usearch . '%'],
                    ['Members.email_address LIKE' => '%' . $usearch . '%'],
                    ['Branches.name LIKE' => '%' . $usearch . '%'],
                ],
            ]);
        }
        #is
        $query = $this->Authorization->applyScope($query);

        $this->paginate = [
            'sortableFields' => [
                'Branches.name',
                'sca_name',
                'first_name',
                'last_name',
                'email_address',
                'status',
                'last_login',
            ],
        ];

        $Members = $this->paginate($query, [
            'order' => [
                'sca_name' => 'asc',
            ],
        ]);

        $this->set(compact('Members', 'sort', 'direction', 'search'));
    }

    /**
     * Display member verification queue for administrative processing.
     * Shows members needing verification: card validation, age/parent verification.
     *
     * @return \Cake\Http\Response|null|void
     */
    public function verifyQueue()
    {
        // Simple index page - just renders the dv_grid element
        // The dv_grid element will lazy-load the actual data via verifyQueueGridData action
    }

    /**
     * Dataverse grid data endpoint for verification queue.
     * Handles toolbar+table frame, table-only frame for members needing verification.
     *
     * @return \Cake\Http\Response|null|void
     */
    public function verifyQueueGridData()
    {
        // Get system views configuration
        $systemViews = VerifyQueueGridColumns::getSystemViews([]);

        // Calculate counts for system views
        $systemViewCounts = $this->getVerifyQueueSystemViewCounts();

        // Append counts to system view tab names (JS reads the name string)
        foreach ($systemViews as $viewId => $view) {
            if (isset($systemViewCounts[$viewId])) {
                $systemViews[$viewId]['name'] = sprintf('%s (%d)', $view['name'], $systemViewCounts[$viewId]);
            }
        }

        // Build base query for members requiring verification
        $baseQuery = $this->Members
            ->find()
            ->contain(['Branches']);

        // Use unified trait for grid processing (system views mode)
        // Note: System view filters are automatically applied by the trait
        // based on the filters defined in getSystemViews()
        $result = $this->processDataverseGrid([
            'gridKey' => 'Members.verifyQueue.main',
            'gridColumnsClass' => VerifyQueueGridColumns::class,
            'baseQuery' => $baseQuery,
            'tableName' => 'Members',
            'defaultSort' => ['Members.sca_name' => 'asc'],
            'defaultPageSize' => 25,
            'systemViews' => $systemViews,
            'defaultSystemView' => 'sys-verify-youth',
            'showAllTab' => false,
            'canAddViews' => false,
            'canFilter' => true,
            'canExportCsv' => false,
            'enableColumnPicker' => false,
            'showFilterPills' => true,
        ]);

        // Set view variables
        $this->set([
            'data' => $result['data'],
            'members' => $result['data'],
            'gridState' => $result['gridState'],
        ]);

        // Determine which template to render based on Turbo-Frame header
        $turboFrame = $this->request->getHeaderLine('Turbo-Frame');
        $frameId = 'verify-queue-grid';

        if ($turboFrame === $frameId . '-table') {
            // Inner frame request - render table data only
            $this->set('tableFrameId', $frameId . '-table');
            $this->viewBuilder()->disableAutoLayout();
            $this->viewBuilder()->setTemplate('../element/dv_grid_table');
        } else {
            // Outer frame request (or no frame) - render toolbar + table frame
            $this->set('frameId', $frameId);
            $this->viewBuilder()->disableAutoLayout();
            $this->viewBuilder()->setTemplate('../element/dv_grid_content');
        }
    }

    /**
     * Get record counts for each verify queue system view.
     *
     * @return array<string, int>
     */
    protected function getVerifyQueueSystemViewCounts(): array
    {
        $counts = [];

        // Youth: minors requiring verification
        $counts['sys-verify-youth'] = $this->Members
            ->find()
            ->where([
                'Members.status IN' => [
                    Member::STATUS_UNVERIFIED_MINOR,
                    Member::STATUS_MINOR_MEMBERSHIP_VERIFIED,
                ],
            ])
            ->count();

        // Card Uploaded: active members with uploaded card
        $counts['sys-verify-with-card'] = $this->Members
            ->find()
            ->where([
                'Members.membership_card_path IS NOT' => null,
                'Members.membership_card_path !=' => '',
            ])
            ->count();

        // Without Card: active members without membership card
        $counts['sys-verify-without-card'] = $this->Members
            ->find()
            ->where([
                'Members.status' => Member::STATUS_ACTIVE,
            ])
            ->andWhere(function ($exp) {
                return $exp->or([
                    'Members.membership_card_path IS' => null,
                    'Members.membership_card_path' => '',
                ]);
            })
            ->count();

        return $counts;
    }

    /**
     * Apply base filter for verification queue (all items needing verification)
     *
     * @param \Cake\ORM\Query\SelectQuery $query Base query to filter
     * @return \Cake\ORM\Query\SelectQuery Filtered query
     */
    protected function applyVerifyQueueBaseFilter($query)
    {
        return $query->where([
            'Members.status IN' => [
                Member::STATUS_ACTIVE,
                Member::STATUS_UNVERIFIED_MINOR,
                Member::STATUS_MINOR_MEMBERSHIP_VERIFIED,
                Member::STATUS_MINOR_PARENT_VERIFIED,
            ]
        ]);
    }

    /**
     * Display detailed member profile with relationships and management tools.
     *
     * Loads roles, branch, parent, current/upcoming/previous role assignments,
     * and gathering attendances. Handles session-based form error display.
     *
     * @param string|null $id Member ID to display
     * @return \Cake\Http\Response|null|void
     * @throws \Cake\Http\Exception\NotFoundException When member not found
     */
    public function view(?string $id = null)
    {
        // Get Member Details
        $memberQuery = $this->Members
            ->find()
            ->contain([
                'Roles',
                'Branches' => function (SelectQuery $q) {
                    return $q->select(['Branches.name', 'Branches.id']);
                },
                'Parents' => function (SelectQuery $q) {
                    return $q->select(['Parents.sca_name', 'Parents.id']);
                },
                'UpcomingMemberRoles' => function (SelectQuery $q) {
                    return $this->_addRolesSelectAndContain($q);
                },
                'CurrentMemberRoles' => function (SelectQuery $q) {
                    return $this->_addRolesSelectAndContain($q);
                },
                'PreviousMemberRoles' => function (SelectQuery $q) {
                    return $this->_addRolesSelectAndContain($q);
                },
                'GatheringAttendances' => function (SelectQuery $q) {
                    return $q->contain([
                        'Gatherings' => function (SelectQuery $q) {
                            return $q->contain(['Branches', 'GatheringTypes'])
                                ->orderBy(['Gatherings.start_date' => 'ASC']);
                        }
                    ]);
                },
            ])
            ->where(['Members.id' => $id]);
        //$memberQuery = $this->Members->addJsonWhere($memberQuery, "Members.additional_info", "$.sports", "football");
        $member = $memberQuery->first();
        if (!$member) {
            throw new NotFoundException();
        }
        $this->Authorization->authorize($member, 'view');
        // Create the new Note form
        $session = $this->request->getSession();
        // Get the member form data for the edit modal
        $memberForm = $this->Members->get($id);
        // If there is form data in the session, patch the entity so we can show the errors
        $memberFormData = $session->consume('memberFormData');
        if ($memberFormData != null) {
            $this->Members->patchEntity($memberForm, $memberFormData);
        }
        // Get the password reset form data for the change password modal so we can show errors
        $passwordResetData = $session->consume('passwordResetData');
        $passwordReset = new ResetPasswordForm();
        if (!$passwordResetData == null) {
            $passwordReset->setData($passwordResetData);
            $passwordReset->validate($passwordResetData);
        }
        $months = array_reduce(range(1, 12), function ($rslt, $m) {
            $rslt[$m] = date('F', mktime(0, 0, 0, $m, 10));

            return $rslt;
        });
        $years = array_combine(range(date('Y'), date('Y') - 130), range(date('Y'), date('Y') - 130));
        $treeList = $this->Members->Branches
            ->find('list', keyPath: function ($entity) {
                return $entity->id . '|' . ($entity->can_have_members == 1 ? 'true' : 'false');
            })
            ->where(['can_have_members' => true])
            ->orderBy(['name' => 'ASC'])->toArray();
        $referer = $this->request->referer(true);
        $backUrl = [];
        $user =  $this->Authentication->getIdentity();
        $canManageMember = $user instanceof Member ? $user->canManageMember($member) : false;
        $canViewPii = $user ? $user->checkCan('viewPii', $member) : false;
        $canViewAdditionalInformation = $user ? $user->checkCan('viewAdditionalInformation', $member) : false;
        $statusList = [
            Member::STATUS_ACTIVE => Member::STATUS_ACTIVE,
            Member::STATUS_DEACTIVATED => Member::STATUS_DEACTIVATED,
            Member::STATUS_VERIFIED_MEMBERSHIP => Member::STATUS_VERIFIED_MEMBERSHIP,
            Member::STATUS_UNVERIFIED_MINOR => Member::STATUS_UNVERIFIED_MINOR,
            Member::STATUS_MINOR_MEMBERSHIP_VERIFIED => Member::STATUS_MINOR_MEMBERSHIP_VERIFIED,
            Member::STATUS_MINOR_PARENT_VERIFIED => Member::STATUS_MINOR_PARENT_VERIFIED,
            Member::STATUS_VERIFIED_MINOR => Member::STATUS_VERIFIED_MINOR,
        ];
        $publicInfo = $member->publicData();

        // Get available gatherings for attendance registration (started or future only, not cancelled)
        $now = new DateTime();

        // Get IDs of gatherings the member is already registered for
        $registeredGatheringIds = [];
        foreach ($member->gathering_attendances as $attendance) {
            $registeredGatheringIds[] = $attendance->gathering_id;
        }

        // Build query for available gatherings, excluding already registered ones and cancelled gatherings
        $query = $this->Members->GatheringAttendances->Gatherings
            ->find('list', keyField: 'id', valueField: function ($entity) {
                return $entity->name . ' (' . $entity->start_date->format('M d, Y') . ')';
            })
            ->where([
                'Gatherings.end_date >=' => $now,
                'Gatherings.cancelled_at IS' => null,
            ])
            ->contain(['Branches', 'GatheringTypes'])
            ->orderBy(['Gatherings.start_date' => 'ASC']);

        // Exclude gatherings the member is already registered for
        if (!empty($registeredGatheringIds)) {
            $query->where(['Gatherings.id NOT IN' => $registeredGatheringIds]);
        }

        $availableGatherings = $query->toArray();

        $children = $this->Members
            ->find()
            ->select([
                'Members.id',
                'Members.sca_name',
                'Members.first_name',
                'Members.last_name',
                'Members.birth_month',
                'Members.birth_year',
                'Members.status',
                'Members.parent_id',
            ])
            ->where(['Members.parent_id' => $member->id])
            ->orderBy(['Members.sca_name' => 'ASC'])
            ->toArray();
        $children = array_values(array_filter($children, function (Member $child) {
            return $child->age !== null && $child->age < 18;
        }));

        $this->set(
            compact(
                'member',
                'treeList',
                'passwordReset',
                'memberForm',
                'months',
                'years',
                'backUrl',
                'statusList',
                'publicInfo',
                'availableGatherings',
                'canViewPii',
                'canViewAdditionalInformation',
                'children',
                'canManageMember',
            ),
        );
        $this->viewBuilder()->setTemplate('view');
    }

    public function viewCard($id = null)
    {
        $member = $this->Members
            ->find()
            ->select('id')
            ->where(['Members.id' => $id])
            ->first();
        if (!$member) {
            throw new NotFoundException();
        }
        $this->Authorization->authorize($member);
        // sort by name
        $message_variables = [
            'secretary_email' => StaticHelpers::getAppSetting(
                'Activity.SecretaryEmail',
            ),
            'kingdom' => StaticHelpers::getAppSetting(
                'KMP.KingdomName',
            ),
            'secratary' => StaticHelpers::getAppSetting(
                'Activity.SecretaryName',
            ),
            'marshal_auth_graphic' => StaticHelpers::getAppSetting(
                'Member.ViewCard.Graphic',
            ),
            'marshal_auth_header_color' => StaticHelpers::getAppSetting(
                'Member.ViewCard.HeaderColor',
            ),
        ];
        $this->set(compact('member', 'message_variables'));
        $customTemplate = StaticHelpers::getAppSetting(
            'Member.ViewCard.Template',
        );
        $this->viewBuilder()->setTemplate($customTemplate);
    }

    public function viewMobileCard($id = null)
    {
        $inactiveStatuses = [
            Member::STATUS_DEACTIVATED,
            Member::STATUS_UNVERIFIED_MINOR,
            Member::STATUS_MINOR_MEMBERSHIP_VERIFIED,
        ];
        $member = $this->Members
            ->find()
            ->where(['Members.mobile_card_token' => $id, 'Members.status NOT IN' => $inactiveStatuses])
            ->first();
        if (!$member) {
            throw new NotFoundException();
        }

        // Authenticate user with mobile card token and persist session
        // This allows the user to access other features that require authentication
        $result = $this->Authentication->getResult();
        if ($result->isValid()) {
            // User authenticated via token - persist the session
            $this->Authentication->setIdentity($member);
        }

        $this->Authorization->skipAuthorization();
        // sort filter out expired member roles
        $message_variables = [
            'secretary_email' => StaticHelpers::getAppSetting(
                'Activity.SecretaryEmail',
            ),
            'kingdom' => StaticHelpers::getAppSetting(
                'KMP.KingdomName',
            ),
            'secratary' => StaticHelpers::getAppSetting(
                'Activity.SecretaryName',
            ),
            'marshal_auth_graphic' => StaticHelpers::getAppSetting(
                'Member.ViewCard.Graphic',
            ),
            'marshal_auth_header_color' => StaticHelpers::getAppSetting(
                'Member.MobileCard.BgColor',
            ),
        ];

        // Prepare watermark image for layout - build full URL for image
        $graphicPath = WWW_ROOT . 'img' . DS . $message_variables["marshal_auth_graphic"];
        if (file_exists($graphicPath)) {
            $watermarkimg = "data:image/gif;base64," . base64_encode(file_get_contents($graphicPath));
        } else {
            $watermarkimg = null;
        }

        // Build card URL for member-mobile-card-profile controller
        $cardUrl = Router::url(['controller' => 'Members', 'action' => 'viewMobileCardJson', $member->mobile_card_token], true);

        // Set layout variables for mobile_app layout
        $this->set('mobileTitle', 'Auth Card');
        $this->set('mobileSection', 'auth-card');
        $this->set('mobileIcon', 'bi-person-vcard');
        $this->set('watermarkImage', $watermarkimg);
        $this->set('showRefreshBtn', true); // Mobile card needs refresh button
        $this->set('cardUrl', $cardUrl); // For member-mobile-card-profile controller

        $this->set(compact('member', 'message_variables'));
        $customTemplate = StaticHelpers::getAppSetting(
            'Member.ViewMobileCard.Template',
        );
        $this->viewBuilder()
            ->setTemplate($customTemplate)
            ->setLayout('mobile_app');
    }

    /**
     * Create new member with age-based status and email notifications.
     *
     * Adults get STATUS_ACTIVE with password reset email.
     * Minors get STATUS_UNVERIFIED_MINOR requiring verification.
     * Generates mobile card token and sends appropriate notifications.
     *
     * @return \\Cake\\Http\\Response|null|void
     */
    public function add()
    {
        $member = $this->Members->newEmptyEntity();
        $this->Authorization->authorize($member);
        if ($this->request->is('post')) {
            $member = $this->Members->patchEntity(
                $member,
                $this->request->getData(),
            );
            $member->password = StaticHelpers::generateToken(32);
            if ($member->getErrors()) {
                $this->Flash->error(
                    __('The Member could not be saved. Please, try again.'),
                );
                $months = array_reduce(range(1, 12), function ($rslt, $m) {
                    $rslt[$m] = date('F', mktime(0, 0, 0, $m, 10));

                    return $rslt;
                });
                $years = array_combine(range(date('Y'), date('Y') - 130), range(date('Y'), date('Y') - 130));
                $treeList = $this->Members->Branches
                    ->find('list', keyPath: function ($entity) {
                        return $entity->id . '|' . ($entity->can_have_members == 1 ? 'true' : 'false');
                    })
                    ->where(['can_have_members' => true])
                    ->orderBy(['name' => 'ASC'])->toArray();
                $this->set(compact(
                    'member',
                    'treeList',
                    'months',
                    'years',
                ));

                return;
            }
            $member->mobile_card_token = StaticHelpers::generateToken(16);
            $member->password = StaticHelpers::generateToken(16);
            if ($member->age < 18) {
                $member->status = Member::STATUS_UNVERIFIED_MINOR;
            } else {
                $member->status = Member::STATUS_ACTIVE;
            }
            if ($this->Members->save($member)) {
                if ($member->age < 18) {
                    $this->Flash->success(__('The Member has been saved and the minor registration email has been sent for verification.'));
                    $this->getMailer('KMP')->send('notifySecretaryOfNewMinorMember', [$member]);
                } else {
                    $this->Flash->success(__("The Member has been saved. Please ask the member to use 'forgot password' to set their password."));
                }

                return $this->redirect(['action' => 'view', $member->id]);
            }
            $this->Flash->error(
                __('The Member could not be saved. Please, try again.'),
            );
        }
        $months = array_reduce(range(1, 12), function ($rslt, $m) {
            $rslt[$m] = date('F', mktime(0, 0, 0, $m, 10));

            return $rslt;
        });
        $years = array_combine(range(date('Y'), date('Y') - 130), range(date('Y'), date('Y') - 130));
        $treeList = $this->Members->Branches
            ->find('list', keyPath: function ($entity) {
                return $entity->id . '|' . ($entity->can_have_members == 1 ? 'true' : 'false');
            })
            ->where(['can_have_members' => true])
            ->orderBy(['name' => 'ASC'])->toArray();
        $this->set(compact(
            'member',
            'treeList',
            'months',
            'years',
        ));
    }

    /**
     * Edit method
     *
     * @param string|null $id Member id.
     * @return \Cake\Http\Response|null|void Redirects on successful edit, renders view otherwise.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function edit(?string $id = null)
    {
        $member = $this->Members->get($id);
        if (!$member) {
            throw new NotFoundException();
        }
        $this->Authorization->authorize($member);
        if ($this->request->is(['patch', 'post', 'put'])) {
            $member = $this->Members->patchEntity(
                $member,
                $this->request->getData(),
            );
            if ($member->getErrors()) {
                $session = $this->request->getSession();
                $session->write('memberFormData', $this->request->getData());

                return $this->redirect(['action' => 'view', $member->id]);
            }
            if ($member->membership_number == null || $member->membership_number == '') {
                $member->membership_expires_on = null;
                switch ($member->status) {
                    case Member::STATUS_VERIFIED_MEMBERSHIP:
                        $member->status = Member::STATUS_ACTIVE;
                        break;
                    case Member::STATUS_MINOR_MEMBERSHIP_VERIFIED:
                        $member->status = Member::STATUS_UNVERIFIED_MINOR;
                        break;
                    case Member::STATUS_VERIFIED_MINOR:
                        $member->status = Member::STATUS_MINOR_PARENT_VERIFIED;
                        break;
                }
            }
            if ($member->membership_expires_on != null && $member->membership_expires_on != '' && is_string($member->membership_expires_on)) {
                //convert to a date
                $member->membership_expires_on = DateTime::createFromFormat('Y-m-d', $member->membership_expires_on);
            }
            if ($this->Members->save($member)) {
                $this->Flash->success(__('The Member has been saved.'));

                return $this->redirect(['action' => 'view', $member->id]);
            }
            $this->Flash->error(
                __('The Member could not be saved. Please, try again.'),
            );
        }
        // $this->redirect(['action' => 'view', $member->id]);
    }

    /**
     * Delete method
     *
     * @param string|null $id Member id.
     * @return \Cake\Http\Response|null Redirects to index.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function delete(?string $id = null)
    {
        $this->request->allowMethod(['post', 'delete']);
        $member = $this->Members->get($id);
        if (!$member) {
            throw new NotFoundException();
        }
        $this->Authorization->authorize($member);

        $member->email_address = 'Deleted: ' . $member->email_address;
        if ($this->Members->delete($member)) {
            $this->Flash->success(__('The Member has been deleted.'));
        } else {
            $this->Flash->error(
                __('The Member could not be deleted. Please, try again.'),
            );
        }

        return $this->redirect(['action' => 'index']);
    }

    #endregion

    #region Member Specific calls

    public function sendMobileCardEmail($id = null)
    {
        $member = $this->Members->get($id);
        if (!$member) {
            throw new NotFoundException();
        }
        $this->Authorization->authorize($member);
        if ($member->mobile_card_token == null || $member->mobile_card_token == '') {
            $member->mobile_card_token = StaticHelpers::generateToken(16);
            $this->Members->save($member);
        }
        $url = Router::url([
            'controller' => 'Members',
            'action' => 'ViewMobileCard',
            'plugin' => null,
            '_full' => true,
            $member->mobile_card_token,
        ]);
        $vars = [
            'url' => $url,
        ];
        $this->queueMail('KMP', 'mobileCard', $member->email_address, $vars);
        $this->Flash->success(__('The email has been sent.'));

        return $this->redirect(['action' => 'view', $member->id]);
    }

    public function partialEdit($id = null)
    {
        $member = $this->Members->get($id);
        if (!$member) {
            throw new NotFoundException();
        }
        $this->Authorization->authorize($member);
        if ($this->request->is(['patch', 'post', 'put'])) {
            $member->title = $this->request->getData('title');
            $member->sca_name = $this->request->getData('sca_name');
            $member->pronunciation = $this->request->getData('pronunciation');
            $member->pronouns = $this->request->getData('pronouns');
            $member->branch_id = $this->request->getData('branch_id');
            $member->first_name = $this->request->getData('first_name');
            $member->middle_name = $this->request->getData('middle_name');
            $member->last_name = $this->request->getData('last_name');
            $member->street_address = $this->request->getData('street_address');
            $member->city = $this->request->getData('city');
            $member->state = $this->request->getData('state');
            $member->zip = $this->request->getData('zip');
            $member->phone_number = $this->request->getData('phone_number');
            $member->email_address = $this->request->getData('email_address');
            //$member->parent_name = $this->request->getData("parent_name");
            if ($member->getErrors()) {
                $session = $this->request->getSession();
                $session->write('memberFormData', $this->request->getData());

                return $this->redirect(['action' => 'view', $member->id]);
            }
            if ($this->Members->save($member)) {
                $this->Flash->success(__('The Member has been saved.'));

                return $this->redirect(['action' => 'view', $member->id]);
            }
            $this->Flash->error(
                __('The Member could not be saved. Please, try again.'),
            );
        }
        $this->redirect(['action' => 'view', $member->id]);
    }

    /**
     * Profile method
     * 
     * Shows the current user's profile without changing the URL
     *
     * @return \Cake\Http\Response|null|void Renders view
     */
    public function profile()
    {
        $user = $this->Authentication->getIdentity();
        if (!$user) {
            throw new NotFoundException(__('User not authenticated.'));
        }

        return $this->view((string)$user->id);
    }

    public function editAdditionalInfo($id = null)
    {
        $member = $this->Members->get($id);
        if (!$member) {
            throw new NotFoundException();
        }
        $this->Authorization->authorize($member);
        $user = $this->Authentication->getIdentity();
        $userEditableOnly = !$user->checkCan('edit', $member);
        if ($this->request->is(['patch', 'post', 'put'])) {
            $member->additional_info = [];
            $aiFormConfig = StaticHelpers::getAppSettingsStartWith('Member.AdditionalInfo.');
            $aiForm = [];
            if (empty($aiFormConfig)) {
                $this->Flash->error(
                    __('The Additional Information could not be saved. Please, try again.'),
                );

                return $this->redirect(['action' => 'view', $member->id]);
            }
            foreach ($aiFormConfig as $key => $value) {
                $shortKey = str_replace('Member.AdditionalInfo.', '', $key);
                $aiForm[$shortKey] = $value;
            }
            foreach ($aiForm as $fieldKey => $fieldType) {
                $userEditable = false;
                //check fieldType for |
                $pipePos = strpos($fieldType, '|');
                if ($pipePos !== false) {
                    $fieldSecDetails = explode('|', $fieldType);
                    $fieldType = $fieldSecDetails[0];
                    $userEditable = $fieldSecDetails[1] == 'user';
                }
                if ($userEditableOnly && !$userEditable) {
                    continue;
                }
                $colonPos = strpos($fieldType, ':');
                $aiOptions = [];
                if ($colonPos !== false) {
                    $fieldDetails = explode(':', $fieldType);
                    $fieldType =  $fieldDetails[0];
                    $aiOptions = explode(',', $fieldDetails[1]);
                }
                //if aiOptions are not emoty then check the value is one of the options
                $fieldValue = $this->request->getData($fieldKey);
                if (!empty($aiOptions)) {
                    if ($fieldValue != '' && !in_array($fieldValue, $aiOptions)) {
                        $this->Flash->error(
                            __('The Additional Information could not be saved. Please, try again.'),
                        );

                        return $this->redirect(['action' => 'view', $member->id]);
                    }
                }
                if ($fieldValue != '') {
                    $newData[$fieldKey] = $this->request->getData($fieldKey);
                }
            }
            $member->additional_info = $newData;
            if ($this->Members->save($member)) {
                $this->Flash->success(__('The Additional Information saved.'));

                return $this->redirect(['action' => 'view', $member->id]);
            }
            $this->Flash->error(
                __('The Additional Information could not be saved. Please, try again.'),
            );
        }

        return $this->redirect(['action' => 'view', $member->id]);
    }

    #endregion

    #region ASYNC calls

    public function searchMembers()
    {
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
        $this->Authorization->skipAuthorization();
        $this->request->allowMethod(['get']);
        $this->viewBuilder()->setClassName('Ajax');
        $query = $this->Members
            ->find('all')
            ->where([
                'status <>' => Member::STATUS_DEACTIVATED,
                'OR' => [['sca_name LIKE' => "%$q%"], ['sca_name LIKE' => "%$nq%"], ['sca_name LIKE' => "%$uq%"]],
            ])
            ->select(['id', 'sca_name'])
            ->limit(10);
        //$query = $this->Authorization->applyScope($query);
        $this->response = $this->response
            ->withType('application/json')
            ->withStringBody(json_encode($query));

        return $this->response;
    }

    public function viewCardJson($id = null)
    {
        $member = $this->Members
            ->find()
            ->select([
                'Members.id',
                'Members.sca_name',
                'Members.title',
                'Members.first_name',
                'Members.last_name',
                'Members.membership_number',
                'Members.membership_expires_on',
                'Members.background_check_expires_on',
                'Members.additional_info',
            ])
            ->contain([
                'Branches' => function (SelectQuery $q) {
                    return $q->select(['Branches.name']);
                },
            ])
            ->where(['Members.id' => $id])
            ->first();
        if (!$member) {
            throw new NotFoundException();
        }
        if ($member->title) {
            $member->sca_name = $member->title . ' ' . $member->sca_name;
        }
        $this->Authorization->authorize($member);
        $this->viewBuilder()
            ->setClassName('Ajax')
            ->setOption('serialize', 'responseData');
        $this->set(compact('member'));
    }

    public function viewMobileCardJson($id = null)
    {
        $inactiveStatuses = [
            Member::STATUS_DEACTIVATED,
            Member::STATUS_UNVERIFIED_MINOR,
            Member::STATUS_MINOR_MEMBERSHIP_VERIFIED,
        ];
        $member = $this->Members
            ->find()
            ->select([
                'Members.id',
                'Members.title',
                'Members.sca_name',
                'Members.first_name',
                'Members.last_name',
                'Members.membership_number',
                'Members.membership_expires_on',
                'Members.background_check_expires_on',
                'Members.additional_info',
            ])
            ->contain([
                'Branches' => function (SelectQuery $q) {
                    return $q->select(['Branches.name']);
                },
            ])
            ->where(['Members.mobile_card_token' => $id, 'Members.status NOT IN' => $inactiveStatuses])
            ->first();
        if (!$member) {
            throw new NotFoundException();
        }
        if ($member->title) {
            $member->sca_name = $member->title . ' ' . $member->sca_name;
        }
        $this->Authorization->skipAuthorization();
        $this->viewBuilder()
            ->setClassName('Ajax')
            ->setOption('serialize', 'responseData');
        $this->set(compact('member'));
        $this->viewBuilder()->setTemplate('view_card_json');
    }

    public function publicProfile($publicId = null)
    {
        $member = $this->Members
            ->find('byPublicId', [$publicId])
            ->first();
        if (!$member) {
            throw new NotFoundException();
        }
        $this->Authorization->skipAuthorization();
        $publicProfile = $member->publicData();
        $this->response = $this->response
            ->withType('application/json')
            ->withStringBody(json_encode($publicProfile));

        return $this->response;
    }

    public function autoComplete()
    {
        //TODO: Audit for Privacy

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

        $this->Authorization->skipAuthorization();
        $this->request->allowMethod(['get']);
        $this->viewBuilder()->setClassName('Ajax');
        $query = $this->Members
            ->find('all')
            ->where([
                'status <>' => Member::STATUS_DEACTIVATED,
                'OR' => [['sca_name LIKE' => "%$q%"], ['sca_name LIKE' => "%$nq%"], ['sca_name LIKE' => "%$uq%"]],
            ])
            ->select(['id', 'public_id', 'sca_name'])
            ->limit(50);
        $this->set(compact('query', 'q', 'nq', 'uq'));
    }

    public function emailTaken()
    {
        $email = $this->request->getQuery('email');
        $this->Authorization->skipAuthorization();
        $this->request->allowMethod(['get']);
        $this->viewBuilder()->setClassName('Ajax');
        $emailUsed = $this->Members
            ->find('all')
            ->where(['email_address' => $email])
            ->count();
        $result = '';
        if ($emailUsed > 0) {
            $result = true;
        } else {
            $result = false;
        }
        $this->response = $this->response
            ->withType('application/json')
            ->withStringBody(json_encode($result));

        return $this->response;
    }

    #endregion

    #region Password specific calls

    public function changePassword($id = null)
    {
        $member = $this->Members->get($id);
        if (!$member) {
            throw new NotFoundException();
        }
        $this->Authorization->authorize($member);
        $passwordReset = new ResetPasswordForm();
        if ($this->request->is(['patch', 'post', 'put'])) {
            $passwordReset->validate($this->request->getData());
            if ($passwordReset->getErrors()) {
                $session = $this->request->getSession();
                $session->write('passwordResetData', $this->request->getData());

                return $this->redirect(['action' => 'view', $member->id]);
            }
            $member->password = $this->request->getData()['new_password'];
            $member->password_token = null;
            $member->password_token_expires_on = null;
            $member->failed_login_attempts = 0;
            if ($this->Members->save($member)) {
                $this->Flash->success(__('The password has been changed.'));

                return $this->redirect(['action' => 'view', $member->id]);
            }
            $this->Flash->error(
                __('The password could not be changed. Please, try again.'),
            );
        }
    }

    public function forgotPassword()
    {
        $this->Authorization->skipAuthorization();
        if ($this->request->is('post')) {
            $member = $this->Members
                ->find()
                ->where([
                    'email_address' => $this->request->getData('email_address'),
                ])
                ->first();
            if ($member) {
                $member->password_token = StaticHelpers::generateToken(32);
                $member->password_token_expires_on = DateTime::now()->addDays(
                    1,
                );
                $this->Members->save($member);
                $url = Router::url([
                    'controller' => 'Members',
                    'action' => 'resetPassword',
                    'plugin' => null,
                    '_full' => true,
                    $member->password_token,
                ]);
                $vars = [
                    'url' => $url,
                ];
                $this->queueMail('KMP', 'resetPassword', $member->email_address, $vars);
                $this->Flash->success(
                    __(
                        'Password reset request sent to ' .
                            $member->email_address,
                    ),
                );

                return $this->redirect(['action' => 'login']);
            } else {
                $this->Flash->error(
                    __(
                        'Your email was not found, please contact the Marshalate Secretary at ' .
                            StaticHelpers::getAppSetting(
                                'Activity.SecretaryEmail',
                            ),
                    ),
                );
            }
        }
        $headerImage = StaticHelpers::getAppSetting(
            'KMP.Login.Graphic',
        );
        $this->set(compact('headerImage'));
    }

    public function resetPassword($token = null)
    {
        $this->Authorization->skipAuthorization();
        $member = $this->Members
            ->find()
            ->where(['password_token' => $token])
            ->first();
        if ($member) {
            if ($member->password_token_expires_on < DateTime::now()) {
                $this->Flash->error('Invalid Token, please request a new one.');

                return $this->redirect(['action' => 'forgotPassword']);
            }
            $passwordReset = new ResetPasswordForm();
            if (
                $this->request->is('post') &&
                $passwordReset->validate($this->request->getData())
            ) {
                $member->password = $this->request->getData('new_password');
                $member->password_token = null;
                $member->password_token_expires_on = null;
                $member->failed_login_attempts = 0;
                $this->Members->save($member);
                $this->Flash->success(__('Password successfully reset'));

                return $this->redirect(['action' => 'login']);
            }
            $headerImage = StaticHelpers::getAppSetting(
                'KMP.Login.Graphic',
            );
            $this->set(compact('headerImage', 'passwordReset'));
        } else {
            $this->Flash->error('Invalid Token, please request a new one.');

            return $this->redirect(['action' => 'forgotPassword']);
        }
    }

    #endregion

    #region Authorization specific calls

    /**
     * login logic
     */
    public function login()
    {
        $this->Authorization->skipAuthorization();
        if ($this->request->is('post')) {
            $authentication = $this->request->getAttribute('authentication');
            $result = $authentication->getResult();
            // regardless of POST or GET, redirect if user is logged in
            if ($result->isValid()) {
                $user = $this->Members->get(
                    $authentication->getIdentity()->getIdentifier(),
                );
                $this->Flash->success('Welcome ' . $user->sca_name . '!');
                $page = $this->request->getQuery('redirect');
                if (
                    $page == '/' ||
                    $page == '/Members/login' ||
                    $page == '/Members/logout' ||
                    $page == null
                ) {
                    // Detect mobile phone and redirect to auth card if applicable
                    $userAgent = $this->request->getHeaderLine('User-Agent');
                    if (StaticHelpers::isMobilePhone($userAgent) && $user->mobile_card_token) {
                        // Set view mode to mobile in session
                        $this->request->getSession()->write('viewMode', 'mobile');
                        return $this->redirect(['action' => 'viewMobileCard', $user->mobile_card_token]);
                    }
                    return $this->redirect(['action' => 'profile']);
                } else {
                    return $this->redirect($page);
                }
            }
            $errors = $result->getErrors();
            if (
                isset($errors['KMPBruteForcePassword']) &&
                count($errors['KMPBruteForcePassword']) > 0
            ) {
                $message = $errors['KMPBruteForcePassword'][0];
                switch ($message) {
                    case 'Account Locked':
                        $this->Flash->error(
                            'Your account has been locked. Please try again later.',
                        );
                        break;
                    case 'Account Not Verified':
                        $contactAddress = StaticHelpers::getAppSetting(
                            'Members.AccountVerificationContactEmail',
                        );
                        $this->Flash->error(
                            'Your account is being verified. This process may take several days after you have verified your email address. Please contact ' . $contactAddress . ' if you have not been verified within a week.',
                        );
                        break;
                    case 'Account Disabled':
                        $contactAddress = StaticHelpers::getAppSetting(
                            'Members.AccountDisabledContactEmail',
                        );
                        $this->Flash->error(
                            'Your account deactivated. Please contact ' . $contactAddress . ' if you feel this is in error.',
                        );
                        break;
                    default:
                        $this->Flash->error(
                            'Your email or password is incorrect.',
                        );
                        break;
                }
            } else {
                $this->Flash->error('Your email or password is incorrect.');
            }
        }
        $headerImage = StaticHelpers::getAppSetting(
            'KMP.Login.Graphic',
        );
        $allowRegistration = StaticHelpers::getAppSetting(
            'KMP.EnablePublicRegistration',
        );
        $this->set(compact('headerImage', 'allowRegistration'));
    }

    public function logout()
    {
        $this->Authorization->skipAuthorization();
        $this->Authentication->logout();

        return $this->redirect([
            'controller' => 'Members',
            'action' => 'login',
        ]);
    }
    public function submitScaMemberInfo()
    {
        $user = $this->Authentication->getIdentity();
        $targetMemberId = $this->request->getData('member_id');
        if (empty($targetMemberId)) {
            $targetMemberId = $user?->id;
        }
        $member = $this->Members->get((string)$targetMemberId);
        if (!$member) {
            throw new NotFoundException();
        }
        $this->Authorization->authorize($member);
        if ($this->request->is('put')) {
            $file = $this->request->getData('member_card');
            if ($file->getSize() > 0) {
                // Validate file type before saving (security fix)
                $allowedTypes = ['image/png', 'image/jpeg', 'image/jpg', 'image/pjpeg'];
                $clientMediaType = $file->getClientMediaType();
                if (!in_array($clientMediaType, $allowedTypes)) {
                    $this->Flash->error(__('Invalid file type. Only PNG and JPEG images are allowed.'));
                    return $this->redirect($this->referer());
                }
                $ext = strtolower(pathinfo($file->getClientFilename(), PATHINFO_EXTENSION));
                if (!in_array($ext, ['png', 'jpg', 'jpeg'])) {
                    $this->Flash->error(__('Invalid file extension. Only .png, .jpg, .jpeg are allowed.'));
                    return $this->redirect($this->referer());
                }

                // Server-side content validation using finfo
                $tempPath = $file->getStream()->getMetadata('uri');
                $finfo = new \finfo(FILEINFO_MIME_TYPE);
                $actualMimeType = $finfo->file($tempPath);
                if (!in_array($actualMimeType, ['image/png', 'image/jpeg'])) {
                    $this->Flash->error(__('File content does not match an allowed image type.'));
                    return $this->redirect($this->referer());
                }

                $storageLoc = WWW_ROOT . '../images/uploaded/';
                $fileName = StaticHelpers::generateToken(10);
                StaticHelpers::ensureDirectoryExists($storageLoc, 0755);
                $file->moveTo(WWW_ROOT . '../images/uploaded/' . $fileName);
                $fileResult = StaticHelpers::saveScaledImage($fileName, 500, 700, $storageLoc, $storageLoc);
                if (!$fileResult) {
                    $this->Flash->error('Error saving image, please try again.');
                }
                //trim the path off of the filename
                $fileName = substr($fileResult, strrpos($fileResult, '/') + 1);
                $member->membership_card_path = $fileName;
                if ($this->Members->save($member)) {
                    $this->Flash->success(__('Membership information has been submitted, please allow several days for our team to review and update the profile.'));
                } else {
                    $this->Flash->error("There was an error please try again.");
                }
            }
        }
        $this->redirect($this->referer());
    }

    public function register()
    {
        $allowRegistration = StaticHelpers::getAppSetting(
            'KMP.EnablePublicRegistration',
        );
        if (strtolower($allowRegistration) != 'yes') {
            $this->Flash->error(
                'Public registration is not allowed at this time.',
            );

            return $this->redirect(['action' => 'login']);
        }
        $member = $this->Members->newEmptyEntity();
        $this->Authorization->skipAuthorization();
        $this->Authentication->logout();
        if ($this->request->is('post')) {
            $file = $this->request->getData('member_card');
            if ($file->getSize() > 0) {
                // Validate file type before saving (security fix)
                $allowedTypes = ['image/png', 'image/jpeg', 'image/jpg', 'image/pjpeg'];
                $clientMediaType = $file->getClientMediaType();
                if (!in_array($clientMediaType, $allowedTypes)) {
                    $this->Flash->error(__('Invalid file type. Only PNG and JPEG images are allowed.'));
                    $this->set(compact('member'));
                    return;
                }
                $ext = strtolower(pathinfo($file->getClientFilename(), PATHINFO_EXTENSION));
                if (!in_array($ext, ['png', 'jpg', 'jpeg'])) {
                    $this->Flash->error(__('Invalid file extension. Only .png, .jpg, .jpeg are allowed.'));
                    $this->set(compact('member'));
                    return;
                }

                $storageLoc = WWW_ROOT . '../images/uploaded/';
                $fileName = StaticHelpers::generateToken(10);
                StaticHelpers::ensureDirectoryExists($storageLoc, 0755);
                $file->moveTo(WWW_ROOT . '../images/uploaded/' . $fileName);
                $fileResult = StaticHelpers::saveScaledImage($fileName, 500, 700, $storageLoc, $storageLoc);
                if (!$fileResult) {
                    $this->Flash->error('Error saving image, please try again.');
                }
                //trim the path off of the filename
                $fileName = substr($fileResult, strrpos($fileResult, '/') + 1);
                $member->membership_card_path = $fileName;
            }
            $member->sca_name = $this->request->getData('sca_name');
            $member->branch_id = $this->request->getData('branch_id');
            $member->first_name = $this->request->getData('first_name');
            $member->middle_name = $this->request->getData('middle_name');
            $member->last_name = $this->request->getData('last_name');
            $member->street_address = $this->request->getData('street_address');
            $member->city = $this->request->getData('city');
            $member->state = $this->request->getData('state');
            $member->zip = $this->request->getData('zip');
            $member->phone_number = $this->request->getData('phone_number');
            $member->email_address = $this->request->getData('email_address');
            $member->birth_month = (int)$this->request->getData('birth_month');
            $member->birth_year = (int)$this->request->getData('birth_year');
            if ($member->age > 17) {
                $member->password_token = StaticHelpers::generateToken(32);
                $member->password_token_expires_on = DateTime::now()->addDays(1);
            }
            $member->password = StaticHelpers::generateToken(12);
            if ($member->getErrors()) {
                $this->Flash->error(
                    __('The Member could not be saved. Please, try again.'),
                );

                return $this->redirect(['action' => 'login']);
            }
            if ($member->age > 17) {
                $member->status = Member::STATUS_ACTIVE;
            } else {
                $member->status = Member::STATUS_UNVERIFIED_MINOR;
            }
            $member->mobile_card_token = StaticHelpers::generateToken(16);
            if ($this->Members->save($member)) {
                if ($member->age > 17) {
                    $url = Router::url([
                        'controller' => 'Members',
                        'action' => 'resetPassword',
                        'plugin' => null,
                        '_full' => true,
                        $member->password_token,
                    ]);
                    $vars = [
                        'url' => $url,
                        'sca_name' => $member->sca_name,
                    ];
                    $this->queueMail('KMP', 'newRegistration', $member->email_address, $vars);
                    $url = Router::url([
                        'controller' => 'Members',
                        'action' => 'view',
                        'plugin' => null,
                        '_full' => true,
                        $member->id,
                    ]);
                    $vars = [
                        'url' => $url,
                        'sca_name' => $member->sca_name,
                    ];
                    if ($member->membership_card_path != null && strlen($member->membership_card_path) > 0) {
                        $vars['membershipCardPresent'] = true;
                    } else {
                        $vars['membershipCardPresent'] = false;
                    }
                    $this->queueMail('KMP', 'notifySecretaryOfNewMember', $member->email_address, $vars);
                    $this->Flash->success(__('Your registration has been submitted. Please check your email for a link to set up your password.'));
                } else {
                    $url = Router::url([
                        'controller' => 'Members',
                        'action' => 'view',
                        'plugin' => null,
                        '_full' => true,
                        $member->id,
                    ]);
                    $vars = [
                        'url' => $url,
                        'sca_name' => $member->sca_name,
                    ];
                    if ($member->membership_card_path != null && strlen($member->membership_card_path) > 0) {
                        $vars['membershipCardPresent'] = true;
                    } else {
                        $vars['membershipCardPresent'] = false;
                    }
                    $this->queueMail('KMP', 'notifySecretaryOfNewMinorMember', $member->email_address, $vars);
                    $this->Flash->success(__('Your registration has been submitted. The Kingdom Secretary will need to verify your account with your parent or guardian'));
                }

                return $this->redirect(['action' => 'login']);
            }
            $this->Flash->error(
                __('The Member could not be saved. Please, try again.'),
            );
        }
        $headerImage = StaticHelpers::getAppSetting(
            'KMP.Login.Graphic',
        );
        $months = array_reduce(range(1, 12), function ($rslt, $m) {
            $rslt[$m] = date('F', mktime(0, 0, 0, $m, 10));

            return $rslt;
        });
        $years = array_combine(range(date('Y'), date('Y') - 130), range(date('Y'), date('Y') - 130));
        $treeList = $this->Members->Branches
            ->find('list', keyPath: function ($entity) {
                return $entity->id . '|' . ($entity->can_have_members == 1 ? 'true' : 'false');
            })
            ->where(['can_have_members' => true])
            ->orderBy(['name' => 'ASC'])->toArray();

        $this->set(compact('member', 'treeList', 'months', 'years', 'headerImage'));
    }

    #endregion

    #region Import/Export calls

    /**
     * Import Member Expiration dates from CSV based on Membership number
     */
    public function importExpirationDates()
    {
        $this->Authorization->authorize($this->Members->newEmptyEntity());
        if ($this->request->is('post')) {
            $file = $this->request->getData('importData');
            $file = $file->getStream()->getMetadata('uri');
            $csv = array_map('str_getcsv', file($file));
            $this->Members->getConnection()->begin();
            foreach ($csv as $row) {
                if (
                    $row[0] == 'Member Number' ||
                    $row[1] == 'Expiration Date'
                ) {
                    continue;
                }

                $member = $this->Members
                    ->find()
                    ->where(['membership_number' => $row[0]])
                    ->first();
                if ($member) {
                    $member->membership_expires_on = new DateTime($row[1]);
                    $member->setDirty('membership_expires_on', true);
                    if (!$this->Members->save($member)) {
                        $this->Members->getConnection()->rollback();
                        $this->Flash->error(
                            __(
                                'Error saving member expiration date at ' .
                                    $row[0] .
                                    ' with date ' .
                                    $row[1] .
                                    '. All modified have been rolled back.',
                            ),
                        );

                        return;
                    }
                }
            }
            $this->Members->getConnection()->commit();
            $this->Flash->success(__('Expiration dates imported successfully'));
        }
    }

    #endregion

    #region Verification calls

    public function verifyMembership($id = null)
    {
        $member = $this->Members->get($id);
        $this->Authorization->authorize($member);
        if ($this->request->is(['patch', 'post', 'put'])) {
            $verifyMembership = $this->request->getData('verify_membership');
            $verifyParent = $this->request->getData('verify_parent');
            if ($verifyMembership == '1') {
                $membership_number = $this->request->getData('membership_number');
                if (strlen($membership_number) == 0) {
                    $this->Flash->error('Membership number is required.');

                    return $this->redirect(['action' => 'view', $member->id]);
                }
                $member->membership_expires_on = $this->request->getData('membership_expires_on');
                if ($member->membership_expires_on == null) {
                    $this->Flash->error('Membership expiration date is required.');

                    return $this->redirect(['action' => 'view', $member->id]);
                }
                $member->membership_number = $membership_number;
                $member->membership_expires_on = $this->request->getData('membership_expires_on');
                if ($member->membership_expires_on != null && $member->membership_expires_on != '' && is_string($member->membership_expires_on)) {
                    //convert to a date
                    $member->membership_expires_on = DateTime::createFromFormat('Y-m-d', $member->membership_expires_on);
                }
            }
            if ($member->age < 18 && $verifyParent == '1') {
                $parentId = $this->request->getData('parent_id');
                if ($parentId) {
                    if ($parentId && strlen($parentId) > 0) {
                        $parent = $this->Members->find()->where(['public_id' => $parentId])->first();
                    }
                    if ($parentId == $member->id) {
                        $this->Flash->error('Parent cannot be the same as the member.');

                        return $this->redirect(['action' => 'view', $member->id]);
                    }
                    if ($parent->age < 18) {
                        $this->Flash->error('Parent must be an adult.');

                        return $this->redirect(['action' => 'view', $member->id]);
                    }
                    $member->parent_id = $parent->id;
                } else {
                    $this->Flash->error('Parent is required for minors.');

                    return $this->redirect(['action' => 'view', $member->id]);
                }
            }
            //if the member is an adult and the membership was validated then set the status to active
            if ($member->age > 17 && $verifyMembership == '1') {
                $member->status = Member::STATUS_VERIFIED_MEMBERSHIP;
            }
            //if the member is a minor and the parent was validated then set the status to verified minor
            if ($member->age < 18 && $verifyParent == '1' && $verifyMembership == '1') {
                $member->status = Member::STATUS_VERIFIED_MINOR;
            }
            //if the member is a minor and the parent was validated then set the status to parent validataed
            if ($member->age < 18 && $verifyParent == '1' && $verifyMembership != '1') {
                //if the member is already membership verified then set to minor verified
                if ($member->status == Member::STATUS_MINOR_MEMBERSHIP_VERIFIED) {
                    $member->status = Member::STATUS_VERIFIED_MINOR;
                } else {
                    $member->status = Member::STATUS_MINOR_PARENT_VERIFIED;
                }
            }
            //if the the member is a minor and the parent was not validated by the membership was then set the status to minor membership verified
            if ($member->age < 18 && $verifyParent != '1' && $verifyMembership == '1') {
                if ($member->status == Member::STATUS_MINOR_PARENT_VERIFIED) {
                    $member->status = Member::STATUS_VERIFIED_MINOR;
                } else {
                    $member->status = Member::STATUS_MINOR_MEMBERSHIP_VERIFIED;
                }
            }
            $image = $member->membership_card_path;
            $deleteImage =  $member->status == Member::STATUS_VERIFIED_MEMBERSHIP ||
                $member->status == Member::STATUS_VERIFIED_MINOR ||
                $member->status == Member::STATUS_MINOR_MEMBERSHIP_VERIFIED;

            $member->verified_by = $this->Authentication->getIdentity()->getIdentifier();
            $member->verified_date = DateTime::now();
            if ($deleteImage) {
                $member->membership_card_path = null;
            }
            if (!$this->Members->save($member)) {
                $this->Flash->error(
                    __('The Member could not be verified. Please, try again.'),
                );
                $this->redirect(['action' => 'view', $member->id]);
            }
            if ($image != null && $deleteImage) {
                $image = WWW_ROOT . '../images/uploaded/' . $image;
                $member->membership_card_path = null;
                if (!StaticHelpers::deleteFile($image)) {
                    $this->Flash->error('Error deleting image, please try again.');

                    return $this->redirect(['action' => 'view', $member->id]);
                }
            }
        }
        $this->Flash->success(__('The Membership has been verified.'));

        return $this->redirect(['action' => 'view', $member->id]);
    }

    #endregion

    #region protected

    protected function _addRolesSelectAndContain(SelectQuery $q)
    {
        return $q
            ->select([
                'id',
                'member_id',
                'role_id',
                'start_on',
                'expires_on',
                'role_id',
                'approver_id',
                'entity_type',
            ])
            ->contain([
                'Roles' => function (SelectQuery $q) {
                    return $q->select(['Roles.name']);
                },
                'ApprovedBy' => function (SelectQuery $q) {
                    return $q->select(['ApprovedBy.sca_name']);
                },
                'Branches' => function (SelectQuery $q) {
                    return $q->select(['Branches.name']);
                },
            ]);
    }

    #endregion
}
