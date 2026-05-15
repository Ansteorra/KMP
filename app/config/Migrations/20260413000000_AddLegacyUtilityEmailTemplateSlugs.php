<?php

declare(strict_types=1);

use App\Migrations\CrossEngineMigrationTrait;
use Migrations\BaseMigration;

/**
 * Seed workflow-native slugs for legacy utility and warrant email templates.
 *
 * Creates DB-backed templates for the remaining KMP mailer methods that are
 * still called directly outside workflow definitions, allowing callers to use
 * KMPMailer::sendFromTemplate() while preserving current content.
 *
 * Slugs created:
 *   - password-reset
 *   - mobile-card-url
 *   - warrant-issued
 */
class AddLegacyUtilityEmailTemplateSlugs extends BaseMigration
{
    use CrossEngineMigrationTrait;

    /**
     * @return void
     */
    public function up(): void
    {
        $now = date('Y-m-d H:i:s');

        foreach ($this->buildTemplates() as $template) {
            $existing = $this->fetchRow(
                "SELECT id FROM email_templates WHERE slug = '" . $this->sqlEscape($template['slug']) . "'",
            );
            if ($existing) {
                continue;
            }

            $this->execute(
                "INSERT INTO email_templates
                    (slug, name, description, mailer_class, action_method,
                     subject_template, text_template, html_template,
                     available_vars, variables_schema, is_active,
                      created, modified, created_by, modified_by)
                 VALUES (
                     '" . $this->sqlEscape($template['slug']) . "',
                     '" . $this->sqlEscape($template['name']) . "',
                     '" . $this->sqlEscape($template['description']) . "',
                     '" . $this->sqlEscape($template['mailer_class']) . "',
                     '" . $this->sqlEscape($template['action_method']) . "',
                     '" . $this->sqlEscape($template['subject_template']) . "',
                     '" . $this->sqlEscape($template['text_template']) . "',
                     NULL,
                     '" . $this->sqlEscape(json_encode($template['available_vars'])) . "',
                     '" . $this->sqlEscape(json_encode($template['variables_schema'])) . "',
                     TRUE,
                      '{$now}', '{$now}', 1, 1
                 )",
            );
        }
    }

    /**
     * @return void
     */
    public function down(): void
    {
        foreach (['password-reset', 'mobile-card-url', 'warrant-issued'] as $slug) {
            $this->execute(
                "DELETE FROM email_templates WHERE slug = '" . $this->sqlEscape($slug) . "'",
            );
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildTemplates(): array
    {
        return [
            [
                'slug' => 'password-reset',
                'name' => 'Password Reset',
                'description' => 'Sent when a member requests a password reset link.',
                'mailer_class' => 'App\Mailer\KMPMailer',
                'action_method' => 'resetPassword',
                'subject_template' => 'Reset password',
                'text_template' =>
                    "Someone has requested a password reset for the Ansteorra Marshallet account with the email address of {{email}}.\n" .
                    "Please use the link below to reset your account password.\n\n" .
                    "{{passwordResetUrl}}\n\n" .
                    "This link will be good for 1 day. If you do not set your password within that time frame you will need to request a new\n" .
                    "password reset email from the \"forgot password\" link on the login page.\n\n" .
                    "If you did not make this request, you are free to disregard this message.\n\n\n" .
                    "Thank you\n{{siteAdminSignature}}.",
                'available_vars' => ['email', 'passwordResetUrl', 'siteAdminSignature'],
                'variables_schema' => [
                    'email' => ['type' => 'string', 'label' => 'Recipient Email', 'required' => true],
                    'passwordResetUrl' => ['type' => 'string', 'label' => 'Password Reset URL', 'required' => true],
                    'siteAdminSignature' => ['type' => 'string', 'label' => 'Site Admin Signature'],
                ],
            ],
            [
                'slug' => 'mobile-card-url',
                'name' => 'Mobile Card URL',
                'description' => 'Sent when a member requests their mobile card link.',
                'mailer_class' => 'App\Mailer\KMPMailer',
                'action_method' => 'mobileCard',
                'subject_template' => 'Your Mobile Card URL',
                'text_template' =>
                    "Below is a link to your mobile card. This link will take you to a page where you can view your mobile card. You can also\n" .
                    "install this card on your phone's home screen for easy access both online and offline.\n\n" .
                    "{{mobileCardUrl}}\n\n\n" .
                    "Thank you\n{{siteAdminSignature}}.",
                'available_vars' => ['mobileCardUrl', 'siteAdminSignature'],
                'variables_schema' => [
                    'mobileCardUrl' => ['type' => 'string', 'label' => 'Mobile Card URL', 'required' => true],
                    'siteAdminSignature' => ['type' => 'string', 'label' => 'Site Admin Signature'],
                ],
            ],
            [
                'slug' => 'warrant-issued',
                'name' => 'Warrant Issued',
                'description' => 'Sent when a warrant roster workflow activates a warrant for a member.',
                'mailer_class' => 'App\Mailer\KMPMailer',
                'action_method' => 'notifyOfWarrant',
                'subject_template' => 'Warrant Issued: {{warrantName}}',
                'text_template' =>
                    "Good day {{memberScaName}}\n\n" .
                    "The \"{{warrantName}}\" warrant has been issued and is valid from {{warrantStart}} to\n" .
                    "{{warrantExpires}}.\n\n" .
                    "If this warrant is for an office that extends passed this warrant date, new warrants will be issued as needed.\n\n" .
                    "This new warrant supersedes any previous warrants issued for for the subjects this warrant covers.\n\n" .
                    "Thank you\n{{siteAdminSignature}}.",
                'available_vars' => [
                    'memberScaName',
                    'warrantName',
                    'warrantStart',
                    'warrantExpires',
                    'siteAdminSignature',
                ],
                'variables_schema' => [
                    'memberScaName' => ['type' => 'string', 'label' => 'Member SCA Name', 'required' => true],
                    'warrantName' => ['type' => 'string', 'label' => 'Warrant Name', 'required' => true],
                    'warrantStart' => ['type' => 'string', 'label' => 'Warrant Start Date', 'required' => true],
                    'warrantExpires' => ['type' => 'string', 'label' => 'Warrant Expiration Date', 'required' => true],
                    'siteAdminSignature' => ['type' => 'string', 'label' => 'Site Admin Signature'],
                ],
            ],
        ];
    }
}
