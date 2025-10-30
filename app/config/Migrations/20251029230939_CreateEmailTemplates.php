<?php

declare(strict_types=1);

use Migrations\BaseMigration;

class CreateEmailTemplates extends BaseMigration
{
    /**
     * Change Method.
     *
     * More information on this method is available here:
     * https://book.cakephp.org/migrations/4/en/migrations.html#the-change-method
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

        // Add indexes for performance
        $table->addIndex(['mailer_class', 'action_method'], [
            'unique' => true,
            'name' => 'idx_mailer_action_unique',
        ]);
        $table->addIndex(['is_active']);

        $table->create();
    }
}
