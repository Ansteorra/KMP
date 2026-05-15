<?php

declare(strict_types=1);

use App\Migrations\CrossEngineMigrationTrait;
use Migrations\BaseMigration;

/**
 * Seed/backfill the workflow-native slug for the authorization retraction email.
 *
 * This notification was still sent through ActivitiesMailer::notifyApproverOfRetraction()
 * after the other legacy emails gained slug identities. The migration preserves any
 * existing customized template content by attaching the new slug to the legacy row
 * when present, and only inserts a new global template when no prior row exists.
 */
class AddAuthorizationRetractionEmailTemplateSlug extends BaseMigration
{
    use CrossEngineMigrationTrait;

    private const SLUG = 'authorization-request-retracted';

    private const LEGACY_MAILER = 'Activities\Mailer\ActivitiesMailer';

    private const LEGACY_ACTION = 'notifyApproverOfRetraction';

    /**
     * @return void
     */
    public function up(): void
    {
        $now = date('Y-m-d H:i:s');
        $name = 'Authorization Request Retracted';
        $description = 'Sent to an approver when a requester retracts an authorization request.';
        $availableVars = ['activityName', 'approverScaName', 'requesterScaName', 'siteAdminSignature'];
        $variablesSchema = [
            'activityName' => ['type' => 'string', 'label' => 'Activity Name', 'required' => true],
            'approverScaName' => ['type' => 'string', 'label' => 'Approver SCA Name', 'required' => true],
            'requesterScaName' => ['type' => 'string', 'label' => 'Requester SCA Name', 'required' => true],
            'siteAdminSignature' => ['type' => 'string', 'label' => 'Site Admin Signature'],
        ];

        $existingSlug = $this->fetchRow(
            "SELECT id FROM email_templates WHERE slug = '" . $this->sqlEscape(self::SLUG) . "' LIMIT 1",
        );
        if ($existingSlug) {
            return;
        }

        $legacyTemplate = $this->fetchRow(
            "SELECT id
               FROM email_templates
              WHERE mailer_class = '" . $this->sqlEscape(self::LEGACY_MAILER) . "'
                 AND action_method = '" . $this->sqlEscape(self::LEGACY_ACTION) . "'
              ORDER BY id ASC
              LIMIT 1",
        );

        if ($legacyTemplate) {
            $this->execute(
                "UPDATE email_templates
                    SET slug = '" . $this->sqlEscape(self::SLUG) . "',
                        name = '" . $this->sqlEscape($name) . "',
                        description = '" . $this->sqlEscape($description) . "',
                        available_vars = '" . $this->sqlEscape(json_encode($availableVars)) . "',
                        variables_schema = '" . $this->sqlEscape(json_encode($variablesSchema)) . "',
                        modified = '{$now}',
                        modified_by = 1
                  WHERE id = " . (int)$legacyTemplate['id'],
            );

            return;
        }

        $this->execute(
            "INSERT INTO email_templates
                 (slug, name, description, subject_template, text_template, html_template,
                  available_vars, variables_schema, is_active,
                  created, modified, created_by, modified_by)
             VALUES (
                 '" . $this->sqlEscape(self::SLUG) . "',
                 '" . $this->sqlEscape($name) . "',
                 '" . $this->sqlEscape($description) . "',
                 'Authorization Request Retracted: {{activityName}}',
                 'Good day {{approverScaName}}\n\n{{requesterScaName}} has retracted their authorization request for {{activityName}}.\n\nThis request has been cancelled and no further action is required on your part.\n\nThank you\n{{siteAdminSignature}}.',
                 NULL,
                 '" . $this->sqlEscape(json_encode($availableVars)) . "',
                 '" . $this->sqlEscape(json_encode($variablesSchema)) . "',
                  TRUE,
                  '{$now}', '{$now}', 1, 1
             )",
        );
    }

    /**
     * @return void
     */
    public function down(): void
    {
        $this->execute(
            "UPDATE email_templates
                SET slug = NULL
              WHERE slug = '" . $this->sqlEscape(self::SLUG) . "'
                AND mailer_class = '" . $this->sqlEscape(self::LEGACY_MAILER) . "'
                AND action_method = '" . $this->sqlEscape(self::LEGACY_ACTION) . "'",
        );

        $this->execute(
            "DELETE FROM email_templates
              WHERE slug = '" . $this->sqlEscape(self::SLUG) . "'
                AND mailer_class IS NULL
                AND action_method IS NULL",
        );
    }
}
