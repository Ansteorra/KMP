<?php
declare(strict_types=1);

namespace Waivers\Controller;

use App\Controller\DataverseGridTrait;
use App\KMP\StaticHelpers;
use App\Services\CsvExportService;
use Cake\Http\Exception\BadRequestException;
use Cake\Http\Exception\NotFoundException;
use Cake\Log\Log;
use Cake\Routing\Router;
use Exception;
use Waivers\KMP\GridColumns\GatheringWaiversGridColumns;
use Waivers\Services\WaiverDashboardService;
use Waivers\Services\WaiverFileService;
use Waivers\Services\WaiverMobileService;
use Waivers\Services\WaiverStateService;

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
                'canCloseWaivers',
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
    public function close(WaiverStateService $stateService, ?string $gatheringId = null)
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

        $result = $stateService->close((int)$gatheringId, $this->Authentication->getIdentity()->getIdentifier());

        if ($result->success) {
            $this->Flash->success($result->reason);
        } elseif (stripos($result->reason, 'already') !== false) {
            $this->Flash->info($result->reason);
        } else {
            $this->Flash->error($result->reason);
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
    public function reopen(WaiverStateService $stateService, ?string $gatheringId = null)
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

        $result = $stateService->reopen((int)$gatheringId);

        if ($result->success) {
            $this->Flash->success($result->reason);
        } elseif (stripos($result->reason, 'already') !== false) {
            $this->Flash->info($result->reason);
        } else {
            $this->Flash->error($result->reason);
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
    public function markReadyToClose(WaiverStateService $stateService, ?string $gatheringId = null)
    {
        $this->request->allowMethod(['post']);

        if (!$gatheringId) {
            throw new BadRequestException(__('Gathering ID is required'));
        }

        $Gatherings = $this->fetchTable('Gatherings');
        $gathering = $Gatherings->get($gatheringId);

        // Use edit permission on gathering - editors and stewards can mark ready
        $this->Authorization->authorize($gathering, 'edit');

        $result = $stateService->markReadyToClose((int)$gatheringId, $this->Authentication->getIdentity()->getIdentifier());

        if ($result->success) {
            $this->Flash->success($result->reason);
        } elseif (stripos($result->reason, 'already') !== false) {
            $this->Flash->info($result->reason);
        } else {
            $this->Flash->error($result->reason);
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
    public function unmarkReadyToClose(WaiverStateService $stateService, ?string $gatheringId = null)
    {
        $this->request->allowMethod(['post']);

        if (!$gatheringId) {
            throw new BadRequestException(__('Gathering ID is required'));
        }

        $Gatherings = $this->fetchTable('Gatherings');
        $gathering = $Gatherings->get($gatheringId);

        // Use edit permission on gathering - editors and stewards can unmark ready
        $this->Authorization->authorize($gathering, 'edit');

        $result = $stateService->unmarkReadyToClose((int)$gatheringId);

        if ($result->success) {
            $this->Flash->success($result->reason);
        } elseif (stripos($result->reason, 'already') !== false || stripos($result->reason, 'not marked') !== false) {
            $this->Flash->info($result->reason);
        } else {
            $this->Flash->error($result->reason);
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
     * @param \App\Services\CsvExportService $csvExportService Injected CSV export service
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
    public function view(WaiverFileService $fileService, ?string $id = null)
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
            $previewAvailable = $fileService->documentPreviewExists($gatheringWaiver->document);
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
    public function upload(WaiverFileService $fileService)
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

        // need an empty gathering waiver to check authorization
        $gatheringWaiver = $this->GatheringWaivers->newEmptyEntity();
        $gatheringWaiver->gathering = $gathering;
        $this->Authorization->authorize($gatheringWaiver, 'uploadWaivers');

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
        $canCloseWaivers = $this->Authentication->getIdentity()->checkCan('closeWaivers', $gatheringWaiver);
        if ($waiverClosure !== null && $waiverClosure->closed_at !== null && !$canCloseWaivers) {
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
        $requiredWaiverTypes = $fileService->getRequiredWaiverTypes($gathering);
        if (empty($requiredWaiverTypes)) {
            $this->Flash->error(__('This gathering is not configured to collect waivers.'));

            return $this->redirect(['plugin' => false, 'controller' => 'Gatherings', 'action' => 'view', $gatheringId]);
        }
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

            if ($fileService->isWaiverTypeAttested((int)$gatheringId, (int)$waiverTypeId)) {
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
                $result = $fileService->processMultipleWaiverImages(
                    $uploadedFiles,
                    $gathering,
                    $waiverType,
                    $notes,
                    $clientThumbnail,
                    $this->Authentication->getIdentity()->getIdentifier(),
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
                            $redirectUrl = Router::url([
                                'plugin' => 'Waivers',
                                'controller' => 'GatheringWaivers',
                                'action' => 'index',
                                '?' => ['gathering_id' => $gatheringId],
                            ], true); // true = full base URL
                        }
                    }

                    // Get result data for messaging
                    $resultData = $result->getData();
                    $pageCount = $resultData['page_count'] ?? count($uploadedFiles);

                    // Set Flash message (will show on redirect page)
                    $this->Flash->success(__(
                        'Waiver uploaded successfully with {0} page(s).',
                        $pageCount,
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
            } catch (Exception $e) {
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
                    'exemption_reasons' => $waiverType->exemption_reasons_parsed ?? [],
                ];
            }
        }

        // Get upload limits for validation
        $uploadLimits = $fileService->getUploadLimits();

        // Get pre-selected values from query parameters (for direct upload links)
        $preSelectedWaiverTypeId = $this->request->getQuery('waiver_type_id');

        $waiverStatusSummary = $fileService->getWaiverStatusSummary((int)$gathering->id, $requiredWaiverTypes);

        $this->set(compact(
            'gathering',
            'requiredWaiverTypes',
            'waiverTypesData',
            'uploadLimits',
            'preSelectedWaiverTypeId',
            'waiverStatusSummary',
        ));
    }

    /**
     * Download method - Serve waiver PDF file securely
     *
     * @param string|null $id Gathering Waiver id.
     * @return \Cake\Http\Response File download response
     * @throws \Cake\Http\Exception\NotFoundException When record not found.
     */
    public function download(WaiverFileService $fileService, ?string $id = null)
    {
        $gatheringWaiver = $this->GatheringWaivers->get($id, [
            'contain' => ['Documents', 'Gatherings'],
        ]);

        $this->Authorization->authorize($gatheringWaiver);

        if (!$gatheringWaiver->document) {
            throw new NotFoundException(__('Document not found for this waiver'));
        }

        $response = $fileService->getDownloadResponse((int)$gatheringWaiver->id);

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
    public function inlinePdf(WaiverFileService $fileService, ?string $id = null)
    {
        $gatheringWaiver = $this->GatheringWaivers->get($id, [
            'contain' => ['Documents', 'Gatherings'],
        ]);

        $this->Authorization->authorize($gatheringWaiver);

        if (!$gatheringWaiver->document) {
            throw new NotFoundException(__('Document not found for this waiver'));
        }

        $response = $fileService->getInlinePdfResponse((int)$gatheringWaiver->id);

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
    public function preview(WaiverFileService $fileService, ?string $id = null)
    {
        $gatheringWaiver = $this->GatheringWaivers->get($id, [
            'contain' => ['Documents', 'Gatherings'],
        ]);

        $this->Authorization->authorize($gatheringWaiver);

        if (!$gatheringWaiver->document) {
            throw new NotFoundException(__('Document not found for this waiver'));
        }

        $response = $fileService->getPreviewResponse((int)$gatheringWaiver->id);

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
                    [],
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
        array $newActivities,
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
            'Changed from %d activity association(s) to %d activity association(s).',
            count($oldActivities),
            count($newActivities),
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
    public function delete(WaiverFileService $fileService, ?string $id = null)
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
            $fileService->deleteDocument($gatheringWaiver->document_id);
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
    public function decline(WaiverStateService $stateService, ?string $id = null)
    {
        $this->request->allowMethod(['post', 'put', 'patch']);

        $gatheringWaiver = $this->GatheringWaivers->get($id, [
            'contain' => ['Gatherings', 'WaiverTypes'],
        ]);

        $this->Authorization->authorize($gatheringWaiver, 'decline');

        $declineReason = $this->request->getData('decline_reason') ?? '';

        $result = $stateService->decline(
            (int)$gatheringWaiver->id,
            $declineReason,
            $this->Authentication->getIdentity()->getIdentifier(),
        );

        if ($result->success) {
            $this->Flash->success($result->reason);
        } else {
            $this->Flash->error($result->reason);
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
    public function needingWaivers(WaiverDashboardService $dashboardService)
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

        $result = $dashboardService->getNeedingWaiversData($branchIds, $stewardGatheringIds);
        $this->set('gatherings', $result['gatherings']);
        $this->set('incompleteCount', $result['incompleteCount']);
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
    public function dashboard(WaiverDashboardService $dashboardService)
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
            $branchIds = $Branches->find()->select(['id'])->all()->extract('id')->toArray();
        }

        // Handle waiver search
        $searchResults = null;
        $searchTerm = $this->request->getQuery('search');
        if ($searchTerm) {
            $searchResults = $dashboardService->searchWaivers($searchTerm, $branchIds);
        }

        // Get key statistics
        $statistics = $dashboardService->getDashboardStatistics($branchIds);

        // Get gatherings with incomplete waivers (separated by status)
        $waiverGatherings = $dashboardService->getGatheringsWithIncompleteWaivers($branchIds, 30);
        $gatheringsMissingWaivers = $waiverGatherings['missing'];
        $gatheringsNeedingWaivers = $waiverGatherings['upcoming'];

        // Get branches with compliance issues
        $branchesWithIssues = $dashboardService->getBranchesWithIssues($branchIds);

        // Get recent waiver activity (last 30 days)
        $recentActivity = $dashboardService->getRecentWaiverActivity($branchIds, 30);

        // Get waiver types summary
        $waiverTypesSummary = $dashboardService->getWaiverTypesSummary($branchIds);

        // Get compliance days setting
        $complianceDays = (int)StaticHelpers::getAppSetting('Waivers.ComplianceDays', '2', 'int', false);

        // Get gatherings marked ready to close
        $gatheringsReadyToClose = $dashboardService->getGatheringsReadyToClose($branchIds);

        // Get gatherings in progress (waivers uploaded/exempted, not ready/closed)
        $gatheringsNeedingClosed = $dashboardService->getGatheringsNeedingClosed($branchIds);

        // Keep past-due "Gatherings Needing Waivers" focused on events with no waiver progress.
        // Any event with uploaded/exempted waivers belongs in "In Progress Waivers".
        $gatheringsMissingWaivers = array_values(array_filter(
            $gatheringsMissingWaivers,
            fn($gathering) => ($gathering->uploaded_waiver_count ?? 0) === 0,
        ));

        // Get recently closed gatherings
        $closedGatherings = $dashboardService->getClosedGatherings($branchIds);

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
            'complianceDays',
        ));
    }

    /**
     * Return JSON calendar data for gatherings in a given month
     *
     * @return \Cake\Http\Response JSON response with gathering calendar data
     */
    public function calendarData(WaiverDashboardService $dashboardService)
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
        $branchIds = $currentUser->getBranchIdsForAction('add', 'Waivers.GatheringWaivers');
        $Branches = $this->fetchTable('Branches');
        if ($branchIds === null) {
            $branchIds = $Branches->find()->select(['id'])->all()->extract('id')->toArray();
        }
        if (is_array($branchIds) && empty($branchIds)) {
            return $this->response->withType('application/json')
                ->withStringBody(json_encode(['events' => []]));
        }

        $result = $dashboardService->getCalendarData($year, $month, $branchIds, $currentUser);

        return $this->response->withType('application/json')
            ->withStringBody(json_encode($result));
    }

    /**
     * Mobile gathering selection interface
     *
     * Displays a list of gatherings that the user has permission to upload waivers for.
     * Filters to show gatherings starting in the next 7 days or ended in the last 30 days.
     *
     * @return \Cake\Http\Response|null|void
     */
    public function mobileSelectGathering(WaiverMobileService $mobileService)
    {
        $tempWaiver = $this->GatheringWaivers->newEmptyEntity();
        $this->Authorization->authorize($tempWaiver, 'uploadWaivers');

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

        $authorizedGatherings = $mobileService->getAuthorizedGatherings($branchIds, $stewardGatheringIds);

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
    public function mobileUpload(WaiverFileService $fileService, ?string $gatheringId = null)
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
        $this->Authorization->authorize($tempWaiver, 'uploadWaivers');

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
        $canCloseWaivers = $this->Authentication->getIdentity()->checkCan('closeWaivers', $tempWaiver);
        if ($waiverClosure !== null && $waiverClosure->closed_at !== null && !$canCloseWaivers) {
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
        $requiredWaiverTypes = $fileService->getRequiredWaiverTypes($gathering);
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

            if ($fileService->isWaiverTypeAttested((int)$gathering->id, (int)$waiverTypeId)) {
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
                $result = $fileService->processMultipleWaiverImages(
                    $uploadedFiles,
                    $gathering,
                    $waiverType,
                    $notes,
                    $clientThumbnail,
                    $this->Authentication->getIdentity()->getIdentifier(),
                );

                if ($result->success) {
                    // Get redirect URL to mobile card
                    // Build the redirect URL using Router
                    $redirectUrl = Router::url([
                        'controller' => 'Members',
                        'action' => 'viewMobileCard',
                        'plugin' => null,
                    ], true); // true = full base URL

                    // Get result data for messaging
                    $resultData = $result->getData();
                    $pageCount = $resultData['page_count'] ?? count($uploadedFiles);

                    // If AJAX request, return JSON response
                    if ($this->request->is('ajax')) {
                        $this->Flash->success(__(
                            'Waiver uploaded successfully with {0} page(s).',
                            $pageCount,
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
                        $pageCount,
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
                    ]);
                } else {
                    $this->Flash->error(__('Failed to upload waiver: {0}', $result->getError()));
                }
            } catch (Exception $e) {
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
                    'exemption_reasons' => $waiverType->exemption_reasons_parsed ?? [],
                ];
            }
        }

        // Get upload limits for validation
        $uploadLimits = $fileService->getUploadLimits();

        // Get pre-selected value from query parameters (for direct upload links)
        $preSelectedWaiverTypeId = $this->request->getQuery('waiver_type_id');

        $waiverStatusSummary = $fileService->getWaiverStatusSummary((int)$gathering->id, $requiredWaiverTypes);

        $this->set(compact(
            'gathering',
            'requiredWaiverTypes',
            'waiverTypesData',
            'uploadLimits',
            'preSelectedWaiverTypeId',
            'waiverStatusSummary',
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
    public function attest(WaiverMobileService $mobileService)
    {
        $this->request->allowMethod(['post']);
        $this->viewBuilder()->setClassName('Json');

        try {
            $data = $this->request->getData();

            $waiverTypeId = (int)($data['waiver_type_id'] ?? 0);
            $gatheringId = (int)($data['gathering_id'] ?? 0);
            $reason = $data['reason'] ?? '';
            $notes = $data['notes'] ?? null;

            if (!$waiverTypeId || !$gatheringId || empty($reason)) {
                $this->set('success', false);
                $this->set('message', __('Missing required fields'));
                $this->viewBuilder()->setOption('serialize', ['success', 'message']);

                return;
            }

            // Load gathering for authorization
            $Gatherings = $this->fetchTable('Gatherings');
            $gathering = $Gatherings->get($gatheringId);

            $gatheringWaiver = $this->GatheringWaivers->newEmptyEntity();
            $gatheringWaiver->gathering = $gathering;
            $this->Authorization->authorize($gatheringWaiver, 'uploadWaivers');

            $result = $mobileService->processAttestation(
                $gatheringId,
                $waiverTypeId,
                $reason,
                $notes,
                $this->request->referer(),
            );

            if ($result->success) {
                $resultData = $result->getData();
                $this->set('success', true);
                $this->set('message', $result->reason);
                $this->set('redirectUrl', $resultData['redirectUrl'] ?? null);
                $this->viewBuilder()->setOption('serialize', ['success', 'message', 'redirectUrl']);
            } else {
                if (stripos($result->reason, 'closed') !== false) {
                    $this->response = $this->response->withStatus(403);
                }
                $this->set('success', false);
                $this->set('message', $result->reason);
                $this->viewBuilder()->setOption('serialize', ['success', 'message']);
            }
        } catch (Exception $e) {
            Log::error('Error creating waiver exemption: ' . $e->getMessage());
            $this->set('success', false);
            $this->set('message', __('An error occurred: {0}', $e->getMessage()));
            $this->viewBuilder()->setOption('serialize', ['success', 'message']);
        }
    }
}
