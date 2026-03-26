<?php
declare(strict_types=1);

namespace App\Services;

use App\Model\Entity\Member;
use Cake\Http\Response;
use Cake\Log\Log;
use Cake\ORM\Locator\LocatorAwareTrait;
use Cake\Routing\Router;
use finfo;
use Psr\Http\Message\UploadedFileInterface;
use RuntimeException;
use Throwable;

/**
 * Handles profile photo management for members.
 *
 * Covers photo validation, upload via DocumentService, old photo cleanup,
 * atomic removal, inline streaming, and mobile card email URL building.
 * Controller-layer concerns (request parsing, flash, authorization)
 * remain in MembersController.
 *
 * @property \App\Model\Table\MembersTable $Members
 */
class MemberProfileService
{
    use LocatorAwareTrait;

    /**
     * @var \App\Model\Table\MembersTable
     */
    private $Members;

    /**
     * Initialize the profile service.
     */
    public function __construct()
    {
        /** @var \App\Model\Table\MembersTable $members */
        $members = $this->fetchTable('Members');
        $this->Members = $members;
    }

    /**
     * Validate, upload, and assign a profile photo to a member.
     *
     * @param \App\Model\Entity\Member $member Target member entity with ProfilePhoto relation loaded.
     * @param \Psr\Http\Message\UploadedFileInterface $file Uploaded image file.
     * @param int $uploaderId ID of the member performing the upload.
     * @return array{success:bool,message:string,warning?:bool}
     */
    public function processProfilePhotoUpload(Member $member, UploadedFileInterface $file, int $uploaderId): array
    {
        $clientMediaType = (string)$file->getClientMediaType();
        if ($clientMediaType !== '' && !str_starts_with($clientMediaType, 'image/')) {
            return [
                'success' => false,
                'message' => (string)__('Invalid file type. Only PNG and JPEG images are allowed.'),
            ];
        }

        $ext = strtolower(pathinfo((string)$file->getClientFilename(), PATHINFO_EXTENSION));
        if (!in_array($ext, ['png', 'jpg', 'jpeg'], true)) {
            return [
                'success' => false,
                'message' => (string)__('Invalid file extension. Only .png, .jpg, .jpeg are allowed.'),
            ];
        }

        $tempPath = $file->getStream()->getMetadata('uri');
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $actualMimeType = $finfo->file($tempPath);
        if (!in_array($actualMimeType, ['image/png', 'image/jpeg'], true)) {
            return [
                'success' => false,
                'message' => (string)__('File content does not match an allowed image type.'),
            ];
        }

        $documentService = new DocumentService();
        $uploadResult = $documentService->createDocument(
            $file,
            'Members.ProfilePhoto',
            (int)$member->id,
            $uploaderId,
            ['type' => 'profile_photo'],
            'member-profile-photos',
            ['png', 'jpg', 'jpeg'],
        );
        if (!$uploadResult->success) {
            $reason = $uploadResult->reason ?? (string)__('Unable to upload profile photo.');

            return ['success' => false, 'message' => $reason];
        }

        $newDocumentId = (int)$uploadResult->data;
        $oldDocumentId = $member->profile_photo_document_id
            ? (int)$member->profile_photo_document_id
            : null;

        $member->profile_photo_document_id = $newDocumentId;
        if (!$this->Members->save($member)) {
            $documentService->deleteDocument($newDocumentId);

            return ['success' => false, 'message' => (string)__('Unable to save profile photo. Please try again.')];
        }

        if ($oldDocumentId && $oldDocumentId !== $newDocumentId) {
            $deleteResult = $documentService->deleteDocument($oldDocumentId);
            if (!$deleteResult->success) {
                return [
                    'success' => true,
                    'warning' => true,
                    'message' => (string)__(
                        'Profile photo updated, but old photo '
                        . 'cleanup failed.',
                    ),
                ];
            }
        }

        return ['success' => true, 'message' => (string)__('Profile photo updated.')];
    }

    /**
     * Remove a member's profile photo and underlying document atomically.
     *
     * @param \App\Model\Entity\Member $member Member with ProfilePhoto loaded.
     * @param int $oldDocumentId Document ID to delete.
     * @return array{success:bool,message:string}
     */
    public function removeProfilePhoto(Member $member, int $oldDocumentId): array
    {
        $connection = $this->Members->getConnection();
        try {
            $connection->transactional(function () use ($member, $oldDocumentId): void {
                $member->profile_photo_document_id = null;
                $this->Members->saveOrFail($member);

                $documentService = new DocumentService();
                $deleteResult = $documentService->deleteDocument($oldDocumentId);
                if (!$deleteResult->success) {
                    throw new RuntimeException((string)__('Unable to remove profile photo. Please try again.'));
                }
            });
        } catch (Throwable $e) {
            Log::error('Profile photo removal failed: ' . $e->getMessage());

            return ['success' => false, 'message' => (string)__('Unable to remove profile photo. Please try again.')];
        }

        return ['success' => true, 'message' => (string)__('Profile photo removed.')];
    }

    /**
     * Stream a profile photo document as an inline response.
     *
     * @param \App\Model\Entity\Member $member Member with ProfilePhoto loaded.
     * @param string $filenamePrefix Prefix for the download filename (e.g. 'member_profile_photo_').
     * @return \Cake\Http\Response|null Response with streamed content, or null if unavailable.
     */
    public function getProfilePhotoResponse(Member $member, string $filenamePrefix): ?Response
    {
        $documentService = new DocumentService();

        return $documentService->getDocumentInlineResponse(
            $member->profile_photo,
            $filenamePrefix . $member->id . '.jpg',
        );
    }

    /**
     * Build the mobile card URL for emailing to a member.
     *
     * @return string Absolute URL to the ViewMobileCard action.
     */
    public function buildMobileCardUrl(): string
    {
        return Router::url([
            'controller' => 'Members',
            'action' => 'ViewMobileCard',
            'plugin' => null,
            '_full' => true,
        ]);
    }
}
