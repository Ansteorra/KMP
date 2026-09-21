<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class AddGridEmailSummaryTemplate extends BaseMigration
{
    /** Seed missing defaults only; each tenant owns its template and setting after installation. */
    public function up(): void
    {
        $now = gmdate('Y-m-d H:i:s');
        if (!$this->fetchRow("SELECT id FROM app_settings WHERE name = 'Email.GridSubscriptionTemplate'")) {
            $this->table('app_settings')->insert([
                'name' => 'Email.GridSubscriptionTemplate', 'value' => 'grid-email-summary',
                'type' => 'string', 'required' => true, 'created' => $now, 'modified' => $now,
            ])->saveData();
        }
        if ($this->fetchRow("SELECT id FROM email_templates WHERE slug = 'grid-email-summary'")) {
            return;
        }
        $variables = [
            'siteTitle' => 'Application short title',
            'summaryName' => 'Name entered by the subscriber',
            'gridLabel' => 'Source grid name',
            'viewUrl' => 'Link to the current view',
            'manageUrl' => 'Link to manage subscriptions',
            'rowCount' => 'Number of included rows',
            'rowLimit' => 'Maximum included rows',
            'isSample' => 'Whether this is a one-time sample',
            'hasRows' => 'Whether any rows match',
            'isEmpty' => 'Whether no rows match',
            'resultsText' => 'Plain-text labeled rows (use in text body)',
            'resultsTable' => 'Escaped Markdown table (use in Markdown body)',
            'siteAdminSignature' => 'Site administrator signature',
        ];
        $schema = [];
        foreach ($variables as $name => $label) {
            $schema[$name] = ['type' => 'string', 'label' => $label];
        }
        $schema['rowCount']['type'] = $schema['rowLimit']['type'] = 'number';
        foreach (['isSample', 'hasRows', 'isEmpty'] as $name) {
            $schema[$name]['type'] = 'boolean';
        }
        $intro = "Current results from {{gridLabel}}. Up to {{rowLimit}} matching rows are included.\n\n";
        $empty = '{{#if isEmpty}}No rows match this view right now. '
            . "Scheduled emails are skipped when there are no matches.{{/if}}\n\n";
        $notice = "{{#if isSample}}This is a one-time sample. It does not create or change a subscription.{{/if}}\n\n"
            . '{{#if isSample == "No"}}You subscribed to this summary. '
            . "Manage or cancel email subscriptions from your profile.{{/if}}\n\n";
        $this->table('email_templates')->insert([
            'slug' => 'grid-email-summary', 'name' => 'Grid Email Summary',
            'description' => 'Scheduled grid summaries and immediate samples. '
                . 'Selected by the Email.GridSubscriptionTemplate app setting; no workflow is required.',
            'subject_template' => '{{siteTitle}}: {{#if isSample}}Sample: {{/if}}{{summaryName}}',
            'html_template' => "# {{summaryName}}\n\n" . $intro . $empty
                . "{{resultsTable}}\n\n[Open this view]({{viewUrl}})\n\n" . $notice
                . "[Manage email subscriptions]({{manageUrl}})\n\n{{siteAdminSignature}}",
            'text_template' => "{{summaryName}}\n\n" . $intro . $empty
                . "{{resultsText}}\n\nOpen this view: {{viewUrl}}\n\n" . $notice
                . "Manage or cancel email subscriptions: {{manageUrl}}\n\n{{siteAdminSignature}}",
            'available_vars' => json_encode(
                array_map(static fn($name) => ['name' => $name], array_keys($variables)),
                JSON_THROW_ON_ERROR,
            ),
            'variables_schema' => json_encode($schema, JSON_THROW_ON_ERROR),
            'is_active' => true, 'created' => $now, 'modified' => $now, 'created_by' => 1, 'modified_by' => 1,
        ])->saveData();
    }

    /** Retain tenant-owned content and configuration when rolling application code back. */
    public function down(): void
    {
    }
}
