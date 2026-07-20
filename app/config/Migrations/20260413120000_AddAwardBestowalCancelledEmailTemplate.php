<?php

declare(strict_types=1);

use App\Migrations\CrossEngineMigrationTrait;
use Migrations\BaseMigration;

class AddAwardBestowalCancelledEmailTemplate extends BaseMigration
{
    use CrossEngineMigrationTrait;

    /**
     * @return void
     */
    public function up(): void
    {
        $now = date('Y-m-d H:i:s');
        $availableVars = json_encode([
            'bestowalId',
            'closeReason',
            'unwindState',
            'recommendationIds',
            'previousState',
            'newState',
            'siteAdminSignature',
        ], JSON_THROW_ON_ERROR);
        $variablesSchema = json_encode([
            'bestowalId' => ['type' => 'string', 'label' => 'Bestowal ID', 'required' => true],
            'closeReason' => ['type' => 'string', 'label' => 'Close Reason', 'required' => true],
            'unwindState' => ['type' => 'string', 'label' => 'Recommendation Unwind State'],
            'recommendationIds' => ['type' => 'string', 'label' => 'Linked Recommendation IDs'],
            'previousState' => ['type' => 'string', 'label' => 'Previous State'],
            'newState' => ['type' => 'string', 'label' => 'New State'],
            'siteAdminSignature' => ['type' => 'string', 'label' => 'Site Admin Signature'],
        ], JSON_THROW_ON_ERROR);
        $subject = $this->sqlEscape('Award Bestowal Cancelled: #{{bestowalId}}');
        $text = $this->sqlEscape(
            "An award bestowal has been cancelled.\n\n"
            . "Bestowal ID: {{bestowalId}}\n"
            . "Close Reason: {{closeReason}}\n"
            . "Previous State: {{previousState}}\n"
            . "New State: {{newState}}\n"
            . "Recommendation Unwind State: {{unwindState}}\n"
            . "Linked Recommendation IDs: {{recommendationIds}}\n\n"
            . "Thank you\n{{siteAdminSignature}}.",
        );

        $existing = $this->fetchRow(
            "SELECT id FROM email_templates WHERE slug = 'award-bestowal-cancelled' LIMIT 1",
        );

        if ($existing) {
            $this->execute(
                "UPDATE email_templates
                    SET name = 'Award Bestowal Cancelled',
                        description = 'Sent to the awards coordinator when an award bestowal is cancelled.',
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
                    'award-bestowal-cancelled',
                    'Award Bestowal Cancelled',
                    'Sent to the awards coordinator when an award bestowal is cancelled.',
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
            "DELETE FROM email_templates WHERE slug = 'award-bestowal-cancelled'",
        );
    }
}
