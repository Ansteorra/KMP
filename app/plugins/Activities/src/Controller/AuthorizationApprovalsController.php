<?php

declare(strict_types=1);

namespace Activities\Controller;

use Activities\Services\AuthorizationManagerInterface;
use Activities\KMP\GridColumns\AuthorizationApprovalsGridColumns;
use Activities\KMP\GridColumns\AuthorizationApproverRollupGridColumns;
use App\Controller\DataverseGridTrait;
use App\Services\CsvExportService;
use Cake\Mailer\MailerAwareTrait;
use Cake\Event\EventInterface;
use App\KMP\StaticHelpers;

/**
 * AuthorizationApprovals Controller - Approval Workflow Management
 *
 * Manages authorization approval queues, multi-level approval processing, and workflow oversight.
 * Handles approve/deny operations, personal queues, and administrative analytics.
 *
 * @property \Activities\Model\Table\AuthorizationApprovalsTable $AuthorizationApprovals
 * @package Activities\Controller
 */
class AuthorizationApprovalsController extends AppController
{
    use MailerAwareTrait;
    use DataverseGridTrait;

    /**
     * Configure authorization and component setup.
     *
     * @param \Cake\Event\EventInterface $event The beforeFilter event
     * @return void
     */
    public function beforeFilter(EventInterface $event)
    {
        parent::beforeFilter($event);
        $this->Authorization->authorizeModel('index', 'gridData', 'myQueue', 'view', 'myQueueGridData', 'viewGridData');
    }
    /**
     * Display approval queue analytics and approver management dashboard.
     *
     * @return \Cake\Http\Response|null|void
     */
    public function index(): void
    {
        // Grid content loads asynchronously via gridData()
    }

    /**
     * Provides Dataverse grid data for authorization approver rollup listing.
     *
     * @param \App\Services\CsvExportService $csvExportService Injected CSV export service
     * @return \Cake\Http\Response|null|void Renders grid view or CSV response
     */
    public function gridData(CsvExportService $csvExportService)
    {
        $baseQuery = $this->AuthorizationApprovals
            ->find()
            ->innerJoinWith('Approvers');

        $func = $baseQuery->func();

        $baseQuery->select([
            'id' => 'Approvers.id',
            'approver_name' => 'Approvers.sca_name',
            'last_login' => 'Approvers.last_login',
            'pending_count' => $func->count('CASE WHEN AuthorizationApprovals.responded_on IS NULL THEN 1 END'),
            'approved_count' => $func->count('CASE WHEN AuthorizationApprovals.approved = 1 THEN 1 END'),
            'denied_count' => $func->count('CASE WHEN AuthorizationApprovals.approved = 0  && AuthorizationApprovals.responded_on IS NOT NULL THEN 1 END'),
        ])
            ->groupBy(['Approvers.id', 'Approvers.sca_name', 'Approvers.last_login'])
            ->enableAutoFields(false);

        $result = $this->processDataverseGrid([
            'gridKey' => 'Activities.AuthorizationApprovals.index.main',
            'gridColumnsClass' => AuthorizationApproverRollupGridColumns::class,
            'baseQuery' => $baseQuery,
            'tableName' => 'AuthorizationApprovals',
            'defaultSort' => ['Approvers.sca_name' => 'ASC'],
            'defaultPageSize' => 25,
            'showAllTab' => false,
            'canAddViews' => false,
            'canFilter' => true,
            'canExportCsv' => false,
            'showFilterPills' => true,
            'enableColumnPicker' => false,
        ]);

        $this->set([
            'authorizationApprovalRollup' => $result['data'],
            'gridState' => $result['gridState'],
            'rowActions' => AuthorizationApproverRollupGridColumns::getRowActions(),
        ]);
        $this->set('data', $result['data']);

        $turboFrame = $this->request->getHeaderLine('Turbo-Frame');

        $this->viewBuilder()->setPlugin(null);

        if ($turboFrame === 'authorization-approvals-grid-table') {
            $this->set('tableFrameId', 'authorization-approvals-grid-table');
            $this->viewBuilder()->disableAutoLayout();
            $this->viewBuilder()->setTemplatePath('element');
            $this->viewBuilder()->setTemplate('dv_grid_table');
        } else {
            $this->set('frameId', 'authorization-approvals-grid');
            $this->viewBuilder()->disableAutoLayout();
            $this->viewBuilder()->setTemplatePath('element');
            $this->viewBuilder()->setTemplate('dv_grid_content');
        }
    }

    /**
     * Display the authenticated approver's personal approval queue, optionally filtered by an email access token.
     *
     * When a token is provided, the queue is restricted to approvals matching that token to support secure
     * email-based access; otherwise the method shows all pending approvals assigned to the authenticated user.
     *
     * @param string|null $token Optional authorization token from an email notification used to filter the queue.
     * @see \Activities\Controller\AuthorizationApprovalsController::getAuthorizationApprovalsQuery() For query construction.
     * @since Activities Plugin 1.0.0
     */
    public function myQueue($token = null)
    {
        $member_id = $this->Authentication->getIdentity()->getIdentifier();
        $queueFor = $this->Authentication->getIdentity()->sca_name;
        $isMyQueue = true;
        $this->set(compact("queueFor", "isMyQueue", "member_id", "token"));
    }

    /**
     * Provides Dataverse grid data for my-queue with system views for pending/approved/denied.
     *
     * @param \App\Services\CsvExportService $csvExportService Injected CSV export service
     * @return \Cake\Http\Response|null|void
     */
    public function myQueueGridData(CsvExportService $csvExportService)
    {
        $member_id = $this->Authentication->getIdentity()->getIdentifier();
        $token = $this->request->getQuery('token');

        // Build base query
        $baseQuery = $this->getAuthorizationApprovalsQuery($member_id);

        if ($token) {
            $baseQuery = $baseQuery->where(["authorization_token" => $token]);
        }

        // Define system views for pending/approved/denied tabs
        $systemViews = AuthorizationApprovalsGridColumns::getSystemViews([]);

        // Calculate counts for system view badges
        $systemViewCounts = $this->getQueueSystemViewCounts($member_id, $token);

        // Use unified trait for grid processing
        $result = $this->processDataverseGrid([
            'gridKey' => 'Activities.AuthorizationApprovals.myQueue',
            'gridColumnsClass' => AuthorizationApprovalsGridColumns::class,
            'baseQuery' => $baseQuery,
            'tableName' => 'AuthorizationApprovals',
            'defaultSort' => ['AuthorizationApprovals.requested_on' => 'asc'],
            'defaultPageSize' => 25,
            'systemViews' => $systemViews,
            'defaultSystemView' => 'pending',
            'showAllTab' => false,
            'canAddViews' => false,
            'canFilter' => true,
            'canExportCsv' => false,
            'lockedFilters' => ['responded_on', 'approved'],
            'showFilterPills' => true,
        ]);

        // Add system view counts to gridState for badge display
        if (!empty($result['gridState']['view']['available'])) {
            foreach ($result['gridState']['view']['available'] as &$view) {
                if (!empty($systemViewCounts[$view['id']])) {
                    $view['count'] = $systemViewCounts[$view['id']];
                }
            }
        }

        // Handle CSV export
        if (!empty($result['isCsvExport'])) {
            return $this->handleCsvExport($result, $csvExportService, 'my-authorization-queue', 'Activities.AuthorizationApprovals');
        }

        // Set view variables
        $this->set([
            'authorizationApprovals' => $result['data'],
            'gridState' => $result['gridState'],
            'isMyQueue' => true,
        ]);
        $this->set('data', $result['data']);
        $this->set('customElement', 'Activities.authorization_approvals_table');

        // Determine which template to render based on Turbo-Frame header
        $turboFrame = $this->request->getHeaderLine('Turbo-Frame');

        // Use main app's element templates (not plugin templates)
        $this->viewBuilder()->setPlugin(null);

        if ($turboFrame === 'my-queue-grid-table') {
            // Inner frame request - render table data only
            $this->set('tableFrameId', 'my-queue-grid-table');
            $this->viewBuilder()->disableAutoLayout();
            $this->viewBuilder()->setTemplatePath('element');
            $this->viewBuilder()->setTemplate('dv_grid_table');
        } else {
            // Outer frame request (or no frame) - render toolbar + table frame
            $this->set('frameId', 'my-queue-grid');
            $this->viewBuilder()->disableAutoLayout();
            $this->viewBuilder()->setTemplatePath('element');
            $this->viewBuilder()->setTemplate('dv_grid_content');
        }
    }

    /**
     * Mobile-optimized approval queue interface for processing authorization requests.
     * 
     * Provides a mobile-friendly interface for approvers to view and process their pending
     * authorization approval requests. Uses the mobile_app layout for consistent PWA experience.
     * 
     * @return void
     */
    public function mobileApproveAuthorizations()
    {
        // Get current user
        $currentUser = $this->Authentication->getIdentity();
        if (!$currentUser) {
            $this->Flash->error(__('You must be logged in to approve authorizations.'));
            return $this->redirect(['controller' => 'Members', 'action' => 'login', 'plugin' => null]);
        }

        // Get pending approvals for this approver
        $member_id = $currentUser->getIdentifier();
        $query = $this->getAuthorizationApprovalsQuery($member_id);
        $this->Authorization->applyScope($query);
        $authorizationApprovals = $query->all();

        // Set view variables
        $queueFor = $currentUser->sca_name;
        $isMyQueue = true;
        $this->set(compact('queueFor', 'isMyQueue', 'authorizationApprovals'));

        // Use mobile app layout for consistent UX
        $this->viewBuilder()->setLayout('mobile_app');
        $this->set('mobileTitle', 'Approve Auths');
        $this->set('mobileSection', 'approvals');
        $this->set('mobileIcon', 'bi-check-circle');
        $this->set('mobileBackUrl', $this->request->referer());
        $this->set('showRefreshBtn', true);
    }

    /**
     * Renders a mobile-optimized approval form for an authorization and processes approval submissions.
     *
     * Displays authorization request details and whether further approvals are required. If the request is a POST,
     * attempts to perform the approval via the provided authorization manager and redirects to the approver's mobile card on success;
     * on failure it renders the form with an error flash message.
     *
     * @param \Activities\Services\AuthorizationManagerInterface $maService Authorization management service used to perform approve operations.
     * @param string|null $id Authorization Approval ID; if null the value is read from request data.
     * @return \Cake\Http\Response|null A redirect response when the approval succeeds, or null after rendering the form or when approval fails.
     */
    public function mobileApprove(AuthorizationManagerInterface $maService, $id = null)
    {
        $this->request->allowMethod(['get', 'post']);

        if (!$id) {
            $id = $this->request->getData('id');
        }

        // Load authorization approval with all required data
        $authorizationApproval = $this->AuthorizationApprovals->get($id, [
            'contain' => [
                'Authorizations' => [
                    'Members',
                    'Activities'
                ]
            ]
        ]);

        if (!$authorizationApproval) {
            throw new \Cake\Http\Exception\NotFoundException();
        }

        $this->Authorization->authorize($authorizationApproval);

        // Handle POST - process approval
        if ($this->request->is('post')) {
            $approverId = $this->Authentication->getIdentity()->getIdentifier();
            $nextApproverId = $this->request->getData('next_approver_id');

            $maResult = $maService->approve(
                (int)$id,
                (int)$approverId,
                (int)$nextApproverId
            );

            if (!$maResult->success) {
                $this->Flash->error(__('The authorization approval could not be approved. Please try again.'));
            } else {
                $this->Flash->success(__('The authorization has been approved.'));

                // Redirect to approver's mobile card
                $approver = $this->AuthorizationApprovals->Approvers->get($approverId, ['fields' => ['id', 'mobile_card_token']]);
                return $this->redirect([
                    'controller' => 'Members',
                    'action' => 'viewMobileCard',
                    'plugin' => null,
                    $approver->mobile_card_token
                ]);
            }
        }

        // GET - display form
        // Check if more approvals are needed
        $authorization = $authorizationApproval->authorization;
        $authsNeeded = $authorization->is_renewal
            ? $authorization->activity->num_required_renewers
            : $authorization->activity->num_required_authorizors;
        $hasMoreApprovalsToGo = ($authsNeeded - $authorization->approval_count) > 1;

        $this->set(compact('authorizationApproval', 'hasMoreApprovalsToGo'));

        // Use mobile app layout
        $this->viewBuilder()->setLayout('mobile_app');
        $this->set('mobileTitle', 'Approve Auth');
        $this->set('mobileSection', 'approvals');
        $this->set('mobileIcon', 'bi-check-circle');
        $this->set('mobileBackUrl', ['action' => 'mobileApproveAuthorizations']);
        $this->set('showRefreshBtn', false);
    }

    /**
     * Mobile-optimized denial form interface.
     * 
     * Displays authorization request details and allows approver to deny with reason.
     * Handles both GET (display form) and POST (process denial).
     * 
     * @param \Activities\Services\AuthorizationManagerInterface $maService Authorization management service
     * @param string|null $id Authorization Approval ID
     * @return \Cake\Http\Response|null
     */
    public function mobileDeny(AuthorizationManagerInterface $maService, $id = null)
    {
        $this->request->allowMethod(['get', 'post']);

        if (!$id) {
            $id = $this->request->getData('id');
        }

        // Load authorization approval with all required data
        $authorizationApproval = $this->AuthorizationApprovals->get($id, [
            'contain' => [
                'Authorizations' => [
                    'Members',
                    'Activities'
                ]
            ]
        ]);

        if (!$authorizationApproval) {
            throw new \Cake\Http\Exception\NotFoundException();
        }

        $this->Authorization->authorize($authorizationApproval);

        // Handle POST - process denial
        if ($this->request->is('post')) {
            $approverId = $this->Authentication->getIdentity()->getIdentifier();
            $approverNotes = $this->request->getData('approver_notes');

            if (empty($approverNotes)) {
                $this->Flash->error(__('Please provide a reason for denial.'));
            } else {
                $maResult = $maService->deny(
                    (int)$id,
                    (int)$approverId,
                    $approverNotes
                );

                if (!$maResult->success) {
                    $this->Flash->error(__('The authorization approval could not be denied. Please try again.'));
                } else {
                    $this->Flash->success(__('The authorization has been denied.'));

                    // Redirect to approver's mobile card
                    $approver = $this->AuthorizationApprovals->Approvers->get($approverId, ['fields' => ['id', 'mobile_card_token']]);
                    return $this->redirect([
                        'controller' => 'Members',
                        'action' => 'viewMobileCard',
                        'plugin' => null,
                        $approver->mobile_card_token
                    ]);
                }
            }
        }

        // GET - display form
        $this->set(compact('authorizationApproval'));

        // Use mobile app layout
        $this->viewBuilder()->setLayout('mobile_app');
        $this->set('mobileTitle', 'Deny Authorization');
        $this->set('mobileSection', 'approvals');
        $this->set('mobileIcon', 'bi-x-circle');
        $this->set('mobileBackUrl', ['action' => 'mobileApproveAuthorizations']);
        $this->set('showRefreshBtn', false);
    }

    /**
     * Display approval queue for a specific approver.
     *
     * @param string|null $id Approver member ID
     * @return \Cake\Http\Response|null|void
     */
    public function view($id = null)
    {
        $queueFor = $this->AuthorizationApprovals->Approvers->find()
            ->select(['sca_name'])
            ->where(['id' => $id])
            ->first()->sca_name;
        $isMyQueue = false;
        $this->set(compact("queueFor", "isMyQueue", "id"));
    }

    /**
     * Provides Dataverse grid data for view queue with system views for pending/approved/denied.
     *
     * @param \App\Services\CsvExportService $csvExportService Injected CSV export service
     * @param string|null $id Approver member ID
     * @return \Cake\Http\Response|null|void
     */
    public function viewGridData(CsvExportService $csvExportService, $id = null)
    {
        if ($id === null) {
            $id = $this->request->getQuery('approver_id');
        }

        // Build base query
        $baseQuery = $this->getAuthorizationApprovalsQuery($id);

        // Define system views for pending/approved/denied tabs
        $systemViews = AuthorizationApprovalsGridColumns::getSystemViews([]);

        // Calculate counts for system view badges
        $systemViewCounts = $this->getQueueSystemViewCounts((int)$id, null);

        // Use unified trait for grid processing
        $result = $this->processDataverseGrid([
            'gridKey' => 'Activities.AuthorizationApprovals.view',
            'gridColumnsClass' => AuthorizationApprovalsGridColumns::class,
            'baseQuery' => $baseQuery,
            'tableName' => 'AuthorizationApprovals',
            'defaultSort' => ['AuthorizationApprovals.requested_on' => 'asc'],
            'defaultPageSize' => 25,
            'systemViews' => $systemViews,
            'defaultSystemView' => 'pending',
            'showAllTab' => false,
            'canAddViews' => false,
            'canFilter' => true,
            'canExportCsv' => false,
            'lockedFilters' => ['responded_on', 'approved'],
            'showFilterPills' => true,
        ]);

        // Add system view counts to gridState for badge display
        if (!empty($result['gridState']['view']['available'])) {
            foreach ($result['gridState']['view']['available'] as &$view) {
                if (!empty($systemViewCounts[$view['id']])) {
                    $view['count'] = $systemViewCounts[$view['id']];
                }
            }
        }

        // Handle CSV export
        if (!empty($result['isCsvExport'])) {
            return $this->handleCsvExport($result, $csvExportService, 'authorization-queue', 'Activities.AuthorizationApprovals');
        }

        // Set view variables
        $this->set([
            'authorizationApprovals' => $result['data'],
            'gridState' => $result['gridState'],
            'isMyQueue' => false,
            'approver_id' => $id,
        ]);
        $this->set('data', $result['data']);
        $this->set('customElement', 'Activities.authorization_approvals_table');

        // Determine which template to render based on Turbo-Frame header
        $turboFrame = $this->request->getHeaderLine('Turbo-Frame');

        // Use main app's element templates (not plugin templates)
        $this->viewBuilder()->setPlugin(null);

        if ($turboFrame === 'view-queue-grid-table') {
            // Inner frame request - render table data only
            $this->set('tableFrameId', 'view-queue-grid-table');
            $this->viewBuilder()->disableAutoLayout();
            $this->viewBuilder()->setTemplatePath('element');
            $this->viewBuilder()->setTemplate('dv_grid_table');
        } else {
            // Outer frame request (or no frame) - render toolbar + table frame
            $this->set('frameId', 'view-queue-grid');
            $this->viewBuilder()->disableAutoLayout();
            $this->viewBuilder()->setTemplatePath('element');
            $this->viewBuilder()->setTemplate('dv_grid_content');
        }
    }

    /**
     * Build query for authorization approvals with related data.
     *
     * @param int $memberId The approver member ID to filter by
     * @return \Cake\ORM\Query
     */
    protected function getAuthorizationApprovalsQuery($memberId)
    {
        $query = $this->AuthorizationApprovals
            ->find()
            ->contain([
                "Authorizations" => function ($q) {
                    return $q->select([
                        "Authorizations.status",
                        "Authorizations.approval_count",
                        "Authorizations.is_renewal",
                    ]);
                },
                "Authorizations.Members" => function ($q) {
                    return $q->select(["Members.sca_name", "Members.membership_number", "Members.membership_expires_on", "Members.background_check_expires_on"]);
                },
                "Authorizations.Activities" => function ($q) {
                    return $q->select([
                        "Activities.name",
                        "Activities.num_required_authorizors",
                        "Activities.num_required_renewers",
                    ]);
                },
                "Approvers" => function ($q) {
                    return $q->select(["Approvers.sca_name"]);
                },
            ])
            ->where(["approver_id" => $memberId]);

        return $query;
    }

    /**
     * Process authorization approval and assign next approver if needed.
     *
     * @param \Activities\Services\AuthorizationManagerInterface $maService Authorization management service
     * @param string|null $id Authorization Approval ID
     * @return \Cake\Http\Response|null Redirect to referer
     */
    public function approve(
        AuthorizationManagerInterface $maService,
        $id = null,
    ) {
        if ($id == null) {
            $id = $this->request->getData("id");
        }
        $this->request->allowMethod(["post"]);

        $authorizationApproval = $this->AuthorizationApprovals->get($id);
        if (!$authorizationApproval) {
            throw new \Cake\Http\Exception\NotFoundException();
        }
        $this->Authorization->authorize($authorizationApproval);

        $approverId = $this->Authentication->getIdentity()->getIdentifier();
        $nextApproverId = $this->request->getData("next_approver_id");
        $nextApproverId = ($nextApproverId === null || $nextApproverId === '') ? null : (int)$nextApproverId;
        $maResult = $maService->approve(
            (int)$id,
            (int)$approverId,
            $nextApproverId,
        );
        if (!$maResult->success) {
            $this->Flash->error(
                __(
                    "The authorization approval could not be approved. Please, try again.",
                ),
            );
            $response = $this->redirect($this->referer());
            return $response->withHeader('Turbo-Frame', '_top');
        }
        $this->Flash->success(
            __("The authorization approval has been processed."),
        );
        $response = $this->redirect($this->referer());
        return $response->withHeader('Turbo-Frame', '_top');
    }

    /**
     * Get available approvers for next approval step, excluding previous approvers.
     *
     * @param string $id Authorization Approval ID
     * @return \Cake\Http\Response JSON response with available approvers
     */
    public function availableApproversList($id)
    {
        $this->request->allowMethod(["get"]);
        $this->viewBuilder()->setClassName("Ajax");
        $authorizationApproval = $this->AuthorizationApprovals->get(
            $id,
            contain: [
                "Authorizations" => function ($q) {
                    return $q->select(["Authorizations.activity_id", 'Authorizations.member_id']);
                },
                "Authorizations.Activities" => function ($q) {
                    return $q->select(["Activities.id", "Activities.permission_id"]);
                },
            ],
        );
        if (!$authorizationApproval) {
            throw new \Cake\Http\Exception\NotFoundException();
        }
        $this->Authorization->authorize($authorizationApproval);
        $previousApprovers = $this->AuthorizationApprovals
            ->find(
                "list",
                keyField: "approver_id",
                valueField: "approver_id"
            )
            ->where([
                "authorization_id" => $authorizationApproval->authorization_id,
            ])
            ->select(["approver_id"])
            ->all()
            ->toList();
        $memberId = $this->Authentication->getIdentity()->getIdentifier();
        $previousApprovers[] = $memberId;
        $previousApprovers[] = $authorizationApproval->authorization->member_id;
        $query = $authorizationApproval->authorization->activity->getApproversQuery(-1000000);
        $result = $query
            ->contain(["Branches"])
            ->where(["Members.id NOT IN " => $previousApprovers])
            ->orderBy(["Branches.name", "Members.sca_name"])
            ->select(["Members.id", "Members.sca_name", "Branches.name"])
            ->distinct()
            ->all()
            ->toArray();
        $responseData = [];
        foreach ($result as $member) {
            $responseData[] = [
                "id" => $member->id,
                "sca_name" => $member->branch->name . ": " . $member->sca_name,
            ];
        }
        $this->response = $this->response
            ->withType("application/json")
            ->withStringBody(json_encode($responseData));

        return $this->response;
    }

    /**
     * Process a denial for an authorization approval and redirect back to the referring page.
     *
     * Calls the AuthorizationManager service to perform denial processing (including recording approver notes,
     * terminating the approval workflow, triggering notifications, and writing audit information), flashes a
     * success or error message for the user, and returns a redirect to the referrer.
     *
     * @param \Activities\Services\AuthorizationManagerInterface $maService Authorization management service used to execute denial logic
     * @param string|null $id Authorization Approval ID; if null the id will be read from request data
     * @return \Cake\Http\Response|null Redirect response to the referring page or null
     * @throws \Cake\Http\Exception\NotFoundException When the specified approval entity does not exist
     * @throws \Cake\Http\Exception\MethodNotAllowedException When the request method is not POST
     * @throws \Authorization\Exception\ForbiddenException When the current user is not authorized to deny the approval
     * @since Activities Plugin 1.0.0
     */
    public function deny(AuthorizationManagerInterface $maService, $id = null)
    {
        $this->request->allowMethod(["post"]);
        if ($id == null) {
            $id = $this->request->getData("id");
        }
        $authorizationApproval = $this->AuthorizationApprovals->get($id);
        if (!$authorizationApproval) {
            throw new \Cake\Http\Exception\NotFoundException();
        }
        $this->Authorization->authorize($authorizationApproval);
        $maResult = $maService->deny(
            (int)$id,
            $this->Authentication->getIdentity()->getIdentifier(),
            $this->request->getData("approver_notes"),
        );
        if (
            !$maResult->success
        ) {
            $this->Flash->error(
                __(
                    "The authorization approval could not be rejected. Please, try again.",
                ),
            );
        } else {
            $this->Flash->success(
                __("The authorization approval has been rejected."),
            );
        }
        $response = $this->redirect($this->referer());
        return $response->withHeader('Turbo-Frame', '_top');
    }

    /**
     * Get counts for authorization approval queue system views
     *
     * @param int $approverId Approver member ID to get counts for
     * @param string|null $token Optional token to filter by
     * @return array<string, int> System view ID => count mapping
     */
    protected function getQueueSystemViewCounts(int $approverId, ?string $token = null): array
    {
        $counts = [];

        // Base query for this approver
        $baseQuery = $this->getAuthorizationApprovalsQuery($approverId);
        if ($token) {
            $baseQuery = clone $baseQuery;
            $baseQuery = $baseQuery->where(["authorization_token" => $token]);
        }

        // Pending: Requests awaiting approval
        $pendingQuery = clone $baseQuery;
        $counts['pending'] = $pendingQuery
            ->where(['AuthorizationApprovals.responded_on IS' => null])
            ->count();
        return $counts;
    }
}
