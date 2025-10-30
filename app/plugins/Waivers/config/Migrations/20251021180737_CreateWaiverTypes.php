<?php

declare(strict_types=1);

use Migrations\BaseMigration;

class CreateWaiverTypes extends BaseMigration
{
    public bool $autoId = false;
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
            'comment' => 'Name of the waiver type (e.g., General Adult Waiver, Minor Waiver, Equestrian Waiver)'
        ]);
        $table->addColumn('description', 'text', [
            'default' => null,
            'null' => true,
            'comment' => 'Description of this waiver type'
        ]);
        $table->addColumn('template_path', 'string', [
            'default' => null,
            'limit' => 500,
            'null' => true,
            'comment' => 'URL or path to blank PDF template'
        ]);

        // Retention policy as JSON
        $table->addColumn('retention_policy', 'text', [
            'default' => null,
            'null' => false,
            'comment' => 'JSON: {"anchor": "gathering_end_date", "duration": {"years": 7, "months": 6, "days": 0}}'
        ]);

        // PDF conversion flag
        $table->addColumn('convert_to_pdf', 'boolean', [
            'default' => true,
            'null' => false,
            'comment' => 'Whether to convert uploaded waivers to PDF format'
        ]);

        // Active status
        $table->addColumn('is_active', 'boolean', [
            'default' => true,
            'null' => false,
            'comment' => 'Whether this waiver type is currently active'
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
        $table->addIndex(['name'], ['name' => 'idx_waiver_types_name', 'unique' => true]);
        $table->addIndex(['is_active'], ['name' => 'idx_waiver_types_active']);

        $table->create();
    }
}