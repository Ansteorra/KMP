<?php

declare(strict_types=1);

namespace Waivers\Controller;

use App\KMP\StaticHelpers;
use App\Services\DocumentService;
use App\Services\ServiceResult;
use Cake\Http\Exception\BadRequestException;
use Cake\Http\Exception\NotFoundException;
use Cake\I18n\Date;
use Cake\Log\Log;
use Waivers\Services\ImageToPdfConversionService;
use Waivers\Services\RetentionPolicyService;

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
     * @var \Waivers\Services\ImageToPdfConversionService
     */
    private ImageToPdfConversionService $ImageToPdfConversionService;

    /**
     * Retention policy service instance
     *
     * @var \Waivers\Services\RetentionPolicyService
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
                ->order(['GatheringWaivers.created' => 'DESC']);

            $gatheringWaivers = $this->paginate($query);

            // Get required waiver types for this gathering's activities
            $GatheringActivityWaivers = $this->fetchTable('Waivers.GatheringActivityWaivers');
            $requiredWaiverTypes = $GatheringActivityWaivers->find()
                ->innerJoinWith('GatheringActivities.Gatherings', function ($q) use ($gatheringId) {
                    return $q->where(['Gatherings.id' => $gatheringId]);
                })
                ->contain(['WaiverTypes'])
                ->where(['GatheringActivityWaivers.deleted IS' => null])
                ->group(['GatheringActivityWaivers.waiver_type_id'])
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
                ->group('waiver_type_id')
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
                ->order(['GatheringWaivers.created' => 'DESC']);

            $gatheringWaivers = $this->paginate($query);

            // Get list of branches for filter dropdown
            $branches = $Branches->find('list')
                ->where(['Branches.id IN' => $branchIds])
                ->order(['Branches.name' => 'ASC'])
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
            ],
        ]);

        $this->Authorization->authorize($gatheringWaiver);

        // Load all waiver types and gathering activities for the change type/activities modal
        $waiverTypes = $this->GatheringWaivers->WaiverTypes->find('list')->order(['name' => 'ASC'])->toArray();
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

        $this->Authorization->authorize($gathering, 'uploadWaivers');

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
                    $this->Flash->success(__(
                        'Waiver uploaded successfully with {0} page(s).',
                        count($uploadedFiles)
                    ));
                } else {
                    $this->Flash->error(__('Failed to upload waiver: {0}', $result->getError()));
                }
            } catch (\Exception $e) {
                $this->Flash->error(__('Error uploading waiver: {0}', $e->getMessage()));
                Log::error('Waiver upload error: ' . $e->getMessage());
            }

            return $this->redirect(['action' => 'index', '?' => ['gathering_id' => $gatheringId]]);
        }

        // GET request - show upload form
        // (requiredWaiverTypes already calculated above)

        $this->set(compact('gathering', 'requiredWaiverTypes'));
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
        $retentionResult = $this->RetentionPolicyService->calculateRetentionDate(
            $waiverType->retention_policy,
            $gathering->end_date,
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
        }        // Now move PDF to permanent storage location
        $storageBasePath = WWW_ROOT . '../images/uploaded/waivers/';
        if (!is_dir($storageBasePath)) {
            if (!mkdir($storageBasePath, 0755, true)) {
                @unlink($pdfPath);
                $this->GatheringWaivers->delete($gatheringWaiver);
                return new ServiceResult(false, 'Failed to create storage directory');
            }
        }

        $storedFilename = uniqid('waiver_', true) . '.pdf';
        $permanentPath = $storageBasePath . $storedFilename;

        if (!rename($pdfPath, $permanentPath)) {
            @unlink($pdfPath);
            $this->GatheringWaivers->delete($gatheringWaiver);
            return new ServiceResult(false, 'Failed to move file to permanent storage');
        }

        // Calculate checksum
        $checksum = hash_file('sha256', $permanentPath);

        // Get current user ID for uploaded_by
        $currentUserId = $this->Authentication->getIdentity()->getIdentifier();

        // Create document record with proper entity_id
        $Documents = $this->fetchTable('Documents');
        $document = $Documents->newEntity([
            'entity_type' => 'GatheringWaiver',
            'entity_id' => $gatheringWaiver->id, // Now we have the real ID
            'uploaded_by' => $currentUserId,
            'original_filename' => $fileName,
            'stored_filename' => $storedFilename,
            'file_path' => 'waivers/' . $storedFilename,
            'mime_type' => 'application/pdf',
            'file_size' => $convertedSize,
            'checksum' => $checksum,
            'storage_adapter' => 'local',
            'metadata' => json_encode([
                'original_filename' => $fileName,
                'original_size' => $originalSize,
                'converted_size' => $convertedSize,
                'conversion_date' => date('Y-m-d H:i:s'),
                'compression_ratio' => round((1 - ($convertedSize / $originalSize)) * 100, 2),
                'source' => 'waiver_upload',
            ]),
        ]);

        if (!$Documents->save($document)) {
            @unlink($permanentPath);
            $this->GatheringWaivers->delete($gatheringWaiver);
            $errors = $document->getErrors();
            Log::error('Failed to save document record', ['errors' => $errors]);
            $errorMessages = [];
            foreach ($errors as $field => $fieldErrors) {
                foreach ($fieldErrors as $error) {
                    $errorMessages[] = "$field: $error";
                }
            }
            return new ServiceResult(false, 'Failed to save document: ' . implode(', ', $errorMessages));
        }

        $documentId = $document->id;

        // Update GatheringWaiver with the real document_id
        $gatheringWaiver->document_id = $documentId;
        if (!$this->GatheringWaivers->save($gatheringWaiver)) {
            // Clean up both records if update fails
            @unlink($permanentPath);
            $Documents->delete($document);
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
        $retentionResult = $this->RetentionPolicyService->calculateRetentionDate(
            $waiverType->retention_policy,
            $gathering->end_date,
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

        // Now move PDF to permanent storage location
        $storageBasePath = WWW_ROOT . '../images/uploaded/waivers/';
        if (!is_dir($storageBasePath)) {
            if (!mkdir($storageBasePath, 0755, true)) {
                @unlink($pdfPath);
                $this->GatheringWaivers->delete($gatheringWaiver);
                return new ServiceResult(false, 'Failed to create storage directory');
            }
        }

        $storedFilename = uniqid('waiver_', true) . '.pdf';
        $permanentPath = $storageBasePath . $storedFilename;

        if (!rename($pdfPath, $permanentPath)) {
            @unlink($pdfPath);
            $this->GatheringWaivers->delete($gatheringWaiver);
            return new ServiceResult(false, 'Failed to move file to permanent storage');
        }

        // Calculate checksum
        $checksum = hash_file('sha256', $permanentPath);

        // Get current user ID for uploaded_by
        $currentUserId = $this->Authentication->getIdentity()->getIdentifier();

        // Create document record with proper entity_id
        $Documents = $this->fetchTable('Documents');
        $document = $Documents->newEntity([
            'entity_type' => 'GatheringWaiver',
            'entity_id' => $gatheringWaiver->id, // Now we have the real ID
            'uploaded_by' => $currentUserId,
            'original_filename' => $originalFilename,
            'stored_filename' => $storedFilename,
            'file_path' => 'waivers/' . $storedFilename,
            'mime_type' => 'application/pdf',
            'file_size' => $convertedSize,
            'checksum' => $checksum,
            'storage_adapter' => 'local',
            'metadata' => json_encode([
                'original_filename' => $originalFilename,
                'original_size' => $totalOriginalSize,
                'converted_size' => $convertedSize,
                'conversion_date' => date('Y-m-d H:i:s'),
                'compression_ratio' => round((1 - ($convertedSize / $totalOriginalSize)) * 100, 2),
                'source' => 'waiver_upload',
                'page_count' => count($uploadedFiles),
                'is_multipage' => true,
            ]),
        ]);

        if (!$Documents->save($document)) {
            @unlink($permanentPath);
            $this->GatheringWaivers->delete($gatheringWaiver);
            $errors = $document->getErrors();
            Log::error('Failed to save document record', ['errors' => $errors]);
            $errorMessages = [];
            foreach ($errors as $field => $fieldErrors) {
                foreach ($fieldErrors as $error) {
                    $errorMessages[] = "$field: $error";
                }
            }
            return new ServiceResult(false, 'Failed to save document: ' . implode(', ', $errorMessages));
        }

        $documentId = $document->id;

        // Update GatheringWaiver with the real document_id
        $gatheringWaiver->document_id = $documentId;
        if (!$this->GatheringWaivers->save($gatheringWaiver)) {
            // Clean up both records if update fails
            @unlink($permanentPath);
            $Documents->delete($document);
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
        $this->Authorization->authorize($this->request);

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
            ->order(['Gatherings.start_date' => 'ASC', 'Gatherings.name' => 'ASC']);

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
                    ->order(['name' => 'ASC'])
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
            ->order(['GatheringWaivers.created' => 'DESC'])
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
            ->order(['Gatherings.start_date' => 'ASC']);

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
                    ->order(['name' => 'ASC'])
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
            ->order(['GatheringWaivers.created' => 'DESC'])
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
            ->order(['name' => 'ASC'])
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
}
