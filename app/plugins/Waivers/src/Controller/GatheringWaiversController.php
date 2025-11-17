<?php

declare(strict_types=1);

namespace Waivers\Controller;

use App\KMP\StaticHelpers;
use App\Services\DocumentService;
use App\Services\ImageToPdfConversionService;
use App\Services\RetentionPolicyService;
use App\Services\ServiceResult;
use Cake\Http\Exception\BadRequestException;
use Cake\Http\Exception\NotFoundException;
use Cake\I18n\Date;
use Cake\Log\Log;

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
        $this->fetchTable('Waivers.GatheringWaiverActivities');

        // Initialize services
        $this->DocumentService = new DocumentService();
        $this->ImageToPdfConversionService = new ImageToPdfConversionService();
        $this->RetentionPolicyService = new RetentionPolicyService();

        // Authorize typical CRUD actions
        $this->Authorization->authorizeModel('index');
    }

    /**
     * Index method - List waivers
     * 
     * If gathering_id is provided, shows waivers for that specific gathering.
     * Otherwise, shows all waivers for gatherings the user has permission to view.
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
                ],
            ]);

            $this->Authorization->authorize($gathering, 'view');

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

            $this->set(compact('gathering', 'gatheringWaivers', 'countsMap', 'requiredWaiverTypes'));
        } else {
            // Show all waivers for accessible gatherings
            $branchIds = $currentUser->getBranchIdsForAction('view', 'Waivers.GatheringWaivers');

            // If user has no permissions, show empty list
            if (is_array($branchIds) && empty($branchIds)) {
                $this->Flash->error(__('You do not have permission to view waivers.'));
                return $this->redirect(['controller' => 'Pages', 'action' => 'display', 'home']);
            }

            // Get all branches or filtered by permission
            $Branches = $this->fetchTable('Branches');
            if ($branchIds === null) {
                // Global permission - all branches
                $branchIds = $Branches->find()->select(['id'])->all()->extract('id')->toArray();
            }

            // Build base query
            $query = $this->GatheringWaivers->find()
                ->where(['GatheringWaivers.deleted IS' => null])
                ->innerJoinWith('Gatherings', function ($q) use ($branchIds) {
                    return $q->where([
                        'Gatherings.branch_id IN' => $branchIds,
                        'Gatherings.deleted IS' => null,
                    ]);
                });

            // Apply search filters
            $searchTerm = $this->request->getQuery('search');
            $branchId = $this->request->getQuery('branch_id');
            $startDate = $this->request->getQuery('start_date');
            $endDate = $this->request->getQuery('end_date');

            if (!empty($searchTerm)) {
                $query->where([
                    'OR' => [
                        'Gatherings.name LIKE' => '%' . $searchTerm . '%',
                    ],
                ]);
            }

            if (!empty($branchId)) {
                $query->where(['Gatherings.branch_id' => $branchId]);
            }

            if (!empty($startDate)) {
                $query->where(['GatheringWaivers.created >=' => $startDate]);
            }

            if (!empty($endDate)) {
                // Add one day to include the end date fully
                $endDateTime = new \Cake\I18n\Date($endDate);
                $endDateTime = $endDateTime->addDay();
                $query->where(['GatheringWaivers.created <' => $endDateTime]);
            }

            $query->contain([
                'Gatherings' => function ($q) {
                    return $q->select(['id', 'name', 'start_date', 'end_date', 'branch_id']);
                },
                'Gatherings.Branches' => function ($q) {
                    return $q->select(['id', 'name']);
                },
                'WaiverTypes',
                'Documents',
            ])
                ->orderBy(['GatheringWaivers.created' => 'DESC']);

            $gatheringWaivers = $this->paginate($query);

            // Get list of branches for filter dropdown
            $branches = $Branches->find('list')
                ->where(['Branches.id IN' => $branchIds])
                ->orderBy(['Branches.name' => 'ASC'])
                ->toArray();

            $this->set(compact('gatheringWaivers', 'branches'));
            $this->set('gathering', null); // No specific gathering
            $this->set('countsMap', []); // No counts when viewing all
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
                'Gatherings' => ['GatheringTypes', 'Branches', 'GatheringActivities'],
                'WaiverTypes',
                'Documents',
                'GatheringWaiverActivities' => ['GatheringActivities'],
                'DeclinedByMembers',
                'CreatedByMembers',
            ],
        ]);

        $this->Authorization->authorize($gatheringWaiver);

        // Load all waiver types and gathering activities for the change type/activities modal
        $waiverTypes = $this->GatheringWaivers->WaiverTypes->find('list')->orderBy(['name' => 'ASC'])->toArray();
        $gatheringActivities = $gatheringWaiver->gathering->gathering_activities ?? [];

        $this->set(compact('gatheringWaiver', 'waiverTypes', 'gatheringActivities'));
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

        // Load waiver requirements for each activity
        if (!empty($gathering->gathering_activities)) {
            $activityIds = collection($gathering->gathering_activities)->extract('id')->toArray();
            $GatheringActivityWaivers = $this->fetchTable('Waivers.GatheringActivityWaivers');
            $activityWaivers = $GatheringActivityWaivers->find()
                ->where(['gathering_activity_id IN' => $activityIds])
                ->toArray();

            // Group by activity ID
            $waiversByActivity = collection($activityWaivers)->groupBy('gathering_activity_id')->toArray();

            // Add to activities
            foreach ($gathering->gathering_activities as $activity) {
                $activity->gathering_activity_waivers = $waiversByActivity[$activity->id] ?? [];
            }
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
            $uploadedFiles = $data['waiver_images'] ?? [];
            $waiverTypeId = $data['waiver_type_id'] ?? null;
            $notes = $data['notes'] ?? '';
            $activityIds = $data['activity_ids'] ?? [];

            if (empty($uploadedFiles) || !$waiverTypeId) {
                $this->Flash->error(__('Please select waiver type and upload at least one image.'));
                return $this->redirect($this->referer());
            }

            if (empty($activityIds)) {
                $this->Flash->error(__('Please select at least one activity that this waiver applies to.'));
                return $this->redirect($this->referer());
            }

            // Get waiver type for retention policy
            $WaiverTypes = $this->fetchTable('Waivers.WaiverTypes');
            $waiverType = $WaiverTypes->get($waiverTypeId);

            // Process all uploaded files as a single multi-page waiver
            try {
                $result = $this->_processMultipleWaiverImages(
                    $uploadedFiles,
                    $gathering,
                    $waiverType,
                    $notes,
                    $activityIds
                );

                if ($result->isSuccess()) {
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

                    // Set Flash message (will show on redirect page)
                    $this->Flash->success(__(
                        'Waiver uploaded successfully with {0} page(s).',
                        count($uploadedFiles)
                    ));

                    // If AJAX request, return JSON response
                    if ($this->request->is('ajax')) {
                        $this->viewBuilder()->setClassName('Json');
                        $this->set('success', true);
                        $this->set('redirectUrl', $redirectUrl);
                        $this->viewBuilder()->setOption('serialize', ['success', 'redirectUrl']);
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

        // Build activity data for client
        $activitiesData = [];
        if (!empty($gathering->gathering_activities)) {
            foreach ($gathering->gathering_activities as $activity) {
                $waiverTypeIds = [];
                if (!empty($activity->gathering_activity_waivers)) {
                    foreach ($activity->gathering_activity_waivers as $activityWaiver) {
                        $waiverTypeIds[] = $activityWaiver->waiver_type_id;
                    }

                    // Only add this activity if it has waivers
                    $activitiesData[] = [
                        'id' => $activity->id,
                        'name' => $activity->name,
                        'description' => $activity->description ?? '',
                        'waiver_types' => $waiverTypeIds
                    ];
                }
            }
        }

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
        $preSelectedActivityId = $this->request->getQuery('activity_id');
        $preSelectedWaiverTypeId = $this->request->getQuery('waiver_type_id');

        $this->set(compact('gathering', 'requiredWaiverTypes', 'activitiesData', 'waiverTypesData', 'uploadLimits', 'preSelectedActivityId', 'preSelectedWaiverTypeId'));
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
     * 7. Create GatheringActivityWaivers associations
     *
     * @param \Psr\Http\Message\UploadedFileInterface $uploadedFile Uploaded file object
     * @param \App\Model\Entity\Gathering $gathering Gathering entity
     * @param \Waivers\Model\Entity\WaiverType $waiverType Waiver type entity
     * @param int|null $memberId Optional member ID
     * @param string $notes Optional notes
     * @param array $activityIds Array of gathering_activity IDs
     * @return \App\Services\ServiceResult Result object
     */
    private function _processWaiverUpload(
        \Psr\Http\Message\UploadedFileInterface $uploadedFile,
        $gathering,
        $waiverType,
        ?int $memberId,
        string $notes,
        array $activityIds
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
        $conversionResult = $this->ImageToPdfConversionService->convertImageToPdf(
            $tmpName,
            $pdfPath
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

        if (!$retentionResult->isSuccess()) {
            @unlink($pdfPath);
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
            ['pdf']    // allowed extensions
        );

        // Clean up temporary PDF file
        @unlink($pdfPath);

        if (!$documentResult->isSuccess()) {
            $this->GatheringWaivers->delete($gatheringWaiver);
            return new ServiceResult(false, 'Failed to save document: ' . $documentResult->getError());
        }

        $documentId = $documentResult->getData();

        // Update GatheringWaiver with the real document_id
        $gatheringWaiver->document_id = $documentId;
        if (!$this->GatheringWaivers->save($gatheringWaiver)) {
            // Clean up document record if update fails
            $this->DocumentService->deleteDocument($documentId);
            $this->GatheringWaivers->delete($gatheringWaiver);
            return new ServiceResult(false, 'Failed to update waiver with document ID');
        }

        // Create GatheringWaiverActivities associations (join table linking waivers to activities)
        $GatheringWaiverActivities = $this->fetchTable('Waivers.GatheringWaiverActivities');
        foreach ($activityIds as $activityId) {
            $waiverActivity = $GatheringWaiverActivities->newEntity([
                'gathering_waiver_id' => $gatheringWaiver->id,
                'gathering_activity_id' => (int)$activityId,
            ]);

            if (!$GatheringWaiverActivities->save($waiverActivity)) {
                Log::error('Failed to save waiver-activity association', [
                    'gathering_waiver_id' => $gatheringWaiver->id,
                    'gathering_activity_id' => $activityId,
                    'errors' => $waiverActivity->getErrors()
                ]);
                // Don't fail the whole upload, just log the error
            }
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
     * @param array $activityIds Array of activity IDs to associate
     * @return ServiceResult
     */
    private function _processMultipleWaiverImages(
        array $uploadedFiles,
        $gathering,
        $waiverType,
        string $notes,
        array $activityIds
    ): ServiceResult {
        // Extract temp paths from uploaded files
        $tempPaths = [];
        $originalFilename = '';
        $totalOriginalSize = 0;

        foreach ($uploadedFiles as $index => $uploadedFile) {
            $tempPaths[] = $uploadedFile->getStream()->getMetadata('uri');
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

        // Convert all images to a single multi-page PDF
        $conversionResult = $this->ImageToPdfConversionService->convertMultipleImagesToPdf(
            $tempPaths,
            $pdfPath
        );

        if (!$conversionResult->success) {
            return new ServiceResult(false, $conversionResult->reason);
        }

        $convertedSize = filesize($pdfPath);

        // Calculate retention date first
        // Convert DateTime to Date for retention calculation
        $gatheringEndDate = Date::parse($gathering->end_date->format('Y-m-d'));
        $retentionResult = $this->RetentionPolicyService->calculateRetentionDate(
            $waiverType->retention_policy,
            $gatheringEndDate,
            Date::now()
        );

        if (!$retentionResult->isSuccess()) {
            @unlink($pdfPath);
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
                'page_count' => count($uploadedFiles),
                'is_multipage' => true,
            ],
            'waivers', // subdirectory
            ['pdf']    // allowed extensions
        );

        // Clean up temporary PDF file
        @unlink($pdfPath);

        if (!$documentResult->isSuccess()) {
            $this->GatheringWaivers->delete($gatheringWaiver);
            return new ServiceResult(false, 'Failed to save document: ' . $documentResult->getError());
        }

        $documentId = $documentResult->getData();

        // Update GatheringWaiver with the real document_id
        $gatheringWaiver->document_id = $documentId;
        if (!$this->GatheringWaivers->save($gatheringWaiver)) {
            // Clean up document record if update fails
            $this->DocumentService->deleteDocument($documentId);
            $this->GatheringWaivers->delete($gatheringWaiver);
            return new ServiceResult(false, 'Failed to update waiver with document ID');
        }

        // Create GatheringWaiverActivities associations (join table linking waivers to activities)
        $GatheringWaiverActivities = $this->fetchTable('Waivers.GatheringWaiverActivities');
        foreach ($activityIds as $activityId) {
            $waiverActivity = $GatheringWaiverActivities->newEntity([
                'gathering_waiver_id' => $gatheringWaiver->id,
                'gathering_activity_id' => (int)$activityId,
            ]);

            if (!$GatheringWaiverActivities->save($waiverActivity)) {
                Log::error('Failed to save waiver-activity association', [
                    'gathering_waiver_id' => $gatheringWaiver->id,
                    'gathering_activity_id' => $activityId,
                    'errors' => $waiverActivity->getErrors()
                ]);
                // Don't fail the whole upload, just log the error
            }
        }

        return new ServiceResult(true, null, [
            'waiver_id' => $gatheringWaiver->id,
            'document_id' => $documentId,
            'page_count' => count($uploadedFiles),
        ]);
    }

    /**
     * Get required waiver types for upload view

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
                'Gatherings' => ['GatheringActivities'],
                'GatheringWaiverActivities' => ['GatheringActivities'],
                'WaiverTypes',
            ],
        ]);

        // Check authorization - this uses the canChangeWaiverType policy
        $this->Authorization->authorize($gatheringWaiver, 'canChangeWaiverType');

        $data = $this->request->getData();
        $waiverTypeId = $data['waiver_type_id'] ?? null;
        $activityIds = $data['activity_ids'] ?? [];

        // Validate inputs
        if (empty($waiverTypeId)) {
            $this->Flash->error(__('Please select a waiver type.'));
            return $this->redirect(['action' => 'view', $id]);
        }

        if (empty($activityIds)) {
            $this->Flash->error(__('Please select at least one activity.'));
            return $this->redirect(['action' => 'view', $id]);
        }

        // Verify waiver type exists
        $WaiverTypes = $this->fetchTable('Waivers.WaiverTypes');
        $newWaiverType = $WaiverTypes->get($waiverTypeId);
        if (!$newWaiverType) {
            $this->Flash->error(__('Invalid waiver type selected.'));
            return $this->redirect(['action' => 'view', $id]);
        }

        // Verify all activities belong to the gathering
        $gatheringActivityIds = array_map(
            fn($activity) => $activity->id,
            $gatheringWaiver->gathering->gathering_activities
        );

        foreach ($activityIds as $activityId) {
            if (!in_array($activityId, $gatheringActivityIds)) {
                $this->Flash->error(__('One or more selected activities do not belong to this gathering.'));
                return $this->redirect(['action' => 'view', $id]);
            }
        }

        // Capture before state for audit note
        $oldWaiverTypeName = $gatheringWaiver->waiver_type->name;
        $oldActivityNames = array_map(
            fn($wa) => $wa->gathering_activity->name,
            $gatheringWaiver->gathering_waiver_activities
        );

        // Get new activity names for audit note
        $newActivityNames = [];
        foreach ($gatheringWaiver->gathering->gathering_activities as $activity) {
            if (in_array($activity->id, $activityIds)) {
                $newActivityNames[] = $activity->name;
            }
        }

        // Start transaction
        $connection = $this->GatheringWaivers->getConnection();
        $connection->begin();

        try {
            // Update waiver type
            $gatheringWaiver->waiver_type_id = $waiverTypeId;
            if (!$this->GatheringWaivers->save($gatheringWaiver)) {
                throw new \Exception('Failed to update waiver type');
            }

            // Delete existing activity associations
            $GatheringWaiverActivities = $this->fetchTable('Waivers.GatheringWaiverActivities');
            $GatheringWaiverActivities->deleteAll(['gathering_waiver_id' => $id]);

            // Create new activity associations
            foreach ($activityIds as $activityId) {
                $activityWaiver = $GatheringWaiverActivities->newEntity([
                    'gathering_waiver_id' => $id,
                    'gathering_activity_id' => $activityId,
                ]);

                if (!$GatheringWaiverActivities->save($activityWaiver)) {
                    throw new \Exception('Failed to create activity association');
                }
            }

            // Create audit note with before/after snapshot
            $auditNote = $this->GatheringWaivers->AuditNotes->newEntity([
                'entity_id' => $id,
                'entity_type' => 'Waivers.GatheringWaivers',
                'subject' => 'Waiver Type and Activities Changed',
                'body' => $this->_buildAuditNoteBody(
                    $oldWaiverTypeName,
                    $newWaiverType->name,
                    $oldActivityNames,
                    $newActivityNames
                ),
                'author_id' => $this->Authentication->getIdentity()->id,
                'private' => false,
            ]);

            if (!$this->GatheringWaivers->AuditNotes->save($auditNote)) {
                throw new \Exception('Failed to create audit note');
            }

            $connection->commit();

            $this->Flash->success(__('Waiver type and activity associations have been updated successfully.'));
        } catch (\Exception $e) {
            $connection->rollback();
            $this->Flash->error(__('Failed to update waiver: {0}', $e->getMessage()));
            Log::error('Error updating waiver type/activities: ' . $e->getMessage());
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

        // Get branches user can upload waivers for
        $branchIds = $currentUser->getBranchIdsForAction('add', 'Waivers.GatheringWaivers');

        // If user has no branch permissions, show empty list
        if ($branchIds === null) {
            // null means global permission - get all branches
            $Branches = $this->fetchTable('Branches');
            $branchIds = $Branches->find()->select(['id'])->all()->extract('id')->toArray();
        } elseif (empty($branchIds)) {
            // Empty array means no permission
            $this->set('gatherings', []);
            return;
        }

        // Get gatherings that need waivers
        $Gatherings = $this->fetchTable('Gatherings');
        $today = Date::now()->toDateString();
        $oneWeekFromNow = Date::now()->addDays(7)->toDateString();

        // Find gatherings with:
        // 1. end_date >= today (or null which defaults to start_date) - ongoing or future
        // 2. If future event: start_date <= one week from now (only show gatherings within the next 7 days)
        // 3. In branches user has permission for
        // 4. Have required waivers configured (checked via activities)
        $query = $Gatherings->find()
            ->where([
                'OR' => [
                    'Gatherings.end_date >=' => $today,
                    'AND' => [
                        'Gatherings.end_date IS' => null,
                        'Gatherings.start_date >=' => $today,
                    ]
                ],
                'OR' => [
                    'Gatherings.start_date <' => $today, // Already started (past or ongoing)
                    'Gatherings.start_date <=' => $oneWeekFromNow, // Starts within next 7 days
                ],
                'Gatherings.branch_id IN' => $branchIds,
                'Gatherings.deleted IS' => null,
            ])
            ->contain([
                'Branches',
                'GatheringTypes',
                'GatheringActivities' => function ($q) {
                    return $q->select(['id', 'name']);
                },
            ])
            ->orderBy(['Gatherings.start_date' => 'ASC', 'Gatherings.name' => 'ASC']);

        $allGatherings = $query->all();

        // Filter to only gatherings that have required waivers and are missing at least one
        $GatheringActivityWaivers = $this->fetchTable('Waivers.GatheringActivityWaivers');
        $gatheringsNeedingWaivers = [];

        foreach ($allGatherings as $gathering) {
            if (empty($gathering->gathering_activities)) {
                continue; // No activities = no waiver requirements
            }

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

            if (empty($requiredWaiverTypes)) {
                continue; // No required waivers
            }

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
                $gatheringsNeedingWaivers[] = $gathering;
            }
        }

        $this->set(compact('gatheringsNeedingWaivers'));
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
        // Authorize access to dashboard
        $this->Authorization->authorize($this->request);

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

        $this->set(compact(
            'statistics',
            'gatheringsMissingWaivers',
            'gatheringsNeedingWaivers',
            'branchesWithIssues',
            'recentActivity',
            'waiverTypesSummary',
            'searchResults',
            'searchTerm',
            'complianceDays'
        ));
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
        $gatheringsMissingCount = count($waiverGatherings['missing']); // Past events
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
        $today = Date::now();
        $todayString = $today->toDateString();
        $futureDate = Date::now()->addDays($daysAhead)->toDateString();
        // Look back further to catch past due events
        $pastCutoff = Date::now()->subDays(90)->toDateString(); // Look back 90 days for missing waivers

        // Get compliance days from settings (default: 2 days)
        $complianceDays = (int)StaticHelpers::getAppSetting('Waivers.ComplianceDays', '2', 'int', false);

        // Find all gatherings in extended date range (including past events)
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
            ])
            ->contain([
                'Branches',
                'GatheringActivities' => function ($q) {
                    return $q->select(['id', 'name']);
                },
            ])
            ->orderBy(['Gatherings.start_date' => 'ASC']);

        $allGatherings = $query->all();
        $gatheringsMissing = []; // Past events (>48hrs after end)
        $gatheringsUpcoming = []; // Future/ongoing events

        foreach ($allGatherings as $gathering) {
            if (empty($gathering->gathering_activities)) {
                continue;
            }

            $activityIds = collection($gathering->gathering_activities)->extract('id')->toArray();

            // Get required waiver types
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

            if (empty($requiredWaiverTypes)) {
                continue;
            }

            // Get uploaded waiver types
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
        $this->Authorization->authorize($tempWaiver, "add");

        $currentUser = $this->Authentication->getIdentity();
        if (!$currentUser) {
            $this->Flash->error(__('You must be logged in to upload waivers.'));
            return $this->redirect(['controller' => 'Members', 'action' => 'login', 'plugin' => null]);
        }

        // Check if user can add GatheringWaivers and get branch IDs they have permission for
        $branchIds = $currentUser->getBranchIdsForAction('add', $tempWaiver);

        // If branchIds is null, user has global permission (super user or global permission)
        // If empty array, user has no permission
        if (is_array($branchIds) && empty($branchIds)) {
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
        // - User has permission to upload waivers
        // - Within date range
        $GatheringActivityWaivers = $this->fetchTable('Waivers.GatheringActivityWaivers');

        // Use innerJoinWith to properly join through the associations
        $gatheringIdsQuery = $GatheringActivityWaivers->find()
            ->innerJoinWith('GatheringActivities.Gatherings')
            ->where([
                'GatheringActivityWaivers.deleted IS' => null,
                'GatheringActivities.deleted IS' => null,
                'Gatherings.deleted IS' => null,
                'OR' => [
                    'Gatherings.start_date <=' => $startDate,
                    'Gatherings.end_date >=' => $endDate
                ]
            ])
            ->select(['gathering_id' => 'Gatherings.id'])
            ->distinct(['Gatherings.id']);

        // If not global permission, filter by branch IDs
        if ($branchIds !== null) {
            $gatheringIdsQuery->where(['Gatherings.branch_id IN' => $branchIds]);
        }

        // Extract gathering IDs
        $gatheringIds = $gatheringIdsQuery->all()->extract('gathering_id')->toArray();

        // If no gatherings found, show empty state
        if (empty($gatheringIds)) {
            $authorizedGatherings = [];
        } else {
            // STEP 2: Fetch full gathering details for authorized gatherings, sorted by start date
            $Gatherings = $this->fetchTable('Gatherings');
            $authorizedGatherings = $Gatherings->find()
                ->where(['Gatherings.id IN' => $gatheringIds])
                ->contain(['Branches', 'GatheringTypes'])
                ->orderBy(['Gatherings.start_date' => 'DESC'])
                ->all()
                ->toArray();
        }

        $this->set(compact('authorizedGatherings'));

        // Use mobile app layout
        $this->viewBuilder()->setLayout('mobile_app');
        $this->set('mobileTitle', 'Select Gathering');
        $this->set('mobileBackUrl', $this->request->referer());
        $this->set('mobileHeaderColor', '#0dcaf0'); // info color
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
        $tempWaiver = $this->GatheringWaivers->newEmptyEntity();
        $tempWaiver->gathering = $gathering;
        $tempWaiver->gathering_id = $gatheringId;
        $this->Authorization->authorize($tempWaiver, "add");

        // Load waiver requirements for each activity
        if (!empty($gathering->gathering_activities)) {
            $activityIds = collection($gathering->gathering_activities)->extract('id')->toArray();
            $GatheringActivityWaivers = $this->fetchTable('Waivers.GatheringActivityWaivers');
            $activityWaivers = $GatheringActivityWaivers->find()
                ->where(['gathering_activity_id IN' => $activityIds])
                ->contain(['WaiverTypes'])
                ->toArray();

            // Group by activity ID
            $waiversByActivity = collection($activityWaivers)->groupBy('gathering_activity_id')->toArray();

            // Add to activities
            foreach ($gathering->gathering_activities as $activity) {
                $activity->gathering_activity_waivers = $waiversByActivity[$activity->id] ?? [];
            }
        }

        // Check if gathering has required waivers
        $requiredWaiverTypes = $this->_getRequiredWaiverTypes($gathering);
        if (empty($requiredWaiverTypes)) {
            $this->Flash->error(__('This gathering is not configured to collect waivers.'));
            return $this->redirect(['action' => 'mobileSelectGathering']);
        }

        // Check if user has permission to add waivers for this gathering's branch
        $currentUser = $this->Authentication->getIdentity();
        $tempWaiver = $this->GatheringWaivers->newEmptyEntity();
        $tempWaiver->gathering_id = $gathering->id;
        $tempWaiver->gathering = $gathering;

        $branchIds = $currentUser->getBranchIdsForAction('add', $tempWaiver);

        // If branchIds is an empty array, user has no permission
        if (is_array($branchIds) && !in_array($gathering->branch_id, $branchIds)) {
            $this->Flash->error(__('You do not have permission to upload waivers for this gathering.'));
            return $this->redirect(['action' => 'mobileSelectGathering']);
        }

        // If branchIds is null, user has global permission (allowed)
        // If branchIds contains the gathering's branch_id, user has permission (allowed)

        // Handle POST - process uploads
        if ($this->request->is('post')) {
            $data = $this->request->getData();
            $uploadedFiles = $data['waiver_images'] ?? [];
            $waiverTypeId = $data['waiver_type_id'] ?? null;
            $notes = $data['notes'] ?? '';
            $activityIds = $data['activity_ids'] ?? [];

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

            if (empty($activityIds)) {
                $this->Flash->error(__('Please select at least one activity that this waiver applies to.'));
                return $this->redirect($this->referer());
            }

            // Get waiver type for retention policy
            $WaiverTypes = $this->fetchTable('Waivers.WaiverTypes');
            $waiverType = $WaiverTypes->get($waiverTypeId);

            // Process all uploaded files as a single multi-page waiver
            try {
                $result = $this->_processMultipleWaiverImages(
                    $uploadedFiles,
                    $gathering,
                    $waiverType,
                    $notes,
                    $activityIds
                );

                if ($result->isSuccess()) {
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

                    // If AJAX request, return JSON response
                    if ($this->request->is('ajax')) {
                        // Don't set Flash message for AJAX - will be set on redirect
                        $this->Flash->success(__(
                            'Waiver uploaded successfully with {0} page(s).',
                            count($uploadedFiles)
                        ));

                        $this->viewBuilder()->setClassName('Json');
                        $this->set('success', true);
                        $this->set('redirectUrl', $redirectUrl);
                        $this->viewBuilder()->setOption('serialize', ['success', 'redirectUrl']);
                        return;
                    }

                    // For non-AJAX, set Flash and redirect
                    $this->Flash->success(__(
                        'Waiver uploaded successfully with {0} page(s).',
                        count($uploadedFiles)
                    ));

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

        // Build activity data for client
        $activitiesData = [];
        if (!empty($gathering->gathering_activities)) {
            foreach ($gathering->gathering_activities as $activity) {
                $waiverTypeIds = [];
                if (!empty($activity->gathering_activity_waivers)) {
                    foreach ($activity->gathering_activity_waivers as $activityWaiver) {
                        $waiverTypeIds[] = $activityWaiver->waiver_type_id;
                    }

                    // Only add this activity if it has waivers
                    $activitiesData[] = [
                        'id' => $activity->id,
                        'name' => $activity->name,
                        'description' => $activity->description ?? '',
                        'waiver_types' => $waiverTypeIds
                    ];
                }
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

        // Get pre-selected values from query parameters (for direct upload links)
        $preSelectedActivityId = $this->request->getQuery('activity_id');
        $preSelectedWaiverTypeId = $this->request->getQuery('waiver_type_id');

        $this->set(compact('gathering', 'requiredWaiverTypes', 'activitiesData', 'waiverTypesData', 'uploadLimits', 'preSelectedActivityId', 'preSelectedWaiverTypeId'));

        // Use mobile app layout
        $this->viewBuilder()->setLayout('mobile_app');
        $this->set('mobileTitle', 'Upload Waiver');
        $this->set('mobileBackUrl', ['action' => 'mobileSelectGathering']);
        $this->set('mobileHeaderColor', '#0dcaf0'); // info color
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
     * Records an exemption for a specific activity/waiver type combination,
     * attesting that the waiver requirement does not apply.
     * 
     * **Expected POST Data**:
     * - gathering_activity_id: int
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

            // Support both single activity (old) and multiple activities (new)
            $gatheringActivityIds = [];
            if (isset($data['gathering_activity_ids']) && is_array($data['gathering_activity_ids'])) {
                // New format: multiple activities
                $gatheringActivityIds = array_map('intval', $data['gathering_activity_ids']);
            } elseif (isset($data['gathering_activity_id'])) {
                // Old format: single activity (backward compatibility)
                $gatheringActivityIds = [(int)$data['gathering_activity_id']];
            }

            $waiverTypeId = (int)($data['waiver_type_id'] ?? 0);
            $gatheringId = (int)($data['gathering_id'] ?? 0);
            $reason = $data['reason'] ?? '';
            $notes = $data['notes'] ?? null;

            // Validate required fields
            if (empty($gatheringActivityIds) || !$waiverTypeId || !$gatheringId || empty($reason)) {
                $this->set('success', false);
                $this->set('message', __('Missing required fields'));
                $this->viewBuilder()->setOption('serialize', ['success', 'message']);
                return;
            }

            // Load gathering for authorization
            $Gatherings = $this->fetchTable('Gatherings');
            $gathering = $Gatherings->get($gatheringId);

            // Check authorization - user must be able to edit the gathering
            $this->Authorization->authorize($gathering, 'uploadWaivers');

            // Verify all activities are assigned to this gathering
            $GatheringsGatheringActivities = $this->fetchTable('GatheringsGatheringActivities');
            $validActivities = $GatheringsGatheringActivities->find()
                ->where([
                    'gathering_activity_id IN' => $gatheringActivityIds,
                    'gathering_id' => $gatheringId
                ])
                ->all()
                ->toArray();

            if (count($validActivities) !== count($gatheringActivityIds)) {
                $this->set('success', false);
                $this->set('message', __('One or more activities are not assigned to this gathering'));
                $this->viewBuilder()->setOption('serialize', ['success', 'message']);
                return;
            }

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

            // Check if exemption already exists for any of these activities with this waiver type
            // Ignore declined waivers when checking for duplicates
            $existing = $this->GatheringWaivers->find()
                ->where([
                    'GatheringWaivers.waiver_type_id' => $waiverTypeId,
                    'GatheringWaivers.gathering_id' => $gatheringId,
                    'GatheringWaivers.is_exemption' => true,
                    'GatheringWaivers.status !=' => 'declined'
                ])
                ->matching('GatheringWaiverActivities', function ($q) use ($gatheringActivityIds) {
                    return $q->where(['GatheringWaiverActivities.gathering_activity_id IN' => $gatheringActivityIds]);
                })
                ->first();

            if ($existing) {
                // Check if the reason is the same
                if ($existing->exemption_reason === $reason) {
                    $this->set('success', false);
                    $this->set('message', __('An exemption with this reason already exists for one or more of the selected activities and waiver type'));
                } else {
                    $this->set('success', false);
                    $this->set('message', __('An exemption already exists for one or more of the selected activities and waiver type. Please delete the existing exemption before creating a new one with a different reason.'));
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

            if (!$retentionResult->isSuccess()) {
                $this->set('success', false);
                $this->set('message', __('Failed to calculate retention date: {0}', $retentionResult->getReason()));
                $this->viewBuilder()->setOption('serialize', ['success', 'message']);
                return;
            }

            $retentionDate = $retentionResult->getData();

            // Create new exemption as a GatheringWaiver record (one exemption for all activities)
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
                // Use the GatheringActivityService to associate the exemption with ALL selected activities
                $GatheringActivityService = new \Waivers\Services\GatheringActivityService();
                $associationResult = $GatheringActivityService->associateWaiverWithActivities(
                    $exemption->id,
                    $gatheringActivityIds  // Pass all activity IDs
                );

                if (!$associationResult->isSuccess()) {
                    // Rollback - delete the exemption
                    $this->GatheringWaivers->delete($exemption);
                    $this->set('success', false);
                    $this->set('message', __('Failed to associate exemption with activity: {0}', $associationResult->getReason()));
                    $this->viewBuilder()->setOption('serialize', ['success', 'message']);
                    return;
                }

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