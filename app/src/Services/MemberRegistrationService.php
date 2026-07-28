<?php
declare(strict_types=1);

namespace App\Services;

use App\KMP\StaticHelpers;
use App\Model\Entity\Document;
use App\Model\Entity\Member;
use Cake\Core\Configure;
use Cake\Http\Response;
use Cake\I18n\DateTime;
use Cake\Log\Log;
use Cake\ORM\Locator\LocatorAwareTrait;
use Cake\Routing\Router;
use Exception;
use finfo;
use Psr\Http\Message\UploadedFileInterface;

/**
 * Handles member registration and SCA membership card submission.
 *
 * Covers file upload validation, image scaling, age-based status assignment,
 * token generation, and building email notification variables.
 * Controller-layer concerns (request parsing, flash, redirect) remain in MembersController.
 *
 * @property \App\Model\Table\MembersTable $Members
 */
class MemberRegistrationService
{
    use LocatorAwareTrait;

    /**
     * @var \App\Model\Table\MembersTable
     */
    private $Members;

    private DocumentService $documentService;

    /**
     * Initialize the registration service.
     */
    public function __construct(?DocumentService $documentService = null)
    {
        /** @var \App\Model\Table\MembersTable $members */
        $members = $this->fetchTable('Members');
        $this->Members = $members;
        $this->documentService = $documentService ?? new DocumentService();
    }

    /**
     * Validate and store an uploaded membership card image.
     *
     * @return array{success:bool,message?:string,fileName?:string,documentId?:int}
     */
    public function processCardUpload(
        UploadedFileInterface $file,
        int $memberId,
        int $uploaderId,
    ): array {
        return $this->storeMembershipCard($file, $memberId, $uploaderId);
    }

    /**
     * Validate and persist an updated SCA membership card.
     *
     * @return array{success:bool,message?:string,fileName?:string,documentId?:int}
     */
    public function processScaCardUpload(
        UploadedFileInterface $file,
        int $memberId,
        int $uploaderId,
    ): array {
        return $this->storeMembershipCard($file, $memberId, $uploaderId);
    }

    /**
     * Stream a membership card from persistent storage or the legacy local path.
     */
    public function getMembershipCardResponse(Member $member): ?Response
    {
        if ($member->membership_card instanceof Document) {
            return $this->documentService->getImageThumbnailInlineResponse(
                $member->membership_card,
                'membership_card_' . $member->id . '.jpg',
            );
        }

        $legacyPath = (string)$member->membership_card_path;
        if ($legacyPath === '' || basename($legacyPath) !== $legacyPath) {
            return null;
        }

        $storageConfig = (array)Configure::read('Documents.storage.local', []);
        $basePath = (string)($storageConfig['path'] ?? WWW_ROOT . '../images/uploaded/');
        $fullPath = realpath(rtrim($basePath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $legacyPath);
        $resolvedBasePath = realpath($basePath);
        if (
            $fullPath === false
            || $resolvedBasePath === false
            || !is_file($fullPath)
            || !str_starts_with($fullPath, rtrim($resolvedBasePath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR)
        ) {
            return null;
        }

        return (new Response())->withFile($fullPath, [
            'download' => false,
            'name' => 'membership_card_' . $member->id . '.' . pathinfo($legacyPath, PATHINFO_EXTENSION),
        ]);
    }

    /**
     * Delete a membership card after its member record no longer references it.
     *
     * @return array{success:bool,message?:string}
     */
    public function deleteMembershipCard(?int $documentId, ?string $legacyPath): array
    {
        if ($documentId !== null) {
            $result = $this->documentService->deleteDocument($documentId);

            return $result->success
                ? ['success' => true]
                : [
                    'success' => false,
                    'message' => $result->reason ?? (string)__('Unable to delete membership card.'),
                ];
        }

        if ($legacyPath === null || $legacyPath === '') {
            return ['success' => true];
        }
        if (basename($legacyPath) !== $legacyPath) {
            Log::warning('Refused to delete unsafe legacy membership card path.');

            return ['success' => false, 'message' => (string)__('Unable to delete membership card.')];
        }

        $storageConfig = (array)Configure::read('Documents.storage.local', []);
        $basePath = (string)($storageConfig['path'] ?? WWW_ROOT . '../images/uploaded/');
        $fullPath = rtrim($basePath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $legacyPath;
        if (!file_exists($fullPath)) {
            Log::warning('Legacy membership card file was already missing.', ['file' => $legacyPath]);

            return ['success' => true];
        }

        try {
            return StaticHelpers::deleteFile($fullPath)
                ? ['success' => true]
                : ['success' => false, 'message' => (string)__('Unable to delete membership card.')];
        } catch (Exception $e) {
            Log::warning('Failed to delete legacy membership card file.', [
                'file' => $legacyPath,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'message' => (string)__('Unable to delete membership card.')];
        }
    }

    /**
     * Validate image content and store it through tenant-aware document storage.
     *
     * @return array{success:bool,message?:string,fileName?:string,documentId?:int}
     */
    private function storeMembershipCard(
        UploadedFileInterface $file,
        int $memberId,
        int $uploaderId,
    ): array {
        if ($file->getError() !== UPLOAD_ERR_OK || $file->getSize() <= 0) {
            return ['success' => false, 'message' => (string)__('Please choose a membership card image.')];
        }

        $allowedTypes = ['image/png', 'image/jpeg', 'image/jpg', 'image/pjpeg'];
        $clientMediaType = $file->getClientMediaType();
        if (!in_array($clientMediaType, $allowedTypes, true)) {
            return [
                'success' => false,
                'message' => (string)__('Invalid file type. Only PNG and JPEG images are allowed.'),
            ];
        }

        $extension = strtolower(pathinfo((string)$file->getClientFilename(), PATHINFO_EXTENSION));
        if (!in_array($extension, ['png', 'jpg', 'jpeg'], true)) {
            return [
                'success' => false,
                'message' => (string)__('Invalid file extension. Only .png, .jpg, .jpeg are allowed.'),
            ];
        }

        $tempPath = $file->getStream()->getMetadata('uri');
        if (!is_string($tempPath) || !is_file($tempPath)) {
            return ['success' => false, 'message' => (string)__('Unable to read the uploaded image.')];
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $actualMimeType = $finfo->file($tempPath);
        if (!in_array($actualMimeType, ['image/png', 'image/jpeg'], true)) {
            return ['success' => false, 'message' => (string)__('File content does not match an allowed image type.')];
        }

        $result = $this->documentService->createDocument(
            $file,
            'Members.MembershipCard',
            $memberId,
            $uploaderId,
            ['type' => 'membership_card'],
            'member-cards',
            ['png', 'jpg', 'jpeg'],
            verifiedMimeType: $actualMimeType,
        );
        if (!$result->success) {
            return [
                'success' => false,
                'message' => $result->reason ?? (string)__('Error saving image, please try again.'),
            ];
        }

        $documentId = (int)$result->data;

        return [
            'success' => true,
            'documentId' => $documentId,
            'fileName' => 'document:' . $documentId,
        ];
    }

    /**
     * Assign registration fields to a new member entity based on age.
     *
     * @param \App\Model\Entity\Member $member New member entity.
     * @param array<string, mixed> $data Form data from the registration request.
     * @return void
     */
    public function applyRegistrationData(Member $member, array $data): void
    {
        $member->sca_name = $data['sca_name'] ?? null;
        $member->branch_id = $data['branch_id'] ?? null;
        $member->first_name = $data['first_name'] ?? null;
        $member->middle_name = $data['middle_name'] ?? null;
        $member->last_name = $data['last_name'] ?? null;
        $member->street_address = $data['street_address'] ?? null;
        $member->city = $data['city'] ?? null;
        $member->state = $data['state'] ?? null;
        $member->zip = $data['zip'] ?? null;
        $member->phone_number = $data['phone_number'] ?? null;
        $member->email_address = $data['email_address'] ?? null;
        $member->birth_month = (int)($data['birth_month'] ?? 0);
        $member->birth_year = (int)($data['birth_year'] ?? 0);
    }

    /**
     * Assign age-based status and generate tokens for a new member.
     *
     * @param \App\Model\Entity\Member $member Entity with birth fields already set.
     * @return void
     */
    public function assignStatusAndTokens(Member $member): void
    {
        if ($member->age > 17 && empty($member->password_token)) {
            $member->password_token = StaticHelpers::generateToken(32);
            $member->password_token_expires_on = DateTime::now()->addDays(1);
        }
        if (empty($member->password)) {
            $member->password = StaticHelpers::generateToken(12);
        }

        if ($member->age > 17) {
            $member->status = Member::STATUS_ACTIVE;
        } else {
            $member->status = Member::STATUS_UNVERIFIED_MINOR;
        }
    }

    /**
     * Save a new member entity.
     *
     * @param \App\Model\Entity\Member $member Entity to persist.
     * @return bool True on success.
     */
    public function saveMember(Member $member): bool
    {
        return (bool)$this->Members->save($member);
    }

    /**
     * Build the email variables for an adult registration notification.
     *
     * @param \App\Model\Entity\Member $member Newly registered member.
     * @return array{resetUrl:string,registrationVars:array<string,mixed>,secretaryVars:array<string,mixed>}
     */
    public function buildAdultRegistrationEmailVars(Member $member): array
    {
        $siteAdminSignature = StaticHelpers::getAppSetting('Email.SiteAdminSignature', '', null, true);
        $portalName = StaticHelpers::getAppSetting('KMP.LongSiteTitle', '', null, true);
        $resetUrl = '';
        if (!empty($member->password_token)) {
            $resetUrl = Router::url('/members/reset-password/' . $member->password_token, true);
        }

        $registrationVars = [
            'passwordResetUrl' => $resetUrl,
            'memberScaName' => $member->sca_name,
            'portalName' => $portalName,
            'siteAdminSignature' => $siteAdminSignature,
        ];

        $viewUrl = Router::url('/members/view/' . $member->id, true);

        $secretaryVars = [
            'memberViewUrl' => $viewUrl,
            'memberScaName' => $member->sca_name,
            'memberCardPresent' => !empty($member->membership_card_path) ? 'uploaded' : 'not uploaded',
            'siteAdminSignature' => $siteAdminSignature,
        ];

        return [
            'resetUrl' => $resetUrl,
            'registrationVars' => $registrationVars,
            'secretaryVars' => $secretaryVars,
        ];
    }

    /**
     * Build the email variables for a minor registration notification.
     *
     * @param \App\Model\Entity\Member $member Newly registered minor.
     * @return array<string, mixed>
     */
    public function buildMinorRegistrationEmailVars(Member $member): array
    {
        $siteAdminSignature = StaticHelpers::getAppSetting('Email.SiteAdminSignature', '', null, true);
        $viewUrl = Router::url('/members/view/' . $member->id, true);

        return [
            'memberViewUrl' => $viewUrl,
            'memberScaName' => $member->sca_name,
            'memberCardPresent' => !empty($member->membership_card_path) ? 'uploaded' : 'not uploaded',
            'siteAdminSignature' => $siteAdminSignature,
        ];
    }
}
