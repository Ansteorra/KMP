<?php

declare(strict_types=1);

use App\Migrations\CrossEngineMigrationTrait;
use Migrations\BaseMigration;

class AddOfficerAssignmentAdjustedEmailTemplate extends BaseMigration
{
    use CrossEngineMigrationTrait;

    /**
     * @return void
     */
    public function up(): void
    {
        $now = date('Y-m-d H:i:s');
        $availableVars = json_encode([
            'memberScaName',
            'officeName',
            'branchName',
            'previousStartDate',
            'previousEndDate',
            'newStartDate',
            'newEndDate',
            'reason',
            'siteAdminSignature',
        ], JSON_THROW_ON_ERROR);
        $variablesSchema = json_encode([
            'memberScaName' => ['type' => 'string', 'label' => 'Member SCA Name', 'required' => true],
            'officeName' => ['type' => 'string', 'label' => 'Office Name', 'required' => true],
            'branchName' => ['type' => 'string', 'label' => 'Branch Name', 'required' => true],
            'previousStartDate' => ['type' => 'string', 'label' => 'Previous Start Date', 'required' => true],
            'previousEndDate' => ['type' => 'string', 'label' => 'Previous End Date', 'required' => true],
            'newStartDate' => ['type' => 'string', 'label' => 'New Start Date', 'required' => true],
            'newEndDate' => ['type' => 'string', 'label' => 'New End Date', 'required' => true],
            'reason' => ['type' => 'string', 'label' => 'Adjustment Reason', 'required' => true],
            'siteAdminSignature' => ['type' => 'string', 'label' => 'Site Admin Signature'],
        ], JSON_THROW_ON_ERROR);
        $subject = $this->sqlEscape('Officer Assignment Dates Updated: {{officeName}}');
        $text = $this->sqlEscape(
            "Good day {{memberScaName}}\n\n"
            . "Your assignment dates for {{officeName}} in {{branchName}} have been updated.\n\n"
            . "Previous Term:\n"
            . "Start Date: {{previousStartDate}}\n"
            . "End Date: {{previousEndDate}}\n\n"
            . "Updated Term:\n"
            . "Start Date: {{newStartDate}}\n"
            . "End Date: {{newEndDate}}\n\n"
            . "Reason: {{reason}}\n\n"
            . 'This is a scheduling adjustment caused by an overlapping replacement assignment. '
            . "It is not a disciplinary release.\n\n"
            . "Thank you\n{{siteAdminSignature}}.",
        );

        $existing = $this->fetchRow(
            "SELECT id FROM email_templates WHERE slug = 'officer-assignment-adjusted-notification' LIMIT 1",
        );

        if ($existing) {
            $this->execute(
                "UPDATE email_templates
                    SET name = 'Officer Assignment Adjusted Notification',
                        description = 'Sent when an officer assignment survives but its dates are adjusted because of a replacement overlap.',
                        subject_template = '{$subject}',
                        text_template = '{$text}',
                        available_vars = '{$availableVars}',
                        variables_schema = '{$variablesSchema}',
                        is_active = TRUE,
                        modified = '{$now}',
                        modified_by = 1
                  WHERE id = " . (int)$existing['id'],
            );

            return;
        }

        $this->execute(
            "INSERT INTO email_templates (
                    slug,
                    name,
                    description,
                    subject_template,
                    text_template,
                    available_vars,
                    variables_schema,
                    is_active,
                    created,
                    modified,
                    created_by,
                    modified_by
                )
             VALUES
                (
                    'officer-assignment-adjusted-notification',
                    'Officer Assignment Adjusted Notification',
                    'Sent when an officer assignment survives but its dates are adjusted because of a replacement overlap.',
                    '{$subject}',
                    '{$text}',
                    '{$availableVars}',
                    '{$variablesSchema}',
                    TRUE,
                    '{$now}',
                    '{$now}',
                    1,
                    1
                )",
        );
    }

    /**
     * @return void
     */
    public function down(): void
    {
        $this->execute(
            "DELETE FROM email_templates WHERE slug = 'officer-assignment-adjusted-notification'",
        );
    }
}
