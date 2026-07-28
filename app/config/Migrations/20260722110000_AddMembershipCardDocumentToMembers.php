<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class AddMembershipCardDocumentToMembers extends BaseMigration
{
    /**
     * Link members to membership cards stored by DocumentService.
     */
    public function change(): void
    {
        $table = $this->table('members');

        $table->addColumn('membership_card_document_id', 'integer', [
            'default' => null,
            'limit' => 11,
            'null' => true,
            'after' => 'membership_card_path',
            'comment' => 'FK to documents.id for persistent membership card storage',
        ]);

        $table->addIndex(['membership_card_document_id'], [
            'name' => 'idx_members_membership_card_document_id',
            'unique' => false,
        ]);

        $table->addForeignKey(
            'membership_card_document_id',
            'documents',
            'id',
            [
                'delete' => 'SET_NULL',
                'update' => 'NO_ACTION',
            ],
        );

        $table->update();
    }
}
