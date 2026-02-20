<?php

declare(strict_types=1);

use Migrations\BaseMigration;

class CreateGatheringActivities extends BaseMigration
{
    public bool $autoId = false;
    /**
     * Create the `gathering_activities` table with its columns and primary key.
     *
     * Defines columns for activity metadata (id, name, description), timestamps
     * (created, modified, deleted) and user reference fields (created_by, modified_by),
     * and sets `id` as the primary key.
     */
    public function change(): void
    {
        $table = $this->table('gathering_activities', ['id' => false]);
        $table->addColumn("id", "integer", [
            "autoIncrement" => true,
            "default" => null,
            "limit" => 11,
            "null" => false,
        ]);

        // Activity information
        $table->addColumn('name', 'string', [
            'default' => null,
            'limit' => 255,
            'null' => false,
            'comment' => 'Name of the activity (e.g., Heavy Combat, Archery, A&S Display)'
        ]);
        $table->addColumn('description', 'text', [
            'default' => null,
            'null' => true,
            'comment' => 'Description of the activity'
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
        $table->addIndex(['name'], ['name' => 'idx_gathering_activities_name', 'unique' => true]);

        $table->create();
    }
}