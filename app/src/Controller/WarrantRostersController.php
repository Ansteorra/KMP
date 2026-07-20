<?php
declare(strict_types=1);

namespace App\Controller;

use App\KMP\GridColumns\WarrantRostersGridColumns;
use App\Services\CsvExportService;
use App\Services\WarrantManager\WarrantManagerInterface;
use App\Services\WarrantManager\WarrantRequest;
use Cake\Http\Exception\NotFoundException;
use Cake\I18n\DateTime;
use Cake\ORM\TableRegistry;

/**
 * Manages warrant roster batches and multi-level approval workflows.
 *
 * Handles CRUD for roster batches and individual warrant management within
 * rosters. Workflow approvals are handled through the unified approvals queue.
 *
 * @property \App\Model\Table\WarrantRostersTable $WarrantRosters
 */
class WarrantRostersController extends AppController
{
    use DataverseGridTrait;

    /**
     * Configure authorization for roster operations.
     *
     * @return void
     */
    public function initialize(): void
    {
        parent::initialize();

        // Load authorization component for policy-based access control
        $this->loadComponent('Authorization.Authorization');

        // Enable automatic model authorization for index and gridData operations
        $this->Authorization->authorizeModel('index', 'gridData');
    }

    /**
     * Index method - Main warrant roster dashboard
     *
     * Simple index page that renders the dv_grid element.
     * The grid lazy-loads actual data via the gridData action.
     *
     * @return \Cake\Http\Response|null|void Renders view
     */
    public function index()
    {
        // Simple index page - just renders the dv_grid element
        // The dv_grid element will lazy-load the actual data via gridData action
    }

    /**
     * Grid Data method - Provides Dataverse grid data for warrant rosters
     *
     * Returns grid content with toolbar and table for the warrant rosters grid.
     * Supports status tabs for filtering by Pending, Approved, or Declined status.
     * Handles both outer frame (toolbar + table frame) and inner frame
     * (table only) requests. Also supports CSV export.
     *
     * @param \App\Services\CsvExportService $csvExportService Injected CSV export service
     * @return \Cake\Http\Response|null|void Renders view or returns CSV response
     */
    public function gridData(CsvExportService $csvExportService)
    {
        // Build query with warrant count subquery and creator info
        $warrantsTable = TableRegistry::getTableLocator()->get('Warrants');
        $warrantCountSubquery = $warrantsTable->find()
            ->select(['count' => $warrantsTable->find()->func()->count('*')])
            ->where(['Warrants.warrant_roster_id = WarrantRosters.id']);

        $baseQuery = $this->WarrantRosters->find()
            ->select($this->WarrantRosters)
            ->select(['warrant_count' => $warrantCountSubquery])
            ->contain(['CreatedByMember' => function ($q) {
                return $q->select(['id', 'sca_name']);
            }]);

        // Apply authorization scoping
        $baseQuery = $this->Authorization->applyScope($baseQuery);

        // Define system views for status filtering
        $systemViews = WarrantRostersGridColumns::getSystemViews([]);

        // Use unified trait for grid processing
        $result = $this->processDataverseGrid([
            'gridKey' => 'WarrantRosters.index.main',
            'gridColumnsClass' => WarrantRostersGridColumns::class,
            'baseQuery' => $baseQuery,
            'tableName' => 'WarrantRosters',
            'defaultSort' => ['WarrantRosters.created' => 'desc'],
            'defaultPageSize' => 25,
            'systemViews' => $systemViews,
            'defaultSystemView' => 'sys-roster-pending',
            'showAllTab' => true,
            'canAddViews' => false,
            'canFilter' => true,
            'canExportCsv' => false,
            'lockedFilters' => ['status'],
            'showFilterPills' => true,
            'showSearchBox' => true,
        ]);

        // Handle CSV export
        if (!empty($result['isCsvExport'])) {
            return $this->handleCsvExport($result, $csvExportService, 'warrant-rosters');
        }

        // Set view variables
        $this->set([
            'warrantRosters' => $result['data'],
            'gridState' => $result['gridState'],
            'columns' => $result['columnsMetadata'],
            'visibleColumns' => $result['visibleColumns'],
            'searchableColumns' => WarrantRostersGridColumns::getSearchableColumns(),
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

        if ($turboFrame === 'warrant-rosters-grid-table') {
            // Inner frame request - render table data only
            $this->set('data', $result['data']);
            $this->set('tableFrameId', 'warrant-rosters-grid-table');
            $this->viewBuilder()->disableAutoLayout();
            $this->viewBuilder()->setTemplate('../element/dv_grid_table');
        } else {
            // Outer frame request (or no frame) - render toolbar + table frame
            $this->set('data', $result['data']);
            $this->set('frameId', 'warrant-rosters-grid');
            $this->viewBuilder()->disableAutoLayout();
            $this->viewBuilder()->setTemplate('../element/dv_grid_content');
        }
    }

    /**
     * List rosters filtered by status with warrant counts.
     *
     * @param string $state Roster status filter (pending, approved, declined)
     * @return void
     */
    public function allRosters($state)
    {
        // Build base query with creator information
        $query = $this->WarrantRosters->find()
            ->contain(['CreatedByMember' => function ($q) {
                return $q->select(['id', 'sca_name']); // Minimal member data for performance
            }]);

        // Add warrant counting with matching for rosters that have warrants
        $query = $query->matching('Warrants')
            ->select([
                'id',
                'name',
                'status',
                'approvals_required',
                'approval_count',
                'created',
                'warrant_count' => $query->func()->count('Warrants.id'), // Aggregate warrant count
            ])
            ->groupBy(['WarrantRosters.id']); // Group by roster for proper counting

        // Apply status filter
        $query = $query->where(['WarrantRosters.status' => $state]);

        // Apply authorization scoping for organizational data access
        $query = $this->Authorization->applyScope($query);

        // Execute paginated query
        $warrantRosters = $this->paginate($query);

        $this->set(compact('warrantRosters'));
    }

    /**
     * Display roster details with warrants and approval history.
     *
     * @param string|null $id Warrant Roster id.
     * @return \Cake\Http\Response|null|void
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function view(?string $id = null)
    {
        // Load complete roster data with all related information
        $warrantRoster = $this->WarrantRosters->find()
            ->where(['WarrantRosters.id' => $id])
            ->contain([
                // Warrants ordered by creation date for consistent display
                'Warrants' => function ($q) {
                    return $q->orderBy(['Warrants.created' => 'ASC']);
                },
                // Member information for warrant holders (minimal data for performance)
                'Warrants.Members' => function ($q) {
                    return $q->select(['id', 'sca_name']);
                },
                // Creator information for accountability
                'CreatedByMember' => function ($q) {
                    return $q->select(['id', 'sca_name']);
                },
            ])
            ->first();

        // Authorize access to specific roster entity
        $this->Authorization->authorize($warrantRoster);

        // Load workflow approval responses for this roster
        $workflowInstancesTable = TableRegistry::getTableLocator()->get('WorkflowInstances');
        $workflowInstance = $workflowInstancesTable->find()
            ->where([
                'entity_type' => 'WarrantRosters',
                'entity_id' => $id,
            ])
            ->first();

        $approvalResponses = [];
        if ($workflowInstance) {
            $responsesTable = TableRegistry::getTableLocator()->get('WorkflowApprovalResponses');
            $approvalResponses = $responsesTable->find()
                ->contain(['Members' => function ($q) {
                    return $q->select(['id', 'sca_name']);
                }])
                ->innerJoinWith('WorkflowApprovals', function ($q) use ($workflowInstance) {
                    return $q->where(['WorkflowApprovals.workflow_instance_id' => $workflowInstance->id]);
                })
                ->orderBy(['WorkflowApprovalResponses.responded_at' => 'ASC'])
                ->all()
                ->toArray();
        }

        $this->set(compact('warrantRoster', 'approvalResponses'));
    }

    /**
     * Create new warrant roster batch via WarrantManager service.
     *
     * @param \App\Services\WarrantManager\WarrantManagerInterface $wManager Warrant management service
     * @return \Cake\Http\Response|null|void Redirects on successful add, renders view otherwise.
     */
    public function add(WarrantManagerInterface $wManager)
    {
        // Create new empty entity for form binding
        $warrantRoster = $this->WarrantRosters->newEmptyEntity();

        // Authorize roster creation before processing
        $this->Authorization->authorize($warrantRoster);

        if ($this->request->is('post')) {
            $data = $this->request->getData();
            $currentUserId = (int)$this->Authentication->getIdentity()->getIdentifier();

            // Build WarrantRequest objects from submitted warrant data
            $warrantRequests = [];
            $warrants = $data['warrants'] ?? [];
            foreach ($warrants as $w) {
                $startOn = !empty($w['start_on']) ? new DateTime($w['start_on']) : null;
                $expiresOn = !empty($w['expires_on']) ? new DateTime($w['expires_on']) : null;
                $memberRoleId = !empty($w['member_role_id']) ? (int)$w['member_role_id'] : null;

                $warrantRequests[] = new WarrantRequest(
                    $w['name'] ?? ($data['name'] ?? ''),
                    $w['entity_type'] ?? '',
                    (int)($w['entity_id'] ?? 0),
                    $currentUserId,
                    (int)($w['member_id'] ?? 0),
                    $startOn,
                    $expiresOn,
                    $memberRoleId,
                );
            }

            $wmResult = $wManager->request(
                $data['name'] ?? '',
                $data['description'] ?? '',
                $warrantRequests,
                $currentUserId,
            );

            if ($wmResult->success) {
                $this->Flash->success(__('The warrant approval set has been saved.'));

                return $this->redirect(['action' => 'index']);
            }
            $this->Flash->error(__($wmResult->reason));

            // Re-patch entity so form redisplays submitted values
            $warrantRoster = $this->WarrantRosters->patchEntity($warrantRoster, $data);
        }

        $this->set(compact('warrantRoster'));
    }

    /**
     * Modify existing warrant roster.
     *
     * @param string|null $id Warrant Roster id.
     * @return \Cake\Http\Response|null|void Redirects on successful edit, renders view otherwise.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function edit(?string $id = null)
    {
        // Load existing roster for editing
        $warrantRoster = $this->WarrantRosters->get($id, contain: []);

        // Authorize edit operation on specific roster
        $this->Authorization->authorize($warrantRoster);

        if ($this->request->is(['patch', 'post', 'put'])) {
            // Process edit form submission
            $warrantRoster = $this->WarrantRosters->patchEntity($warrantRoster, $this->request->getData());

            if ($this->WarrantRosters->save($warrantRoster)) {
                $this->Flash->success(__('The warrant approval set has been saved.'));

                return $this->redirect(['action' => 'index']);
            }
            $this->Flash->error(__('The warrant approval set could not be saved. Please, try again.'));
        }

        $this->set(compact('warrantRoster'));
    }

    /**
     * Decline individual warrant within a roster batch.
     *
     * Note: If warrant is associated with an office, officer is released but not notified.
     *
     * @param \App\Services\WarrantManager\WarrantManagerInterface $wService Warrant management service
     * @param string $roster_id Warrant roster ID containing the warrant
     * @param string|null $warrant_id Individual warrant ID to decline
     * @return \Cake\Http\Response Redirect to referring page
     * @throws \Cake\Http\Exception\NotFoundException When warrant or roster not found
     */
    public function declineWarrantInRoster(WarrantManagerInterface $wService, $roster_id, $warrant_id = null)
    {
        // Require POST request for security
        $this->request->allowMethod(['post']);

        // Handle flexible parameter input (URL or form data)
        if (!$roster_id) {
            $roster_id = $this->request->getData('roster_id');
        }
        if (!$warrant_id) {
            $warrant_id = $this->request->getData('warrant_id');
        }

        // Validate warrant exists within specified roster
        $warrant = $this->WarrantRosters->Warrants->find()
            ->where(['id' => $warrant_id, 'warrant_roster_id' => $roster_id])
            ->first();

        if ($warrant == null) {
            throw new NotFoundException();
        }

        // Authorize decline operation on specific warrant
        $this->Authorization->authorize($warrant);

        // Process individual warrant decline through WarrantManager
        $wResult = $wService->declineSingleWarrant(
            (int)$warrant_id,
            'Declined Warrant',
            $this->Authentication->getIdentity()->get('id'),
        );

        if (!$wResult->success) {
            $this->Flash->error($wResult->reason);

            return $this->redirect($this->referer());
        }

        // Provide comprehensive feedback about warrant and office impacts
        $this->Flash->success(__(
            'The warrant has been deactivated. If this warrant is associated with an office,'
            . ' the officer has been released however they have not been notified.'
            . '  Please notify them at your earliest convienence.',
        ));

        return $this->redirect($this->referer());
    }
}
