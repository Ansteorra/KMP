<?php

use Migrations\AbstractMigration;

require_once __DIR__ . '/../Seeds/InitSeed.php';

class Init extends AbstractMigration
{
    public bool $autoId = false;

    public function up()
    {
        #region Configuration Schema

        $this->table("branches")
            ->addColumn("id", "integer", [
                "autoIncrement" => true,
                "default" => null,
                "limit" => 11,
                "null" => false,
            ])
            ->addColumn("name", "string", [
                "default" => null,
                "limit" => 40,
                "null" => false,
            ])
            ->addColumn("location", "string", [
                "default" => null,
                "limit" => 40,
                "null" => false,
            ])
            ->addColumn("parent_id", "integer", [
                "default" => null,
                "limit" => 11,
                "null" => true,
                "signed" => true,
            ])
            ->addColumn("lft", "integer", [
                "default" => null,
                "limit" => 11,
                "null" => true,
                "signed" => true,
            ])
            ->addColumn("rght", "integer", [
                "default" => null,
                "limit" => 11,
                "null" => true,
                "signed" => true,
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
            ->addIndex(["parent_id"])
            ->addIndex(["lft"])
            ->addIndex(["rght"])
            ->addIndex(["deleted"])
            ->create();

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

        $this->table("roles")
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

        $this->table("permissions")
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
            ->addColumn("activity_id", "integer", [
                "default" => null,
                "limit" => 11,
                "null" => true,
            ])
            ->addColumn("require_active_membership", "boolean", [
                "default" => false,
                "limit" => null,
                "null" => false,
            ])
            ->addColumn("require_active_background_check", "boolean", [
                "default" => false,
                "limit" => null,
                "null" => false,
            ])
            ->addColumn("require_min_age", "integer", [
                "default" => 0,
                "limit" => 2,
                "null" => false,
            ])
            ->addColumn("system", "boolean", [
                "default" => false,
                "limit" => null,
                "null" => false,
            ])
            ->addColumn("is_super_user", "boolean", [
                "default" => false,
                "limit" => null,
                "null" => false,
            ])
            ->addColumn("requires_warrant", "boolean", [
                "default" => false,
                "limit" => null,
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
            ->addIndex(["activity_id"])
            ->addIndex(["deleted"])
            ->create();

        $this->table("roles_permissions")
            ->addColumn("id", "integer", [
                "autoIncrement" => true,
                "default" => null,
                "limit" => 11,
                "null" => false,
            ])
            ->addColumn("permission_id", "integer", [
                "default" => null,
                "limit" => 11,
                "null" => false,
            ])
            ->addColumn("role_id", "integer", [
                "default" => null,
                "limit" => 11,
                "null" => false,
            ])
            ->addColumn("created", "timestamp", [
                "default" => "CURRENT_TIMESTAMP",
                "limit" => null,
                "null" => false,
            ])
            ->addColumn("created_by", "integer", [
                "default" => null,
                "limit" => null,
                "null" => false,
            ])
            ->addPrimaryKey(["id"])
            ->create();

        $this->table("app_settings")
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
            ->addColumn("value", "string", [
                "default" => null,
                "limit" => 255,
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
            ->addIndex(["name"], ["unique" => true])
            ->create();
        #endregion //
        #region Operational Tables
        $this->table("notes")
            ->addColumn("id", "integer", [
                "autoIncrement" => true,
                "default" => null,
                "limit" => 11,
                "null" => false,
            ])
            ->addColumn("author_id", "integer", [
                "default" => null,
                "limit" => 11,
                "null" => false,
            ])
            ->addColumn("created", "timestamp", [
                "default" => "CURRENT_TIMESTAMP",
                "limit" => null,
                "null" => false,
            ])
            ->addColumn("topic_model", "string", [
                "default" => null,
                "limit" => 255,
                "null" => true,
            ])
            ->addColumn("topic_id", "integer", [
                "default" => null,
                "limit" => 11,
                "null" => false,
            ])
            ->addColumn("subject", "string", [
                "default" => null,
                "limit" => 255,
                "null" => true,
            ])
            ->addColumn("body", "text", [
                "default" => null,
                "limit" => null,
                "null" => true,
            ])
            ->addColumn("private", "boolean", [
                "default" => false,
                "limit" => null,
                "null" => false,
            ])
            ->addPrimaryKey(["id"])
            ->addIndex(["topic_id"])
            ->addIndex(["topic_model"])
            ->create();

        $this->table("members")
            ->addColumn("id", "integer", [
                "autoIncrement" => true,
                "default" => null,
                "limit" => 11,
                "null" => false,
            ])
            ->addColumn("password", "string", [
                "default" => null,
                "limit" => 512,
                "null" => false,
            ])
            ->addColumn("sca_name", "string", [
                "default" => null,
                "limit" => 50,
                "null" => false,
            ])
            ->addColumn("first_name", "string", [
                "default" => null,
                "limit" => 30,
                "null" => false,
            ])
            ->addColumn("middle_name", "string", [
                "default" => null,
                "limit" => 30,
                "null" => true,
            ])
            ->addColumn("last_name", "string", [
                "default" => null,
                "limit" => 30,
                "null" => false,
            ])
            ->addColumn("street_address", "string", [
                "default" => null,
                "limit" => 75,
                "null" => true,
            ])
            ->addColumn("city", "string", [
                "default" => null,
                "limit" => 30,
                "null" => true,
            ])
            ->addColumn("state", "string", [
                "default" => null,
                "limit" => 2,
                "null" => true,
            ])
            ->addColumn("zip", "string", [
                "default" => null,
                "limit" => 5,
                "null" => true,
            ])
            ->addColumn("phone_number", "string", [
                "default" => null,
                "limit" => 15,
                "null" => true,
            ])
            ->addColumn("email_address", "string", [
                "default" => null,
                "limit" => 50,
                "null" => false,
            ])
            ->addColumn("membership_number", "string", [
                "default" => null,
                "limit" => 50,
                "null" => true,
            ])
            ->addColumn("membership_expires_on", "date", [
                "default" => null,
                "limit" => null,
                "null" => true,
            ])
            ->addColumn("branch_id", "integer", [
                "default" => null,
                "limit" => 11,
                "null" => true,
            ])
            ->addColumn("background_check_expires_on", "date", [
                "default" => null,
                "limit" => null,
                "null" => true,
            ])
            ->addColumn("status", "string", [
                "default" => "active",
                "limit" => 20,
                "null" => true,
            ])
            ->addColumn("verified_date", "datetime", [
                "default" => null,
                "limit" => null,
                "null" => true,
            ])
            ->addColumn("verified_by", "integer", [
                "default" => null,
                "limit" => 11,
                "null" => true,
            ])
            ->addColumn("parent_id", "integer", [
                "default" => null,
                "limit" => 11,
                "null" => true,
            ])
            ->addColumn("password_token", "string", [
                "default" => null,
                "limit" => 255,
                "null" => true,
            ])
            ->addColumn("password_token_expires_on", "datetime", [
                "default" => null,
                "limit" => null,
                "null" => true,
            ])
            ->addColumn("last_login", "datetime", [
                "default" => null,
                "limit" => null,
                "null" => true,
            ])
            ->addColumn("last_failed_login", "datetime", [
                "default" => null,
                "limit" => null,
                "null" => true,
            ])
            ->addColumn("failed_login_attempts", "integer", [
                "default" => null,
                "limit" => 2,
                "null" => true,
            ])
            ->addColumn("birth_month", "integer", [
                "default" => null,
                "limit" => 11,
                "null" => true,
            ])
            ->addColumn("birth_year", "integer", [
                "default" => null,
                "limit" => 11,
                "null" => true,
            ])
            ->addColumn("membership_card_path", "string", [
                "default" => null,
                "limit" => 256,
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
            ->addIndex(["branch_id"])
            ->addIndex(["email_address"], ["unique" => true])
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
                "default" => "new",
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

        $this->table("member_roles")
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
            ->addColumn("role_id", "integer", [
                "default" => null,
                "limit" => 11,
                "null" => false,
            ])
            ->addColumn("expires_on", "datetime", [
                "default" => null,
                "limit" => null,
                "null" => true,
            ])
            ->addColumn("start_on", "datetime", [
                "default" => "CURRENT_TIMESTAMP",
                "limit" => null,
                "null" => false,
            ])
            ->addColumn("granting_model", "string", [
                "default" => null,
                "limit" => 255,
                "null" => true,
            ])
            ->addColumn("granting_id", "integer", [
                "default" => null,
                "limit" => null,
                "null" => true,
            ])
            ->addColumn("approver_id", "integer", [
                "default" => null,
                "limit" => 11,
                "null" => false,
            ])
            ->addColumn("revoker_id", "integer", [
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
            ->addIndex(["member_id"])
            ->addIndex(["role_id"])
            ->addIndex(["approver_id"])
            ->addIndex(["start_on"])
            ->addIndex(["expires_on"])
            ->addIndex(["granting_id"])
            ->addIndex(["granting_model"])
            ->create();
        #endregion
        #region Relationships

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

        $this->table("members")
            ->addForeignKey("branch_id", "branches", "id", [
                "update" => "NO_ACTION",
                "delete" => "NO_ACTION",
            ])
            ->update();

        $this->table("member_roles")
            ->addForeignKey("member_id", "members", "id", [
                "update" => "NO_ACTION",
                "delete" => "CASCADE",
            ])
            ->addForeignKey("role_id", "roles", "id", [
                "update" => "NO_ACTION",
                "delete" => "CASCADE",
            ])
            ->addForeignKey("approver_id", "members", "id", [
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

        $this->table("permissions")
            ->addForeignKey(
                "activity_id",
                "activities",
                "id",
                [
                    "update" => "NO_ACTION",
                    "delete" => "CASCADE",
                ],
            )
            ->update();

        $this->table("roles_permissions")
            ->addForeignKey("role_id", "roles", "id", [
                "update" => "NO_ACTION",
                "delete" => "CASCADE",
            ])
            ->addForeignKey("permission_id", "permissions", "id", [
                "update" => "NO_ACTION",
                "delete" => "CASCADE",
            ])
            ->update();
        #endregion
        (new InitSeed())
            ->setAdapter($this->getAdapter())
            ->setInput($this->getInput())
            ->setOutput($this->getOutput())
            ->run();
    }

    public function down()
    {
        $this->table("activities")
            ->dropForeignKey("activity_group_id")
            ->save();

        $this->table("authorizations")
            ->dropForeignKey("activity_id")
            ->dropForeignKey("granted_member_role_id")
            ->dropForeignKey("member_id")
            ->save();

        $this->table("members")->dropForeignKey("branch_id")->save();

        $this->table("member_roles")
            ->dropForeignKey("member_id")
            ->dropForeignKey("role_id")
            ->save();

        $this->table("authorization_approvals")
            ->dropForeignKey("authorization_id")
            ->dropForeignKey("approver_id")
            ->save();
        $this->table("roles_permissions")->dropForeignKey("role_id")->save();

        $this->table("permissions")
            ->dropForeignKey("activity_id")
            ->save();

        $this->table("notes")->drop()->save();
        $this->table("roles_permissions")->drop()->save();
        $this->table("permissions")->drop()->save();
        $this->table("member_roles")->drop()->save();
        $this->table("authorizations")->drop()->save();
        $this->table("authorization_approvals")->drop()->save();
        $this->table("members")->drop()->save();
        $this->table("activities")->drop()->save();
        $this->table("branches")->drop()->save();
        $this->table("activity_groups")->drop()->save();
        $this->table("roles")->drop()->save();
        $this->table("app_settings")->drop()->save();
    }
}