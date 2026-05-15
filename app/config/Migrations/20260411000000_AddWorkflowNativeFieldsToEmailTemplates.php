<?php

declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * Introduce workflow-native identity fields to email_templates.
 *
 * New first-class fields: slug (stable key), name, description, and
 * variables_schema (explicit variable contract for workflow-native templates).
 *
 * Legacy provenance fields mailer_class and action_method are demoted to
 * nullable so workflow-native templates do not require them.  The old unique
 * index on (mailer_class, action_method) is replaced with:
 *   - a unique index on slug for workflow-native identity
 *   - a partial-style unique index on (mailer_class, action_method) retained for
 *     legacy lookup, but these columns now allow NULL.
 */
class AddWorkflowNativeFieldsToEmailTemplates extends BaseMigration
{
    /**
     * @return void
     */
    public function change(): void
    {
        $table = $this->table('email_templates');

        // Demote legacy identity columns to nullable provenance fields
        $table
            ->changeColumn('mailer_class', 'string', [
                'null' => true,
                'default' => null,
                'limit' => 255,
                'comment' => 'Legacy: Mailer class for legacy-backed templates (provenance only)',
            ])
            ->changeColumn('action_method', 'string', [
                'null' => true,
                'default' => null,
                'limit' => 255,
                'comment' => 'Legacy: Mailer action method for legacy-backed templates (provenance only)',
            ]);

        // Add workflow-native identity and metadata columns
        $table
            ->addColumn('slug', 'string', [
                'null' => true,
                'default' => null,
                'limit' => 100,
                'after' => 'id',
                'comment' => 'Stable workflow-native key (e.g. warrant-issued).',
            ])
            ->addColumn('name', 'string', [
                'null' => true,
                'default' => null,
                'limit' => 255,
                'after' => 'slug',
                'comment' => 'Human-readable admin label for this template',
            ])
            ->addColumn('description', 'text', [
                'null' => true,
                'default' => null,
                'after' => 'name',
                'comment' => 'Admin-facing description of template purpose',
            ])
            ->addColumn('variables_schema', 'text', [
                'null' => true,
                'default' => null,
                'after' => 'available_vars',
                'comment' => 'JSON schema for the explicit variable contract of this template',
            ]);

        // Remove old unique index keyed on legacy columns
        $table->removeIndexByName('idx_mailer_action_unique');

        // Add workflow-native unique slug index
        $table->addIndex(['slug'], [
            'unique' => true,
            'name' => 'idx_et_slug',
        ]);

        $table->update();
    }
}
