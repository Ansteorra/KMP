<?php
declare(strict_types=1);

use App\Migrations\CrossEngineMigrationTrait;
use Migrations\BaseMigration;

class AddMembershipCardReuploadEmailTemplate extends BaseMigration
{
    use CrossEngineMigrationTrait;

    /**
     * Add the member-facing replacement-card request template.
     *
     * @return void
     */
    public function up(): void
    {
        $now = gmdate('Y-m-d H:i:s');
        $availableVars = json_encode([
            'memberScaName',
            'contactEmail',
            'siteAdminSignature',
        ], JSON_THROW_ON_ERROR);
        $variablesSchema = json_encode([
            'memberScaName' => ['type' => 'string', 'label' => 'Member SCA Name', 'required' => true],
            'contactEmail' => [
                'type' => 'string',
                'label' => 'Membership Verification Contact Email',
                'required' => true,
            ],
            'siteAdminSignature' => ['type' => 'string', 'label' => 'Site Admin Signature'],
        ], JSON_THROW_ON_ERROR);
        $subject = $this->sqlEscape('New membership card upload requested');
        $text = $this->sqlEscape(
            "Good day {{memberScaName}}\n\n"
            . 'We could not verify your membership from the card image you submitted in AMP. '
            . "The image did not clearly show all of the information needed to verify it.\n\n"
            . 'Please sign in to AMP and upload a new screenshot that clearly shows your name, '
            . "membership number, and membership expiration date.\n\n"
            . "If you cannot upload the screenshot through AMP, email it to {{contactEmail}}.\n\n"
            . "Thank you\n{{siteAdminSignature}}.",
        );

        $existing = $this->fetchRow(
            "SELECT id FROM email_templates WHERE slug = 'membership-card-reupload-requested' LIMIT 1",
        );
        if ($existing) {
            $this->execute(
                "UPDATE email_templates
                    SET name = 'Membership Card Re-upload Requested',
                        description = 'Sent when an administrator requests a clearer membership-card upload.',
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
             VALUES (
                    'membership-card-reupload-requested',
                    'Membership Card Re-upload Requested',
                    'Sent when an administrator requests a clearer membership-card upload.',
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
     * Preserve any tenant edits but make the rolled-back template unavailable.
     *
     * @return void
     */
    public function down(): void
    {
        $this->execute(
            "UPDATE email_templates
                SET is_active = FALSE
              WHERE slug = 'membership-card-reupload-requested'",
        );
    }
}
