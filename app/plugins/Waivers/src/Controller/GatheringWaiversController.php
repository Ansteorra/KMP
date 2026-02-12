<?php

declare(strict_types=1);

namespace Waivers\Controller;

use App\Controller\DataverseGridTrait;
use App\KMP\StaticHelpers;
use App\Services\CsvExportService;
use App\Services\DocumentService;
use App\Services\ImageToPdfConversionService;
use App\Services\RetentionPolicyService;
use App\Services\ServiceResult;
use Cake\Http\Exception\BadRequestException;
use Cake\Http\Exception\NotFoundException;
use Cake\I18n\Date;
use Cake\I18n\DateTime;
use Cake\Log\Log;
use Cake\Routing\Router;
use Waivers\KMP\GridColumns\GatheringWaiversGridColumns;

/**
 * GatheringWaivers Controller
 *
 * Manages waiver uploads, viewing, downloading, and deletion for gatherings.
 * Supports image-to-PDF conversion, mobile camera capture, and retention policy tracking.
 *
 * @property \Waivers\Model\Table\GatheringWaiversTable $GatheringWaivers
 */
class GatheringWaiversController extends AppController
{
    use DataverseGridTrait;
    /**
     * Document service instance
     *
     * @var \App\Services\DocumentService
     */
    private DocumentService $DocumentService;

    /**
     * Image to PDF conversion service instance
     *
     * @var \App\Services\ImageToPdfConversionService
     */
    private ImageToPdfConversionService $ImageToPdfConversionService;

    /**
     * Retention policy service instance
     *
     * @var \App\Services\RetentionPolicyService
     */
    private RetentionPolicyService $RetentionPolicyService;

    /**
     * Initialize method
     *
     * @return void
     */
    public function initialize(): void
    {
        parent::initialize();

        // Load required models
        $this->fetchTable('Gatherings');
        $this->fetchTable('Waivers.WaiverTypes');

        // Initialize services
        $this->DocumentService = new DocumentService();
        $this->ImageToPdfConversionService = new ImageToPdfConversionService();
        $this->RetentionPolicyService = new RetentionPolicyService();

        // Authorize typical CRUD actions
        $this->Authorization->authorizeModel('index', 'gridData');
    }

    /**
     * Index method - List waivers
     * 
     * If gathering_id is provided, shows waivers for that specific gathering.
     * Otherwise, shows all waivers using the dv_grid interface.
     *
     * @return \Cake\Http\Response|null|void Renders view
     */
    public function index()
    {
        $gatheringId = $this->request->getQuery('gathering_id');
        $currentUser = $this->Authentication->getIdentity();

        if ($gatheringId) {
            // Show waivers for specific gathering
            $Gatherings = $this->fetchTable('Gatherings');
            $gathering = $Gatherings->get($gatheringId, [
                'contain' => [
                    'GatheringTypes',
                    'Branches',
                    'GatheringActivities',
                ],
            ]);

            $this->Authorization->authorize($gathering, 'view');

            $GatheringWaiverClosures = $this->fetchTable('Waivers.GatheringWaiverClosures');
            $waiverClosure = $GatheringWaiverClosures->getClosureForGathering((int)$gatheringId);
            $waiverCollectionClosed = $waiverClosure !== null && $waiverClosure->isClosed();
            $waiverReadyToClose = $waiverClosure !== null && $waiverClosure->isReadyToClose() && !$waiverClosure->isClosed();

            // Get all waivers for this gathering
            $query = $this->GatheringWaivers->find()
                ->where(['gathering_id' => $gatheringId])
                ->contain(['WaiverTypes', 'Documents'])
                ->orderBy(['GatheringWaivers.created' => 'DESC']);

            $gatheringWaivers = $this->paginate($query);

            // Get required waiver types for this gathering's activities
            $GatheringActivityWaivers = $this->fetchTable('Waivers.GatheringActivityWaivers');
            $requiredWaiverTypes = $GatheringActivityWaivers->find()
                ->innerJoinWith('GatheringActivities.Gatherings', function ($q) use ($gatheringId) {
                    return $q->where(['Gatherings.id' => $gatheringId]);
                })
                ->contain(['WaiverTypes'])
                ->where(['GatheringActivityWaivers.deleted IS' => null])
                ->groupBy(['GatheringActivityWaivers.waiver_type_id'])
                ->all();

            // Calculate waiver counts per type (excluding declined waivers)
            $waiverCounts = $this->GatheringWaivers->find()
                ->where([
                    'gathering_id' => $gatheringId,
                    'declined_at IS' => null, // Exclude declined waivers from counts
                ])
                ->contain(['WaiverTypes'])
                ->select([
                    'waiver_type_id',
                    'count' => $query->func()->count('*'),
                ])
                ->groupBy('waiver_type_id')
                ->toArray();

            // Format counts for easy lookup
            $countsMap = [];
            foreach ($waiverCounts as $count) {
                $countsMap[$count->waiver_type_id] = $count->count;
            }

            // Check if user can close/reopen waivers for this gathering
            $tempWaiver = $this->GatheringWaivers->newEmptyEntity();
            $tempWaiver->gathering = $gathering;
            $canCloseWaivers = $currentUser->checkCan('closeWaivers', $tempWaiver);

            $this->set(compact(
                'gathering',
                'gatheringWaivers',
                'countsMap',
                'requiredWaiverTypes',
                'waiverClosure',
                'waiverCollectionClosed',
                'waiverReadyToClose',
                'canCloseWaivers'
            ));
            $this->render('index_gathering');
        } else {
            // Show all waivers using dv_grid
            // The actual data will be loaded via gridData action
            $this->set('gathering', null);
        }
    }

    /**
     * Close waiver collection for a gathering.
     *
     * @param string|null $gatheringId Gathering ID.
     * @return \Cake\Http\Response|null
     */
    public function close(?string $gatheringId = null)
    {
        $this->request->allowMethod(['post']);

        if (!$gatheringId) {
            throw new BadRequestException(__('Gathering ID is required'));
        }

        $Gatherings = $this->fetchTable('Gatherings');
        $gathering = $Gatherings->get($gatheringId);

        $tempWaiver = $this->GatheringWaivers->newEmptyEntity();
        $tempWaiver->gathering = $gathering;
        $this->Authorization->authorize($tempWaiver, 'closeWaivers');

        $GatheringWaiverClosures = $this->fetchTable('Waivers.GatheringWaiverClosures');
        $existing = $GatheringWaiverClosures->getClosureForGathering((int)$gatheringId);

        if ($existing && $existing->isClosed()) {
            $this->Flash->info(__('Waiver collection is already closed for this gathering.'));
        } elseif ($existing) {
            // Update existing record (may be marked ready to close)
            $existing->closed_at = DateTime::now();
            $existing->closed_by = $this->Authentication->getIdentity()->getIdentifier();

            if ($GatheringWaiverClosures->save($existing)) {
                $this->Flash->success(__('Waiver collection has been closed.'));
            } else {
                $this->Flash->error(__('Unable to close waiver collection. Please try again.'));
            }
        } else {
            $closure = $GatheringWaiverClosures->newEntity([
                'gathering_id' => $gathering->id,
                'closed_at' => DateTime::now(),
                'closed_by' => $this->Authentication->getIdentity()->getIdentifier(),
            ]);

            if ($GatheringWaiverClosures->save($closure)) {
                $this->Flash->success(__('Waiver collection has been closed.'));
            } else {
                $this->Flash->error(__('Unable to close waiver collection. Please try again.'));
            }
        }

        $redirectUrl = $this->request->referer();
        if (!$redirectUrl) {
            $redirectUrl = [
                'plugin' => false,
                'controller' => 'Gatherings',
                'action' => 'view',
                $gathering->public_id,
                '?' => ['tab' => 'gathering-waivers'],
            ];
        }

        return $this->redirect($redirectUrl);
    }

    /**
     * Reopen waiver collection for a gathering.
     *
     * @param string|null $gatheringId Gathering ID.
     * @return \Cake\Http\Response|null
     */
    public function reopen(?string $gatheringId = null)
    {
        $this->request->allowMethod(['post']);

        if (!$gatheringId) {
            throw new BadRequestException(__('Gathering ID is required'));
        }

        $Gatherings = $this->fetchTable('Gatherings');
        $gathering = $Gatherings->get($gatheringId);

        $tempWaiver = $this->GatheringWaivers->newEmptyEntity();
        $tempWaiver->gathering = $gathering;
        $this->Authorization->authorize($tempWaiver, 'closeWaivers');

        $GatheringWaiverClosures = $this->fetchTable('Waivers.GatheringWaiverClosures');
        $existing = $GatheringWaiverClosures->getClosureForGathering((int)$gatheringId);

        if (!$existing) {
            $this->Flash->info(__('Waiver collection is already open for this gathering.'));
        } elseif ($GatheringWaiverClosures->delete($existing)) {
            $this->Flash->success(__('Waiver collection has been reopened.'));
        } else {
            $this->Flash->error(__('Unable to reopen waiver collection. Please try again.'));
        }

        $redirectUrl = $this->request->referer();
        if (!$redirectUrl) {
            $redirectUrl = [
                'plugin' => false,
                'controller' => 'Gatherings',
                'action' => 'view',
                $gathering->public_id,
                '?' => ['tab' => 'gathering-waivers'],
            ];
        }

        return $this->redirect($redirectUrl);
    }

    /**
     * Mark a gathering as ready for waiver secretary to close.
     *
     * @param string|null $gatheringId Gathering ID.
     * @return \Cake\Http\Response|null
     */
    public function markReadyToClose(?string $gatheringId = null)
    {
        $this->request->allowMethod(['post']);

        if (!$gatheringId) {
            throw new BadRequestException(__('Gathering ID is required'));
        }

        $Gatherings = $this->fetchTable('Gatherings');
        $gathering = $Gatherings->get($gatheringId);

        // Use edit permission on gathering - editors and stewards can mark ready
        $this->Authorization->authorize($gathering, 'edit');

        $GatheringWaiverClosures = $this->fetchTable('Waivers.GatheringWaiverClosures');
        $existing = $GatheringWaiverClosures->getClosureForGathering((int)$gatheringId);

        // If already closed, cannot mark ready
        if ($existing && $existing->isClosed()) {
            $this->Flash->info(__('Waiver collection is already closed for this gathering.'));
        } elseif ($existing && $existing->isReadyToClose()) {
            $this->Flash->info(__('This gathering is already marked as ready to close.'));
        } else {
            $closure = $GatheringWaiverClosures->markReadyToClose(
                (int)$gatheringId,
                $this->Authentication->getIdentity()->getIdentifier()
            );

            if ($closure) {
                $this->Flash->success(__('Gathering marked as ready for waiver secretary review.'));
            } else {
                $this->Flash->error(__('Unable to mark gathering as ready. Please try again.'));
            }
        }

        $redirectUrl = $this->request->referer();
        if (!$redirectUrl) {
            $redirectUrl = [
                'plugin' => false,
                'controller' => 'Gatherings',
                'action' => 'view',
                $gathering->public_id,
                '?' => ['tab' => 'gathering-waivers'],
            ];
        }

        return $this->redirect($redirectUrl);
    }

    /**
     * Unmark a gathering as ready to close (reverts ready status).
     *
     * @param string|null $gatheringId Gathering ID.
     * @return \Cake\Http\Response|null
     */
    public function unmarkReadyToClose(?string $gatheringId = null)
    {
        $this->request->allowMethod(['post']);

        if (!$gatheringId) {
            throw new BadRequestException(__('Gathering ID is required'));
        }

        $Gatherings = $this->fetchTable('Gatherings');
        $gathering = $Gatherings->get($gatheringId);

        // Use edit permission on gathering - editors and stewards can unmark ready
        $this->Authorization->authorize($gathering, 'edit');

        $GatheringWaiverClosures = $this->fetchTable('Waivers.GatheringWaiverClosures');
        $existing = $GatheringWaiverClosures->getClosureForGathering((int)$gatheringId);

        // If already closed, cannot unmark
        if ($existing && $existing->isClosed()) {
            $this->Flash->error(__('Cannot unmark - waiver collection is already closed by the waiver secretary.'));
        } elseif (!$existing || !$existing->isReadyToClose()) {
            $this->Flash->info(__('This gathering is not marked as ready to close.'));
        } elseif ($GatheringWaiverClosures->unmarkReadyToClose((int)$gatheringId)) {
            $this->Flash->success(__('Gathering is no longer marked as ready to close.'));
        } else {
            $this->Flash->error(__('Unable to unmark gathering. Please try again.'));
        }

        $redirectUrl = $this->request->referer();
        if (!$redirectUrl) {
            $redirectUrl = [
                'plugin' => false,
                'controller' => 'Gatherings',
                'action' => 'view',
                $gathering->public_id,
                '?' => ['tab' => 'gathering-waivers'],
            ];
        }

        return $this->redirect($redirectUrl);
    }

    /**
     * Grid Data method - provides data for the Dataverse grid (All Waivers view)
     *
     * @param CsvExportService $csvExportService Injected CSV export service
     * @return \Cake\Http\Response|null|void Renders view or returns CSV response
     */
    public function gridData(CsvExportService $csvExportService)
    {
        $currentUser = $this->Authentication->getIdentity();

        // Get branches user can view waivers for
        $branchIds = $currentUser->getBranchIdsForAction('view', 'Waivers.GatheringWaivers');

        // If user has no permissions, show empty grid
        if (is_array($branchIds) && empty($branchIds)) {
            $this->set([
                'gatheringWaivers' => [],
                'gridState' => [],
                'columns' => GatheringWaiversGridColumns::getColumns(),
                'visibleColumns' => array_keys(GatheringWaiversGridColumns::getDefaultVisibleColumns()),
                'searchableColumns' => GatheringWaiversGridColumns::getSearchableColumns(),
                'dropdownFilterColumns' => [],
                'filterOptions' => [],
                'currentFilters' => [],
                'currentSearch' => '',
                'currentView' => 'sys-gathering-waivers-active',
                'availableViews' => [],
                'gridKey' => 'Waivers.GatheringWaivers.index.main',
                'currentSort' => [],
                'currentMember' => $currentUser,
            ]);

            $this->viewBuilder()->setTemplate('grid_data');
            return;
        }

        // Get all branches or filtered by permission
        $Branches = $this->fetchTable('Branches');
        if ($branchIds === null) {
            // Global permission - all branches
            $branchIds = $Branches->find()->select(['id'])->all()->extract('id')->toArray();
        }

        // Build base query
        $baseQuery = $this->GatheringWaivers->find()
            ->where(['GatheringWaivers.deleted IS' => null])
            ->innerJoinWith('Gatherings', function ($q) use ($branchIds) {
                return $q->where([
                    'Gatherings.branch_id IN' => $branchIds,
                    'Gatherings.deleted IS' => null,
                ]);
            })
            ->innerJoinWith('Gatherings.Branches')
            ->contain([
                'Gatherings' => function ($q) {
                    return $q->select(['id', 'name', 'start_date', 'end_date', 'branch_id']);
                },
                'Gatherings.Branches' => function ($q) {
                    return $q->select(['id', 'name']);
                },
                'WaiverTypes' => function ($q) {
                    return $q->select(['id', 'name']);
                },
                'Documents',
            ]);

        // Build branch filter options
        $branchOptions = $Branches->find('list')
            ->where(['Branches.id IN' => $branchIds])
            ->orderBy(['Branches.name' => 'ASC'])
            ->toArray();
        $branchFilterOptions = [];
        foreach ($branchOptions as $id => $name) {
            $branchFilterOptions[] = ['value' => (string)$id, 'label' => $name];
        }

        // Build waiver type filter options
        $WaiverTypes = $this->fetchTable('Waivers.WaiverTypes');
        $waiverTypeOptions = $WaiverTypes->find('list')
            ->where(['is_active' => true])
            ->orderBy(['name' => 'ASC'])
            ->toArray();
        $waiverTypeFilterOptions = [];
        foreach ($waiverTypeOptions as $id => $name) {
            $waiverTypeFilterOptions[] = ['value' => (string)$id, 'label' => $name];
        }

        // Get system views

        // Use unified trait for grid processing
        $result = $this->processDataverseGrid([
            'gridKey' => 'Waivers.GatheringWaivers.index.main',
            'gridColumnsClass' => GatheringWaiversGridColumns::class,
            'baseQuery' => $baseQuery,
            'tableName' => 'GatheringWaivers',
            'defaultSort' => ['GatheringWaivers.created' => 'desc'],
            'defaultPageSize' => 25,
            'showAllTab' => true,
            'canAddViews' => true,
            'canFilter' => true,
            'canExportCsv' => true,
        ]);

        // Merge permission-scoped filter options for dropdown filters
        $result['filterOptions']['branch_id'] = $branchFilterOptions;
        $result['filterOptions']['waiver_type_id'] = $waiverTypeFilterOptions;

        // Handle CSV export
        if (!empty($result['isCsvExport'])) {
            return $this->handleCsvExport($result, $csvExportService, 'gathering-waivers');
        }

        // Get row actions from grid columns
        $rowActions = GatheringWaiversGridColumns::getRowActions();

        // Set view variables
        $this->set([
            'gatheringWaivers' => $result['data'],
            'rowActions' => $rowActions,
            'gridState' => $result['gridState'],
            'columns' => $result['columnsMetadata'],
            'visibleColumns' => $result['visibleColumns'],
            'searchableColumns' => GatheringWaiversGridColumns::getSearchableColumns(),
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

        // Override data for grid rendering
        $this->set('data', $result['data']);

        if ($turboFrame === 'gathering-waivers-grid-table') {
            // Inner frame request - render table data only
            $this->set('tableFrameId', 'gathering-waivers-grid-table');
            $this->viewBuilder()->setPlugin(null);
            $this->viewBuilder()->disableAutoLayout();
            $this->viewBuilder()->setTemplatePath('element');
            $this->viewBuilder()->setTemplate('dv_grid_table');
        } else {
            // Outer frame request (or no frame) - render toolbar + table frame
            $this->set('frameId', 'gathering-waivers-grid');
            $this->viewBuilder()->setPlugin(null);
            $this->viewBuilder()->disableAutoLayout();
            $this->viewBuilder()->setTemplatePath('element');
            $this->viewBuilder()->setTemplate('dv_grid_content');
        }
    }

    /**
     * View method - Display a single waiver with details and retention policy
     *
     * @param string|null $id Gathering Waiver id.
     * @return \Cake\Http\Response|null|void Renders view
     * @throws \Cake\Http\Exception\NotFoundException When record not found.
     */
    public function view(?string $id = null)
    {
        $gatheringWaiver = $this->GatheringWaivers->get($id, [
            'contain' => [
                'Gatherings' => ['GatheringTypes', 'Branches'],
                'WaiverTypes',
                'Documents',
                'DeclinedByMembers',
                'CreatedByMembers',
            ],
        ]);

        $this->Authorization->authorize($gatheringWaiver);

        // Load all waiver types for the change type modal
        $waiverTypes = $this->GatheringWaivers->WaiverTypes->find('list')->orderBy(['name' => 'ASC'])->toArray();

        $previewAvailable = false;
        if ($gatheringWaiver->document) {
            $previewAvailable = $this->DocumentService->documentPreviewExists($gatheringWaiver->document);
        }

        $this->set(compact('gatheringWaiver', 'waiverTypes', 'previewAvailable'));
    }

    /**
     * Upload method - Handle waiver image uploads with conversion to PDF
     *
     * Supports:
     * - Multiple image uploads (JPEG, PNG, TIFF)
     * - Mobile camera capture
     * - Automatic image-to-PDF conversion
     * - Retention policy capture at upload time
     * - Batch processing
     *
     * @return \Cake\Http\Response|null|void Renders view or redirects on success
     * @throws \Cake\Http\Exception\BadRequestException When gathering_id is missing
     */
    public function upload()
    {
        $gatheringId = $this->request->getQuery('gathering_id');

        if (!$gatheringId) {
            throw new BadRequestException(__('Gathering ID is required'));
        }

        // Get gathering with activities
        $Gatherings = $this->fetchTable('Gatherings');
        $gathering = $Gatherings->get($gatheringId, [
            'contain' => [
                'GatheringTypes',
                'Branches',
                'GatheringActivities',
            ],
        ]);

        // Check if gathering is cancelled
        if ($gathering->cancelled_at !== null) {
            $message = __('This gathering has been cancelled. Waivers are not required.');
            if ($this->request->is('ajax')) {
                $this->viewBuilder()->setClassName('Json');
                $this->response = $this->response->withStatus(403);
                $this->set('message', $message);
                $this->viewBuilder()->setOption('serialize', ['message']);
                return;
            }
            $this->Flash->error($message);
            return $this->redirect([
                'plugin' => false,
                'controller' => 'Gatherings',
                'action' => 'view',
                $gathering->public_id,
            ]);
        }

        $GatheringWaiverClosures = $this->fetchTable('Waivers.GatheringWaiverClosures');
        $waiverClosure = $GatheringWaiverClosures->getClosureForGathering((int)$gatheringId);
        if ($waiverClosure) {
            $message = __('Waiver collection is closed for this gathering.');
            if ($this->request->is('ajax')) {
                $this->viewBuilder()->setClassName('Json');
                $this->response = $this->response->withStatus(403);
                $this->set('message', $message);
                $this->viewBuilder()->setOption('serialize', ['message']);
                return;
            }
            $this->Flash->error($message);
            return $this->redirect([
                'plugin' => false,
                'controller' => 'Gatherings',
                'action' => 'view',
                $gathering->public_id,
                '?' => ['tab' => 'gathering-waivers'],
            ]);
        }

        // Check if gathering has required waivers
        $requiredWaiverTypes = $this->_getRequiredWaiverTypes($gathering);
        if (empty($requiredWaiverTypes)) {
            $this->Flash->error(__('This gathering is not configured to collect waivers.'));
            return $this->redirect(['plugin' => false, 'controller' => 'Gatherings', 'action' => 'view', $gatheringId]);
        }
        // need an empty gathering waiver to check authorization
        $gatheringWaiver = $this->GatheringWaivers->newEmptyEntity();
        $gatheringWaiver->gathering = $gathering;
        $this->Authorization->authorize($gatheringWaiver, 'uploadWaivers');

        // Handle POST - process uploads
        if ($this->request->is('post')) {
            $data = $this->request->getData();
            Log::debug('Waiver upload POST data received', [
                'keys' => array_keys($data),
                'waiver_type_id' => $data['waiver_type_id'] ?? null,
                'notes' => $data['notes'] ?? '',
                'waiver_images_count' => isset($data['waiver_images']) ? count($data['waiver_images']) : 0,
            ]);

            $uploadedFiles = $data['waiver_images'] ?? [];
            $waiverTypeId = $data['waiver_type_id'] ?? null;
            $notes = $data['notes'] ?? '';

            if (empty($uploadedFiles) || !$waiverTypeId) {
                $this->Flash->error(__('Please select waiver type and upload at least one image.'));
                return $this->redirect($this->referer());
            }

            if ($this->_isWaiverTypeAttested((int)$gatheringId, (int)$waiverTypeId)) {
                $message = __('This waiver type has been attested as not needed for this gathering.');
                if ($this->request->is('ajax')) {
                    $this->viewBuilder()->setClassName('Json');
                    $this->response = $this->response->withStatus(400);
                    $this->set('message', $message);
                    $this->viewBuilder()->setOption('serialize', ['message']);
                    return;
                }
                $this->Flash->error($message);
                return $this->redirect($this->referer());
            }

            // Get waiver type for retention policy
            $WaiverTypes = $this->fetchTable('Waivers.WaiverTypes');
            $waiverType = $WaiverTypes->get($waiverTypeId);

            // Get client-generated thumbnail if provided (from PDF.js rendering)
            $clientThumbnail = $data['client_thumbnail'] ?? null;

            // Process all uploaded files as a single multi-page waiver
            try {
                $result = $this->_processMultipleWaiverImages(
                    $uploadedFiles,
                    $gathering,
                    $waiverType,
                    $notes,
                    $clientThumbnail
                );

                if ($result->success) {
                    // Determine redirect URL based on referer or default to index
                    $referer = $this->request->referer();
                    $redirectUrl = null;

                    // If coming from mobile card view, redirect back there
                    if ($referer && strpos($referer, 'view-mobile-card') !== false) {
                        $redirectUrl = $referer;
                    } elseif ($referer && strpos($referer, 'viewMobileCard') !== false) {
                        $redirectUrl = $referer;
                    } else {
                        // default back to the last page the user was on based on our managed back stack
                        $pageStack = $this->request->getSession()->read('pageStack') ?? [];
                        $historyCount = count($pageStack);
                        if ($historyCount >= 2) {
                            $redirectUrl = $pageStack[$historyCount - 2];
                        } else {
                            // Default to waiver index for this gathering
                            $redirectUrl = \Cake\Routing\Router::url([
                                'plugin' => 'Waivers',
                                'controller' => 'GatheringWaivers',
                                'action' => 'index',
                                '?' => ['gathering_id' => $gatheringId]
                            ], true); // true = full base URL
                        }
                    }

                    // Get result data for messaging
                    $resultData = $result->getData();
                    $pageCount = $resultData['page_count'] ?? count($uploadedFiles);

                    // Set Flash message (will show on redirect page)
                    $this->Flash->success(__(
                        'Waiver uploaded successfully with {0} page(s).',
                        $pageCount
                    ));

                    // Show warning if files were skipped
                    if (!empty($resultData['warning'])) {
                        $this->Flash->warning($resultData['warning']);
                    }

                    // If AJAX request, return JSON response
                    if ($this->request->is('ajax')) {
                        $this->viewBuilder()->setClassName('Json');
                        $this->set('success', true);
                        $this->set('redirectUrl', $redirectUrl);
                        $this->set('warning', $resultData['warning'] ?? null);
                        $this->viewBuilder()->setOption('serialize', ['success', 'redirectUrl', 'warning']);
                        return;
                    }

                    // For non-AJAX, redirect
                    return $this->redirect($redirectUrl);
                } else {
                    // Handle error case
                    if ($this->request->is('ajax')) {
                        $this->Flash->error(__('Failed to upload waiver: {0}', $result->getError()));
                        $this->viewBuilder()->setClassName('Json');
                        $this->set('success', false);
                        $this->set('redirectUrl', $this->request->referer());
                        $this->viewBuilder()->setOption('serialize', ['success', 'redirectUrl']);
                        return;
                    }

                    $this->Flash->error(__('Failed to upload waiver: {0}', $result->getError()));
                }
            } catch (\Exception $e) {
                Log::error('Waiver upload error: ' . $e->getMessage());

                // Handle exception for AJAX request
                if ($this->request->is('ajax')) {
                    $this->Flash->error(__('Error uploading waiver: {0}', $e->getMessage()));
                    $this->viewBuilder()->setClassName('Json');
                    $this->set('success', false);
                    $this->set('redirectUrl', $this->request->referer());
                    $this->viewBuilder()->setOption('serialize', ['success', 'redirectUrl']);
                    return;
                }

                $this->Flash->error(__('Error uploading waiver: {0}', $e->getMessage()));
            }

            return $this->redirect($this->referer());
        }

        // GET request - show upload form
        // (requiredWaiverTypes already calculated above)

        // Build waiver types data with exemption reasons for attestation support
        $waiverTypesData = [];
        if (!empty($requiredWaiverTypes)) {
            foreach ($requiredWaiverTypes as $waiverType) {
                $waiverTypesData[] = [
                    'id' => $waiverType->id,
                    'name' => $waiverType->name,
                    'description' => $waiverType->description ?? '',
                    'exemption_reasons' => $waiverType->exemption_reasons_parsed ?? []
                ];
            }
        }

        // Get upload limits for validation
        $uploadLimits = $this->_getUploadLimits();

        // Get pre-selected values from query parameters (for direct upload links)
        $preSelectedWaiverTypeId = $this->request->getQuery('waiver_type_id');

        $waiverStatusSummary = $this->_getWaiverStatusSummary((int)$gathering->id, $requiredWaiverTypes);

        $this->set(compact(
            'gathering',
            'requiredWaiverTypes',
            'waiverTypesData',
            'uploadLimits',
            'preSelectedWaiverTypeId',
            'waiverStatusSummary'
        ));
    }

    /**
     * Process a single waiver image upload
     *
     * Workflow:
     * 1. Validate image file
     * 2. Convert image to black and white PDF
     * 3. Save PDF using DocumentService
     * 4. Calculate retention date
     * 5. Create GatheringWaiver record
     * 6. Update document entity_id
     *
     * @param \Psr\Http\Message\UploadedFileInterface $uploadedFile Uploaded file object
     * @param \App\Model\Entity\Gathering $gathering Gathering entity
     * @param \Waivers\Model\Entity\WaiverType $waiverType Waiver type entity
     * @param int|null $memberId Optional member ID
     * @param string $notes Optional notes
     * @return \App\Services\ServiceResult Result object
     */
    private function _processWaiverUpload(
        \Psr\Http\Message\UploadedFileInterface $uploadedFile,
        $gathering,
        $waiverType,
        ?int $memberId,
        string $notes
    ) {
        // Handle UploadedFile object (from form upload)
        $tmpName = $uploadedFile->getStream()->getMetadata('uri');
        $fileName = $uploadedFile->getClientFilename();
        $fileSize = $uploadedFile->getSize();

        // Generate unique output path for PDF
        $tmpDir = sys_get_temp_dir();
        $uniqueId = uniqid('waiver_', true);
        $pdfPath = $tmpDir . DIRECTORY_SEPARATOR . $uniqueId . '.pdf';

        // Convert image to PDF (includes validation)
        $previewPath = null;
        $conversionResult = $this->ImageToPdfConversionService->convertImageToPdf(
            $tmpName,
            $pdfPath,
            'letter',
            $previewPath
        );

        if (!$conversionResult->success) {
            return new ServiceResult(false, $conversionResult->reason);
        }

        $originalSize = $fileSize;
        $convertedSize = filesize($pdfPath);

        // Calculate retention date first
        // Convert DateTime to Date for retention calculation
        $gatheringEndDate = Date::parse($gathering->end_date->format('Y-m-d'));
        $retentionResult = $this->RetentionPolicyService->calculateRetentionDate(
            $waiverType->retention_policy,
            $gatheringEndDate,
            Date::now()
        );

        if (!$retentionResult->success) {
            @unlink($pdfPath);
            if ($previewPath !== null && file_exists($previewPath)) {
                @unlink($previewPath);
            }
            return $retentionResult;
        }

        $retentionDate = $retentionResult->getData();

        // Create GatheringWaiver record first (with temporary document_id = 0)
        $gatheringWaiver = $this->GatheringWaivers->newEntity([
            'gathering_id' => $gathering->id,
            'waiver_type_id' => $waiverType->id,
            'member_id' => $memberId,
            'document_id' => null, // Temporary - will be updated after Document is created
            'retention_date' => $retentionDate,
            'status' => 'active',
            'notes' => $notes,
        ]);

        // Save without checking rules (document_id=0 doesn't exist yet)
        if (!$this->GatheringWaivers->save($gatheringWaiver, ['checkRules' => false])) {
            @unlink($pdfPath);
            if ($previewPath !== null && file_exists($previewPath)) {
                @unlink($previewPath);
            }
            $errors = $gatheringWaiver->getErrors();
            Log::error('Failed to save waiver record', ['errors' => $errors, 'data' => $gatheringWaiver->toArray()]);
            $errorMessages = [];
            foreach ($errors as $field => $fieldErrors) {
                foreach ($fieldErrors as $error) {
                    $errorMessages[] = "$field: $error";
                }
            }
            $errorDetail = !empty($errorMessages) ? implode(', ', $errorMessages) : 'Unknown validation error';
            return new ServiceResult(false, 'Failed to save waiver record: ' . $errorDetail);
        }        // Create UploadedFile object from the converted PDF
        // This allows us to use DocumentService for consistent storage handling (local/Azure)
        $uploadedPdf = new \Laminas\Diactoros\UploadedFile(
            $pdfPath,
            $convertedSize,
            UPLOAD_ERR_OK,
            $fileName,
            'application/pdf'
        );

        // Get current user ID for uploaded_by
        $currentUserId = $this->Authentication->getIdentity()->getIdentifier();

        try {
            // Store document using DocumentService (handles local or Azure storage)
            $documentResult = $this->DocumentService->createDocument(
                $uploadedPdf,
                'GatheringWaiver',
                $gatheringWaiver->id,
                $currentUserId,
                [
                    'original_filename' => $fileName,
                    'original_size' => $originalSize,
                    'converted_size' => $convertedSize,
                    'conversion_date' => date('Y-m-d H:i:s'),
                    'compression_ratio' => round((1 - ($convertedSize / $originalSize)) * 100, 2),
                    'source' => 'waiver_upload',
                ],
                'waivers', // subdirectory
                ['pdf'],   // allowed extensions
                $previewPath
            );
        } catch (\Throwable $exception) {
            @unlink($pdfPath);
            if ($previewPath !== null && file_exists($previewPath)) {
                @unlink($previewPath);
            }

            $this->GatheringWaivers->delete($gatheringWaiver);
            Log::error('Document creation threw an exception', [
                'gathering_waiver_id' => $gatheringWaiver->id,
                'error' => $exception->getMessage(),
            ]);

            return new ServiceResult(false, __('Failed to save document: {0}', $exception->getMessage()));
        }

        // Clean up temporary PDF file regardless of outcome
        @unlink($pdfPath);

        if (!$documentResult->success) {
            if ($previewPath !== null && file_exists($previewPath)) {
                @unlink($previewPath);
            }
            Log::error('Document service returned failure', [
                'gathering_waiver_id' => $gatheringWaiver->id,
                'error' => $documentResult->getError(),
            ]);
            $this->GatheringWaivers->delete($gatheringWaiver);

            return new ServiceResult(false, 'Failed to save document: ' . $documentResult->getError());
        }

        $documentId = (int)$documentResult->getData();

        // Update GatheringWaiver with the real document_id
        $gatheringWaiver->document_id = $documentId;
        if (!$this->GatheringWaivers->save($gatheringWaiver)) {
            // Clean up document record if update fails
            $this->DocumentService->deleteDocument($documentId);
            $this->GatheringWaivers->delete($gatheringWaiver);
            return new ServiceResult(false, 'Failed to update waiver with document ID');
        }

        return new ServiceResult(true, null, [
            'waiver_id' => $gatheringWaiver->id,
            'document_id' => $documentId,
        ]);
    }

    /**
     * Process multiple waiver images into a single multi-page PDF
     *
     * @param array $uploadedFiles Array of UploadedFileInterface objects
     * @param mixed $gathering Gathering entity
     * @param mixed $waiverType WaiverType entity
     * @param string $notes Notes for the waiver
     * @return ServiceResult
     */
    private function _processMultipleWaiverImages(
        array $uploadedFiles,
        $gathering,
        $waiverType,
        string $notes,
        ?string $clientThumbnail = null
    ): ServiceResult {
        // Debug: Log what we received
        Log::debug('_processMultipleWaiverImages called', [
            'uploadedFiles_count' => count($uploadedFiles),
            'gathering_id' => $gathering->id,
            'waiverType_id' => $waiverType->id,
            'notes_length' => strlen($notes),
            'has_client_thumbnail' => $clientThumbnail !== null,
        ]);

        // Extract temp paths and original filenames from uploaded files
        $fileInfos = [];
        $originalFilename = '';
        $totalOriginalSize = 0;

        foreach ($uploadedFiles as $index => $uploadedFile) {
            $fileInfos[] = [
                'path' => $uploadedFile->getStream()->getMetadata('uri'),
                'original_name' => $uploadedFile->getClientFilename(),
                'mime_type' => $uploadedFile->getClientMediaType(),
            ];
            $totalOriginalSize += $uploadedFile->getSize();

            // Use first filename as basis for stored filename
            if ($index === 0) {
                $originalFilename = pathinfo($uploadedFile->getClientFilename(), PATHINFO_FILENAME) . '.pdf';
            }
        }

        // Generate unique output path for multi-page PDF
        $tmpDir = sys_get_temp_dir();
        $uniqueId = uniqid('waiver_multipage_', true);
        $pdfPath = $tmpDir . DIRECTORY_SEPARATOR . $uniqueId . '.pdf';

        // Convert all files (images and/or PDFs) to a single multi-page PDF
        $previewPath = null;
        $conversionResult = $this->ImageToPdfConversionService->convertMixedToPdf(
            $fileInfos,
            $pdfPath,
            'letter',
            $previewPath
        );

        if (!$conversionResult->success) {
            return new ServiceResult(false, $conversionResult->reason);
        }

        $convertedSize = filesize($pdfPath);
        $pageCount = $conversionResult->data['page_count'] ?? count($uploadedFiles);
        $firstFileIsImage = $conversionResult->data['first_file_is_image'] ?? false;
        $skippedFiles = $conversionResult->data['skipped_files'] ?? [];

        // Log if any files were skipped
        if (!empty($skippedFiles)) {
            Log::warning('Some PDF files were skipped during waiver upload due to unsupported compression', [
                'skipped_count' => count($skippedFiles),
                'processed_pages' => $pageCount,
            ]);
        }

        // Use client-generated thumbnail only if first file was a PDF
        // (If first file was an image, the server-generated thumbnail is better quality)
        if (!$firstFileIsImage && $clientThumbnail !== null && str_starts_with($clientThumbnail, 'data:image/')) {
            // Decode base64 data URL and save to temp file
            $parts = explode(',', $clientThumbnail, 2);
            if (count($parts) === 2) {
                $imageData = base64_decode($parts[1]);
                if ($imageData !== false) {
                    $clientThumbPath = $tmpDir . DIRECTORY_SEPARATOR . $uniqueId . '_thumb.png';
                    if (file_put_contents($clientThumbPath, $imageData) !== false) {
                        // Replace server-generated preview with client-generated one
                        if ($previewPath !== null && file_exists($previewPath)) {
                            @unlink($previewPath);
                        }
                        $previewPath = $clientThumbPath;
                        Log::debug('Using client-generated thumbnail for PDF');
                    }
                }
            }
        }

        // Calculate retention date first
        // Convert DateTime to Date for retention calculation
        $gatheringEndDate = Date::parse($gathering->end_date->format('Y-m-d'));
        $retentionResult = $this->RetentionPolicyService->calculateRetentionDate(
            $waiverType->retention_policy,
            $gatheringEndDate,
            Date::now()
        );

        if (!$retentionResult->success) {
            @unlink($pdfPath);
            if ($previewPath !== null && file_exists($previewPath)) {
                @unlink($previewPath);
            }
            return $retentionResult;
        }

        $retentionDate = $retentionResult->getData();

        // Create GatheringWaiver record first (with temporary document_id = null)
        $gatheringWaiver = $this->GatheringWaivers->newEntity([
            'gathering_id' => $gathering->id,
            'waiver_type_id' => $waiverType->id,
            'document_id' => null, // Temporary - will be updated after Document is created
            'retention_date' => $retentionDate,
            'status' => 'active',
            'notes' => $notes,
        ]);

        // Save without checking rules (document_id doesn't exist yet)
        if (!$this->GatheringWaivers->save($gatheringWaiver, ['checkRules' => false])) {
            @unlink($pdfPath);
            if ($previewPath !== null && file_exists($previewPath)) {
                @unlink($previewPath);
            }
            $errors = $gatheringWaiver->getErrors();
            Log::error('Failed to save waiver record', ['errors' => $errors, 'data' => $gatheringWaiver->toArray()]);
            $errorMessages = [];
            foreach ($errors as $field => $fieldErrors) {
                foreach ($fieldErrors as $error) {
                    $errorMessages[] = "$field: $error";
                }
            }
            $errorDetail = !empty($errorMessages) ? implode(', ', $errorMessages) : 'Unknown validation error';
            return new ServiceResult(false, 'Failed to save waiver record: ' . $errorDetail);
        }

        // Create UploadedFile object from the converted PDF
        // This allows us to use DocumentService for consistent storage handling (local/Azure)
        $uploadedPdf = new \Laminas\Diactoros\UploadedFile(
            $pdfPath,
            $convertedSize,
            UPLOAD_ERR_OK,
            $originalFilename,
            'application/pdf'
        );

        // Get current user ID for uploaded_by
        $currentUserId = $this->Authentication->getIdentity()->getIdentifier();

        try {
            // Store document using DocumentService (handles local or Azure storage)
            $documentResult = $this->DocumentService->createDocument(
                $uploadedPdf,
                'GatheringWaiver',
                $gatheringWaiver->id,
                $currentUserId,
                [
                    'original_filename' => $originalFilename,
                    'original_size' => $totalOriginalSize,
                    'converted_size' => $convertedSize,
                    'conversion_date' => date('Y-m-d H:i:s'),
                    'compression_ratio' => round((1 - ($convertedSize / $totalOriginalSize)) * 100, 2),
                    'source' => 'waiver_upload',
                    'page_count' => $pageCount,
                    'is_multipage' => $pageCount > 1,
                ],
                'waivers', // subdirectory
                ['pdf'],   // allowed extensions
                $previewPath
            );
        } catch (\Throwable $exception) {
            @unlink($pdfPath);
            if ($previewPath !== null && file_exists($previewPath)) {
                @unlink($previewPath);
            }

            $this->GatheringWaivers->delete($gatheringWaiver);
            Log::error('Document creation threw an exception (multi-image)', [
                'gathering_waiver_id' => $gatheringWaiver->id,
                'error' => $exception->getMessage(),
            ]);

            return new ServiceResult(false, __('Failed to save document: {0}', $exception->getMessage()));
        }

        // Clean up temporary PDF file regardless of outcome
        @unlink($pdfPath);

        if (!$documentResult->success) {
            if ($previewPath !== null && file_exists($previewPath)) {
                @unlink($previewPath);
            }
            Log::error('Document service returned failure (multi-image)', [
                'gathering_waiver_id' => $gatheringWaiver->id,
                'error' => $documentResult->getError(),
            ]);
            $this->GatheringWaivers->delete($gatheringWaiver);

            return new ServiceResult(false, 'Failed to save document: ' . $documentResult->getError());
        }

        $documentId = (int)$documentResult->getData();

        // Update GatheringWaiver with the real document_id
        $gatheringWaiver->document_id = $documentId;
        if (!$this->GatheringWaivers->save($gatheringWaiver)) {
            // Clean up document record if update fails
            $this->DocumentService->deleteDocument($documentId);
            $this->GatheringWaivers->delete($gatheringWaiver);
            return new ServiceResult(false, 'Failed to update waiver with document ID');
        }

        $resultData = [
            'waiver_id' => $gatheringWaiver->id,
            'document_id' => $documentId,
            'page_count' => $pageCount,
        ];

        // Include warning about skipped files with their names
        if (!empty($skippedFiles)) {
            $resultData['skipped_files'] = $skippedFiles;
            $fileList = implode(', ', $skippedFiles);
            $resultData['warning'] = __('The following file(s) could not be processed due to unsupported PDF compression and were skipped: {0}', $fileList);
        }

        return new ServiceResult(true, null, $resultData);
    }

    /**
     * Get required waiver types for a gathering based on selected activities
     *
     * @param \App\Model\Entity\Gathering $gathering Gathering entity
     * @return array Array of waiver types
     */
    private function _getRequiredWaiverTypes($gathering): array
    {
        if (empty($gathering->gathering_activities)) {
            return [];
        }

        // Get activity IDs
        $activityIds = array_column($gathering->gathering_activities, 'id');

        // Query through the plugin's association to get required waiver types
        $GatheringActivityWaivers = $this->fetchTable('Waivers.GatheringActivityWaivers');
        $activityWaivers = $GatheringActivityWaivers->find()
            ->where(['GatheringActivityWaivers.gathering_activity_id IN' => $activityIds])
            ->contain(['WaiverTypes'])
            ->all();

        // Extract unique waiver types
        $waiverTypes = [];
        $uniqueWaiverTypeIds = [];
        foreach ($activityWaivers as $activityWaiver) {
            if ($activityWaiver->waiver_type && !in_array($activityWaiver->waiver_type_id, $uniqueWaiverTypeIds)) {
                $uniqueWaiverTypeIds[] = $activityWaiver->waiver_type_id;
                $waiverTypes[] = $activityWaiver->waiver_type;
            }
        }

        return $waiverTypes;
    }

    /**
     * Build a summary of existing waiver uploads and attestations for a gathering.
     *
     * @param int $gatheringId Gathering ID
     * @param array $requiredWaiverTypes Required waiver types
     * @return array Summary of waiver status per type
     */
    private function _getWaiverStatusSummary(int $gatheringId, array $requiredWaiverTypes): array
    {
        if (empty($requiredWaiverTypes)) {
            return [];
        }

        $summary = [];
        $waiverTypeIds = [];
        foreach ($requiredWaiverTypes as $waiverType) {
            $waiverTypeIds[] = $waiverType->id;
            $summary[$waiverType->id] = [
                'id' => $waiverType->id,
                'name' => $waiverType->name,
                'uploaded_count' => 0,
                'attestation_reasons' => [],
            ];
        }

        if (empty($waiverTypeIds)) {
            return [];
        }

        $existingWaivers = $this->GatheringWaivers->find()
            ->where([
                'GatheringWaivers.gathering_id' => $gatheringId,
                'GatheringWaivers.waiver_type_id IN' => $waiverTypeIds,
                'GatheringWaivers.declined_at IS' => null,
                'GatheringWaivers.deleted IS' => null,
                'GatheringWaivers.status !=' => 'deleted',
            ])
            ->select(['waiver_type_id', 'is_exemption', 'exemption_reason'])
            ->all();

        foreach ($existingWaivers as $waiver) {
            if (!isset($summary[$waiver->waiver_type_id])) {
                continue;
            }

            if ($waiver->is_exemption) {
                $reason = $waiver->exemption_reason ?: __('Attested');
                $summary[$waiver->waiver_type_id]['attestation_reasons'][] = $reason;
            } else {
                $summary[$waiver->waiver_type_id]['uploaded_count']++;
            }
        }

        foreach ($summary as &$item) {
            if (!empty($item['attestation_reasons'])) {
                $item['attestation_reasons'] = array_values(array_unique($item['attestation_reasons']));
            }
        }
        unset($item);

        return array_values($summary);
    }

    /**
     * Check if a waiver type has been attested as not needed for a gathering.
     *
     * @param int $gatheringId Gathering ID
     * @param int $waiverTypeId Waiver type ID
     * @return bool True if an attestation exists
     */
    private function _isWaiverTypeAttested(int $gatheringId, int $waiverTypeId): bool
    {
        return $this->GatheringWaivers->find()
            ->where([
                'GatheringWaivers.gathering_id' => $gatheringId,
                'GatheringWaivers.waiver_type_id' => $waiverTypeId,
                'GatheringWaivers.is_exemption' => true,
                'GatheringWaivers.declined_at IS' => null,
                'GatheringWaivers.deleted IS' => null,
                'GatheringWaivers.status !=' => 'deleted',
            ])
            ->count() > 0;
    }

    /**
     * Download method - Serve waiver PDF file securely
     *
     * @param string|null $id Gathering Waiver id.
     * @return \Cake\Http\Response File download response
     * @throws \Cake\Http\Exception\NotFoundException When record not found.
     */
    public function download(?string $id = null)
    {
        $gatheringWaiver = $this->GatheringWaivers->get($id, [
            'contain' => ['Documents', 'Gatherings'],
        ]);

        $this->Authorization->authorize($gatheringWaiver);

        if (!$gatheringWaiver->document) {
            throw new NotFoundException(__('Document not found for this waiver'));
        }

        // Get file download response from DocumentService
        $response = $this->DocumentService->getDocumentDownloadResponse(
            $gatheringWaiver->document,
            'waiver_' . $gatheringWaiver->id . '.pdf'
        );

        if ($response === null) {
            throw new NotFoundException(__('File not found'));
        }

        return $response;
    }

    /**
     * Inline PDF method - Stream waiver PDF for browser embedding/review.
     *
     * @param string|null $id Gathering Waiver id.
     * @return \Cake\Http\Response Inline PDF response
     * @throws \Cake\Http\Exception\NotFoundException When record/file not found.
     */
    public function inlinePdf(?string $id = null)
    {
        $gatheringWaiver = $this->GatheringWaivers->get($id, [
            'contain' => ['Documents', 'Gatherings'],
        ]);

        $this->Authorization->authorize($gatheringWaiver);

        if (!$gatheringWaiver->document) {
            throw new NotFoundException(__('Document not found for this waiver'));
        }

        $response = $this->DocumentService->getDocumentInlineResponse(
            $gatheringWaiver->document,
            'waiver_' . $gatheringWaiver->id . '.pdf'
        );

        if ($response === null) {
            throw new NotFoundException(__('File not found'));
        }

        return $response;
    }

    /**
     * Preview method - Serve the generated JPEG preview for a waiver PDF.
     *
     * @param string|null $id Gathering Waiver id.
     * @return \Cake\Http\Response Inline image response
     * @throws \Cake\Http\Exception\NotFoundException When preview is not available
     */
    public function preview(?string $id = null)
    {
        $gatheringWaiver = $this->GatheringWaivers->get($id, [
            'contain' => ['Documents', 'Gatherings'],
        ]);

        $this->Authorization->authorize($gatheringWaiver);

        if (!$gatheringWaiver->document) {
            throw new NotFoundException(__('Document not found for this waiver'));
        }

        $response = $this->DocumentService->getDocumentPreviewResponse($gatheringWaiver->document);

        if ($response === null) {
            throw new NotFoundException(__('Preview not available for this waiver.'));
        }

        return $response;
    }

    /**
     * Change waiver type and activity associations
     *
     * Allows authorized users to change which waiver type and activities
     * an uploaded waiver is associated with. This is useful for correcting
     * mistakes made during upload.
     *
     * Requires canChangeWaiverType authorization policy.
     *
     * @param string|null $id Waiver id.
     * @return \Cake\Http\Response|null Redirects on success.
     * @throws \Cake\Http\Exception\NotFoundException When record not found.
     * @throws \Cake\Http\Exception\BadRequestException When validation fails.
     */
    public function changeTypeActivities(?string $id = null)
    {
        $this->request->allowMethod(['post', 'put']);

        $gatheringWaiver = $this->GatheringWaivers->get($id, [
            'contain' => [
                'Gatherings',
                'WaiverTypes',
            ],
        ]);

        // Check authorization - this uses the canChangeWaiverType policy
        $this->Authorization->authorize($gatheringWaiver, 'changeWaiverType');

        $data = $this->request->getData();
        $waiverTypeId = $data['waiver_type_id'] ?? null;

        // Validate inputs
        if (empty($waiverTypeId)) {
            $this->Flash->error(__('Please select a waiver type.'));
            return $this->redirect(['action' => 'view', $id]);
        }

        // Verify waiver type exists
        $WaiverTypes = $this->fetchTable('Waivers.WaiverTypes');
        $newWaiverType = $WaiverTypes->get($waiverTypeId);
        if (!$newWaiverType) {
            $this->Flash->error(__('Invalid waiver type selected.'));
            return $this->redirect(['action' => 'view', $id]);
        }
        $oldWaiverTypeName = $gatheringWaiver->waiver_type->name;

        $gatheringWaiver->waiver_type_id = $waiverTypeId;

        if ($this->GatheringWaivers->save($gatheringWaiver)) {
            $auditNote = $this->GatheringWaivers->AuditNotes->newEntity([
                'entity_id' => $id,
                'entity_type' => 'Waivers.GatheringWaivers',
                'subject' => 'Waiver Type Changed',
                'body' => $this->_buildAuditNoteBody(
                    $oldWaiverTypeName,
                    $newWaiverType->name,
                    [],
                    []
                ),
                'author_id' => $this->Authentication->getIdentity()->id,
                'private' => false,
            ]);

            if (!$this->GatheringWaivers->AuditNotes->save($auditNote)) {
                Log::warning('Failed to save audit note when changing waiver type', [
                    'waiver_id' => $id,
                    'errors' => $auditNote->getErrors(),
                ]);
            }

            $this->Flash->success(__('Waiver type has been updated successfully.'));
        } else {
            $this->Flash->error(__('Failed to update waiver.'));
            Log::error('Error updating waiver type');
        }

        return $this->redirect(['action' => 'view', $id]);
    }

    /**
     * Build the audit note body with before/after comparison
     *
     * @param string $oldWaiverType Old waiver type name
     * @param string $newWaiverType New waiver type name
     * @param array $oldActivities Old activity names
     * @param array $newActivities New activity names
     * @return string Formatted audit note body
     */
    private function _buildAuditNoteBody(
        string $oldWaiverType,
        string $newWaiverType,
        array $oldActivities,
        array $newActivities
    ): string {
        $body = "Administrative change made to waiver associations.\n\n";

        // Waiver type changes
        $body .= "=== Waiver Type ===\n";
        if ($oldWaiverType !== $newWaiverType) {
            $body .= "Changed from: {$oldWaiverType}\n";
            $body .= "Changed to: {$newWaiverType}\n\n";
        } else {
            $body .= "Unchanged: {$oldWaiverType}\n\n";
        }

        // Activity associations changes
        $body .= "=== Activity Associations ===\n";

        // Activities removed
        $removedActivities = array_diff($oldActivities, $newActivities);
        if (!empty($removedActivities)) {
            $body .= "Removed from:\n";
            foreach ($removedActivities as $activity) {
                $body .= "  - {$activity}\n";
            }
            $body .= "\n";
        }

        // Activities added
        $addedActivities = array_diff($newActivities, $oldActivities);
        if (!empty($addedActivities)) {
            $body .= "Added to:\n";
            foreach ($addedActivities as $activity) {
                $body .= "  + {$activity}\n";
            }
            $body .= "\n";
        }

        // Activities unchanged
        $unchangedActivities = array_intersect($oldActivities, $newActivities);
        if (!empty($unchangedActivities)) {
            $body .= "Remained on:\n";
            foreach ($unchangedActivities as $activity) {
                $body .= "  = {$activity}\n";
            }
            $body .= "\n";
        }

        $body .= "=== Summary ===\n";
        $body .= sprintf(
            "Changed from %d activity association(s) to %d activity association(s).",
            count($oldActivities),
            count($newActivities)
        );

        return $body;
    }

    /**
     * Delete method - Delete expired waivers only
     *
     * Only waivers with status='expired' can be deleted.
     * Requires appropriate authorization.
     *
     * @param string|null $id Gathering Waiver id.
     * @return \Cake\Http\Response|null Redirects on success.
     * @throws \Cake\Http\Exception\NotFoundException When record not found.
     */
    public function delete(?string $id = null)
    {
        $this->request->allowMethod(['post', 'delete']);

        $gatheringWaiver = $this->GatheringWaivers->get($id, [
            'contain' => ['Documents'],
        ]);

        $this->Authorization->authorize($gatheringWaiver);

        // Only allow deletion of expired waivers
        if ($gatheringWaiver->status !== 'expired') {
            $this->Flash->error(__('Only expired waivers can be deleted.'));
            return $this->redirect($this->referer());
        }

        // Delete document first
        if ($gatheringWaiver->document_id) {
            $this->DocumentService->deleteDocument($gatheringWaiver->document_id);
        }

        // Delete waiver record
        if ($this->GatheringWaivers->delete($gatheringWaiver)) {
            $this->Flash->success(__('The waiver has been deleted.'));
        } else {
            $this->Flash->error(__('The waiver could not be deleted. Please, try again.'));
        }

        return $this->redirect($this->referer());
    }

    /**
     * Decline method - Decline/reject an invalid waiver
     *
     * Allows authorized users to decline waivers that were submitted but are invalid.
     * Waivers can only be declined within 30 days of upload.
     *
     * Requirements:
     * - User must have decline permission
     * - Waiver must be within 30 days of upload
     * - Waiver must not already be declined
     * - Decline reason must be provided
     *
     * @param string|null $id Gathering Waiver id.
     * @return \Cake\Http\Response|null Redirects on success.
     * @throws \Cake\Http\Exception\NotFoundException When record not found.
     */
    public function decline(?string $id = null)
    {
        $this->request->allowMethod(['post', 'put', 'patch']);

        $gatheringWaiver = $this->GatheringWaivers->get($id, [
            'contain' => ['Gatherings', 'WaiverTypes'],
        ]);

        $this->Authorization->authorize($gatheringWaiver, 'decline');

        // Check if waiver can be declined
        if (!$gatheringWaiver->can_be_declined) {
            if ($gatheringWaiver->is_declined) {
                $this->Flash->error(__('This waiver has already been declined.'));
            } elseif ($gatheringWaiver->status === 'expired' || $gatheringWaiver->status === 'deleted') {
                $this->Flash->error(__('Expired or deleted waivers cannot be declined.'));
            } else {
                $this->Flash->error(__('This waiver can no longer be declined. Waivers can only be declined within 30 days of upload.'));
            }
            return $this->redirect($this->referer());
        }

        // Get decline reason from request
        $declineReason = $this->request->getData('decline_reason');

        if (empty($declineReason)) {
            $this->Flash->error(__('Please provide a reason for declining this waiver.'));
            return $this->redirect($this->referer());
        }

        // Update waiver with decline information
        $gatheringWaiver->declined_at = new \Cake\I18n\DateTime();
        $gatheringWaiver->declined_by = $this->Authentication->getIdentity()->getIdentifier();
        $gatheringWaiver->decline_reason = $declineReason;
        $gatheringWaiver->status = 'declined';

        if ($this->GatheringWaivers->save($gatheringWaiver)) {
            $this->Flash->success(__('The waiver has been declined.'));

            // Log the decline action
            Log::info('Waiver declined', [
                'waiver_id' => $gatheringWaiver->id,
                'gathering_id' => $gatheringWaiver->gathering_id,
                'declined_by' => $gatheringWaiver->declined_by,
                'decline_reason' => $declineReason,
            ]);
        } else {
            $this->Flash->error(__('The waiver could not be declined. Please, try again.'));
        }

        return $this->redirect($this->referer());
    }

    /**
     * Needing Waivers method - List gatherings that need waivers uploaded
     *
     * Shows gatherings where:
     * - End date is today or in the future
     * - Have required waivers configured
     * - Missing at least one required waiver upload
     * - User has permission to upload waivers for the gathering's branch
     *
     * This provides an action list for users with waiver upload permissions.
     *
     * @return \Cake\Http\Response|null|void Renders view
     */
    public function needingWaivers()
    {
        // Authorize access to this action
        $this->authorizeCurrentUrl();

        $currentUser = $this->Authentication->getIdentity();

        // Get branches user can upload waivers for via standard permissions
        $branchIds = $currentUser->getBranchIdsForAction('uploadWaivers', 'Waivers.GatheringWaivers');

        // Get gathering IDs where user is a steward (these are allowed even without branch permissions)
        $GatheringStaff = $this->fetchTable('GatheringStaff');
        $stewardGatheringIds = $GatheringStaff->find()
            ->where([
                'GatheringStaff.member_id' => $currentUser->getIdentifier(),
                'GatheringStaff.is_steward' => true,
            ])
            ->select(['gathering_id'])
            ->all()
            ->extract('gathering_id')
            ->toArray();

        // Determine access mode
        $hasGlobalAccess = ($branchIds === null);
        $hasBranchAccess = !empty($branchIds);
        $hasStewardAccess = !empty($stewardGatheringIds);

        // If user has no permissions at all (no branch access and not a steward), show empty list
        if (!$hasGlobalAccess && !$hasBranchAccess && !$hasStewardAccess) {
            $this->set('gatherings', []);
            $this->set('gatheringsNeedingWaivers', []);
            return;
        }

        // Get all branch IDs for global access
        if ($hasGlobalAccess) {
            $Branches = $this->fetchTable('Branches');
            $branchIds = $Branches->find()->select(['id'])->all()->extract('id')->toArray();
        }

        // Get gatherings - show ALL non-closed gatherings user has access to
        $Gatherings = $this->fetchTable('Gatherings');
        $today = Date::now()->toDateString();
        $oneWeekFromNow = Date::now()->addDays(7)->toDateString();
        $GatheringWaiverClosures = $this->fetchTable('Waivers.GatheringWaiverClosures');
        $closedGatheringIds = $GatheringWaiverClosures->getClosedGatheringIds();

        // Build the access condition: branch-based OR steward-based
        $accessConditions = [];
        if (!empty($branchIds)) {
            $accessConditions[] = ['Gatherings.branch_id IN' => $branchIds];
        }
        if (!empty($stewardGatheringIds)) {
            $accessConditions[] = ['Gatherings.id IN' => $stewardGatheringIds];
        }

        // If no access conditions, show empty (shouldn't happen due to earlier check)
        if (empty($accessConditions)) {
            $this->set('gatherings', []);
            $this->set('gatheringsNeedingWaivers', []);
            return;
        }

        // Find gatherings with:
        // 1. Past gatherings (already started) OR future gatherings starting within 7 days
        // 2. In branches user has permission for OR user is a steward
        // 3. Have required waivers configured (checked via activities)
        // 4. Not cancelled
        // Note: Past gatherings remain visible until waiver secretary closes them
        $query = $Gatherings->find()
            ->where([
                'OR' => [
                    'Gatherings.start_date <' => $today, // Already started (past or ongoing)
                    'Gatherings.start_date <=' => $oneWeekFromNow, // Starts within next 7 days
                ],
                'Gatherings.deleted IS' => null,
                'Gatherings.cancelled_at IS' => null,
            ])
            ->where(['OR' => $accessConditions])
            ->contain([
                'Branches',
                'GatheringTypes',
                'GatheringActivities' => function ($q) {
                    return $q->select(['id', 'name']);
                },
            ])
            ->orderBy(['Gatherings.start_date' => 'ASC', 'Gatherings.name' => 'ASC']);

        // Exclude closed gatherings
        if (!empty($closedGatheringIds)) {
            $query->where(['Gatherings.id NOT IN' => $closedGatheringIds]);
        }

        $allGatherings = $query->all();

        // Get ready-to-close gathering IDs
        $readyToCloseGatheringIds = $GatheringWaiverClosures->getReadyToCloseGatheringIds();

        // Process gatherings to determine waiver status for each
        $GatheringActivityWaivers = $this->fetchTable('Waivers.GatheringActivityWaivers');
        $gatherings = [];
        $incompleteCount = 0;

        foreach ($allGatherings as $gathering) {
            // Default: no waivers needed/missing
            $gathering->missing_waiver_count = 0;
            $gathering->missing_waiver_names = [];
            $gathering->uploaded_waiver_count = 0;
            $gathering->has_waiver_requirements = false;
            $gathering->is_waiver_complete = true;
            $gathering->is_ready_to_close = in_array($gathering->id, $readyToCloseGatheringIds, true);

            if (!empty($gathering->gathering_activities)) {
                $activityIds = collection($gathering->gathering_activities)->extract('id')->toArray();

                // Get required waiver types for this gathering's activities
                $requiredWaiverTypes = $GatheringActivityWaivers->find()
                    ->where([
                        'gathering_activity_id IN' => $activityIds,
                        'deleted IS' => null,
                    ])
                    ->select(['waiver_type_id'])
                    ->distinct(['waiver_type_id'])
                    ->all()
                    ->extract('waiver_type_id')
                    ->toArray();

                if (!empty($requiredWaiverTypes)) {
                    $gathering->has_waiver_requirements = true;

                    // Get uploaded waiver types for this gathering
                    $uploadedWaiverTypes = $this->GatheringWaivers->find()
                        ->where([
                            'gathering_id' => $gathering->id,
                            'deleted IS' => null,
                            'declined_at IS' => null, // Exclude declined waivers
                        ])
                        ->select(['waiver_type_id'])
                        ->distinct(['waiver_type_id'])
                        ->all()
                        ->extract('waiver_type_id')
                        ->toArray();

                    // Check if any required waivers are missing
                    $missingWaiverTypes = array_diff($requiredWaiverTypes, $uploadedWaiverTypes);

                    if (!empty($missingWaiverTypes)) {
                        // Load waiver type names
                        $WaiverTypes = $this->fetchTable('Waivers.WaiverTypes');
                        $missingWaiverNames = $WaiverTypes->find()
                            ->where(['id IN' => $missingWaiverTypes])
                            ->orderBy(['name' => 'ASC'])
                            ->all()
                            ->extract('name')
                            ->toArray();

                        $gathering->missing_waiver_count = count($missingWaiverTypes);
                        $gathering->missing_waiver_names = $missingWaiverNames;
                        $gathering->uploaded_waiver_count = count($uploadedWaiverTypes);
                        $gathering->is_waiver_complete = false;
                        $incompleteCount++;
                    } else {
                        $gathering->uploaded_waiver_count = count($uploadedWaiverTypes);
                    }

                    // Include all gatherings with waiver requirements (complete or not)
                    $gatherings[] = $gathering;
                }
            }
        }

        $this->set(compact('gatherings', 'incompleteCount'));
    }

    /**
     * Dashboard method - Comprehensive waiver secretary dashboard
     *
     * Provides a centralized view of waiver compliance status including:
     * - Overall statistics and metrics
     * - Gatherings missing waivers
     * - Branches with compliance issues
     * - Recent waiver activity
     * - Search functionality
     *
     * @return \Cake\Http\Response|null|void Renders dashboard view
     */
    public function dashboard()
    {
        // Authorize access to dashboard using URL-based authorization
        $this->authorizeCurrentUrl();

        $currentUser = $this->Authentication->getIdentity();
        $branchIds = $currentUser->getBranchIdsForAction('add', 'Waivers.GatheringWaivers');

        // If user has no permissions, redirect
        if (is_array($branchIds) && empty($branchIds)) {
            $this->Flash->error(__('You do not have permission to access the waiver dashboard.'));
            return $this->redirect(['controller' => 'Pages', 'action' => 'display', 'home']);
        }

        // Get all branches or filtered by permission
        $Branches = $this->fetchTable('Branches');
        if ($branchIds === null) {
            // Global permission - all branches
            $branchIds = $Branches->find()->select(['id'])->all()->extract('id')->toArray();
        }

        // Handle waiver search
        $searchResults = null;
        $searchTerm = $this->request->getQuery('search');
        if ($searchTerm) {
            $searchResults = $this->_searchWaivers($searchTerm, $branchIds);
        }

        // Get key statistics
        $statistics = $this->_getDashboardStatistics($branchIds);

        // Get gatherings with incomplete waivers (separated by status)
        $waiverGatherings = $this->_getGatheringsWithIncompleteWaivers($branchIds, 30);
        $gatheringsMissingWaivers = $waiverGatherings['missing']; // Past events (>48hrs after end)
        $gatheringsNeedingWaivers = $waiverGatherings['upcoming']; // Upcoming/ongoing events

        // Get branches with compliance issues
        $branchesWithIssues = $this->_getBranchesWithIssues($branchIds);

        // Get recent waiver activity (last 30 days)
        $recentActivity = $this->_getRecentWaiverActivity($branchIds, 30);

        // Get waiver types summary
        $waiverTypesSummary = $this->_getWaiverTypesSummary($branchIds);

        // Get compliance days setting
        $complianceDays = (int)StaticHelpers::getAppSetting('Waivers.ComplianceDays', '2', 'int', false);

        // Get gatherings marked ready to close
        $gatheringsReadyToClose = $this->_getGatheringsReadyToClose($branchIds);

        // Get gatherings in progress (waivers uploaded/exempted, not ready/closed)
        $gatheringsNeedingClosed = $this->_getGatheringsNeedingClosed($branchIds);

        // Keep past-due "Gatherings Needing Waivers" focused on events with no waiver progress.
        // Any event with uploaded/exempted waivers belongs in "In Progress Waivers".
        $gatheringsMissingWaivers = array_values(array_filter(
            $gatheringsMissingWaivers,
            fn($gathering) => ($gathering->uploaded_waiver_count ?? 0) === 0
        ));

        // Get recently closed gatherings
        $closedGatherings = $this->_getClosedGatherings($branchIds);

        $this->set(compact(
            'statistics',
            'gatheringsMissingWaivers',
            'gatheringsNeedingWaivers',
            'gatheringsReadyToClose',
            'gatheringsNeedingClosed',
            'closedGatherings',
            'branchesWithIssues',
            'recentActivity',
            'waiverTypesSummary',
            'searchResults',
            'searchTerm',
            'complianceDays'
        ));
    }

    /**
     * Return JSON calendar data for gatherings in a given month
     *
     * @return \Cake\Http\Response JSON response with gathering calendar data
     */
    public function calendarData()
    {
        $this->authorizeCurrentUrl();
        $this->request->allowMethod(['get']);

        $year = (int)$this->request->getQuery('year', date('Y'));
        $month = (int)$this->request->getQuery('month', date('n'));

        // Clamp values
        if ($year < 2000 || $year > 2100) {
            $year = (int)date('Y');
        }
        if ($month < 1 || $month > 12) {
            $month = (int)date('n');
        }

        $currentUser = $this->Authentication->getIdentity();
        $userTimezone = \App\KMP\TimezoneHelper::getUserTimezone($currentUser);
        $timezone = new \DateTimeZone($userTimezone);
        $branchIds = $currentUser->getBranchIdsForAction('add', 'Waivers.GatheringWaivers');
        $Branches = $this->fetchTable('Branches');
        if ($branchIds === null) {
            $branchIds = $Branches->find()->select(['id'])->all()->extract('id')->toArray();
        }
        if (is_array($branchIds) && empty($branchIds)) {
            return $this->response->withType('application/json')
                ->withStringBody(json_encode(['events' => []]));
        }

        // Build month boundaries in user timezone, then convert to UTC for querying
        $startOfMonth = new \DateTime(sprintf('%04d-%02d-01', $year, $month), $timezone);
        $startOfMonth->setTime(0, 0, 0);
        $endOfMonth = (clone $startOfMonth)->modify('last day of this month')->setTime(23, 59, 59);

        $startOfMonthUtc = \App\KMP\TimezoneHelper::toUtc($startOfMonth->format('Y-m-d H:i:s'), $userTimezone);
        $endOfMonthUtc = \App\KMP\TimezoneHelper::toUtc($endOfMonth->format('Y-m-d H:i:s'), $userTimezone);
        $startUtcString = $startOfMonthUtc->format('Y-m-d H:i:s');
        $endUtcString = $endOfMonthUtc->format('Y-m-d H:i:s');

        $Gatherings = $this->fetchTable('Gatherings');
        $GatheringWaiverClosures = $this->fetchTable('Waivers.GatheringWaiverClosures');
        $GatheringActivityWaivers = $this->fetchTable('Waivers.GatheringActivityWaivers');

        $closedGatheringIds = $GatheringWaiverClosures->getClosedGatheringIds();
        $readyToCloseGatheringIds = $GatheringWaiverClosures->getReadyToCloseGatheringIds();

        $gatherings = $Gatherings->find()
            ->where([
                'Gatherings.branch_id IN' => $branchIds,
                'Gatherings.deleted IS' => null,
                'Gatherings.cancelled_at IS' => null,
                'Gatherings.start_date <=' => $endUtcString,
                'OR' => [
                    'Gatherings.end_date >=' => $startUtcString,
                    'AND' => [
                        'Gatherings.end_date IS' => null,
                        'Gatherings.start_date >=' => $startUtcString,
                    ],
                ],
            ])
            ->contain([
                'Branches' => function ($q) {
                    return $q->select(['id', 'name']);
                },
                'GatheringActivities' => function ($q) {
                    return $q->select(['id', 'name']);
                },
            ])
            ->orderBy(['Gatherings.start_date' => 'ASC'])
            ->all();

        $events = [];
        foreach ($gatherings as $gathering) {
            $isClosed = in_array($gathering->id, $closedGatheringIds);

            // Determine waiver requirements
            $activityIds = collection($gathering->gathering_activities)->extract('id')->toArray();
            $requiredCount = 0;
            if (!empty($activityIds)) {
                $requiredCount = $GatheringActivityWaivers->find()
                    ->where([
                        'gathering_activity_id IN' => $activityIds,
                        'deleted IS' => null,
                    ])
                    ->select(['waiver_type_id'])
                    ->distinct(['waiver_type_id'])
                    ->count();
            }

            // Skip gatherings with no waiver requirements (unless closed with waivers)
            if ($requiredCount === 0 && !$isClosed) {
                continue;
            }

            // Get detailed waiver counts
            $uploadedCount = $this->GatheringWaivers->find()
                ->where([
                    'gathering_id' => $gathering->id,
                    'deleted IS' => null,
                    'declined_at IS' => null,
                    'is_exemption' => false,
                ])
                ->count();

            $exemptedCount = $this->GatheringWaivers->find()
                ->where([
                    'gathering_id' => $gathering->id,
                    'deleted IS' => null,
                    'declined_at IS' => null,
                    'is_exemption' => true,
                ])
                ->count();

            $uploadedTypeCount = $this->GatheringWaivers->find()
                ->where([
                    'gathering_id' => $gathering->id,
                    'deleted IS' => null,
                    'declined_at IS' => null,
                ])
                ->select(['waiver_type_id'])
                ->distinct(['waiver_type_id'])
                ->count();

            $pendingCount = max(0, $requiredCount - $uploadedTypeCount);

            // Determine status and color
            $status = 'missing';
            $color = 'danger';
            if ($isClosed) {
                $status = 'closed';
                $color = 'primary';
            } elseif ($requiredCount > 0 && $uploadedTypeCount >= $requiredCount) {
                $status = 'complete';
                $color = 'success';
            } elseif ($uploadedTypeCount > 0) {
                $status = 'partial';
                $color = 'warning';
            }

            $startDate = Date::parse($gathering->start_date);
            $endDate = $gathering->end_date ? Date::parse($gathering->end_date) : $startDate;
            $isMultiDay = $startDate->toDateString() !== $endDate->toDateString();

            $events[] = [
                'id' => $gathering->id,
                'name' => $gathering->name,
                'branch' => $gathering->branch ? $gathering->branch->name : '',
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
                'multi_day' => $isMultiDay,
                'status' => $status,
                'color' => $color,
                'uploaded' => $uploadedCount,
                'exempted' => $exemptedCount,
                'pending' => $pendingCount,
                'ready_to_close' => in_array($gathering->id, $readyToCloseGatheringIds),
                'url' => Router::url([
                    'plugin' => 'Waivers',
                    'controller' => 'GatheringWaivers',
                    'action' => 'index',
                    '?' => ['gathering_id' => $gathering->id],
                ]),
            ];
        }

        return $this->response->withType('application/json')
            ->withStringBody(json_encode([
                'year' => $year,
                'month' => $month,
                'monthName' => $startOfMonth->format('F Y'),
                'events' => $events,
            ]));
    }

    /**
     * Get gatherings that have been marked ready to close by event staff
     *
     * @param array $branchIds Branches the user can access
     * @return array Gatherings ready to close
     */
    private function _getGatheringsReadyToClose(array $branchIds): array
    {
        $GatheringWaiverClosures = $this->fetchTable('Waivers.GatheringWaiverClosures');
        $Gatherings = $this->fetchTable('Gatherings');

        // Get all closure records that are ready but not yet closed
        $readyClosures = $GatheringWaiverClosures->find()
            ->where([
                'ready_to_close_at IS NOT' => null,
                'closed_at IS' => null,
            ])
            ->contain(['ReadyToCloseByMembers'])
            ->all()
            ->indexBy('gathering_id')
            ->toArray();

        if (empty($readyClosures)) {
            return [];
        }

        $readyGatheringIds = array_keys($readyClosures);

        // Get gathering details
        $gatherings = $Gatherings->find()
            ->where([
                'Gatherings.id IN' => $readyGatheringIds,
                'Gatherings.branch_id IN' => $branchIds,
                'Gatherings.deleted IS' => null,
            ])
            ->contain([
                'Branches',
                'GatheringActivities' => function ($q) {
                    return $q->select(['id', 'name']);
                },
            ])
            ->orderBy(['Gatherings.start_date' => 'DESC'])
            ->all();

        // Add closure info and waiver stats to each gathering
        $GatheringActivityWaivers = $this->fetchTable('Waivers.GatheringActivityWaivers');
        $result = [];

        foreach ($gatherings as $gathering) {
            $closure = $readyClosures[$gathering->id] ?? null;
            $gathering->ready_to_close_at = $closure?->ready_to_close_at;
            $gathering->ready_to_close_by_member = $closure?->ready_to_close_by_member;

            // Calculate waiver completion status
            $gathering->missing_waiver_count = 0;
            $gathering->missing_waiver_names = [];
            $gathering->is_waiver_complete = true;

            if (!empty($gathering->gathering_activities)) {
                $activityIds = collection($gathering->gathering_activities)->extract('id')->toArray();

                $requiredWaiverTypes = $GatheringActivityWaivers->find()
                    ->where([
                        'gathering_activity_id IN' => $activityIds,
                        'deleted IS' => null,
                    ])
                    ->select(['waiver_type_id'])
                    ->distinct(['waiver_type_id'])
                    ->all()
                    ->extract('waiver_type_id')
                    ->toArray();

                if (!empty($requiredWaiverTypes)) {
                    $uploadedWaiverTypes = $this->GatheringWaivers->find()
                        ->where([
                            'gathering_id' => $gathering->id,
                            'deleted IS' => null,
                            'declined_at IS' => null,
                        ])
                        ->select(['waiver_type_id'])
                        ->distinct(['waiver_type_id'])
                        ->all()
                        ->extract('waiver_type_id')
                        ->toArray();

                    $missingWaiverTypes = array_diff($requiredWaiverTypes, $uploadedWaiverTypes);

                    if (!empty($missingWaiverTypes)) {
                        $WaiverTypes = $this->fetchTable('Waivers.WaiverTypes');
                        $missingWaiverNames = $WaiverTypes->find()
                            ->where(['id IN' => $missingWaiverTypes])
                            ->orderBy(['name' => 'ASC'])
                            ->all()
                            ->extract('name')
                            ->toArray();

                        $gathering->missing_waiver_count = count($missingWaiverTypes);
                        $gathering->missing_waiver_names = $missingWaiverNames;
                        $gathering->is_waiver_complete = false;
                    }
                }
            }

            $result[] = $gathering;
        }

        return $result;
    }

    /**
     * Search for waivers across all accessible gatherings
     *
     * @param string $searchTerm Search term (gathering name, branch name, member name)
     * @param array $branchIds Branches the user can access
     * @return array Search results
     */
    private function _searchWaivers(string $searchTerm, array $branchIds): array
    {
        $query = $this->GatheringWaivers->find()
            ->where([
                'GatheringWaivers.deleted IS' => null,
                'OR' => [
                    'Gatherings.name LIKE' => '%' . $searchTerm . '%',
                    'Branches.name LIKE' => '%' . $searchTerm . '%',
                    'CreatedByMembers.sca_name LIKE' => '%' . $searchTerm . '%',
                    'CreatedByMembers.first_name LIKE' => '%' . $searchTerm . '%',
                    'CreatedByMembers.last_name LIKE' => '%' . $searchTerm . '%',
                    'WaiverTypes.name LIKE' => '%' . $searchTerm . '%',
                ],
            ])
            ->innerJoinWith('Gatherings.Branches', function ($q) use ($branchIds) {
                return $q->where([
                    'Branches.id IN' => $branchIds,
                    'Branches.deleted IS' => null,
                ]);
            })
            ->innerJoinWith('Gatherings', function ($q) {
                return $q->where([
                    'Gatherings.deleted IS' => null,
                ]);
            })
            ->contain([
                'Gatherings' => function ($q) {
                    return $q->select(['id', 'name', 'start_date', 'end_date', 'branch_id']);
                },
                'Gatherings.Branches' => function ($q) {
                    return $q->select(['id', 'name']);
                },
                'WaiverTypes' => function ($q) {
                    return $q->select(['id', 'name']);
                },
                'CreatedByMembers' => function ($q) {
                    return $q->select(['id', 'sca_name', 'first_name', 'last_name']);
                },
            ])
            ->orderBy(['GatheringWaivers.created' => 'DESC'])
            ->limit(50);

        return $query->all()->toArray();
    }

    /**
     * Get dashboard statistics
     *
     * @param array $branchIds Branches to include in statistics
     * @return array Statistics data
     */
    private function _getDashboardStatistics(array $branchIds): array
    {
        $Gatherings = $this->fetchTable('Gatherings');
        $today = Date::now();
        $thirtyDaysAgo = Date::now()->subDays(30);
        $thirtyDaysFromNow = Date::now()->addDays(30);

        // Total waivers count
        $totalWaivers = $this->GatheringWaivers->find()
            ->innerJoinWith('Gatherings', function ($q) use ($branchIds) {
                return $q->where([
                    'Gatherings.branch_id IN' => $branchIds,
                    'Gatherings.deleted IS' => null,
                ]);
            })
            ->where(['GatheringWaivers.deleted IS' => null])
            ->count();

        // Waivers uploaded in last 30 days
        $recentWaivers = $this->GatheringWaivers->find()
            ->innerJoinWith('Gatherings', function ($q) use ($branchIds) {
                return $q->where([
                    'Gatherings.branch_id IN' => $branchIds,
                    'Gatherings.deleted IS' => null,
                ]);
            })
            ->where([
                'GatheringWaivers.deleted IS' => null,
                'GatheringWaivers.created >=' => $thirtyDaysAgo->toDateString(),
            ])
            ->count();

        // Declined waivers count
        $declinedWaivers = $this->GatheringWaivers->find()
            ->innerJoinWith('Gatherings', function ($q) use ($branchIds) {
                return $q->where([
                    'Gatherings.branch_id IN' => $branchIds,
                    'Gatherings.deleted IS' => null,
                ]);
            })
            ->where([
                'GatheringWaivers.deleted IS' => null,
                'GatheringWaivers.declined_at IS NOT' => null,
            ])
            ->count();

        // Get gatherings with incomplete waivers
        $waiverGatherings = $this->_getGatheringsWithIncompleteWaivers($branchIds, 30);
        // Past-due count matches dashboard section: only events with no waiver progress yet.
        $gatheringsMissingCount = count(array_filter(
            $waiverGatherings['missing'],
            fn($gathering) => ($gathering->uploaded_waiver_count ?? 0) === 0
        )); // Past events
        $gatheringsNeedingCount = count($waiverGatherings['upcoming']); // Upcoming events

        // Unique branches with gatherings
        $branchesWithGatherings = $Gatherings->find()
            ->where([
                'branch_id IN' => $branchIds,
                'deleted IS' => null,
                'start_date >=' => $thirtyDaysAgo->toDateString(),
            ])
            ->select(['branch_id'])
            ->distinct(['branch_id'])
            ->count();

        return [
            'totalWaivers' => $totalWaivers,
            'recentWaivers' => $recentWaivers,
            'declinedWaivers' => $declinedWaivers,
            'gatheringsMissingCount' => $gatheringsMissingCount,
            'gatheringsNeedingCount' => $gatheringsNeedingCount,
            'branchesWithGatherings' => $branchesWithGatherings,
        ];
    }

    /**
     * Get gatherings with incomplete waivers, separated by status
     *
     * Returns two arrays:
     * - 'missing': Events that ended >ComplianceDays ago without complete waivers (compliance issue)
     * - 'upcoming': Future/ongoing events without complete waivers (action needed)
     *
     * @param array $branchIds Branches to check
     * @param int $daysAhead How many days ahead to look for upcoming events
     * @return array Array with 'missing' and 'upcoming' keys
     */
    private function _getGatheringsWithIncompleteWaivers(array $branchIds, int $daysAhead): array
    {
        $Gatherings = $this->fetchTable('Gatherings');
        $GatheringActivityWaivers = $this->fetchTable('Waivers.GatheringActivityWaivers');
        $GatheringWaiverClosures = $this->fetchTable('Waivers.GatheringWaiverClosures');
        $WaiverTypes = $this->fetchTable('Waivers.WaiverTypes');
        $today = Date::now();
        $todayString = $today->toDateString();
        $futureDate = Date::now()->addDays($daysAhead)->toDateString();
        // Look back further to catch past due events
        $pastCutoff = Date::now()->subDays(90)->toDateString(); // Look back 90 days for missing waivers

        // Get compliance days from settings (default: 2 days)
        $complianceDays = (int)StaticHelpers::getAppSetting('Waivers.ComplianceDays', '2', 'int', false);

        // Find all gatherings in extended date range (including past events, excluding cancelled)
        $query = $Gatherings->find()
            ->where([
                'OR' => [
                    // Future/ongoing events
                    'Gatherings.end_date >=' => $todayString,
                    // Events with null end date
                    'AND' => [
                        'Gatherings.end_date IS' => null,
                        'Gatherings.start_date >=' => $todayString,
                    ],
                    // Past events (look back up to 90 days)
                    'Gatherings.end_date >=' => $pastCutoff,
                ],
                'Gatherings.start_date <=' => $futureDate,
                'Gatherings.branch_id IN' => $branchIds,
                'Gatherings.deleted IS' => null,
                'Gatherings.cancelled_at IS' => null,
            ])
            ->contain([
                'Branches',
                'GatheringActivities' => function ($q) {
                    return $q->select(['id', 'name']);
                },
            ])
            ->orderBy(['Gatherings.start_date' => 'ASC']);

        $closedGatheringIds = $GatheringWaiverClosures->getClosedGatheringIds();
        if (!empty($closedGatheringIds)) {
            $query->where(['Gatherings.id NOT IN' => $closedGatheringIds]);
        }

        $allGatherings = $query->all()->toArray();
        if (empty($allGatherings)) {
            return [
                'missing' => [],
                'upcoming' => [],
            ];
        }

        $gatheringsMissing = []; // Past events (>ComplianceDays after end)
        $gatheringsUpcoming = []; // Future/ongoing events

        $gatheringIds = [];
        $activityToGatheringMap = [];
        foreach ($allGatherings as $gathering) {
            $gatheringIds[] = (int)$gathering->id;

            foreach ($gathering->gathering_activities ?? [] as $activity) {
                $activityToGatheringMap[(int)$activity->id] = (int)$gathering->id;
            }
        }

        // Batch 1: required waiver types by gathering (grouped via activity IDs).
        $requiredTypeIdsByGathering = [];
        if (!empty($activityToGatheringMap)) {
            $requiredRows = $GatheringActivityWaivers->find()
                ->where([
                    'gathering_activity_id IN' => array_keys($activityToGatheringMap),
                    'deleted IS' => null,
                ])
                ->select([
                    'gathering_activity_id',
                    'waiver_type_id',
                ])
                ->distinct(['gathering_activity_id', 'waiver_type_id'])
                ->all();

            foreach ($requiredRows as $row) {
                $activityId = (int)$row->get('gathering_activity_id');
                $waiverTypeId = (int)$row->get('waiver_type_id');
                $gatheringId = $activityToGatheringMap[$activityId] ?? null;
                if ($gatheringId !== null) {
                    $requiredTypeIdsByGathering[$gatheringId][$waiverTypeId] = true;
                }
            }
        }

        // Batch 2: uploaded waiver types by gathering.
        $uploadedTypeIdsByGathering = [];
        if (!empty($gatheringIds)) {
            $uploadedRows = $this->GatheringWaivers->find()
                ->where([
                    'gathering_id IN' => $gatheringIds,
                    'deleted IS' => null,
                    'declined_at IS' => null,
                ])
                ->select([
                    'gathering_id',
                    'waiver_type_id',
                ])
                ->distinct(['gathering_id', 'waiver_type_id'])
                ->all();

            foreach ($uploadedRows as $row) {
                $gatheringId = (int)$row->get('gathering_id');
                $waiverTypeId = (int)$row->get('waiver_type_id');
                $uploadedTypeIdsByGathering[$gatheringId][$waiverTypeId] = true;
            }
        }

        $statsByGathering = [];
        $allWaiverTypeIds = [];
        foreach ($allGatherings as $gathering) {
            $gatheringId = (int)$gathering->id;
            $requiredWaiverTypes = array_map(
                'intval',
                array_keys($requiredTypeIdsByGathering[$gatheringId] ?? [])
            );
            if (empty($requiredWaiverTypes)) {
                continue;
            }

            $uploadedWaiverTypes = array_map(
                'intval',
                array_keys($uploadedTypeIdsByGathering[$gatheringId] ?? [])
            );
            $missingWaiverTypes = array_values(array_diff($requiredWaiverTypes, $uploadedWaiverTypes));
            if (empty($missingWaiverTypes)) {
                continue;
            }

            $statsByGathering[$gatheringId] = [
                'required_type_ids' => $requiredWaiverTypes,
                'uploaded_type_ids' => $uploadedWaiverTypes,
                'missing_type_ids' => $missingWaiverTypes,
            ];

            foreach (array_merge($uploadedWaiverTypes, $missingWaiverTypes) as $waiverTypeId) {
                $allWaiverTypeIds[(int)$waiverTypeId] = true;
            }
        }

        // Batch 3: resolve waiver type names once.
        $waiverTypeNameMap = [];
        if (!empty($allWaiverTypeIds)) {
            $waiverTypes = $WaiverTypes->find()
                ->where(['id IN' => array_keys($allWaiverTypeIds)])
                ->select(['id', 'name'])
                ->all();

            foreach ($waiverTypes as $waiverType) {
                $waiverTypeNameMap[(int)$waiverType->id] = (string)$waiverType->name;
            }
        }

        foreach ($allGatherings as $gathering) {
            $gatheringId = (int)$gathering->id;
            if (empty($statsByGathering[$gatheringId])) {
                continue;
            }

            $uploadedWaiverNames = array_values(array_filter(array_map(
                static fn($waiverTypeId) => $waiverTypeNameMap[(int)$waiverTypeId] ?? null,
                $statsByGathering[$gatheringId]['uploaded_type_ids']
            )));
            sort($uploadedWaiverNames, SORT_NATURAL | SORT_FLAG_CASE);

            $missingWaiverNames = array_values(array_filter(array_map(
                static fn($waiverTypeId) => $waiverTypeNameMap[(int)$waiverTypeId] ?? null,
                $statsByGathering[$gatheringId]['missing_type_ids']
            )));
            sort($missingWaiverNames, SORT_NATURAL | SORT_FLAG_CASE);

            $gathering->missing_waiver_count = count($statsByGathering[$gatheringId]['missing_type_ids']);
            $gathering->missing_waiver_names = $missingWaiverNames;
            $gathering->uploaded_waiver_count = count($statsByGathering[$gatheringId]['uploaded_type_ids']);
            $gathering->uploaded_waiver_names = $uploadedWaiverNames;

            // Determine if this is missing (>ComplianceDays past end) or upcoming
            $endDate = $gathering->end_date ? Date::parse($gathering->end_date) : Date::parse($gathering->start_date);
            $daysAfterEnd = $today->diffInDays($endDate, false);

            // diffInDays with false returns negative if end date is in the past
            // So -3 means 3 days ago, -1 means 1 day ago, +1 means 1 day in future
            if ($daysAfterEnd < -$complianceDays) {
                // Event ended more than ComplianceDays ago - MISSING (compliance issue)
                // Example: If complianceDays=2, daysAfterEnd=-3 means event ended 3 days ago (past due)
                $gatheringsMissing[] = $gathering;
            } else {
                // Event is upcoming, ongoing, or ended within ComplianceDays - UPCOMING (action needed)
                // Example: If complianceDays=2, daysAfterEnd=-1 (ended yesterday), 0 (ends today), +5 (5 days from now)
                $gatheringsUpcoming[] = $gathering;
            }
        }

        return [
            'missing' => $gatheringsMissing,
            'upcoming' => $gatheringsUpcoming,
        ];
    }

    /**
     * Get branches with compliance issues
     *
     * @param array $branchIds Branches to check
     * @return array Branches with issue counts
     */
    private function _getBranchesWithIssues(array $branchIds): array
    {
        $Gatherings = $this->fetchTable('Gatherings');
        $Branches = $this->fetchTable('Branches');
        $waiverGatherings = $this->_getGatheringsWithIncompleteWaivers($branchIds, 60);

        // Only use 'missing' gatherings (past due >2 days) for compliance issues
        // Upcoming events are not compliance issues yet
        $allGatheringsWithIssues = $waiverGatherings['missing'];

        // Group by branch
        $branchIssues = [];
        foreach ($allGatheringsWithIssues as $gathering) {
            $branchId = $gathering->branch_id;
            if (!isset($branchIssues[$branchId])) {
                $branchIssues[$branchId] = [
                    'branch' => $gathering->branch,
                    'gathering_count' => 0,
                    'total_missing_waivers' => 0,
                ];
            }
            $branchIssues[$branchId]['gathering_count']++;
            $branchIssues[$branchId]['total_missing_waivers'] += $gathering->missing_waiver_count;
        }

        // Sort by gathering count descending
        usort($branchIssues, function ($a, $b) {
            return $b['gathering_count'] <=> $a['gathering_count'];
        });

        return array_slice($branchIssues, 0, 10); // Top 10 branches with issues
    }

    /**
     * Get recent waiver activity
     *
     * @param array $branchIds Branches to include
     * @param int $days Days to look back
     * @return array Recent waivers
     */
    private function _getRecentWaiverActivity(array $branchIds, int $days): array
    {
        $sinceDate = Date::now()->subDays($days);

        $query = $this->GatheringWaivers->find()
            ->where([
                'GatheringWaivers.deleted IS' => null,
                'GatheringWaivers.created >=' => $sinceDate->toDateString(),
            ])
            ->innerJoinWith('Gatherings', function ($q) use ($branchIds) {
                return $q->where([
                    'Gatherings.branch_id IN' => $branchIds,
                    'Gatherings.deleted IS' => null,
                ]);
            })
            ->contain([
                'Gatherings' => function ($q) {
                    return $q->select(['id', 'name', 'branch_id']);
                },
                'Gatherings.Branches' => function ($q) {
                    return $q->select(['id', 'name']);
                },
                'WaiverTypes' => function ($q) {
                    return $q->select(['id', 'name']);
                },
                'CreatedByMembers' => function ($q) {
                    return $q->select(['id', 'sca_name']);
                },
            ])
            ->orderBy(['GatheringWaivers.created' => 'DESC'])
            ->limit(20);

        return $query->all()->toArray();
    }

    /**
     * Get summary of waivers by type
     *
     * @param array $branchIds Branches to include
     * @return array Waiver type counts
     */
    private function _getWaiverTypesSummary(array $branchIds): array
    {
        $WaiverTypes = $this->fetchTable('Waivers.WaiverTypes');

        // Get all active waiver types
        $waiverTypes = $WaiverTypes->find()
            ->where([
                'is_active' => true,
                'deleted IS' => null,
            ])
            ->orderBy(['name' => 'ASC'])
            ->all();

        $summary = [];
        foreach ($waiverTypes as $waiverType) {
            $count = $this->GatheringWaivers->find()
                ->innerJoinWith('Gatherings', function ($q) use ($branchIds) {
                    return $q->where([
                        'Gatherings.branch_id IN' => $branchIds,
                        'Gatherings.deleted IS' => null,
                    ]);
                })
                ->where([
                    'GatheringWaivers.waiver_type_id' => $waiverType->id,
                    'GatheringWaivers.deleted IS' => null,
                ])
                ->count();

            $summary[] = [
                'waiver_type' => $waiverType,
                'count' => $count,
            ];
        }

        return $summary;
    }

    /**
     * Get gatherings that need to be closed (have waivers uploaded but not yet closed)
     *
     * @param array $branchIds Branches to check
     * @return array Gatherings needing closure
     */
    private function _getGatheringsNeedingClosed(array $branchIds): array
    {
        $Gatherings = $this->fetchTable('Gatherings');
        $GatheringWaiverClosures = $this->fetchTable('Waivers.GatheringWaiverClosures');
        $GatheringActivityWaivers = $this->fetchTable('Waivers.GatheringActivityWaivers');
        $WaiverTypes = $this->fetchTable('Waivers.WaiverTypes');
        $today = Date::now();
        $pastCutoff = Date::now()->subDays(180)->toDateString();

        // Get already closed gathering IDs
        $closedGatheringIds = $GatheringWaiverClosures->getClosedGatheringIds();
        $readyToCloseGatheringIds = $GatheringWaiverClosures->getReadyToCloseGatheringIds();

        // Find gatherings that have ended, have at least one uploaded waiver, and are not yet closed
        $query = $Gatherings->find()
            ->where([
                'Gatherings.branch_id IN' => $branchIds,
                'Gatherings.deleted IS' => null,
                'Gatherings.cancelled_at IS' => null,
                'Gatherings.end_date <' => $today->toDateString(),
                'Gatherings.end_date >=' => $pastCutoff,
            ])
            ->contain([
                'Branches' => function ($q) {
                    return $q->select(['id', 'name']);
                },
            ])
            ->orderBy(['Gatherings.end_date' => 'ASC']);

        if (!empty($closedGatheringIds)) {
            $query->where(['Gatherings.id NOT IN' => $closedGatheringIds]);
        }
        if (!empty($readyToCloseGatheringIds)) {
            $query->where(['Gatherings.id NOT IN' => $readyToCloseGatheringIds]);
        }

        $gatherings = $query->all()->toArray();
        if (empty($gatherings)) {
            return [];
        }

        $gatheringIds = array_map(
            static fn($gathering) => (int)$gathering->id,
            $gatherings
        );

        // Batch 1: required waiver types by gathering (via activity assignments).
        $requiredRows = $GatheringActivityWaivers->find()
            ->innerJoinWith('GatheringActivities', function ($q) use ($gatheringIds) {
                return $q->where([
                    'GatheringActivities.gathering_id IN' => $gatheringIds,
                    'GatheringActivities.deleted IS' => null,
                ]);
            })
            ->where([
                'GatheringActivityWaivers.deleted IS' => null,
            ])
            ->select([
                'gathering_id' => 'GatheringActivities.gathering_id',
                'waiver_type_id' => 'GatheringActivityWaivers.waiver_type_id',
            ])
            ->distinct(['GatheringActivities.gathering_id', 'GatheringActivityWaivers.waiver_type_id'])
            ->all();

        $requiredTypeIdsByGathering = [];
        foreach ($requiredRows as $row) {
            $gid = (int)$row->get('gathering_id');
            $waiverTypeId = (int)$row->get('waiver_type_id');
            $requiredTypeIdsByGathering[$gid][$waiverTypeId] = true;
        }

        // Batch 2: uploaded/exempted waiver types by gathering.
        $uploadedRows = $this->GatheringWaivers->find()
            ->where([
                'GatheringWaivers.gathering_id IN' => $gatheringIds,
                'GatheringWaivers.deleted IS' => null,
                'GatheringWaivers.declined_at IS' => null,
            ])
            ->select([
                'gathering_id',
                'waiver_type_id',
            ])
            ->distinct(['gathering_id', 'waiver_type_id'])
            ->all();

        $uploadedTypeIdsByGathering = [];
        foreach ($uploadedRows as $row) {
            $gid = (int)$row->get('gathering_id');
            $waiverTypeId = (int)$row->get('waiver_type_id');
            $uploadedTypeIdsByGathering[$gid][$waiverTypeId] = true;
        }

        $statsByGathering = [];
        $allWaiverTypeIds = [];

        foreach ($gatherings as $gathering) {
            $gid = (int)$gathering->id;
            $uploadedTypeIds = array_map('intval', array_keys($uploadedTypeIdsByGathering[$gid] ?? []));

            // "In Progress" requires at least one waiver upload/exemption.
            if (empty($uploadedTypeIds)) {
                continue;
            }

            $requiredTypeIds = array_map('intval', array_keys($requiredTypeIdsByGathering[$gid] ?? []));
            $missingTypeIds = array_values(array_diff($requiredTypeIds, $uploadedTypeIds));

            $statsByGathering[$gid] = [
                'uploaded_type_ids' => $uploadedTypeIds,
                'missing_type_ids' => $missingTypeIds,
                'is_complete' => empty($missingTypeIds),
            ];

            foreach (array_merge($uploadedTypeIds, $missingTypeIds) as $waiverTypeId) {
                $allWaiverTypeIds[(int)$waiverTypeId] = true;
            }
        }

        // Batch 3: resolve all waiver type names once.
        $waiverTypeNameMap = [];
        if (!empty($allWaiverTypeIds)) {
            $waiverTypes = $WaiverTypes->find()
                ->where(['id IN' => array_keys($allWaiverTypeIds)])
                ->select(['id', 'name'])
                ->all();

            foreach ($waiverTypes as $waiverType) {
                $waiverTypeNameMap[(int)$waiverType->id] = (string)$waiverType->name;
            }
        }

        $result = [];
        foreach ($gatherings as $gathering) {
            $gid = (int)$gathering->id;
            if (empty($statsByGathering[$gid])) {
                continue;
            }

            $uploadedWaiverNames = array_values(array_filter(array_map(
                static fn($waiverTypeId) => $waiverTypeNameMap[(int)$waiverTypeId] ?? null,
                $statsByGathering[$gid]['uploaded_type_ids']
            )));
            sort($uploadedWaiverNames, SORT_NATURAL | SORT_FLAG_CASE);

            $missingWaiverNames = array_values(array_filter(array_map(
                static fn($waiverTypeId) => $waiverTypeNameMap[(int)$waiverTypeId] ?? null,
                $statsByGathering[$gid]['missing_type_ids']
            )));
            sort($missingWaiverNames, SORT_NATURAL | SORT_FLAG_CASE);

            $gathering->uploaded_waiver_count = count($statsByGathering[$gid]['uploaded_type_ids']);
            $gathering->uploaded_waiver_names = $uploadedWaiverNames;
            $gathering->missing_waiver_count = count($statsByGathering[$gid]['missing_type_ids']);
            $gathering->missing_waiver_names = $missingWaiverNames;
            $gathering->is_waiver_complete = (bool)$statsByGathering[$gid]['is_complete'];

            $result[] = $gathering;
        }

        return $result;
    }

    /**
     * Get recently closed gatherings
     *
     * @param array $branchIds Branches to check
     * @return array Closed gatherings with closure details
     */
    private function _getClosedGatherings(array $branchIds): array
    {
        $GatheringWaiverClosures = $this->fetchTable('Waivers.GatheringWaiverClosures');
        $ninetyDaysAgo = Date::now()->subDays(90)->toDateString();

        $closures = $GatheringWaiverClosures->find()
            ->where([
                'GatheringWaiverClosures.closed_at IS NOT' => null,
                'GatheringWaiverClosures.closed_at >=' => $ninetyDaysAgo,
            ])
            ->contain([
                'Gatherings' => function ($q) use ($branchIds) {
                    return $q->where([
                        'Gatherings.branch_id IN' => $branchIds,
                        'Gatherings.deleted IS' => null,
                    ]);
                },
                'Gatherings.Branches' => function ($q) {
                    return $q->select(['id', 'name']);
                },
                'ClosedByMembers' => function ($q) {
                    return $q->select(['id', 'sca_name']);
                },
            ])
            ->orderBy(['GatheringWaiverClosures.closed_at' => 'DESC'])
            ->all()
            ->toArray();

        // Filter out closures where gathering didn't match the branch filter
        $closures = array_filter($closures, function ($closure) {
            return $closure->gathering !== null;
        });

        return array_values($closures);
    }

    /**
     * Mobile gathering selection interface
     * 
     * Displays a list of gatherings that the user has permission to upload waivers for.
     * Filters to show gatherings starting in the next 7 days or ended in the last 30 days.
     * 
     * @return \Cake\Http\Response|null|void
     */
    public function mobileSelectGathering()
    {
        $tempWaiver = $this->GatheringWaivers->newEmptyEntity();
        $this->Authorization->authorize($tempWaiver, "uploadWaivers");

        $currentUser = $this->Authentication->getIdentity();
        if (!$currentUser) {
            $this->Flash->error(__('You must be logged in to upload waivers.'));
            return $this->redirect(['controller' => 'Members', 'action' => 'login', 'plugin' => null]);
        }

        // Check if user can add GatheringWaivers and get branch IDs they have permission for
        $branchIds = $currentUser->getBranchIdsForAction('uploadWaivers', $tempWaiver);

        // Get gathering IDs where user is a steward (these are allowed even without branch permissions)
        $GatheringStaff = $this->fetchTable('GatheringStaff');
        $stewardGatheringIds = $GatheringStaff->find()
            ->where([
                'GatheringStaff.member_id' => $currentUser->getIdentifier(),
                'GatheringStaff.is_steward' => true,
            ])
            ->select(['gathering_id'])
            ->all()
            ->extract('gathering_id')
            ->toArray();

        // Determine access mode
        $hasGlobalAccess = ($branchIds === null);
        $hasBranchAccess = !empty($branchIds);
        $hasStewardAccess = !empty($stewardGatheringIds);

        // If user has no permissions at all (no branch access and not a steward), deny access
        if (!$hasGlobalAccess && !$hasBranchAccess && !$hasStewardAccess) {
            $this->Flash->error(__('You do not have permission to upload waivers.'));
            return $this->redirect($this->request->referer());
        }

        // Get date range for filtering gatherings
        // Start: 7 days from now
        // End: 30 days ago
        $startDate = new \DateTime('+7 days');
        $endDate = new \DateTime('-30 days');

        // STEP 1: Get list of gathering IDs that meet all criteria
        // - Have waiver requirements configured
        // - User has permission to upload waivers (via branch OR steward access)
        // - Within date range
        $GatheringActivityWaivers = $this->fetchTable('Waivers.GatheringActivityWaivers');

        // Use innerJoinWith to properly join through the associations
        $gatheringIdsQuery = $GatheringActivityWaivers->find()
            ->innerJoinWith('GatheringActivities.Gatherings')
            ->where([
                'GatheringActivityWaivers.deleted IS' => null,
                'GatheringActivities.deleted IS' => null,
                'Gatherings.deleted IS' => null,
                'Gatherings.cancelled_at IS' => null,
                'OR' => [
                    'Gatherings.start_date <=' => $startDate,
                    'Gatherings.end_date >=' => $endDate
                ]
            ])
            ->select(['gathering_id' => 'Gatherings.id'])
            ->distinct(['Gatherings.id']);

        // Build access filter: branch-based OR steward-based (unless global)
        if (!$hasGlobalAccess) {
            $accessConditions = [];
            if (!empty($branchIds)) {
                $accessConditions[] = ['Gatherings.branch_id IN' => $branchIds];
            }
            if (!empty($stewardGatheringIds)) {
                $accessConditions[] = ['Gatherings.id IN' => $stewardGatheringIds];
            }
            if (!empty($accessConditions)) {
                $gatheringIdsQuery->where(['OR' => $accessConditions]);
            }
        }

        // Extract gathering IDs
        $gatheringIds = $gatheringIdsQuery->all()->extract('gathering_id')->toArray();

        $GatheringWaiverClosures = $this->fetchTable('Waivers.GatheringWaiverClosures');
        $closedGatheringIds = $GatheringWaiverClosures->getClosedGatheringIds($gatheringIds);
        if (!empty($closedGatheringIds)) {
            $gatheringIds = array_values(array_diff($gatheringIds, $closedGatheringIds));
        }

        // Get gatherings that are marked ready to close
        $readyToCloseGatheringIds = $GatheringWaiverClosures->getReadyToCloseGatheringIds($gatheringIds);

        // If no gatherings found, show empty state
        if (empty($gatheringIds)) {
            $authorizedGatherings = [];
        } else {
            // STEP 2: Fetch full gathering details for authorized gatherings, sorted by start date
            $Gatherings = $this->fetchTable('Gatherings');
            $allGatherings = $Gatherings->find()
                ->where(['Gatherings.id IN' => $gatheringIds])
                ->contain(['Branches', 'GatheringTypes', 'GatheringActivities'])
                ->orderBy(['Gatherings.start_date' => 'DESC'])
                ->all()
                ->toArray();

            // STEP 3: Calculate waiver status for each gathering
            $authorizedGatherings = [];
            $now = new \DateTime();

            foreach ($allGatherings as $gathering) {
                // Default status values
                $gathering->missing_waiver_count = 0;
                $gathering->missing_waiver_names = [];
                $gathering->is_waiver_complete = true;
                $gathering->is_ready_to_close = in_array($gathering->id, $readyToCloseGatheringIds, true);

                // Determine time-based status
                $gathering->is_upcoming = $gathering->start_date > $now;
                $gathering->is_ongoing = $gathering->start_date <= $now && $gathering->end_date >= $now;
                $gathering->is_ended = $gathering->end_date < $now;

                if (!empty($gathering->gathering_activities)) {
                    $activityIds = collection($gathering->gathering_activities)->extract('id')->toArray();

                    // Get required waiver types for this gathering's activities
                    $requiredWaiverTypes = $GatheringActivityWaivers->find()
                        ->where([
                            'gathering_activity_id IN' => $activityIds,
                            'deleted IS' => null,
                        ])
                        ->select(['waiver_type_id'])
                        ->distinct(['waiver_type_id'])
                        ->all()
                        ->extract('waiver_type_id')
                        ->toArray();

                    if (!empty($requiredWaiverTypes)) {
                        // Get uploaded waiver types for this gathering
                        $uploadedWaiverTypes = $this->GatheringWaivers->find()
                            ->where([
                                'gathering_id' => $gathering->id,
                                'deleted IS' => null,
                                'declined_at IS' => null,
                            ])
                            ->select(['waiver_type_id'])
                            ->distinct(['waiver_type_id'])
                            ->all()
                            ->extract('waiver_type_id')
                            ->toArray();

                        // Check if any required waivers are missing
                        $missingWaiverTypes = array_diff($requiredWaiverTypes, $uploadedWaiverTypes);

                        if (!empty($missingWaiverTypes)) {
                            // Load waiver type names
                            $WaiverTypes = $this->fetchTable('Waivers.WaiverTypes');
                            $missingWaiverNames = $WaiverTypes->find()
                                ->where(['id IN' => $missingWaiverTypes])
                                ->orderBy(['name' => 'ASC'])
                                ->all()
                                ->extract('name')
                                ->toArray();

                            $gathering->missing_waiver_count = count($missingWaiverTypes);
                            $gathering->missing_waiver_names = $missingWaiverNames;
                            $gathering->is_waiver_complete = false;
                        }
                    }
                }

                $authorizedGatherings[] = $gathering;
            }
        }

        $this->set(compact('authorizedGatherings'));

        // Use mobile app layout
        $this->viewBuilder()->setLayout('mobile_app');
        $this->set('mobileTitle', 'Submit Waiver');
        $this->set('mobileSection', 'waivers');
        $this->set('mobileIcon', 'bi-file-earmark-text');
        $this->set('mobileBackUrl', $this->request->referer());
        $this->set('showRefreshBtn', false);
    }

    /**
     * Mobile waiver upload interface
     * 
     * Simplified mobile waiver upload wizard optimized for phone cameras.
     * Takes gathering_id parameter and provides streamlined upload flow.
     * 
     * @param string|null $gatheringId Gathering ID
     * @return \Cake\Http\Response|null|void
     */
    public function mobileUpload(?string $gatheringId = null)
    {

        if (!$gatheringId) {
            $gatheringId = $this->request->getQuery('gathering_id');
        }

        if (!$gatheringId) {
            // Skip authorization for redirect - will be checked on the target page
            $this->Authorization->skipAuthorization();
            return $this->redirect(['action' => 'mobileSelectGathering']);
        }

        // Get gathering with activities
        $Gatherings = $this->fetchTable('Gatherings');
        $gathering = $Gatherings->get($gatheringId, [
            'contain' => [
                'GatheringTypes',
                'Branches',
                'GatheringActivities',
            ],
        ]);

        // Check authorization first - before any other checks
        $tempWaiver = $this->GatheringWaivers->newEmptyEntity();
        $tempWaiver->gathering = $gathering;
        $tempWaiver->gathering_id = $gatheringId;
        $this->Authorization->authorize($tempWaiver, "uploadWaivers");

        // Check if gathering is cancelled
        if ($gathering->cancelled_at !== null) {
            $message = __('This gathering has been cancelled. Waivers are not required.');
            if ($this->request->is('ajax')) {
                $this->viewBuilder()->setClassName('Json');
                $this->response = $this->response->withStatus(403);
                $this->set('message', $message);
                $this->viewBuilder()->setOption('serialize', ['message']);
                return;
            }
            $this->Flash->error($message);
            return $this->redirect(['action' => 'mobileSelectGathering']);
        }

        $GatheringWaiverClosures = $this->fetchTable('Waivers.GatheringWaiverClosures');
        $waiverClosure = $GatheringWaiverClosures->getClosureForGathering((int)$gatheringId);
        if ($waiverClosure) {
            $message = __('Waiver collection is closed for this gathering.');
            if ($this->request->is('ajax')) {
                $this->viewBuilder()->setClassName('Json');
                $this->response = $this->response->withStatus(403);
                $this->set('message', $message);
                $this->viewBuilder()->setOption('serialize', ['message']);
                return;
            }
            $this->Flash->error($message);
            return $this->redirect(['action' => 'mobileSelectGathering']);
        }

        // Check if gathering has required waivers
        $requiredWaiverTypes = $this->_getRequiredWaiverTypes($gathering);
        if (empty($requiredWaiverTypes)) {
            $this->Flash->error(__('This gathering is not configured to collect waivers.'));
            return $this->redirect(['action' => 'mobileSelectGathering']);
        }

        // Authorization was already checked above which includes steward permissions.
        // The authorization system handles branch scoping, so we don't need a redundant check here.

        // Handle POST - process uploads
        if ($this->request->is('post')) {
            $data = $this->request->getData();
            $uploadedFiles = $data['waiver_images'] ?? [];
            $waiverTypeId = $data['waiver_type_id'] ?? null;
            $notes = $data['notes'] ?? '';

            if (empty($uploadedFiles) || !$waiverTypeId) {
                // Handle error for AJAX request
                if ($this->request->is('ajax')) {
                    $this->viewBuilder()->setClassName('Json');
                    $this->set('success', false);
                    $this->set('message', __('Please select waiver type and upload at least one image.'));
                    $this->viewBuilder()->setOption('serialize', ['success', 'message']);
                    return;
                }
                $this->Flash->error(__('Please select waiver type and upload at least one image.'));
                return $this->redirect($this->referer());
            }

            if ($this->_isWaiverTypeAttested((int)$gathering->id, (int)$waiverTypeId)) {
                $message = __('This waiver type has been attested as not needed for this gathering.');
                if ($this->request->is('ajax')) {
                    $this->viewBuilder()->setClassName('Json');
                    $this->response = $this->response->withStatus(400);
                    $this->set('message', $message);
                    $this->viewBuilder()->setOption('serialize', ['message']);
                    return;
                }
                $this->Flash->error($message);
                return $this->redirect($this->referer());
            }

            // Get waiver type for retention policy
            $WaiverTypes = $this->fetchTable('Waivers.WaiverTypes');
            $waiverType = $WaiverTypes->get($waiverTypeId);

            // Get client-generated thumbnail if provided (from PDF.js rendering)
            $clientThumbnail = $data['client_thumbnail'] ?? null;

            // Process all uploaded files as a single multi-page waiver
            try {
                $result = $this->_processMultipleWaiverImages(
                    $uploadedFiles,
                    $gathering,
                    $waiverType,
                    $notes,
                    $clientThumbnail
                );

                if ($result->success) {
                    // Get redirect URL to mobile card
                    $currentUser = $this->Authentication->getIdentity();
                    $Members = $this->fetchTable('Members');
                    $member = $Members->get($currentUser->id, ['fields' => ['id', 'mobile_card_token']]);

                    // Build the redirect URL using Router
                    $redirectUrl = \Cake\Routing\Router::url([
                        'controller' => 'Members',
                        'action' => 'viewMobileCard',
                        'plugin' => null,
                        $member->mobile_card_token
                    ], true); // true = full base URL

                    // Get result data for messaging
                    $resultData = $result->getData();
                    $pageCount = $resultData['page_count'] ?? count($uploadedFiles);

                    // If AJAX request, return JSON response
                    if ($this->request->is('ajax')) {
                        $this->Flash->success(__(
                            'Waiver uploaded successfully with {0} page(s).',
                            $pageCount
                        ));

                        // Show warning if files were skipped
                        if (!empty($resultData['warning'])) {
                            $this->Flash->warning($resultData['warning']);
                        }

                        $this->viewBuilder()->setClassName('Json');
                        $this->set('success', true);
                        $this->set('redirectUrl', $redirectUrl);
                        $this->set('warning', $resultData['warning'] ?? null);
                        $this->viewBuilder()->setOption('serialize', ['success', 'redirectUrl', 'warning']);
                        return;
                    }

                    // For non-AJAX, set Flash and redirect
                    $this->Flash->success(__(
                        'Waiver uploaded successfully with {0} page(s).',
                        $pageCount
                    ));

                    // Show warning if files were skipped
                    if (!empty($resultData['warning'])) {
                        $this->Flash->warning($resultData['warning']);
                    }

                    // Otherwise, regular redirect
                    return $this->redirect([
                        'controller' => 'Members',
                        'action' => 'viewMobileCard',
                        'plugin' => null,
                        $member->mobile_card_token
                    ]);
                } else {
                    $this->Flash->error(__('Failed to upload waiver: {0}', $result->getError()));
                }
            } catch (\Exception $e) {
                $this->Flash->error(__('Error uploading waiver: {0}', $e->getMessage()));
                Log::error('Waiver upload error: ' . $e->getMessage());
            }
        }

        $waiverTypesData = [];
        if (!empty($requiredWaiverTypes)) {
            foreach ($requiredWaiverTypes as $waiverType) {
                $waiverTypesData[] = [
                    'id' => $waiverType->id,
                    'name' => $waiverType->name,
                    'description' => $waiverType->description ?? '',
                    'exemption_reasons' => $waiverType->exemption_reasons_parsed ?? []
                ];
            }
        }

        // Get upload limits for validation
        $uploadLimits = $this->_getUploadLimits();

        // Get pre-selected value from query parameters (for direct upload links)
        $preSelectedWaiverTypeId = $this->request->getQuery('waiver_type_id');

        $waiverStatusSummary = $this->_getWaiverStatusSummary((int)$gathering->id, $requiredWaiverTypes);

        $this->set(compact(
            'gathering',
            'requiredWaiverTypes',
            'waiverTypesData',
            'uploadLimits',
            'preSelectedWaiverTypeId',
            'waiverStatusSummary'
        ));

        // Use mobile app layout
        $this->viewBuilder()->setLayout('mobile_app');
        $this->set('mobileTitle', 'Upload Waiver');
        $this->set('mobileSection', 'waivers');
        $this->set('mobileIcon', 'bi-cloud-upload');
        $this->set('mobileBackUrl', ['action' => 'mobileSelectGathering']);
        $this->set('showRefreshBtn', false);
    }

    /**
     * Get upload limits from PHP configuration
     * 
     * @return array Upload limits with maxFileSize and formatted values
     */
    private function _getUploadLimits(): array
    {
        // Parse upload_max_filesize
        $uploadMax = $this->_parsePhpSize(ini_get('upload_max_filesize'));

        // Parse post_max_size
        $postMax = $this->_parsePhpSize(ini_get('post_max_size'));

        // The effective limit is the smaller of the two
        $maxFileSize = min($uploadMax, $postMax);

        return [
            'maxFileSize' => $maxFileSize,
            'maxFileSizeMB' => round($maxFileSize / 1024 / 1024, 2),
            'formatted' => $this->_formatBytes($maxFileSize),
            'uploadMaxFilesize' => $uploadMax,
            'postMaxSize' => $postMax,
        ];
    }

    /**
     * Parse PHP size notation to bytes
     * 
     * @param string $size Size string from PHP ini setting
     * @return int Size in bytes
     */
    private function _parsePhpSize(string $size): int
    {
        $size = trim($size);
        $last = strtolower($size[strlen($size) - 1]);
        $value = (int)$size;

        switch ($last) {
            case 'g':
                $value *= 1024;
                // Fall through
            case 'm':
                $value *= 1024;
                // Fall through
            case 'k':
                $value *= 1024;
        }

        return $value;
    }

    /**
     * Format bytes to human-readable size
     * 
     * @param int $bytes Size in bytes
     * @param int $precision Decimal precision
     * @return string Formatted size string
     */
    private function _formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, $precision) . ' ' . $units[$i];
    }

    /**
     * Attest that a waiver is not needed
     *
     * Records an exemption for a gathering/waiver type combination,
     * attesting that the waiver requirement does not apply for the gathering.
     *
     * **Expected POST Data**:
     * - waiver_type_id: int
     * - gathering_id: int (for authorization)
     * - reason: string (from waiver type exemption_reasons)
     * - notes: string (optional)
     *
     * **Response**:
     * JSON with success status and message
     *
     * @return \Cake\Http\Response|null JSON response
     */
    public function attest()
    {
        $this->request->allowMethod(['post']);
        $this->viewBuilder()->setClassName('Json');

        try {
            // Get request data
            $data = $this->request->getData();

            $waiverTypeId = (int)($data['waiver_type_id'] ?? 0);
            $gatheringId = (int)($data['gathering_id'] ?? 0);
            $reason = $data['reason'] ?? '';
            $notes = $data['notes'] ?? null;

            // Validate required fields
            if (!$waiverTypeId || !$gatheringId || empty($reason)) {
                $this->set('success', false);
                $this->set('message', __('Missing required fields'));
                $this->viewBuilder()->setOption('serialize', ['success', 'message']);
                return;
            }

            // Load gathering for authorization
            $Gatherings = $this->fetchTable('Gatherings');
            $gathering = $Gatherings->get($gatheringId);

            $GatheringWaiverClosures = $this->fetchTable('Waivers.GatheringWaiverClosures');
            if ($GatheringWaiverClosures->isGatheringClosed($gatheringId)) {
                $this->response = $this->response->withStatus(403);
                $this->set('success', false);
                $this->set('message', __('Waiver collection is closed for this gathering.'));
                $this->viewBuilder()->setOption('serialize', ['success', 'message']);
                return;
            }

            // Check authorization - user must be able upload waivers for this gathering to attest
            $gatheringWaiver = $this->GatheringWaivers->newEmptyEntity();
            $gatheringWaiver->gathering = $gathering;
            $this->Authorization->authorize($gatheringWaiver, 'uploadWaivers');

            // Verify the waiver type exists and has valid exemption reasons
            $WaiverTypes = $this->fetchTable('Waivers.WaiverTypes');
            $waiverType = $WaiverTypes->get($waiverTypeId);

            // Validate reason is in the waiver type's exemption reasons
            $validReasons = $waiverType->exemption_reasons_parsed ?? [];
            if (!in_array($reason, $validReasons)) {
                $this->set('success', false);
                $this->set('message', __('Invalid exemption reason'));
                $this->viewBuilder()->setOption('serialize', ['success', 'message']);
                return;
            }

            // Check if exemption already exists for this gathering/waiver type
            // Ignore declined waivers when checking for duplicates
            $existing = $this->GatheringWaivers->find()
                ->where([
                    'GatheringWaivers.waiver_type_id' => $waiverTypeId,
                    'GatheringWaivers.gathering_id' => $gatheringId,
                    'GatheringWaivers.is_exemption' => true,
                    'GatheringWaivers.status !=' => 'declined'
                ])
                ->first();

            if ($existing) {
                // Check if the reason is the same
                if ($existing->exemption_reason === $reason) {
                    $this->set('success', false);
                    $this->set('message', __('An exemption with this reason already exists for this gathering and waiver type'));
                } else {
                    $this->set('success', false);
                    $this->set('message', __('An exemption already exists for this gathering and waiver type. Please delete the existing exemption before creating a new one with a different reason.'));
                }
                $this->viewBuilder()->setOption('serialize', ['success', 'message']);
                return;
            }

            // Calculate retention date for the exemption record
            $gatheringEndDate = Date::parse($gathering->end_date->format('Y-m-d'));
            $retentionResult = $this->RetentionPolicyService->calculateRetentionDate(
                $waiverType->retention_policy,
                $gatheringEndDate,
                Date::now()
            );

            if (!$retentionResult->success) {
                $this->set('success', false);
                $this->set('message', __('Failed to calculate retention date: {0}', $retentionResult->getReason()));
                $this->viewBuilder()->setOption('serialize', ['success', 'message']);
                return;
            }

            $retentionDate = $retentionResult->getData();

            // Create new exemption as a GatheringWaiver record scoped to the gathering
            $exemption = $this->GatheringWaivers->newEntity([
                'gathering_id' => $gatheringId,
                'waiver_type_id' => $waiverTypeId,
                'document_id' => null,
                'is_exemption' => true,
                'exemption_reason' => $reason,
                'notes' => $notes,
                'status' => 'active',
                'retention_date' => $retentionDate
            ]);

            if ($this->GatheringWaivers->save($exemption)) {
                // Determine redirect URL based on referer
                $referer = $this->request->referer();
                $redirectUrl = null;

                // Check if request came from mobile upload
                if ($referer && strpos($referer, 'mobile-upload') !== false) {
                    // Redirect to mobile card
                    $currentUser = $this->Authentication->getIdentity();
                    $Members = $this->fetchTable('Members');
                    $member = $Members->get($currentUser->id, ['fields' => ['id', 'mobile_card_token']]);

                    $redirectUrl = \Cake\Routing\Router::url([
                        'controller' => 'Members',
                        'action' => 'viewMobileCard',
                        'plugin' => null,
                        $member->mobile_card_token
                    ], true);
                } else {
                    // Default: redirect to gathering view
                    $redirectUrl = \Cake\Routing\Router::url([
                        'plugin' => false,
                        'controller' => 'Gatherings',
                        'action' => 'view',
                        $gathering->public_id,
                        '?' => ['tab' => 'gathering-waivers']
                    ], true);
                }

                $this->set('success', true);
                $this->set('message', __('Exemption recorded successfully'));
                $this->set('redirectUrl', $redirectUrl);
                $this->viewBuilder()->setOption('serialize', ['success', 'message', 'redirectUrl']);
            } else {
                $errors = $exemption->getErrors();
                $errorMessages = [];
                foreach ($errors as $field => $fieldErrors) {
                    foreach ($fieldErrors as $error) {
                        $errorMessages[] = $error;
                    }
                }

                $this->set('success', false);
                $this->set('message', __('Failed to save exemption: {0}', implode(', ', $errorMessages)));
                $this->viewBuilder()->setOption('serialize', ['success', 'message']);
            }
        } catch (\Exception $e) {
            Log::error('Error creating waiver exemption: ' . $e->getMessage());
            $this->set('success', false);
            $this->set('message', __('An error occurred: {0}', $e->getMessage()));
            $this->viewBuilder()->setOption('serialize', ['success', 'message']);
        }
    }
}
