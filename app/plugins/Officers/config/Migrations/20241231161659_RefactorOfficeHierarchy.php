<?php

declare(strict_types=1);

use Migrations\AbstractMigration;

class RefactorOfficeHierarchy extends AbstractMigration
{
    /**
     * Change Method.
     *
     * More information on this method is available here:
     * https://book.cakephp.org/phinx/0/en/migrations.html#the-change-method
     * @return void
     */
    public function change(): void
    {
        // add new columns to the offices table
        $table = $this->table('officers_offices');
        $table->addColumn('applicable_branch_types', 'string', [
            'default' => null,
            'limit' => 255,
            'null' => true,
        ]);
        $table->addColumn('reports_to_id', 'integer', [
            'default' => null,
            'limit' => 11,
            'null' => true,
        ]);
        $table->removeColumn('kingdom_only');
        $table->update();

        $this->table("officers_offices")
            ->addForeignKey(
                "reports_to_id",
                "officers_offices",
                "id",
                [
                    "update" => "NO_ACTION",
                    "delete" => "NO_ACTION",
                ],
            )
            ->update();

        $table = $this->table('officers_officers');
        $table->addColumn("reports_to_branch_id", "integer", [
            "default" => null,
            "limit" => 11,
            "null" => true,
        ]);
        $table->addColumn("reports_to_office_id", "integer", [
            "default" => null,
            "limit" => 11,
            "null" => true,
        ]);
        $table->update();

        $this->table("officers_offices")
            ->addForeignKey(
                "reports_to_branch_id",
                "branches",
                "id",
                [
                    "update" => "NO_ACTION",
                    "delete" => "NO_ACTION",
                ],
            )
            ->addForeignKey(
                "reports_to_office_id",
                "officers_offices",
                "id",
                [
                    "update" => "NO_ACTION",
                    "delete" => "NO_ACTION",
                ],
            )
            ->update();
    }
}