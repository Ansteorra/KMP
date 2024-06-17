<?php

declare(strict_types=1);

use Migrations\AbstractMigration;
use Cake\ORM\TableRegistry;

require_once __DIR__ . '/../Seeds/InitActivitiesSeed.php';

class InitActivities extends AbstractMigration
{
    public bool $autoId = false;
    /**
     * Change Method.
     *
     * More information on this method is available here:
     * https://book.cakephp.org/phinx/0/en/migrations.html#the-change-method
     * @return void
     */
    public function up(): void
    {

        $this->table("activity_groups")
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

        $this->table("activities")
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
            ->addColumn("term_length", "integer", [
                "default" => null,
                "limit" => 11,
                "null" => false,
            ])
            ->addColumn("activity_group_id", "integer", [
                "default" => null,
                "limit" => 11,
                "null" => false,
            ])
            ->addColumn("grants_role_id", "integer", [
                "default" => null,
                "limit" => 11,
                "null" => true,
            ])
            ->addColumn("minimum_age", "integer", [
                "default" => null,
                "limit" => 2,
                "null" => true,
            ])
            ->addColumn("maximum_age", "integer", [
                "default" => null,
                "limit" => 2,
                "null" => true,
            ])
            ->addColumn("num_required_authorizors", "integer", [
                "default" => 1,
                "limit" => 2,
                "null" => false,
            ])
            ->addColumn("num_required_renewers", "integer", [
                "default" => 1,
                "limit" => 2,
                "null" => false,
            ])
            ->addColumn("permission_id", "integer", [
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
            ->addColumn("deleted", "datetime", [
                "default" => null,
                "limit" => null,
                "null" => true,
            ])
            ->addPrimaryKey(["id"])
            ->addIndex(["name"], ["unique" => true])
            ->addIndex(["activity_group_id"])
            ->addIndex(["deleted"])
            ->create();

        $this->table("authorizations")
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
            ->addColumn("activity_id", "integer", [
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
            ->addColumn("created", "timestamp", [
                "default" => "CURRENT_TIMESTAMP",
                "limit" => null,
                "null" => false,
            ])
            ->addColumn("approval_count", "integer", [
                "default" => 0,
                "limit" => 11,
                "null" => false,
            ])
            ->addColumn("status", "string", [
                "default" => "pending",
                "limit" => 20,
                "null" => false,
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
            ->addColumn("is_renewal", "boolean", [
                "default" => false,
                "limit" => null,
                "null" => false,
            ])
            ->addPrimaryKey(["id"])
            ->addIndex(["activity_id"])
            ->addIndex(["member_id"])
            ->addIndex(["start_on"])
            ->addIndex(["expires_on"])
            ->create();

        $this->table("authorization_approvals")
            ->addColumn("id", "integer", [
                "autoIncrement" => true,
                "default" => null,
                "limit" => 11,
                "null" => false,
            ])
            ->addColumn("authorization_id", "integer", [
                "default" => null,
                "limit" => 11,
                "null" => false,
            ])
            ->addColumn("approver_id", "integer", [
                "default" => null,
                "limit" => 11,
                "null" => false,
            ])
            ->addColumn("authorization_token", "string", [
                "default" => null,
                "limit" => 255,
                "null" => false,
            ])
            ->addColumn("requested_on", "datetime", [
                "default" => null,
                "limit" => null,
                "null" => false,
            ])
            ->addColumn("responded_on", "datetime", [
                "default" => null,
                "limit" => null,
                "null" => true,
            ])
            ->addColumn("approved", "boolean", [
                "default" => false,
                "limit" => null,
                "null" => false,
            ])
            ->addColumn("approver_notes", "string", [
                "default" => null,
                "limit" => 255,
                "null" => true,
            ])
            ->addPrimaryKey(["id"])
            ->addIndex(["approver_id"])
            ->addIndex(["authorization_id"])
            ->create();

        $this->table("activities")
            ->addForeignKey(
                "activity_group_id",
                "activity_groups",
                "id",
                [
                    "update" => "NO_ACTION",
                    "delete" => "NO_ACTION",
                ],
            )

            ->addForeignKey(
                "permission_id",
                "permissions",
                "id",
                [
                    "update" => "NO_ACTION",
                    "delete" => "CASCADE",
                ],
            )
            ->update();

        $this->table("authorizations")

            ->addForeignKey(
                "activity_id",
                "activities",
                "id",
                [
                    "update" => "NO_ACTION",
                    "delete" => "CASCADE",
                ],
            )
            ->addForeignKey("member_id", "members", "id", [
                "update" => "NO_ACTION",
                "delete" => "CASCADE",
            ])
            ->addForeignKey("granted_member_role_id", "member_roles", "id", [
                "update" => "NO_ACTION",
                "delete" => "CASCADE",
            ])
            ->update();

        $this->table("authorization_approvals")
            ->addForeignKey("authorization_id", "authorizations", "id", [
                "update" => "NO_ACTION",
                "delete" => "CASCADE",
            ])
            ->addForeignKey("approver_id", "members", "id", [
                "update" => "NO_ACTION",
                "delete" => "CASCADE",
            ])
            ->update();

        (new InitActivitiesSeed())
            ->setAdapter($this->getAdapter())
            ->setInput($this->getInput())
            ->setOutput($this->getOutput())
            ->run();
    }

    public function down()
    {
        $this->table("authorization_approvals")
            ->dropForeignKey("authorization_id")
            ->dropForeignKey("approver_id")
            ->save();

        $this->table("authorizations")
            ->dropForeignKey("activity_id")
            ->dropForeignKey("member_id")
            ->dropForeignKey("granted_member_role_id")
            ->save();

        $this->table("activities")
            ->dropForeignKey("activity_group_id")
            ->dropForeignKey("permission_id")
            ->save();

        $this->table("authorization_approvals")->drop()->save();
        $this->table("authorizations")->drop()->save();
        $this->table("activities")->drop()->save();
        $this->table("activity_groups")->drop()->save();

        $permissionsTbl = TableRegistry::getTableLocator()->get("Permissions");
        $permissionsTbl->deleteAll(["name" => "Can Manage Activities"]);
        $permissionsTbl->deleteAll(["name" => "Can Revoke Authorizations"]);
        $permissionsTbl->deleteAll(["name" => "Can Manage Authorization Queues"]);
    }
}