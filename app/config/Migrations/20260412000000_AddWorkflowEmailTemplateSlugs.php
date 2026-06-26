<?php

declare(strict_types=1);

use App\Migrations\CrossEngineMigrationTrait;
use Migrations\BaseMigration;

/**
 * Seed stable-slug email templates required by migrated workflow definitions.
 *
 * Creates one DB email template per workflow-native slug (idempotent — skips
 * any slug that already exists). Content is ported from the corresponding PHP
 * view templates and converted to {{var}} substitution syntax so they are
 * editable by kingdom admins without a code deploy.
 *
 * Slugs created:
 *   - award-recommendation-submitted
 *   - member-registration-welcome
 *   - member-registration-secretary
 *   - member-registration-secretary-minor
 *   - officer-hire-notification
 *   - officer-release-notification
 */
class AddWorkflowEmailTemplateSlugs extends BaseMigration
{
    use CrossEngineMigrationTrait;

    /**
     * @return void
     */
    public function up(): void
    {
        $now = date('Y-m-d H:i:s');

        $templates = $this->buildTemplates($now);

        foreach ($templates as $tpl) {
            $existing = $this->findExistingTemplate($tpl);
            if ($existing) {
                $this->execute(
                    "UPDATE email_templates
                    SET slug = '" . $this->sqlEscape($tpl['slug']) . "',
                        name = '" . $this->sqlEscape($tpl['name']) . "',
                        description = '" . $this->sqlEscape($tpl['description']) . "',
                        subject_template = '" . $this->sqlEscape($tpl['subject_template']) . "',
                        text_template = '" . $this->sqlEscape($tpl['text_template']) . "',
                        html_template = NULL,
                        available_vars = '" . $this->sqlEscape(json_encode($tpl['available_vars'])) . "',
                        variables_schema = '" . $this->sqlEscape(json_encode($tpl['variables_schema'])) . "',
                        is_active = TRUE,
                        modified = '{$now}',
                        modified_by = 1
                    WHERE id = " . (int)$existing['id'],
                );

                continue;
            }

            $this->execute(
                "INSERT INTO email_templates
                    (slug, name, description, mailer_class, action_method,
                     subject_template, text_template, html_template,
                     available_vars, variables_schema, is_active,
                      created, modified, created_by, modified_by)
                 VALUES (
                     '" . $this->sqlEscape($tpl['slug']) . "',
                     '" . $this->sqlEscape($tpl['name']) . "',
                     '" . $this->sqlEscape($tpl['description']) . "',
                     " . ($tpl['mailer_class'] ? "'" . $this->sqlEscape($tpl['mailer_class']) . "'" : 'NULL') . ",
                     " . ($tpl['action_method'] ? "'" . $this->sqlEscape($tpl['action_method']) . "'" : 'NULL') . ",
                     '" . $this->sqlEscape($tpl['subject_template']) . "',
                     '" . $this->sqlEscape($tpl['text_template']) . "',
                     NULL,
                     '" . $this->sqlEscape(json_encode($tpl['available_vars'])) . "',
                     '" . $this->sqlEscape(json_encode($tpl['variables_schema'])) . "',
                     TRUE,
                      '{$now}', '{$now}', 1, 1
                 )",
            );
        }
    }

    /**
     * Find a template by new slug or by legacy mailer/action identity.
     *
     * @param array<string, mixed> $tpl Template definition
     * @return array<string, mixed>|false
     */
    private function findExistingTemplate(array $tpl): array|false
    {
        $existing = $this->fetchRow(
            "SELECT id FROM email_templates WHERE slug = '" . $this->sqlEscape($tpl['slug']) . "'",
        );
        if ($existing) {
            return $existing;
        }

        if (empty($tpl['mailer_class']) || empty($tpl['action_method'])) {
            return false;
        }

        return $this->fetchRow(
            "SELECT id FROM email_templates
            WHERE mailer_class = '" . $this->sqlEscape($tpl['mailer_class']) . "'
                AND action_method = '" . $this->sqlEscape($tpl['action_method']) . "'
            ORDER BY id ASC
            LIMIT 1",
        );
    }

    /**
     * @return void
     */
    public function down(): void
    {
        $slugs = [
            'award-recommendation-submitted',
            'member-registration-welcome',
            'member-registration-secretary',
            'member-registration-secretary-minor',
            'officer-hire-notification',
            'officer-release-notification',
        ];

        foreach ($slugs as $slug) {
            $this->execute(
                "DELETE FROM email_templates WHERE slug = '" . $this->sqlEscape($slug) . "'",
            );
        }
    }

    /**
     * @param string $now Current timestamp for created/modified
     * @return array<int, array<string, mixed>>
     */
    private function buildTemplates(string $now): array
    {
        return [
            [
                'slug' => 'award-recommendation-submitted',
                'name' => 'Award Recommendation Submitted',
                'description' => 'Sent to the Crown when a new award recommendation is submitted.',
                'mailer_class' => 'App\Mailer\KMPMailer',
                'action_method' => 'sendFromTemplate',
                'subject_template' => 'Award Recommendation: {{awardName}} for {{memberScaName}}',
                'text_template' =>
                    "A new award recommendation has been submitted.\n\n" .
                    "Nominee: {{memberScaName}}\n" .
                    "Award: {{awardName}}\n" .
                    "Reason: {{reason}}\n" .
                    "Contact Email: {{contactEmail}}\n\n" .
                    "Thank you\n{{siteAdminSignature}}.",
                'available_vars' => ['memberScaName', 'awardName', 'reason', 'contactEmail', 'siteAdminSignature'],
                'variables_schema' => [
                    'memberScaName' => ['type' => 'string', 'label' => 'Nominee SCA Name', 'required' => true],
                    'awardName' => ['type' => 'string', 'label' => 'Award Name', 'required' => true],
                    'reason' => ['type' => 'string', 'label' => 'Reason for Recommendation', 'required' => true],
                    'contactEmail' => ['type' => 'string', 'label' => 'Contact Email', 'required' => true],
                    'siteAdminSignature' => ['type' => 'string', 'label' => 'Site Admin Signature'],
                ],
            ],
            [
                'slug' => 'member-registration-welcome',
                'name' => 'Member Registration Welcome',
                'description' => 'Welcome email sent to a newly registered adult member with a password-reset link.',
                'mailer_class' => 'App\Mailer\KMPMailer',
                'action_method' => 'newRegistration',
                'subject_template' => 'Welcome {{memberScaName}} to {{portalName}}',
                'text_template' =>
                    "Welcome, {{memberScaName}}!\n\n" .
                    "To verify your email address please use the link below to set your password.\n\n" .
                    "{{passwordResetUrl}}\n\n" .
                    'This link will be good for 1 day. If you do not set your password within that time frame ' .
                    'you will need to request a new password reset email from the "forgot password" link on ' .
                    "the login page.\n\n\n" .
                    "Thank you\n{{siteAdminSignature}}.",
                'available_vars' => ['memberScaName', 'passwordResetUrl', 'portalName', 'siteAdminSignature'],
                'variables_schema' => [
                    'memberScaName' => ['type' => 'string', 'label' => 'Member SCA Name', 'required' => true],
                    'passwordResetUrl' => ['type' => 'string', 'label' => 'Password Reset URL', 'required' => true],
                    'portalName' => ['type' => 'string', 'label' => 'Portal Name'],
                    'siteAdminSignature' => ['type' => 'string', 'label' => 'Site Admin Signature'],
                ],
            ],
            [
                'slug' => 'member-registration-secretary',
                'name' => 'New Member Secretary Notification',
                'description' => 'Sent to the kingdom secretary when a new adult member registers.',
                'mailer_class' => 'App\Mailer\KMPMailer',
                'action_method' => 'notifySecretaryOfNewMember',
                'subject_template' => 'New Member Registration: {{memberScaName}}',
                'text_template' =>
                    "Good day,\n\n" .
                    '{{memberScaName}} has recently registered. They have been emailed to set their password ' .
                    "and their membership card was {{memberCardPresent}}.\n\n" .
                    "You can view their information at the link below:\n" .
                    "{{memberViewUrl}}\n\n\n" .
                    "Thank you\n{{siteAdminSignature}}.",
                'available_vars' => ['memberScaName', 'memberCardPresent', 'memberViewUrl', 'siteAdminSignature'],
                'variables_schema' => [
                    'memberScaName' => ['type' => 'string', 'label' => 'Member SCA Name', 'required' => true],
                    'memberCardPresent' => ['type' => 'string', 'label' => 'Membership Card Status', 'required' => true],
                    'memberViewUrl' => ['type' => 'string', 'label' => 'Member Profile URL', 'required' => true],
                    'siteAdminSignature' => ['type' => 'string', 'label' => 'Site Admin Signature'],
                ],
            ],
            [
                'slug' => 'member-registration-secretary-minor',
                'name' => 'New Minor Member Secretary Notification',
                'description' => 'Sent to the kingdom secretary when a new minor member registers.',
                'mailer_class' => 'App\Mailer\KMPMailer',
                'action_method' => 'notifySecretaryOfNewMinorMember',
                'subject_template' => 'New Minor Member Registration: {{memberScaName}}',
                'text_template' =>
                    "Good day,\n\n" .
                    'A new minor named {{memberScaName}} has recently registered. Their account is currently ' .
                    'inaccessible and they have been notified you will follow up. Their membership card ' .
                    "was {{memberCardPresent}} at the time of registration.\n\n" .
                    "You can view their information at the link below:\n" .
                    "{{memberViewUrl}}\n\n\n" .
                    "Thank you\n{{siteAdminSignature}}.",
                'available_vars' => ['memberScaName', 'memberCardPresent', 'memberViewUrl', 'siteAdminSignature'],
                'variables_schema' => [
                    'memberScaName' => ['type' => 'string', 'label' => 'Member SCA Name', 'required' => true],
                    'memberCardPresent' => ['type' => 'string', 'label' => 'Membership Card Status', 'required' => true],
                    'memberViewUrl' => ['type' => 'string', 'label' => 'Member Profile URL', 'required' => true],
                    'siteAdminSignature' => ['type' => 'string', 'label' => 'Site Admin Signature'],
                ],
            ],
            [
                'slug' => 'officer-hire-notification',
                'name' => 'Officer Hire Notification',
                'description' => 'Sent to a member when they are appointed to an office.',
                'mailer_class' => 'Officers\Mailer\OfficersMailer',
                'action_method' => 'notifyOfHire',
                'subject_template' => 'Appointment Notification: {{officeName}}',
                'text_template' =>
                    "Good day {{memberScaName}}\n\n" .
                    'First we would like to thank you for your offer of service in the office of ' .
                    "{{officeName}} for {{branchName}}.\n" .
                    'We are pleased to inform you that your offer has been accepted and you have been ' .
                    "appointed and can start in the role on {{hireDate}}.\n\n" .
                    "{{requiresWarrantNotice}}\n\n" .
                    "Office: {{officeName}}\n" .
                    "Branch: {{branchName}}\n" .
                    "Start Date: {{hireDate}}\n" .
                    "End Date: {{endDate}}\n\n" .
                    "Thank you\n{{siteAdminSignature}}.",
                'available_vars' => [
                    'memberScaName', 'officeName', 'branchName',
                    'hireDate', 'endDate', 'requiresWarrantNotice', 'siteAdminSignature',
                ],
                'variables_schema' => [
                    'memberScaName' => ['type' => 'string', 'label' => 'Member SCA Name', 'required' => true],
                    'officeName' => ['type' => 'string', 'label' => 'Office Name', 'required' => true],
                    'branchName' => ['type' => 'string', 'label' => 'Branch Name', 'required' => true],
                    'hireDate' => ['type' => 'string', 'label' => 'Appointment Start Date', 'required' => true],
                    'endDate' => ['type' => 'string', 'label' => 'Appointment End Date', 'required' => true],
                    'requiresWarrantNotice' => ['type' => 'string', 'label' => 'Warrant Required Notice (empty if not required)'],
                    'siteAdminSignature' => ['type' => 'string', 'label' => 'Site Admin Signature'],
                ],
            ],
            [
                'slug' => 'officer-release-notification',
                'name' => 'Officer Release Notification',
                'description' => 'Sent to a member when they are released from an office.',
                'mailer_class' => 'Officers\Mailer\OfficersMailer',
                'action_method' => 'notifyOfRelease',
                'subject_template' => 'Release from Office Notification: {{officeName}}',
                'text_template' =>
                    "Good day {{memberScaName}}\n\n" .
                    'We regret to inform you that you have been released from the office of {{officeName}} ' .
                    "for {{branchName}} as of {{releaseDate}}.\n\n" .
                    "The reason for this release is: {{reason}}.\n\n" .
                    'We thank you for your service and hope that you will continue to offer your service ' .
                    "in other capacities.\n\n" .
                    "Thank you\n{{siteAdminSignature}}.",
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
        ];
    }
}
