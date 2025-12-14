<?php

declare(strict_types=1);

namespace App\Controller;

use App\KMP\GridColumns\WarrantsGridColumns;
use App\Model\Entity\Warrant;
use App\Services\CsvExportService;
use App\Services\GridViewService;
use App\Services\WarrantManager\WarrantManagerInterface;
use Cake\Http\Exception\NotFoundException;
use Cake\I18n\FrozenDate;
use Cake\I18n\DateTime;

/**
 * Manages warrant lifecycle: listing, filtering, deactivation, and CSV export.
 *
 * Handles temporal filtering (current, pending, upcoming, previous warrants),
 * administrative controls, and WarrantManager service integration for RBAC validation.
 *
 * @property \App\Model\Table\WarrantsTable $Warrants
 */
class WarrantsController extends AppController
{
    use DataverseGridTrait;

    /**
     * @var array<string> Service injection configuration
     */
    public static array $inject = [CsvExportService::class];

    /**
     * @var \App\Services\CsvExportService
     */
    protected CsvExportService $csvExportService;

    /**
     * Configure authorization for warrant management.
     *
     * @return void
     */
    public function initialize(): void
    {
        parent::initialize();

        // Load authorization component for warrant management security
        $this->loadComponent('Authorization.Authorization');

        // Authorize model-level access for warrant listing and grid data operations
        $this->Authorization->authorizeModel('index', 'gridData');
    }

    /**
     * Warrant management dashboard.
     *
     * @return \Cake\Http\Response|null|void
     */
    public function index() {}

    /**
     * Index DV - Dataverse-style warrant grid with nested turbo-frames
     *
     * Main page for warrant dataverse grid. Renders outer turbo-frame with toolbar
     * and delegates data loading to gridData() method.
     *
     * @return void
     */
    public function indexDv(): void
    {
        // Authorization check (reuse index permission)
        $securityWarrant = $this->Warrants->newEmptyEntity();
        $this->Authorization->authorize($securityWarrant, 'index');

        $systemViews = WarrantsGridColumns::getSystemViews([]);
        $queryCallback = $this->buildSystemViewQueryCallback(FrozenDate::today());

        // Use unified trait for grid processing (system views mode)
        $result = $this->processDataverseGrid([
            'gridKey' => 'Warrants.index.main',
            'gridColumnsClass' => \App\KMP\GridColumns\WarrantsGridColumns::class,
            'baseQuery' => $this->Warrants->find(),
            'tableName' => 'Warrants',
            'defaultSort' => ['Warrants.start_on' => 'ASC', 'Members.sca_name' => 'ASC'],
            'defaultPageSize' => 25,
            'systemViews' => $systemViews,
            'defaultSystemView' => 'sys-warrants-current',
            'queryCallback' => $queryCallback,
            'showAllTab' => false,
            'canAddViews' => true,
            'canFilter' => true,
            'lockedFilters' => ['status', 'start_on', 'expires_on'],
            'showFilterPills' => true,
        ]);

        $this->set('gridState', $result['gridState']);
        $this->set('warrants', $result['data']);
    }

    /**
     * Grid Data - Returns inner turbo-frame with warrant table data
     *
     * Handles all grid state changes (view selection, filters, search, sort, pagination)
     * and returns the inner turbo-frame with updated table content.
     * Also supports CSV export when export=csv query parameter is present.
     *
     * @return \Cake\Http\Response|void
     */
    public function gridData(CsvExportService $csvExportService)
    {
        // Authorization check
        $securityWarrant = $this->Warrants->newEmptyEntity();
        $this->Authorization->authorize($securityWarrant, 'index');

        $systemViews = WarrantsGridColumns::getSystemViews([]);
        $queryCallback = $this->buildSystemViewQueryCallback(FrozenDate::today());

        // Use unified trait for grid processing (system views mode)
        $result = $this->processDataverseGrid([
            'gridKey' => 'Warrants.index.main',
            'gridColumnsClass' => \App\KMP\GridColumns\WarrantsGridColumns::class,
            'baseQuery' => $this->Warrants->find(),
            'tableName' => 'Warrants',
            'defaultSort' => ['Warrants.start_on' => 'ASC', 'Members.sca_name' => 'ASC'],
            'defaultPageSize' => 25,
            'systemViews' => $systemViews,
            'defaultSystemView' => 'sys-warrants-current',
            'queryCallback' => $queryCallback,
            'showAllTab' => false,
            'canAddViews' => true,
            'canFilter' => true,
            'canExportCsv' => true,
            'lockedFilters' => ['status', 'start_on', 'expires_on'],
            'showFilterPills' => true,
        ]);

        // Handle CSV export
        if (!empty($result['isCsvExport'])) {
            return $this->handleCsvExport($result, $csvExportService, 'warrants');
        }

        // Set view variables
        $this->set([
            'warrants' => $result['data'],
            'gridState' => $result['gridState'],
            'columns' => $result['columnsMetadata'],
            'visibleColumns' => $result['visibleColumns'],
            'searchableColumns' => \App\KMP\GridColumns\WarrantsGridColumns::getSearchableColumns(),
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

        if ($turboFrame === 'warrants-grid-table') {
            // Inner frame request - render table data only
            $this->set('data', $result['data']);
            $this->set('tableFrameId', 'warrants-grid-table');
            $this->viewBuilder()->disableAutoLayout();
            $this->viewBuilder()->setTemplate('../element/dv_grid_table');
        } else {
            // Outer frame request (or no frame) - render toolbar + table frame
            $this->set('data', $result['data']);
            $this->set('frameId', 'warrants-grid');
            $this->viewBuilder()->disableAutoLayout();
            $this->viewBuilder()->setTemplate('../element/dv_grid_content');
        }
    }

    /**
     * List warrants filtered by temporal state with export capability.
     *
     * @param \App\Services\CsvExportService $csvExportService CSV export service
     * @param string $state Temporal filter (current|pending|upcoming|previous)
     * @return \Cake\Http\Response|null|void
     * @throws \Cake\Http\Exception\NotFoundException When invalid state provided
     */
    public function allWarrants(CsvExportService $csvExportService, $state)
    {
        // Validate state parameter to prevent invalid filter attempts
        if ($state != 'current' && $state == 'pending' && $state == 'previous') {
            throw new NotFoundException();
        }

        // Create security entity for authorization checking
        $securityWarrant = $this->Warrants->newEmptyEntity();
        $this->Authorization->authorize($securityWarrant);

        // Build base query with optimized association loading
        $warrantsQuery = $this->Warrants->find()
            ->contain(['Members', 'WarrantRosters', 'MemberRoles']);

        // Apply temporal filtering based on current date
        $today = new DateTime();
        switch ($state) {
            case 'current':
                // Active warrants providing RBAC temporal validation
                $warrantsQuery = $warrantsQuery->where([
                    'Warrants.expires_on >=' => $today,           // Not expired
                    'Warrants.start_on <=' => $today,             // Already started
                    'Warrants.status' => Warrant::CURRENT_STATUS  // Active status
                ]);
                break;
            case 'upcoming':
                // Future warrants scheduled for activation
                $warrantsQuery = $warrantsQuery->where([
                    'Warrants.start_on >' => $today,              // Future start date
                    'Warrants.status' => Warrant::CURRENT_STATUS  // Approved status
                ]);
                break;
            case 'pending':
                // Warrants awaiting approval through roster system
                $warrantsQuery = $warrantsQuery->where([
                    'Warrants.status' => Warrant::PENDING_STATUS
                ]);
                break;
            case 'previous':
                // Expired or administratively terminated warrants
                $warrantsQuery = $warrantsQuery->where([
                    'OR' => [
                        'Warrants.expires_on <' => $today,        // Expired by date
                        'Warrants.status IN ' => [                // Terminated by admin
                            Warrant::DEACTIVATED_STATUS,
                            Warrant::EXPIRED_STATUS
                        ]
                    ]
                ]);
                break;
        }

        // Apply additional query conditions for optimization
        $warrantsQuery = $this->addConditions($warrantsQuery);

        // CSV export for filtered warrant data
        if ($this->isCsvRequest()) {
            return $csvExportService->outputCsv(
                $warrantsQuery->order(['Members.sca_name' => 'asc']),  // Alphabetical order
                'warrants.csv',
            );
        }

        // Paginated results for web interface
        $warrants = $this->paginate($warrantsQuery);
        $this->set(compact('warrants', 'state'));
    }

    /**
     * Build query callback for system view processing.
     *
     * Applies base conditions; complex OR/AND logic handled via expression trees in view configs.
     *
     * @param \Cake\I18n\FrozenDate $today Reference date for temporal filtering
     * @return callable
     */
    protected function buildSystemViewQueryCallback(FrozenDate $today): callable
    {
        return function ($query, $selectedSystemView) use ($today) {
            // Always apply base conditions (field selection, associations)
            $query = $this->addConditions($query);

            // Note: OR logic for Previous view now handled by expression tree in config
            // If you need exceptional custom logic that can't be expressed declaratively,
            // add it here with appropriate view ID checks

            return $query;
        };
    }

    /**
     * Optimize warrant queries with field selection and associations.
     *
     * @param \Cake\ORM\Query $query Base warrant query
     * @return \Cake\ORM\Query Optimized query
     */
    protected function addConditions($query)
    {
        return $query
            // Select optimized field set for performance
            ->select([
                'id',
                'name',
                'member_id',
                'entity_type',
                'start_on',
                'expires_on',
                'revoker_id',
                'warrant_roster_id',
                'status',
                'revoked_reason'
            ])
            // Optimize association loading
            ->contain([
                'Members' => function ($q) {
                    return $q->select(['id', 'sca_name']);  // Member identification
                },
                'RevokedBy' => function ($q) {
                    return $q->select(['id', 'sca_name']);  // Revocation audit
                },
            ]);
    }

    /**
     * Deactivate warrant with audit trail via WarrantManager service.
     *
     * @param \App\Services\WarrantManager\WarrantManagerInterface $wService Warrant management service
     * @param int|null $id Warrant ID to deactivate
     * @return \Cake\Http\Response Redirect with status feedback
     * @throws \Cake\Http\Exception\NotFoundException When warrant doesn't exist
     */
    public function deactivate(WarrantManagerInterface $wService, $id = null)
    {
        // Security: Only allow POST method for destructive operations
        $this->request->allowMethod(['post']);

        // Get warrant ID from URL parameter or POST data
        if (!$id) {
            $id = $this->request->getData('id');
        }

        // Load warrant with member data for authorization context
        $warrant = $this->Warrants->find()
            ->where(['Warrants.id' => $id])
            ->contain(['Members'])                  // Load for authorization
            ->first();

        // Validate warrant exists
        if (!$warrant) {
            throw new NotFoundException(__('The warrant does not exist.'));
        }

        // Entity-level authorization for warrant deactivation
        $this->Authorization->authorize($warrant);

        // Delegate to WarrantManager service for business logic
        $wResult = $wService->cancel(
            (int)$id,                                       // Warrant ID
            'Deactivated from Warrant List',                // Audit reason
            $this->Authentication->getIdentity()->get('id'), // Administrator ID
            DateTime::now()                                 // Deactivation timestamp
        );

        // Handle service result and provide user feedback
        if (!$wResult->success) {
            $this->Flash->error($wResult->reason);
            return $this->redirect($this->referer());
        }

        // Success feedback and redirect
        $this->Flash->success(__('The warrant has been deactivated.'));
        return $this->redirect($this->referer());
    }
}
