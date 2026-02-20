<?php

declare(strict_types=1);

use Migrations\BaseMigration;
use Migrations\Migration\ManagerFactory;

require_once __DIR__ . '/../Seeds/InitWarrantsSeed.php';

class Warrants extends BaseMigration
{
    public bool $autoId = false;
    /** Disable transaction wrapping so embedded seed failures don't roll back DDL on Postgres. */
    public function useTransactions(): bool
    {
        return false;
    }
    /**
     * Change Method.
     *
     * More information on this method is available here:
     * https://book.cakephp.org/phinx/0/en/migrations.html#the-change-method
     * @return void
     */
    public function up()
    {
        $this->table('warrant_periods', ['id' => false])
            ->addColumn("id", "integer", [
                "autoIncrement" => true,
                "default" => null,
                "limit" => 11,
                "null" => false,
            ])
            ->addColumn('start_date',  "date", [
                "default" => null,
                "limit" => null,
                "null" => false,
            ])
            ->addColumn('end_date',  "date", [
                "default" => null,
                "limit" => null,
                "null" => false,
            ])
            ->addColumn('created',  "datetime", [
                "default" => null,
                "limit" => null,
                "null" => false,
            ])
            ->addColumn("created_by", "integer", [
                "default" => null,
                "limit" => null,
                "null" => true,
            ])
            ->addPrimaryKey(["id"])
            ->create();

        $this->table("warrants", ['id' => false])
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
            ->addColumn("member_id", "integer", [
                "default" => null,
                "limit" => 11,
                "null" => false,
            ])
            ->addColumn("warrant_roster_id", "integer", [
                "default" => null,
                "limit" => 11,
                "null" => false,
            ])
            ->addColumn("entity_type", "string", [
                "default" => null,
                "limit" => 255,
                "null" => true,
            ])
            ->addColumn("entity_id", "integer", [
                "default" => null,
                "limit" => 11,
                "null" => false,
            ])
            ->addColumn("member_role_id", "integer", [
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
            ->addColumn("approved_date", "datetime", [
                "default" => null,
                "limit" => null,
                "null" => true,
            ])
            ->addColumn("status", "string", [
                "default" => "Pending",
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
            ->addIndex(["entity_id"])
            ->addIndex(["entity_type"])
            ->addIndex(["start_on"])
            ->addIndex(["expires_on"])
            ->create();

        $this->table("warrant_rosters", ['id' => false])
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
            ->addColumn("approvals_required", "integer", [
                "default" => null,
                "limit" => 11,
                "null" => false,
            ])
            ->addColumn("approval_count", "integer", [
                "default" => null,
                "limit" => 11,
                "null" => true,
            ])
            ->addColumn("status", "string", [
                "default" => "Pending",
                "limit" => 20,
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
            ->addPrimaryKey(["id"])
            ->create();

        $this->table("warrant_roster_approvals", ['id' => false])
            ->addColumn("id", "integer", [
                "autoIncrement" => true,
                "default" => null,
                "limit" => 11,
                "null" => false,
            ])
            ->addColumn("warrant_roster_id", "integer", [
                "default" => null,
                "limit" => 11,
                "null" => false,
            ])
            ->addColumn("approver_id", "integer", [
                "default" => null,
                "limit" => 11,
                "null" => false,
            ])
            ->addColumn("approved_on", "datetime", [
                "default" => null,
                "limit" => null,
                "null" => true,
            ])
            ->addPrimaryKey(["id"])
            ->addIndex(["approver_id"])
            ->create();

        $this->table("warrants")
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
                "member_role_id",
                "member_roles",
                "id",
                [
                    "update" => "NO_ACTION",
                    "delete" => "NO_ACTION",
                ],
            )

            ->addForeignKey(
                "warrant_roster_id",
                "warrant_rosters",
                "id",
                [
                    "update" => "NO_ACTION",
                    "delete" => "NO_ACTION",
                ],
            )
            ->update();

        $this->table("warrant_roster_approvals")
            ->addForeignKey(
                "warrant_roster_id",
                "warrant_rosters",
                "id",
                [
                    "update" => "NO_ACTION",
                    "delete" => "NO_ACTION",
                ],
            )
            ->addForeignKey(
                "approver_id",
                "members",
                "id",
                [
                    "update" => "NO_ACTION",
                    "delete" => "NO_ACTION",
                ],
            )
            ->update();

        [$pluginName, $seeder] = pluginSplit("InitWarrantsSeed");
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
        $this->table("warrant_periods")->drop()->save();
        $this->table("warrants")->drop()->save();
        $this->table("warrant_roster_approvals")->drop()->save();
        $this->table("warrants_approval_sets")->drop()->save();
    }
}