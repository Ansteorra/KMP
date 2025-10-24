<?php

declare(strict_types=1);

namespace Waivers\Controller;

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
     * Index method - List waivers for a gathering with counts per waiver type
     *
     * @return \Cake\Http\Response|null|void Renders view
     * @throws \Cake\Http\Exception\BadRequestException When gathering_id is missing
     */
    public function index()
    {
        $gatheringId = $this->request->getQuery('gathering_id');

        if (!$gatheringId) {
            throw new BadRequestException(__('Gathering ID is required'));
        }

        // Get gathering with its activities
        $Gatherings = $this->fetchTable('Gatherings');
        $gathering = $Gatherings->get($gatheringId, [
            'contain' => [
                'GatheringTypes',
                'Branches',
                'GatheringActivities',
            ],
        ]);

        $this->Authorization->authorize($gathering, 'view');

        // Get all waivers for this gathering
        $query = $this->GatheringWaivers->find()
            ->where(['gathering_id' => $gatheringId])
            ->contain(['WaiverTypes', 'Members', 'Documents'])
            ->order(['GatheringWaivers.created' => 'DESC']);

        $gatheringWaivers = $this->paginate($query);

        // Calculate waiver counts per type
        $waiverCounts = $this->GatheringWaivers->find()
            ->where(['gathering_id' => $gatheringId])
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

        $this->set(compact('gathering', 'gatheringWaivers', 'countsMap'));
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
                'Members',
                'Documents',
                'GatheringWaiverActivities' => ['GatheringActivities'],
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
            // Convert empty string to null for member_id
            $memberId = !empty($data['member_id']) ? (int)$data['member_id'] : null;
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
                    $memberId,
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
     * @param int|null $memberId Member ID if applicable
     * @param string $notes Notes for the waiver
     * @param array $activityIds Array of activity IDs to associate
     * @return ServiceResult
     */
    private function _processMultipleWaiverImages(
        array $uploadedFiles,
        $gathering,
        $waiverType,
        ?int $memberId,
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
            'member_id' => $memberId,
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
}
