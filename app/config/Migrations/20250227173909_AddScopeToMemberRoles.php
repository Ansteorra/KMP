<?php

declare(strict_types=1);

use Migrations\BaseMigration;

class AddScopeToMemberRoles extends BaseMigration
{
    /**
     * Change Method.
     *
     * More information on this method is available here:
     * https://book.cakephp.org/migrations/4/en/migrations.html#the-change-method
     * @return void
     */
    public function change(): void
    {
        $table = $this->table('member_roles');
        $table->addColumn("branch_id", "integer", [
            "default" => null,
            "limit" => 11,
            "null" => true,
        ])
            ->update();

        $table = $this->table('member_roles');
        $table->addForeignKey("branch_id", "branches", "id", [
            "update" => "NO_ACTION",
            "delete" => "NO_ACTION",
        ])
            ->update();

        $table = $this->table('permissions');
        $table->addColumn("scoping_rule", "string", [
            "default" => "Global",
            "limit" => 255,
            "null" => false,
        ])
            ->update();

        //update existing permissions to have no scoping
        $this->execute("UPDATE permissions SET scoping_rule = 'Global'");
        $table = $this->table('permissions');
    }
}