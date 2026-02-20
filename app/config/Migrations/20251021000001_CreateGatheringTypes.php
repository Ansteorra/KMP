<?php

declare(strict_types=1);

use Migrations\BaseMigration;

class CreateGatheringTypes extends BaseMigration
{
    public bool $autoId = false;
    /**
     * Create the `gathering_types` table with its columns and indexes.
     *
     * Defines columns: `id` (primary key, auto-increment integer), `name` (string, unique),
     * `description` (text), `clonable` (boolean), `modified` (datetime), `created` (datetime),
     * `created_by` (integer), `modified_by` (integer), and `deleted` (datetime), then adds a
     * primary key on `id` and a unique index on `name` before creating the table.
     */
    public function change(): void
    {
        $table = $this->table('gathering_types', ['id' => false]);
        $table->addColumn("id", "integer", [
            "autoIncrement" => true,
            "default" => null,
            "limit" => 11,
            "null" => false,
        ]);
        // Basic information
        $table->addColumn('name', 'string', [
            'default' => null,
            'limit' => 255,
            'null' => false,
            'comment' => 'Name of the gathering type (e.g., Tournament, Practice, Feast)'
        ]);
        $table->addColumn('description', 'text', [
            'default' => null,
            'null' => true,
            'comment' => 'Description of this gathering type'
        ]);
        $table->addColumn('clonable', 'boolean', [
            'default' => true,
            'null' => false,
            'comment' => 'Whether this type can be used as a template for new gatherings'
        ]);

        $table->addColumn("modified", "datetime", [
            "default" => null,
            "limit" => null,
            "null" => true,
        ]);
        $table->addColumn("created", "datetime", [
            "default" => null,
            "limit" => null,
            "null" => false,
        ]);
        $table->addColumn("created_by", "integer", [
            "default" => null,
            "limit" => null,
            "null" => true,
        ]);
        $table->addColumn("modified_by", "integer", [
            "default" => null,
            "limit" => null,
            "null" => true,
        ]);
        $table->addColumn("deleted", "datetime", [
            "default" => null,
            "limit" => null,
            "null" => true,
        ]);

        // Indexes
        $table->addPrimaryKey(["id"]);
        $table->addIndex(['name'], ['name' => 'idx_gathering_types_name', 'unique' => true]);

        $table->create();
    }
}