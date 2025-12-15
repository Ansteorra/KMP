<?php

declare(strict_types=1);

use Migrations\AbstractMigration;

class AddGridViewKeyToGridViewPreferences extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('grid_view_preferences');

        if (!$table->hasColumn('grid_view_key')) {
            $table
                ->addColumn('grid_view_key', 'string', [
                    'limit' => 100,
                    'null' => true,
                    'default' => null,
                    'comment' => 'Preferred system view key (string); supports system views by name',
                ])
                ->update();
        }
    }
}
