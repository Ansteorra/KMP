<?php

declare(strict_types=1);

use Migrations\BaseMigration;

class AddProfilePhotoDocumentToMembers extends BaseMigration
{
    public function change(): void
    {
        $table = $this->table('members');

        $table->addColumn('profile_photo_document_id', 'integer', [
            'default' => null,
            'limit' => 11,
            'null' => true,
            'after' => 'membership_card_path',
            'comment' => 'FK to documents.id for persistent profile photo storage',
        ]);

        $table->addIndex(['profile_photo_document_id'], [
            'name' => 'idx_members_profile_photo_document_id',
            'unique' => false,
        ]);

        $table->addForeignKey(
            'profile_photo_document_id',
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
