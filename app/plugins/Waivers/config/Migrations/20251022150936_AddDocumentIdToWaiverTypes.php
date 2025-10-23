<?php

declare(strict_types=1);

use Migrations\BaseMigration;

class AddDocumentIdToWaiverTypes extends BaseMigration
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
        $table = $this->table('waivers_waiver_types');

        // Add document_id for uploaded files (uses Documents model)
        $table->addColumn('document_id', 'integer', [
            'default' => null,
            'limit' => 11,
            'null' => true,
            'after' => 'description',
            'comment' => 'FK to documents.id for uploaded template files (null if using external URL)'
        ]);

        // Add foreign key constraint
        $table->addForeignKey(
            'document_id',
            'documents',
            'id',
            [
                'delete' => 'NO_ACTION',
                'update' => 'NO_ACTION'
            ]
        );

        // Add index for faster lookups
        $table->addIndex(['document_id'], [
            'name' => 'idx_waiver_types_document_id',
            'unique' => false,
        ]);

        // Update template_path comment to clarify it's only for external URLs now
        $table->changeColumn('template_path', 'string', [
            'default' => null,
            'limit' => 500,
            'null' => true,
            'comment' => 'External URL to template (e.g., SCA.org link). Use document_id for uploaded files.'
        ]);

        $table->update();
    }
}