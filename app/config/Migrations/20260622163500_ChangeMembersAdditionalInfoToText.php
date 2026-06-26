<?php

declare(strict_types=1);

use Migrations\BaseMigration;

class ChangeMembersAdditionalInfoToText extends BaseMigration
{
    /**
     * Widen member additional info JSON storage for restored legacy payloads.
     */
    public function up(): void
    {
        $table = $this->table('members');
        $table->changeColumn('additional_info', 'text', [
            'default' => '{}',
            'null' => false,
        ]);
        $table->update();
    }

    /**
     * Revert additional info storage to the original string column.
     */
    public function down(): void
    {
        $table = $this->table('members');
        $table->changeColumn('additional_info', 'string', [
            'default' => '{}',
            'limit' => 255,
            'null' => false,
        ]);
        $table->update();
    }
}
