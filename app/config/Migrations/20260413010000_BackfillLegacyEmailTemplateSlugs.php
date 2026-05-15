<?php

declare(strict_types=1);

use App\Migrations\CrossEngineMigrationTrait;
use Migrations\BaseMigration;

/**
 * Backfill workflow-native slugs onto legacy mailer/action email template rows.
 *
 * Earlier migrations seeded new slugged rows for workflow-native templates. That
 * preserved content, but left the long-lived legacy rows blank in the admin UI.
 * This migration makes the existing mailer/action rows canonical by applying the
 * slug and metadata in place, then removing the duplicate seeded rows for the
 * same slug/kingdom scope.
 */
class BackfillLegacyEmailTemplateSlugs extends BaseMigration
{
    use CrossEngineMigrationTrait;

    /**
     * @return void
     */
    public function up(): void
    {
        $now = date('Y-m-d H:i:s');

        foreach ($this->buildMappings() as $mapping) {
            $legacyRows = $this->fetchAll(
                "SELECT id
                 FROM email_templates
                 WHERE mailer_class = '" . $this->sqlEscape($mapping['mailer_class']) . "'
                   AND action_method = '" . $this->sqlEscape($mapping['action_method']) . "'
                   AND (slug IS NULL OR slug = '')",
            );

            foreach ($legacyRows as $legacyRow) {
                $duplicateId = $this->findDuplicateSlugRowId(
                    $mapping['slug'],
                    (int)$legacyRow['id'],
                );

                if ($duplicateId !== null) {
                    $this->execute("DELETE FROM email_templates WHERE id = {$duplicateId}");
                }

                $this->execute(
                    "UPDATE email_templates
                     SET slug = '" . $this->sqlEscape($mapping['slug']) . "',
                         name = CASE
                             WHEN name IS NULL OR name = '' THEN '" . $this->sqlEscape($mapping['name']) . "'
                             ELSE name
                         END,
                         description = CASE
                             WHEN description IS NULL OR description = '' THEN '" . $this->sqlEscape($mapping['description']) . "'
                             ELSE description
                         END,
                         available_vars = '" . $this->sqlEscape(json_encode($mapping['available_vars'])) . "',
                         variables_schema = '" . $this->sqlEscape(json_encode($mapping['variables_schema'])) . "',
                         modified = '{$now}',
                         modified_by = 1
                     WHERE id = " . (int)$legacyRow['id'],
                );
            }
        }
    }

    /**
     * @return void
     */
    public function down(): void
    {
        foreach ($this->buildMappings() as $mapping) {
            $this->execute(
                "UPDATE email_templates
                 SET slug = NULL
                 WHERE mailer_class = '" . $this->sqlEscape($mapping['mailer_class']) . "'
                   AND action_method = '" . $this->sqlEscape($mapping['action_method']) . "'
                   AND slug = '" . $this->sqlEscape($mapping['slug']) . "'",
            );
        }
    }

    /**
     * @param string $slug
     * @param int $legacyId
     * @return int|null
     */
    private function findDuplicateSlugRowId(string $slug, int $legacyId): ?int
    {
        $row = $this->fetchRow(
            "SELECT id
             FROM email_templates
             WHERE slug = '" . $this->sqlEscape($slug) . "'
               AND id != {$legacyId}
             ORDER BY id ASC
             LIMIT 1",
        );

        return $row ? (int)$row['id'] : null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildMappings(): array
    {
        return [
            [
                'mailer_class' => 'App\Mailer\KMPMailer',
                'action_method' => 'resetPassword',
                'slug' => 'password-reset',
                'name' => 'Password Reset',
                'description' => 'Sent when a member requests a password reset link.',
                'available_vars' => ['email', 'passwordResetUrl', 'siteAdminSignature'],
                'variables_schema' => [
                    'email' => ['type' => 'string', 'label' => 'Recipient Email', 'required' => true],
                    'passwordResetUrl' => ['type' => 'string', 'label' => 'Password Reset URL', 'required' => true],
                    'siteAdminSignature' => ['type' => 'string', 'label' => 'Site Admin Signature'],
                ],
            ],
            [
                'mailer_class' => 'App\Mailer\KMPMailer',
                'action_method' => 'mobileCard',
                'slug' => 'mobile-card-url',
                'name' => 'Mobile Card URL',
                'description' => 'Sent when a member requests their mobile card link.',
                'available_vars' => ['mobileCardUrl', 'siteAdminSignature'],
                'variables_schema' => [
                    'mobileCardUrl' => ['type' => 'string', 'label' => 'Mobile Card URL', 'required' => true],
                    'siteAdminSignature' => ['type' => 'string', 'label' => 'Site Admin Signature'],
                ],
            ],
            [
                'mailer_class' => 'App\Mailer\KMPMailer',
                'action_method' => 'newRegistration',
                'slug' => 'member-registration-welcome',
                'name' => 'Member Registration Welcome',
                'description' => 'Welcome email sent to a newly registered adult member with a password-reset link.',
                'available_vars' => ['memberScaName', 'passwordResetUrl', 'portalName', 'siteAdminSignature'],
                'variables_schema' => [
                    'memberScaName' => ['type' => 'string', 'label' => 'Member SCA Name', 'required' => true],
                    'passwordResetUrl' => ['type' => 'string', 'label' => 'Password Reset URL', 'required' => true],
                    'portalName' => ['type' => 'string', 'label' => 'Portal Name'],
                    'siteAdminSignature' => ['type' => 'string', 'label' => 'Site Admin Signature'],
                ],
            ],
            [
                'mailer_class' => 'App\Mailer\KMPMailer',
                'action_method' => 'notifySecretaryOfNewMember',
                'slug' => 'member-registration-secretary',
                'name' => 'New Member Secretary Notification',
                'description' => 'Sent to the kingdom secretary when a new adult member registers.',
                'available_vars' => ['memberScaName', 'memberCardPresent', 'memberViewUrl', 'siteAdminSignature'],
                'variables_schema' => [
                    'memberScaName' => ['type' => 'string', 'label' => 'Member SCA Name', 'required' => true],
                    'memberCardPresent' => ['type' => 'string', 'label' => 'Membership Card Status', 'required' => true],
                    'memberViewUrl' => ['type' => 'string', 'label' => 'Member Profile URL', 'required' => true],
                    'siteAdminSignature' => ['type' => 'string', 'label' => 'Site Admin Signature'],
                ],
            ],
            [
                'mailer_class' => 'App\Mailer\KMPMailer',
                'action_method' => 'notifySecretaryOfNewMinorMember',
                'slug' => 'member-registration-secretary-minor',
                'name' => 'New Minor Member Secretary Notification',
                'description' => 'Sent to the kingdom secretary when a new minor member registers.',
                'available_vars' => ['memberScaName', 'memberCardPresent', 'memberViewUrl', 'siteAdminSignature'],
                'variables_schema' => [
                    'memberScaName' => ['type' => 'string', 'label' => 'Member SCA Name', 'required' => true],
                    'memberCardPresent' => ['type' => 'string', 'label' => 'Membership Card Status', 'required' => true],
                    'memberViewUrl' => ['type' => 'string', 'label' => 'Member Profile URL', 'required' => true],
                    'siteAdminSignature' => ['type' => 'string', 'label' => 'Site Admin Signature'],
                ],
            ],
            [
                'mailer_class' => 'App\Mailer\KMPMailer',
                'action_method' => 'notifyOfWarrant',
                'slug' => 'warrant-issued',
                'name' => 'Warrant Issued',
                'description' => 'Sent when a warrant roster workflow activates a warrant for a member.',
                'available_vars' => ['memberScaName', 'warrantName', 'warrantStart', 'warrantExpires', 'siteAdminSignature'],
                'variables_schema' => [
                    'memberScaName' => ['type' => 'string', 'label' => 'Member SCA Name', 'required' => true],
                    'warrantName' => ['type' => 'string', 'label' => 'Warrant Name', 'required' => true],
                    'warrantStart' => ['type' => 'string', 'label' => 'Warrant Start Date', 'required' => true],
                    'warrantExpires' => ['type' => 'string', 'label' => 'Warrant Expiration Date', 'required' => true],
                    'siteAdminSignature' => ['type' => 'string', 'label' => 'Site Admin Signature'],
                ],
            ],
            [
                'mailer_class' => 'Officers\Mailer\OfficersMailer',
                'action_method' => 'notifyOfHire',
                'slug' => 'officer-hire-notification',
                'name' => 'Officer Hire Notification',
                'description' => 'Sent to a member when they are appointed to an office.',
                'available_vars' => [
                    'memberScaName',
                    'officeName',
                    'branchName',
                    'hireDate',
                    'endDate',
                    'requiresWarrantNotice',
                    'siteAdminSignature',
                ],
                'variables_schema' => [
                    'memberScaName' => ['type' => 'string', 'label' => 'Member SCA Name', 'required' => true],
                    'officeName' => ['type' => 'string', 'label' => 'Office Name', 'required' => true],
                    'branchName' => ['type' => 'string', 'label' => 'Branch Name', 'required' => true],
                    'hireDate' => ['type' => 'string', 'label' => 'Appointment Start Date', 'required' => true],
                    'endDate' => ['type' => 'string', 'label' => 'Appointment End Date', 'required' => true],
                    'requiresWarrantNotice' => ['type' => 'string', 'label' => 'Warrant Required Notice'],
                    'siteAdminSignature' => ['type' => 'string', 'label' => 'Site Admin Signature'],
                ],
            ],
            [
                'mailer_class' => 'Officers\Mailer\OfficersMailer',
                'action_method' => 'notifyOfRelease',
                'slug' => 'officer-release-notification',
                'name' => 'Officer Release Notification',
                'description' => 'Sent to a member when they are released from an office.',
                'available_vars' => ['memberScaName', 'officeName', 'branchName', 'reason', 'releaseDate', 'siteAdminSignature'],
                'variables_schema' => [
                    'memberScaName' => ['type' => 'string', 'label' => 'Member SCA Name', 'required' => true],
                    'officeName' => ['type' => 'string', 'label' => 'Office Name', 'required' => true],
                    'branchName' => ['type' => 'string', 'label' => 'Branch Name', 'required' => true],
                    'reason' => ['type' => 'string', 'label' => 'Release Reason', 'required' => true],
                    'releaseDate' => ['type' => 'string', 'label' => 'Release Date', 'required' => true],
                    'siteAdminSignature' => ['type' => 'string', 'label' => 'Site Admin Signature'],
                ],
            ],
            [
                'mailer_class' => 'Activities\Mailer\ActivitiesMailer',
                'action_method' => 'notifyApprover',
                'slug' => 'authorization-approval-request',
                'name' => 'Authorization Approval Request',
                'description' => 'Sent to the next approver when an authorization request needs review.',
                'available_vars' => ['authorizationResponseUrl', 'memberScaName', 'approverScaName', 'activityName', 'siteAdminSignature'],
                'variables_schema' => [
                    'authorizationResponseUrl' => ['type' => 'string', 'label' => 'Authorization Response URL', 'required' => true],
                    'memberScaName' => ['type' => 'string', 'label' => 'Member SCA Name', 'required' => true],
                    'approverScaName' => ['type' => 'string', 'label' => 'Approver SCA Name', 'required' => true],
                    'activityName' => ['type' => 'string', 'label' => 'Activity Name', 'required' => true],
                    'siteAdminSignature' => ['type' => 'string', 'label' => 'Site Admin Signature'],
                ],
            ],
            [
                'mailer_class' => 'Activities\Mailer\ActivitiesMailer',
                'action_method' => 'notifyRequester',
                'slug' => 'authorization-request-update',
                'name' => 'Authorization Request Update',
                'description' => 'Sent to the requester when an authorization request status changes.',
                'available_vars' => [
                    'memberScaName',
                    'approverScaName',
                    'status',
                    'activityName',
                    'memberCardUrl',
                    'nextApproverScaName',
                    'siteAdminSignature',
                ],
                'variables_schema' => [
                    'memberScaName' => ['type' => 'string', 'label' => 'Member SCA Name', 'required' => true],
                    'approverScaName' => ['type' => 'string', 'label' => 'Approver SCA Name', 'required' => true],
                    'status' => ['type' => 'string', 'label' => 'Authorization Status', 'required' => true],
                    'activityName' => ['type' => 'string', 'label' => 'Activity Name', 'required' => true],
                    'memberCardUrl' => ['type' => 'string', 'label' => 'Member Card URL', 'required' => true],
                    'nextApproverScaName' => ['type' => 'string', 'label' => 'Next Approver SCA Name'],
                    'siteAdminSignature' => ['type' => 'string', 'label' => 'Site Admin Signature'],
                ],
            ],
        ];
    }
}
