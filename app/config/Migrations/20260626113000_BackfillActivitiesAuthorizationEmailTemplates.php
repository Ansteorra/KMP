<?php

declare(strict_types=1);

use App\Migrations\CrossEngineMigrationTrait;
use Migrations\BaseMigration;

class BackfillActivitiesAuthorizationEmailTemplates extends BaseMigration
{
    use CrossEngineMigrationTrait;

    /**
     * @return void
     */
    public function up(): void
    {
        $now = date('Y-m-d H:i:s');

        foreach ($this->templates() as $template) {
            $existing = $this->fetchRow(
                "SELECT id FROM email_templates WHERE slug = '" . $this->sqlEscape($template['slug']) . "' LIMIT 1",
            );
            if ($existing) {
                $this->execute(
                    "UPDATE email_templates
                        SET name = '" . $this->sqlEscape($template['name']) . "',
                            description = '" . $this->sqlEscape($template['description']) . "',
                            subject_template = '" . $this->sqlEscape($template['subject_template']) . "',
                            text_template = '" . $this->sqlEscape($template['text_template']) . "',
                            html_template = NULL,
                            available_vars = '" . $this->sqlEscape(json_encode($template['available_vars'])) . "',
                            variables_schema = '" . $this->sqlEscape(json_encode($template['variables_schema'])) . "',
                            is_active = TRUE,
                            modified = '{$now}',
                            modified_by = 1
                      WHERE id = " . (int)$existing['id'],
                );

                continue;
            }

            $this->execute(
                "INSERT INTO email_templates
                     (slug, name, description, subject_template, text_template, html_template,
                      available_vars, variables_schema, is_active,
                      created, modified, created_by, modified_by)
                 VALUES (
                     '" . $this->sqlEscape($template['slug']) . "',
                     '" . $this->sqlEscape($template['name']) . "',
                     '" . $this->sqlEscape($template['description']) . "',
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
        foreach (array_keys($this->templates()) as $slug) {
            $this->execute(
                "DELETE FROM email_templates WHERE slug = '" . $this->sqlEscape($slug) . "'",
            );
        }
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function templates(): array
    {
        return [
            'authorization-approval-request' => [
                'slug' => 'authorization-approval-request',
                'name' => 'Authorization Approval Request',
                'description' => 'Sent to the next approver when an authorization request needs review.',
                'subject_template' => 'Authorization Approval Request',
                'text_template' =>
                    "Good day {{approverScaName}}\n\n"
                    . '{{memberScaName}} has requested your authorization in the fine and noble art of '
                    . '{{activityName}}. If you could go to the following link to respond to the request, '
                    . "that would be most kind and helpful.\n\n"
                    . "{{authorizationResponseUrl}}\n\n\nThank you\n{{siteAdminSignature}}.",
                'available_vars' => [
                    'authorizationResponseUrl',
                    'memberScaName',
                    'approverScaName',
                    'activityName',
                    'siteAdminSignature',
                ],
                'variables_schema' => [
                    [
                        'name' => 'authorizationResponseUrl',
                        'type' => 'string',
                        'label' => 'Authorization Response URL',
                        'required' => true,
                    ],
                    [
                        'name' => 'memberScaName',
                        'type' => 'string',
                        'label' => 'Member SCA Name',
                        'required' => true,
                    ],
                    [
                        'name' => 'approverScaName',
                        'type' => 'string',
                        'label' => 'Approver SCA Name',
                        'required' => true,
                    ],
                    ['name' => 'activityName', 'type' => 'string', 'label' => 'Activity Name', 'required' => true],
                    ['name' => 'siteAdminSignature', 'type' => 'string', 'label' => 'Site Admin Signature'],
                ],
            ],
            'authorization-request-update' => [
                'slug' => 'authorization-request-update',
                'name' => 'Authorization Request Update',
                'description' => 'Sent to the requester when an authorization request status changes.',
                'subject_template' => 'Update on Authorization Request',
                'text_template' =>
                    "Good day {{memberScaName}}\n\n"
                    . '{{approverScaName}} has responded to your request and the authorization is now '
                    . "{{status}} for {{activityName}}.\n\n"
                    . '{{#if status == "Pending"}}Your request has been forwarded to {{nextApproverScaName}} '
                    . "for additional approval.\n\n{{/if}}"
                    . '{{#if status == "Denied"}}If you feel this decision was made in error please reach out '
                    . "to {{approverScaName}} for more information.\n\n{{/if}}"
                    . '{{#if status == "Revoked"}}If you feel this decision was made in error please reach out '
                    . "to {{approverScaName}} for more information.\n\n{{/if}}"
                    . '{{#if status == "Approved" || status == "Revoked"}}You may view your updated member '
                    . "card at the following URL:\n\n{{memberCardUrl}}\n\n{{/if}}"
                    . 'Thank you' . "\n" . '{{siteAdminSignature}}.',
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
                    [
                        'name' => 'memberScaName',
                        'type' => 'string',
                        'label' => 'Member SCA Name',
                        'required' => true,
                    ],
                    [
                        'name' => 'approverScaName',
                        'type' => 'string',
                        'label' => 'Approver SCA Name',
                        'required' => true,
                    ],
                    ['name' => 'status', 'type' => 'string', 'label' => 'Authorization Status', 'required' => true],
                    ['name' => 'activityName', 'type' => 'string', 'label' => 'Activity Name', 'required' => true],
                    ['name' => 'memberCardUrl', 'type' => 'string', 'label' => 'Member Card URL', 'required' => true],
                    ['name' => 'nextApproverScaName', 'type' => 'string', 'label' => 'Next Approver SCA Name'],
                    ['name' => 'siteAdminSignature', 'type' => 'string', 'label' => 'Site Admin Signature'],
                ],
            ],
        ];
    }
}
