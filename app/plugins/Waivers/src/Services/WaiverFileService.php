<?php
declare(strict_types=1);

namespace Waivers\Services;

use App\Services\DocumentService;
use App\Services\ImageToPdfConversionService;
use App\Services\RetentionPolicyService;
use App\Services\ServiceResult;
use Cake\Http\Response;
use Cake\I18n\Date;
use Cake\Log\Log;
use Cake\ORM\TableRegistry;
use Laminas\Diactoros\UploadedFile;
use Psr\Http\Message\UploadedFileInterface;
use Throwable;

/**
 * Waiver File Service
 *
 * Handles file I/O operations for gathering waivers: single and multi-page
 * image-to-PDF conversion, document storage, download, preview, and inline display.
 */
class WaiverFileService
{
    /**
     * @var \App\Services\DocumentService
     */
    private DocumentService $documentService;

    /**
     * @var \App\Services\ImageToPdfConversionService
     */
    private ImageToPdfConversionService $imageToPdfConversionService;

    /**
     * @var \App\Services\RetentionPolicyService
     */
    private RetentionPolicyService $retentionPolicyService;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->documentService = new DocumentService();
        $this->imageToPdfConversionService = new ImageToPdfConversionService();
        $this->retentionPolicyService = new RetentionPolicyService();
    }

    /**
     * Process a single waiver image upload into a PDF.
     *
     * @param \Psr\Http\Message\UploadedFileInterface $uploadedFile Uploaded file
     * @param mixed $gathering Gathering entity
     * @param mixed $waiverType WaiverType entity
     * @param int|null $memberId Optional member ID
     * @param string $notes Optional notes
     * @param int $currentUserId Current user ID for uploaded_by
     * @return \App\Services\ServiceResult
     */
    public function processWaiverUpload(
        UploadedFileInterface $uploadedFile,
        $gathering,
        $waiverType,
        ?int $memberId,
        string $notes,
        int $currentUserId,
    ): ServiceResult {
        $GatheringWaivers = TableRegistry::getTableLocator()->get('Waivers.GatheringWaivers');

        $tmpName = $uploadedFile->getStream()->getMetadata('uri');
        $fileName = $uploadedFile->getClientFilename();
        $fileSize = $uploadedFile->getSize();

        $tmpDir = sys_get_temp_dir();
        $uniqueId = uniqid('waiver_', true);
        $pdfPath = $tmpDir . DIRECTORY_SEPARATOR . $uniqueId . '.pdf';

        $previewPath = null;
        $conversionResult = $this->imageToPdfConversionService->convertImageToPdf(
            $tmpName,
            $pdfPath,
            'letter',
            $previewPath,
        );

        if (!$conversionResult->success) {
            return new ServiceResult(false, $conversionResult->reason);
        }

        $originalSize = $fileSize;
        $convertedSize = filesize($pdfPath);

        $gatheringEndDate = Date::parse($gathering->end_date->format('Y-m-d'));
        $retentionResult = $this->retentionPolicyService->calculateRetentionDate(
            $waiverType->retention_policy,
            $gatheringEndDate,
            Date::now(),
        );

        if (!$retentionResult->success) {
            @unlink($pdfPath);
            if ($previewPath !== null && file_exists($previewPath)) {
                @unlink($previewPath);
            }

            return $retentionResult;
        }

        $retentionDate = $retentionResult->getData();

        $gatheringWaiver = $GatheringWaivers->newEntity([
            'gathering_id' => $gathering->id,
            'waiver_type_id' => $waiverType->id,
            'member_id' => $memberId,
            'document_id' => null,
            'retention_date' => $retentionDate,
            'status' => 'active',
            'notes' => $notes,
        ]);

        if (!$GatheringWaivers->save($gatheringWaiver, ['checkRules' => false])) {
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

        $uploadedPdf = new UploadedFile(
            $pdfPath,
            $convertedSize,
            UPLOAD_ERR_OK,
            $fileName,
            'application/pdf',
        );

        try {
            $documentResult = $this->documentService->createDocument(
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
                'waivers',
                ['pdf'],
                $previewPath,
            );
        } catch (Throwable $exception) {
            @unlink($pdfPath);
            if ($previewPath !== null && file_exists($previewPath)) {
                @unlink($previewPath);
            }

            $GatheringWaivers->delete($gatheringWaiver);
            Log::error('Document creation threw an exception', [
                'gathering_waiver_id' => $gatheringWaiver->id,
                'error' => $exception->getMessage(),
            ]);

            return new ServiceResult(false, __('Failed to save document: {0}', $exception->getMessage()));
        }

        @unlink($pdfPath);

        if (!$documentResult->success) {
            if ($previewPath !== null && file_exists($previewPath)) {
                @unlink($previewPath);
            }
            Log::error('Document service returned failure', [
                'gathering_waiver_id' => $gatheringWaiver->id,
                'error' => $documentResult->getError(),
            ]);
            $GatheringWaivers->delete($gatheringWaiver);

            return new ServiceResult(false, 'Failed to save document: ' . $documentResult->getError());
        }

        $documentId = (int)$documentResult->getData();

        $gatheringWaiver->document_id = $documentId;
        if (!$GatheringWaivers->save($gatheringWaiver)) {
            $this->documentService->deleteDocument($documentId);
            $GatheringWaivers->delete($gatheringWaiver);

            return new ServiceResult(false, 'Failed to update waiver with document ID');
        }

        return new ServiceResult(true, null, [
            'waiver_id' => $gatheringWaiver->id,
            'document_id' => $documentId,
        ]);
    }

    /**
     * Process multiple waiver images into a single multi-page PDF.
     *
     * @param array $uploadedFiles Array of UploadedFileInterface objects
     * @param mixed $gathering Gathering entity
     * @param mixed $waiverType WaiverType entity
     * @param string $notes Notes for the waiver
     * @param string|null $clientThumbnail Client-generated thumbnail data URL
     * @param int $currentUserId Current user ID for uploaded_by
     * @return \App\Services\ServiceResult
     */
    public function processMultipleWaiverImages(
        array $uploadedFiles,
        $gathering,
        $waiverType,
        string $notes,
        ?string $clientThumbnail,
        int $currentUserId,
    ): ServiceResult {
        $GatheringWaivers = TableRegistry::getTableLocator()->get('Waivers.GatheringWaivers');

        Log::debug('processMultipleWaiverImages called', [
            'uploadedFiles_count' => count($uploadedFiles),
            'gathering_id' => $gathering->id,
            'waiverType_id' => $waiverType->id,
            'notes_length' => strlen($notes),
            'has_client_thumbnail' => $clientThumbnail !== null,
        ]);

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

            if ($index === 0) {
                $originalFilename = pathinfo($uploadedFile->getClientFilename(), PATHINFO_FILENAME) . '.pdf';
            }
        }

        $tmpDir = sys_get_temp_dir();
        $uniqueId = uniqid('waiver_multipage_', true);
        $pdfPath = $tmpDir . DIRECTORY_SEPARATOR . $uniqueId . '.pdf';

        $previewPath = null;
        $conversionResult = $this->imageToPdfConversionService->convertMixedToPdf(
            $fileInfos,
            $pdfPath,
            'letter',
            $previewPath,
        );

        if (!$conversionResult->success) {
            return new ServiceResult(false, $conversionResult->reason);
        }

        $convertedSize = filesize($pdfPath);
        $pageCount = $conversionResult->data['page_count'] ?? count($uploadedFiles);
        $firstFileIsImage = $conversionResult->data['first_file_is_image'] ?? false;
        $skippedFiles = $conversionResult->data['skipped_files'] ?? [];

        if (!empty($skippedFiles)) {
            Log::warning('Some PDF files were skipped during waiver upload due to unsupported compression', [
                'skipped_count' => count($skippedFiles),
                'processed_pages' => $pageCount,
            ]);
        }

        // Use client-generated thumbnail only if first file was a PDF
        if (!$firstFileIsImage && $clientThumbnail !== null && str_starts_with($clientThumbnail, 'data:image/')) {
            $parts = explode(',', $clientThumbnail, 2);
            if (count($parts) === 2) {
                $imageData = base64_decode($parts[1]);
                if ($imageData !== false) {
                    $clientThumbPath = $tmpDir . DIRECTORY_SEPARATOR . $uniqueId . '_thumb.png';
                    if (file_put_contents($clientThumbPath, $imageData) !== false) {
                        if ($previewPath !== null && file_exists($previewPath)) {
                            @unlink($previewPath);
                        }
                        $previewPath = $clientThumbPath;
                        Log::debug('Using client-generated thumbnail for PDF');
                    }
                }
            }
        }

        $gatheringEndDate = Date::parse($gathering->end_date->format('Y-m-d'));
        $retentionResult = $this->retentionPolicyService->calculateRetentionDate(
            $waiverType->retention_policy,
            $gatheringEndDate,
            Date::now(),
        );

        if (!$retentionResult->success) {
            @unlink($pdfPath);
            if ($previewPath !== null && file_exists($previewPath)) {
                @unlink($previewPath);
            }

            return $retentionResult;
        }

        $retentionDate = $retentionResult->getData();

        $gatheringWaiver = $GatheringWaivers->newEntity([
            'gathering_id' => $gathering->id,
            'waiver_type_id' => $waiverType->id,
            'document_id' => null,
            'retention_date' => $retentionDate,
            'status' => 'active',
            'notes' => $notes,
        ]);

        if (!$GatheringWaivers->save($gatheringWaiver, ['checkRules' => false])) {
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

        $uploadedPdf = new UploadedFile(
            $pdfPath,
            $convertedSize,
            UPLOAD_ERR_OK,
            $originalFilename,
            'application/pdf',
        );

        try {
            $documentResult = $this->documentService->createDocument(
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
                'waivers',
                ['pdf'],
                $previewPath,
            );
        } catch (Throwable $exception) {
            @unlink($pdfPath);
            if ($previewPath !== null && file_exists($previewPath)) {
                @unlink($previewPath);
            }

            $GatheringWaivers->delete($gatheringWaiver);
            Log::error('Document creation threw an exception (multi-image)', [
                'gathering_waiver_id' => $gatheringWaiver->id,
                'error' => $exception->getMessage(),
            ]);

            return new ServiceResult(false, __('Failed to save document: {0}', $exception->getMessage()));
        }

        @unlink($pdfPath);

        if (!$documentResult->success) {
            if ($previewPath !== null && file_exists($previewPath)) {
                @unlink($previewPath);
            }
            Log::error('Document service returned failure (multi-image)', [
                'gathering_waiver_id' => $gatheringWaiver->id,
                'error' => $documentResult->getError(),
            ]);
            $GatheringWaivers->delete($gatheringWaiver);

            return new ServiceResult(false, 'Failed to save document: ' . $documentResult->getError());
        }

        $documentId = (int)$documentResult->getData();

        $gatheringWaiver->document_id = $documentId;
        if (!$GatheringWaivers->save($gatheringWaiver)) {
            $this->documentService->deleteDocument($documentId);
            $GatheringWaivers->delete($gatheringWaiver);

            return new ServiceResult(false, 'Failed to update waiver with document ID');
        }

        $resultData = [
            'waiver_id' => $gatheringWaiver->id,
            'document_id' => $documentId,
            'page_count' => $pageCount,
        ];

        if (!empty($skippedFiles)) {
            $resultData['skipped_files'] = $skippedFiles;
            $fileList = implode(', ', $skippedFiles);
            $resultData['warning'] = __('The following file(s) could not be processed due to unsupported PDF compression and were skipped: {0}', $fileList);
        }

        return new ServiceResult(true, null, $resultData);
    }

    /**
     * Get a document download response for a waiver.
     *
     * @param int $waiverId Waiver ID
     * @return \Cake\Http\Response|null
     * @throws \Cake\Http\Exception\NotFoundException
     */
    public function getDownloadResponse(int $waiverId): ?Response
    {
        $GatheringWaivers = TableRegistry::getTableLocator()->get('Waivers.GatheringWaivers');
        $gatheringWaiver = $GatheringWaivers->get($waiverId, [
            'contain' => ['Documents', 'Gatherings'],
        ]);

        if (!$gatheringWaiver->document) {
            return null;
        }

        return $this->documentService->getDocumentDownloadResponse(
            $gatheringWaiver->document,
            'waiver_' . $gatheringWaiver->id . '.pdf',
        );
    }

    /**
     * Get an inline PDF response for a waiver.
     *
     * @param int $waiverId Waiver ID
     * @return \Cake\Http\Response|null
     */
    public function getInlinePdfResponse(int $waiverId): ?Response
    {
        $GatheringWaivers = TableRegistry::getTableLocator()->get('Waivers.GatheringWaivers');
        $gatheringWaiver = $GatheringWaivers->get($waiverId, [
            'contain' => ['Documents', 'Gatherings'],
        ]);

        if (!$gatheringWaiver->document) {
            return null;
        }

        return $this->documentService->getDocumentInlineResponse(
            $gatheringWaiver->document,
            'waiver_' . $gatheringWaiver->id . '.pdf',
        );
    }

    /**
     * Get a preview image response for a waiver.
     *
     * @param int $waiverId Waiver ID
     * @return \Cake\Http\Response|null
     */
    public function getPreviewResponse(int $waiverId): ?Response
    {
        $GatheringWaivers = TableRegistry::getTableLocator()->get('Waivers.GatheringWaivers');
        $gatheringWaiver = $GatheringWaivers->get($waiverId, [
            'contain' => ['Documents', 'Gatherings'],
        ]);

        if (!$gatheringWaiver->document) {
            return null;
        }

        return $this->documentService->getDocumentPreviewResponse($gatheringWaiver->document);
    }

    /**
     * Check if a document preview exists for a waiver.
     *
     * @param mixed $document Document entity
     * @return bool
     */
    public function documentPreviewExists($document): bool
    {
        return $this->documentService->documentPreviewExists($document);
    }

    /**
     * Delete a document by ID.
     *
     * @param int $documentId Document ID
     * @return void
     */
    public function deleteDocument(int $documentId): void
    {
        $this->documentService->deleteDocument($documentId);
    }

    /**
     * Get required waiver types for a gathering based on selected activities.
     *
     * @param mixed $gathering Gathering entity with gathering_activities loaded
     * @return array Array of waiver type entities
     */
    public function getRequiredWaiverTypes($gathering): array
    {
        if (empty($gathering->gathering_activities)) {
            return [];
        }

        $activityIds = array_column($gathering->gathering_activities, 'id');

        $GatheringActivityWaivers = TableRegistry::getTableLocator()->get('Waivers.GatheringActivityWaivers');
        $activityWaivers = $GatheringActivityWaivers->find()
            ->where(['GatheringActivityWaivers.gathering_activity_id IN' => $activityIds])
            ->contain(['WaiverTypes'])
            ->all();

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
     * Check if a waiver type has been attested as not needed for a gathering.
     *
     * @param int $gatheringId Gathering ID
     * @param int $waiverTypeId Waiver type ID
     * @return bool
     */
    public function isWaiverTypeAttested(int $gatheringId, int $waiverTypeId): bool
    {
        $GatheringWaivers = TableRegistry::getTableLocator()->get('Waivers.GatheringWaivers');

        return $GatheringWaivers->find()
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
     * Build a summary of existing waiver uploads and attestations for a gathering.
     *
     * @param int $gatheringId Gathering ID
     * @param array $requiredWaiverTypes Required waiver types
     * @return array Summary of waiver status per type
     */
    public function getWaiverStatusSummary(int $gatheringId, array $requiredWaiverTypes): array
    {
        if (empty($requiredWaiverTypes)) {
            return [];
        }

        $GatheringWaivers = TableRegistry::getTableLocator()->get('Waivers.GatheringWaivers');

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

        $existingWaivers = $GatheringWaivers->find()
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
     * Get upload limits from PHP configuration.
     *
     * @return array Upload limits with maxFileSize and formatted values
     */
    public function getUploadLimits(): array
    {
        $uploadMax = $this->parsePhpSize(ini_get('upload_max_filesize'));
        $postMax = $this->parsePhpSize(ini_get('post_max_size'));
        $maxFileSize = min($uploadMax, $postMax);

        return [
            'maxFileSize' => $maxFileSize,
            'maxFileSizeMB' => round($maxFileSize / 1024 / 1024, 2),
            'formatted' => $this->formatBytes($maxFileSize),
            'uploadMaxFilesize' => $uploadMax,
            'postMaxSize' => $postMax,
        ];
    }

    /**
     * Parse PHP size notation to bytes.
     *
     * @param string $size Size string from PHP ini setting
     * @return int Size in bytes
     */
    private function parsePhpSize(string $size): int
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
     * Format bytes to human-readable size.
     *
     * @param int $bytes Size in bytes
     * @param int $precision Decimal precision
     * @return string Formatted size string
     */
    private function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        $unitCount = count($units);

        for ($i = 0; $bytes > 1024 && $i < $unitCount - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, $precision) . ' ' . $units[$i];
    }
}
