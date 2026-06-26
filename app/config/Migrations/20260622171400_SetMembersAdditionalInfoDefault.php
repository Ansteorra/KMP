<?php

declare(strict_types=1);

use Migrations\BaseMigration;

class SetMembersAdditionalInfoDefault extends BaseMigration
{
    /**
     * Ensure member additional info defaults to an empty JSON object.
     */
    public function up(): void
    {
        $this->execute("UPDATE members SET additional_info = '{}' WHERE additional_info IS NULL");

        $table = $this->table('members');
        $table->changeColumn('additional_info', 'text', [
            'default' => '{}',
            'null' => false,
        ]);
        $table->update();
    }

    /**
     * Revert only this follow-up default adjustment.
     */
    public function down(): void
    {
        $table = $this->table('members');
        $table->changeColumn('additional_info', 'text', [
            'default' => null,
            'null' => false,
        ]);
        $table->update();
    }
}
