<?php
declare(strict_types=1);

use App\Migrations\CrossEngineMigrationTrait;
use Migrations\BaseMigration;

class AddOfficerAssignmentUpdatedEmailTemplate extends BaseMigration
{
    use CrossEngineMigrationTrait;

    private const SLUG = 'officer-assignment-updated-notification';
    private const OWNERSHIP_MARKER = '20260902100000_AddOfficerAssignmentUpdatedEmailTemplate';

    /**
     * Add the member-facing officer assignment update template.
     *
     * @return void
     */
    public function up(): void
    {
        $existing = $this->fetchRow(
            "SELECT id FROM email_templates WHERE slug = '" . self::SLUG . "' LIMIT 1",
        );
        if ($existing) {
            return;
        }

        $now = gmdate('Y-m-d H:i:s');
        $availableVars = $this->sqlEscape(json_encode([
            ['name' => 'memberScaName', '_migration' => self::OWNERSHIP_MARKER],
            ['name' => 'officeName'],
            ['name' => 'branchName'],
            ['name' => 'startDate'],
            ['name' => 'endDate'],
            ['name' => 'changeSummary'],
            ['name' => 'termChangeNote'],
            ['name' => 'warrantMessage'],
            ['name' => 'siteAdminSignature'],
        ], JSON_THROW_ON_ERROR));
        $variablesSchema = $this->sqlEscape(json_encode([
            'memberScaName' => ['type' => 'string', 'label' => 'Member SCA Name', 'required' => true],
            'officeName' => ['type' => 'string', 'label' => 'Office Name', 'required' => true],
            'branchName' => ['type' => 'string', 'label' => 'Branch Name', 'required' => true],
            'startDate' => ['type' => 'string', 'label' => 'Term Start Date', 'required' => true],
            'endDate' => ['type' => 'string', 'label' => 'Term End Date', 'required' => true],
            'changeSummary' => ['type' => 'string', 'label' => 'Change Summary', 'required' => true],
            'termChangeNote' => ['type' => 'string', 'label' => 'Term Change Note'],
            'warrantMessage' => ['type' => 'string', 'label' => 'Warrant Status'],
            'siteAdminSignature' => ['type' => 'string', 'label' => 'Site Admin Signature'],
        ], JSON_THROW_ON_ERROR));
        $subject = $this->sqlEscape('Officer assignment updated: {{officeName}}');
        $text = $this->sqlEscape(
            "Good day {{memberScaName}}\n\n"
            . "Your assignment as {{officeName}} for {{branchName}} has been updated.\n\n"
            . "Term: {{startDate}} through {{endDate}}\n\n"
            . "Update summary:\n{{changeSummary}}\n\n"
            . "{{#if termChangeNote}}Term note:\n{{termChangeNote}}\n\n{{/if}}"
            . "{{#if warrantMessage}}Warrant status:\n{{warrantMessage}}\n\n{{/if}}"
            . "Thank you\n{{siteAdminSignature}}.",
        );

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
                    '" . self::SLUG . "',
                    'Officer Assignment Updated Notification',
                    'Sent to an officer after an authorized assignment update.',
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
     * Preserve tenant edits but make the rolled-back template unavailable.
     *
     * @return void
     */
    public function down(): void
    {
        $template = $this->fetchRow(
            "SELECT id, available_vars FROM email_templates WHERE slug = '" . self::SLUG . "' LIMIT 1",
        );
        if (!$template) {
            return;
        }

        $availableVars = is_array($template['available_vars'])
            ? $template['available_vars']
            : json_decode((string)$template['available_vars'], true);
        if (!is_array($availableVars)) {
            return;
        }

        foreach ($availableVars as $variable) {
            if (!is_array($variable) || ($variable['_migration'] ?? null) !== self::OWNERSHIP_MARKER) {
                continue;
            }

            $this->execute(
                'UPDATE email_templates SET is_active = FALSE WHERE id = ' . (int)$template['id'],
            );

            return;
        }
    }
}
