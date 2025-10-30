<?php

declare(strict_types=1);

use Migrations\BaseMigration;

class CreateEmailTemplates extends BaseMigration
{
    /**
     * Create the `email_templates` database table used to store mailer templates and their metadata.
     *
     * The table includes columns for the mailer class, action method, subject template, HTML and text
     * template bodies, available variables (JSON), an `is_active` flag, and `created`/`modified`
     * timestamps. It also adds a unique composite index on (`mailer_class`, `action_method`) named
     * `idx_mailer_action_unique` and a non-unique index on `is_active`.
     *
     * @return void
     */
    public function change(): void
    {
        $table = $this->table('email_templates');
        $table->addColumn('mailer_class', 'string', [
            'default' => null,
            'limit' => 255,
            'null' => false,
            'comment' => 'Fully qualified class name of the Mailer (e.g., App\Mailer\KMPMailer)',
        ]);
        $table->addColumn('action_method', 'string', [
            'default' => null,
            'limit' => 255,
            'null' => false,
            'comment' => 'Method name in the Mailer class (e.g., resetPassword)',
        ]);
        $table->addColumn('subject_template', 'string', [
            'default' => null,
            'limit' => 500,
            'null' => false,
            'comment' => 'Email subject line template with variable placeholders',
        ]);
        $table->addColumn('html_template', 'text', [
            'default' => null,
            'null' => true,
            'comment' => 'HTML version of email template',
        ]);
        $table->addColumn('text_template', 'text', [
            'default' => null,
            'null' => true,
            'comment' => 'Plain text version of email template',
        ]);
        $table->addColumn('available_vars', 'text', [
            'default' => null,
            'null' => true,
            'comment' => 'JSON array of available variables for this template',
        ]);
        $table->addColumn('is_active', 'boolean', [
            'default' => true,
            'null' => false,
            'comment' => 'Whether this template is active and should be used',
        ]);
        $table->addColumn('created', 'datetime', [
            'default' => null,
            'null' => true,
        ]);
        $table->addColumn('modified', 'datetime', [
            'default' => null,
            'null' => true,
        ]);
        $table->addColumn('created_by', 'integer', [
            'default' => null,
            'limit' => 11,
            'null' => true,
            'comment' => 'Member ID who created this template',
        ]);
        $table->addColumn('modified_by', 'integer', [
            'default' => null,
            'limit' => 11,
            'null' => true,
            'comment' => 'Member ID who last modified this template',
        ]);

        // Add indexes for performance
        $table->addIndex(['mailer_class', 'action_method'], [
            'unique' => true,
            'name' => 'idx_mailer_action_unique',
        ]);
        $table->addIndex(['is_active']);

        // Add foreign key constraints for audit fields
        $table->addForeignKey('created_by', 'members', 'id', [
            'delete' => 'SET_NULL',
            'update' => 'CASCADE',
            'constraint' => 'fk_email_templates_created_by'
        ]);
        $table->addForeignKey('modified_by', 'members', 'id', [
            'delete' => 'SET_NULL',
            'update' => 'CASCADE',
            'constraint' => 'fk_email_templates_modified_by'
        ]);

        $table->create();
    }
}
