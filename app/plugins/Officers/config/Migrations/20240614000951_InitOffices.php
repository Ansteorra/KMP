<?php

declare(strict_types=1);

use Migrations\BaseMigration;
use Cake\ORM\TableRegistry;
use Migrations\Migration\ManagerFactory;

require_once __DIR__ . '/../Seeds/InitOfficersSeed.php';

class InitOffices extends BaseMigration
{
    public bool $autoId = false;
    /** Disable transaction wrapping so embedded seed failures don't roll back DDL on Postgres. */
    public function useTransactions(): bool
    {
        return false;
    }

    public function up(): void
    {
        $this->table("officers_departments", ['id' => false])
            ->addColumn("id", "integer", [
                "autoIncrement" => true,
                "default" => null,
                "limit" => 11,
                "null" => false,
            ])
            ->addColumn("name", "string", [
                "default" => null,
                "limit" => 255,
                "null" => false,
            ])
            ->addColumn("modified", "datetime", [
                "default" => null,
                "limit" => null,
                "null" => true,
            ])
            ->addColumn("created", "datetime", [
                "default" => null,
                "limit" => null,
                "null" => false,
            ])
            ->addColumn("created_by", "integer", [
                "default" => null,
                "limit" => null,
                "null" => true,
            ])
            ->addColumn("modified_by", "integer", [
                "default" => null,
                "limit" => null,
                "null" => true,
            ])
            ->addColumn("deleted", "datetime", [
                "default" => null,
                "limit" => null,
                "null" => true,
            ])
            ->addPrimaryKey(["id"])
            ->addIndex(["name"], ["unique" => true])
            ->addIndex(["deleted"])
            ->create();

        $this->table("officers_offices", ['id' => false])
            ->addColumn("id", "integer", [
                "autoIncrement" => true,
                "default" => null,
                "limit" => 11,
                "null" => false,
            ])
            ->addColumn("name", "string", [
                "default" => null,
                "limit" => 255,
                "null" => false,
            ])
            ->addColumn("department_id", "integer", [
                "default" => null,
                "limit" => 11,
                "null" => true,
            ])
            ->addColumn("requires_warrant", "boolean", [
                "default" => false,
                "limit" => null,
                "null" => false,
            ])
            ->addColumn("required_office", "boolean", [
                "default" => false,
                "limit" => null,
                "null" => false,
            ])
            ->addColumn("kingdom_only", "boolean", [
                "default" => false,
                "limit" => null,
                "null" => false,
            ])
            ->addColumn("can_skip_report", "boolean", [
                "default" => false,
                "limit" => null,
                "null" => false,
            ])
            ->addColumn("only_one_per_branch", "boolean", [
                "default" => false,
                "limit" => null,
                "null" => false,
            ])
            ->addColumn("deputy_to_id", "integer", [
                "default" => null,
                "limit" => 11,
                "null" => true,
            ])
            ->addColumn("grants_role_id", "integer", [
                "default" => null,
                "limit" => 11,
                "null" => true,
            ])
            ->addColumn("term_length", "integer", [
                "default" => null,
                "limit" => 11,
                "null" => false,
            ])
            ->addColumn("modified", "datetime", [
                "default" => null,
                "limit" => null,
                "null" => true,
            ])
            ->addColumn("created", "datetime", [
                "default" => null,
                "limit" => null,
                "null" => false,
            ])
            ->addColumn("created_by", "integer", [
                "default" => null,
                "limit" => null,
                "null" => true,
            ])
            ->addColumn("modified_by", "integer", [
                "default" => null,
                "limit" => null,
                "null" => true,
            ])
            ->addColumn("deleted", "datetime", [
                "default" => null,
                "limit" => null,
                "null" => true,
            ])
            ->addPrimaryKey(["id"])
            ->addIndex(["name"], ["unique" => true])
            ->addIndex(["department_id"])
            ->addIndex(["deleted"])
            ->create();

        $this->table("officers_officers", ['id' => false])
            ->addColumn("id", "integer", [
                "autoIncrement" => true,
                "default" => null,
                "limit" => 11,
                "null" => false,
            ])
            ->addColumn("member_id", "integer", [
                "default" => null,
                "limit" => 11,
                "null" => false,
            ])
            ->addColumn("branch_id", "integer", [
                "default" => null,
                "limit" => 11,
                "null" => false,
            ])
            ->addColumn("office_id", "integer", [
                "default" => null,
                "limit" => 11,
                "null" => false,
            ])
            ->addColumn("granted_member_role_id", "integer", [
                "default" => null,
                "limit" => 11,
                "null" => true,
            ])
            ->addColumn("expires_on", "datetime", [
                "default" => null,
                "limit" => null,
                "null" => true,
            ])
            ->addColumn("start_on", "datetime", [
                "default" => null,
                "limit" => null,
                "null" => true,
            ])
            ->addColumn("status", "string", [
                "default" => "new",
                "limit" => 20,
                "null" => false,
            ])
            ->addColumn("deputy_description", "string", [
                "default" => null,
                "limit" => 255,
                "null" => true,
            ])
            ->addColumn("revoked_reason", "string", [
                "default" => "",
                "limit" => 255,
                "null" => true,
            ])
            ->addColumn("revoker_id", "integer", [
                "default" => null,
                "limit" => 11,
                "null" => true,
            ])
            ->addColumn("approver_id", "integer", [
                "default" => null,
                "limit" => 11,
                "null" => false,
            ])
            ->addColumn("approval_date", "datetime", [
                "default" => null,
                "limit" => null,
                "null" => false,
            ])
            ->addColumn("reports_to_branch_id", "integer", [
                "default" => null,
                "limit" => 11,
                "null" => true,
            ])
            ->addColumn("reports_to_office_id", "integer", [
                "default" => null,
                "limit" => 11,
                "null" => true,
            ])
            ->addColumn("modified", "datetime", [
                "default" => null,
                "limit" => null,
                "null" => true,
            ])
            ->addColumn("created", "datetime", [
                "default" => null,
                "limit" => null,
                "null" => false,
            ])
            ->addColumn("created_by", "integer", [
                "default" => null,
                "limit" => null,
                "null" => true,
            ])
            ->addColumn("modified_by", "integer", [
                "default" => null,
                "limit" => null,
                "null" => true,
            ])
            ->addPrimaryKey(["id"])
            ->addIndex(["branch_id"])
            ->addIndex(["office_id"])
            ->addIndex(["member_id"])
            ->addIndex(["start_on"])
            ->addIndex(["expires_on"])
            ->create();


        $this->table("officers_offices")
            ->addForeignKey(
                "department_id",
                "officers_departments",
                "id",
                [
                    "update" => "NO_ACTION",
                    "delete" => "NO_ACTION",
                ],
            )
            ->addForeignKey(
                "grants_role_id",
                "roles",
                "id",
                [
                    "update" => "NO_ACTION",
                    "delete" => "NO_ACTION",
                ],
            )
            ->addForeignKey(
                "deputy_to_id",
                "officers_offices",
                "id",
                [
                    "update" => "NO_ACTION",
                    "delete" => "NO_ACTION",
                ],
            )
            ->update();
        $this->table("officers_officers")
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
                "branch_id",
                "branches",
                "id",
                [
                    "update" => "NO_ACTION",
                    "delete" => "NO_ACTION",
                ],
            )
            ->addForeignKey(
                "member_id",
                "members",
                "id",
                [
                    "update" => "NO_ACTION",
                    "delete" => "NO_ACTION",
                ],
            )
            ->addForeignKey(
                "office_id",
                "officers_offices",
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

        [$pluginName, $seeder] = pluginSplit("Officers.InitOfficersSeed");
        $adapter = $this->getAdapter();
        $connection = $adapter->getConnection()->configName();

        $factory = new ManagerFactory([
            'plugin' => $options['plugin'] ?? $pluginName ?? null,
            'source' => 'Seeds',
            'connection' => $options['connection'] ?? $connection,
        ]);
        $io = $this->getIo();
        assert($io !== null, 'Missing ConsoleIo instance');
        $manager = $factory->createManager($io);
        $manager->seed($seeder);
    }



    public function down()
    {
        $this->table("officers_offices")
            ->dropForeignKey(
                "department_id"
            )
            ->dropForeignKey(
                "grants_role_id"
            )
            ->dropForeignKey(
                "deputy_to_id"
            )
            ->save();
        $this->table("officers_officers")
            ->dropForeignKey(
                "branch_id"
            )
            ->dropForeignKey(
                "member_id"
            )
            ->dropForeignKey(
                "office_id"
            )
            ->dropForeignKey(
                "deputy_to_branch_id"
            )
            ->dropForeignKey(
                "deputy_to_office_id"
            )
            ->save();

        $this->table("officers_officers")->drop()->save();
        $this->table("officers_offices")->drop()->save();
        $this->table("officers_departments")->drop()->save();

        $permissionsTbl = TableRegistry::getTableLocator()->get("Permissions");
        $permissionsTbl->deleteAll(["name" => "Can Manage Offices"]);
        $permissionsTbl->deleteAll(["name" => "Can Manage Officers"]);
        $permissionsTbl->deleteAll(["name" => "Can Manage Departments"]);
        $permissionsTbl->deleteAll(["name" => "Can View Officer Reports"]);
    }
}
