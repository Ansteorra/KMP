<?php

declare(strict_types=1);

use Migrations\BaseMigration;

class AddIsActiveToAwards extends BaseMigration
{
    /**
     * Add an activity flag so retired awards can be hidden from new recommendation selection.
     */
    public function up(): void
    {
        $table = $this->table('awards_awards');
        $table
            ->addColumn('is_active', 'boolean', [
                'default' => true,
                'null' => false,
                'after' => 'branch_id',
            ])
            ->addIndex(['is_active'], [
                'name' => 'idx_awards_awards_is_active',
            ])
            ->update();

        $this->execute('UPDATE awards_awards SET is_active = 1');
    }

    /**
     * Remove the activity flag from awards.
     */
    public function down(): void
    {
        $table = $this->table('awards_awards');
        $table
            ->removeIndexByName('idx_awards_awards_is_active')
            ->removeColumn('is_active')
            ->update();
    }
}
