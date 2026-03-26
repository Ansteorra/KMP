<?php
declare(strict_types=1);

namespace App\Services;

use App\KMP\StaticHelpers;
use App\Model\Entity\Member;
use Cake\I18n\DateTime;
use Cake\ORM\Locator\LocatorAwareTrait;
use Cake\Routing\Router;
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

    /**
     * Initialize the registration service.
     */
    public function __construct()
    {
        /** @var \App\Model\Table\MembersTable $members */
        $members = $this->fetchTable('Members');
        $this->Members = $members;
    }

    /**
     * Validate and store an uploaded membership card image.
     *
     * @param \Psr\Http\Message\UploadedFileInterface $file Uploaded card image.
     * @return array{success:bool,message?:string,fileName?:string}
     */
    public function processCardUpload(UploadedFileInterface $file): array
    {
        $allowedTypes = ['image/png', 'image/jpeg', 'image/jpg', 'image/pjpeg'];
        $clientMediaType = $file->getClientMediaType();
        if (!in_array($clientMediaType, $allowedTypes)) {
            return [
                'success' => false,
                'message' => (string)__('Invalid file type. Only PNG and JPEG images are allowed.'),
            ];
        }

        $ext = strtolower(pathinfo((string)$file->getClientFilename(), PATHINFO_EXTENSION));
        if (!in_array($ext, ['png', 'jpg', 'jpeg'])) {
            return [
                'success' => false,
                'message' => (string)__('Invalid file extension. Only .png, .jpg, .jpeg are allowed.'),
            ];
        }

        $storageLoc = WWW_ROOT . '../images/uploaded/';
        $fileName = StaticHelpers::generateToken(10);
        StaticHelpers::ensureDirectoryExists($storageLoc, 0755);
        $file->moveTo(WWW_ROOT . '../images/uploaded/' . $fileName);
        $fileResult = StaticHelpers::saveScaledImage($fileName, 500, 700, $storageLoc, $storageLoc);
        if (!$fileResult) {
            return ['success' => false, 'message' => (string)__('Error saving image, please try again.')];
        }
        $fileName = substr($fileResult, strrpos($fileResult, '/') + 1);

        return ['success' => true, 'fileName' => $fileName];
    }

    /**
     * Validate an SCA membership card upload with server-side content verification.
     *
     * @param \Psr\Http\Message\UploadedFileInterface $file Uploaded card image.
     * @return array{success:bool,message?:string,fileName?:string}
     */
    public function processScaCardUpload(UploadedFileInterface $file): array
    {
        $allowedTypes = ['image/png', 'image/jpeg', 'image/jpg', 'image/pjpeg'];
        $clientMediaType = $file->getClientMediaType();
        if (!in_array($clientMediaType, $allowedTypes)) {
            return [
                'success' => false,
                'message' => (string)__('Invalid file type. Only PNG and JPEG images are allowed.'),
            ];
        }

        $ext = strtolower(pathinfo((string)$file->getClientFilename(), PATHINFO_EXTENSION));
        if (!in_array($ext, ['png', 'jpg', 'jpeg'])) {
            return [
                'success' => false,
                'message' => (string)__('Invalid file extension. Only .png, .jpg, .jpeg are allowed.'),
            ];
        }

        $tempPath = $file->getStream()->getMetadata('uri');
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $actualMimeType = $finfo->file($tempPath);
        if (!in_array($actualMimeType, ['image/png', 'image/jpeg'])) {
            return ['success' => false, 'message' => (string)__('File content does not match an allowed image type.')];
        }

        $storageLoc = WWW_ROOT . '../images/uploaded/';
        $fileName = StaticHelpers::generateToken(10);
        StaticHelpers::ensureDirectoryExists($storageLoc, 0755);
        $file->moveTo(WWW_ROOT . '../images/uploaded/' . $fileName);
        $fileResult = StaticHelpers::saveScaledImage($fileName, 500, 700, $storageLoc, $storageLoc);
        if (!$fileResult) {
            return ['success' => false, 'message' => (string)__('Error saving image, please try again.')];
        }
        $fileName = substr($fileResult, strrpos($fileResult, '/') + 1);

        return ['success' => true, 'fileName' => $fileName];
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
        if ($member->age > 17) {
            $member->password_token = StaticHelpers::generateToken(32);
            $member->password_token_expires_on = DateTime::now()->addDays(1);
        }
        $member->password = StaticHelpers::generateToken(12);

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
        $resetUrl = Router::url([
            'controller' => 'Members',
            'action' => 'resetPassword',
            'plugin' => null,
            '_full' => true,
            $member->password_token,
        ]);

        $registrationVars = [
            'url' => $resetUrl,
            'sca_name' => $member->sca_name,
        ];

        $viewUrl = Router::url([
            'controller' => 'Members',
            'action' => 'view',
            'plugin' => null,
            '_full' => true,
            $member->id,
        ]);

        $secretaryVars = [
            'url' => $viewUrl,
            'sca_name' => $member->sca_name,
            'membershipCardPresent' => !empty($member->membership_card_path),
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
        $viewUrl = Router::url([
            'controller' => 'Members',
            'action' => 'view',
            'plugin' => null,
            '_full' => true,
            $member->id,
        ]);

        return [
            'url' => $viewUrl,
            'sca_name' => $member->sca_name,
            'membershipCardPresent' => !empty($member->membership_card_path),
        ];
    }
}
