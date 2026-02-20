<?php

declare(strict_types=1);

use Migrations\BaseMigration;

class CreateBackups extends BaseMigration
{
    public function change(): void
    {
        $table = $this->table('backups');

        $table->addColumn('filename', 'string', [
            'limit' => 255,
            'null' => false,
        ]);
        $table->addColumn('size_bytes', 'biginteger', [
            'null' => true,
            'default' => null,
        ]);
        $table->addColumn('table_count', 'integer', [
            'null' => true,
            'default' => null,
        ]);
        $table->addColumn('row_count', 'integer', [
            'null' => true,
            'default' => null,
        ]);
        $table->addColumn('storage_type', 'string', [
            'limit' => 20,
            'null' => false,
            'default' => 'local',
        ]);
        $table->addColumn('status', 'string', [
            'limit' => 20,
            'null' => false,
            'default' => 'pending',
        ]);
        $table->addColumn('notes', 'text', [
            'null' => true,
            'default' => null,
        ]);
        $table->addColumn('created', 'datetime', [
            'null' => false,
        ]);
        $table->addColumn('modified', 'datetime', [
            'null' => false,
        ]);

        $table->addIndex(['status'], ['name' => 'backups_status']);
        $table->addIndex(['created'], ['name' => 'backups_created']);

        $table->create();
    }
}
