<?php

declare(strict_types=1);

use Migrations\AbstractMigration;
use Cake\ORM\TableRegistry;

require_once __DIR__ . '/../Seeds/InitAwardsSeed.php';

class InitAwards extends AbstractMigration
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

        $this->table("awards_domains")
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

        $this->table("awards_levels")
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
            ->addColumn("progression_order", "integer", [
                "default" => null,
                "limit" => null,
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
            ->addIndex(["deleted"])
            ->create();

        $this->table("awards_awards")
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
            ->addColumn("description", "text", [
                "default" => null,
                "limit" => null,
                "null" => true,
            ])
            ->addColumn("insignia", "text", [
                "default" => null,
                "limit" => null,
                "null" => true,
            ])
            ->addColumn("badge", "text", [
                "default" => null,
                "limit" => null,
                "null" => true,
            ])
            ->addColumn("charter", "text", [
                "default" => null,
                "limit" => null,
                "null" => true,
            ])
            ->addColumn("domain_id", "integer", [
                "default" => null,
                "limit" => 11,
                "null" => false,
            ])
            ->addColumn("level_id", "integer", [
                "default" => null,
                "limit" => 11,
                "null" => false,
            ])
            ->addColumn("branch_id", "integer", [
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
            ->addIndex(["level_id"])
            ->addIndex(["domain_id"])
            ->addIndex(["branch_id"])
            ->addIndex(["deleted"])
            ->create();

        $this->table("awards_events")
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
            ->addColumn("description", "string", [
                "default" => null,
                "limit" => 255,
                "null" => false,
            ])
            ->addColumn("branch_id", "integer", [
                "default" => null,
                "limit" => 11,
                "null" => false,
            ])
            ->addColumn("start_date", "datetime", [
                "default" => null,
                "limit" => null,
                "null" => true,
            ])
            ->addColumn("end_date", "datetime", [
                "default" => null,
                "limit" => null,
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
            ->addIndex(["start_date"])
            ->addIndex(["end_date"])
            ->addIndex(["branch_id"])
            ->addIndex(["deleted"])
            ->create();

        $this->table("awards_recommendations")
            ->addColumn("id", "integer", [
                "autoIncrement" => true,
                "default" => null,
                "limit" => 11,
                "null" => false,
            ])
            ->addColumn("stack_rank", "integer", [
                "default" => null,
                "limit" => 11,
                "null" => false,
            ])
            ->addColumn("requester_id", "integer", [
                "default" => null,
                "limit" => 11,
                "null" => true,
            ])
            ->addColumn("member_id", "integer", [
                "default" => null,
                "limit" => 11,
                "null" => true,
            ])
            ->addColumn("branch_id", "integer", [
                "default" => null,
                "limit" => 11,
                "null" => true,
            ])
            ->addColumn("award_id", "integer", [
                "default" => null,
                "limit" => 11,
                "null" => false,
            ])
            ->addColumn("requester_sca_name", "string", [
                "default" => null,
                "limit" => 255,
                "null" => false,
            ])
            ->addColumn("member_sca_name", "string", [
                "default" => null,
                "limit" => 255,
                "null" => false,
            ])
            ->addColumn("contact_number", "string", [
                "default" => null,
                "limit" => 100,
                "null" => true,
            ])
            ->addColumn("contact_email", "string", [
                "default" => null,
                "limit" => 255,
                "null" => false,
            ])
            ->addColumn("reason", "text", [
                "default" => null,
                "limit" => null,
                "null" => true,
            ])
            ->addColumn("call_into_court", "string", [
                "default" => null,
                "limit" => 100,
                "null" => false,
            ])
            ->addColumn("court_availability", "string", [
                "default" => null,
                "limit" => 100,
                "null" => false,
            ])
            ->addColumn("status", "string", [
                "default" => "submitted",
                "limit" => 100,
                "null" => false,
            ])
            ->addColumn("status_date", "datetime", [
                "default" => null,
                "limit" => null,
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
            ->addIndex(["stack_rank"])
            ->addIndex(["deleted"])
            ->create();

        $this->table("awards_recommendations_events")
            ->addColumn("id", "integer", [
                "autoIncrement" => true,
                "default" => null,
                "limit" => 11,
                "null" => false,
            ])
            ->addColumn("recommendation_id", "integer", [
                "default" => null,
                "limit" => 11,
                "null" => false,
            ])
            ->addColumn("event_id", "integer", [
                "default" => null,
                "limit" => 11,
                "null" => false,
            ])
            ->addPrimaryKey(["id"])
            ->create();


        $this->table("awards_recommendations_events")
            ->addForeignKey(
                "recommendation_id",
                "awards_recommendations",
                "id",
                [
                    "update" => "NO_ACTION",
                    "delete" => "NO_ACTION",
                ],
            )
            ->addForeignKey(
                "event_id",
                "awards_events",
                "id",
                [
                    "update" => "NO_ACTION",
                    "delete" => "NO_ACTION",
                ],
            )
            ->update();


        $this->table("awards_awards")
            ->addForeignKey(
                "domain_id",
                "awards_domains",
                "id",
                [
                    "update" => "NO_ACTION",
                    "delete" => "NO_ACTION",
                ],
            )
            ->addForeignKey(
                "level_id",
                "awards_levels",
                "id",
                [
                    "update" => "NO_ACTION",
                    "delete" => "CASCADE",
                ],
            )
            ->addForeignKey(
                "branch_id",
                "branches",
                "id",
                [
                    "update" => "NO_ACTION",
                    "delete" => "CASCADE",
                ],
            )
            ->update();


        $this->table("awards_events")
            ->addForeignKey(
                "branch_id",
                "branches",
                "id",
                [
                    "update" => "NO_ACTION",
                    "delete" => "CASCADE",
                ],
            )
            ->update();


        $this->table("awards_recommendations")
            ->addForeignKey(
                "branch_id",
                "branches",
                "id",
                [
                    "update" => "NO_ACTION",
                    "delete" => "CASCADE",
                ],
            )
            ->addForeignKey(
                "requester_id",
                "members",
                "id",
                [
                    "update" => "NO_ACTION",
                    "delete" => "CASCADE",
                ],
            )
            ->addForeignKey(
                "member_id",
                "members",
                "id",
                [
                    "update" => "NO_ACTION",
                    "delete" => "CASCADE",
                ],
            )
            ->addForeignKey(
                "award_id",
                "awards_awards",
                "id",
                [
                    "update" => "NO_ACTION",
                    "delete" => "CASCADE",
                ],
            )
            ->update();

        (new InitAwardsSeed())
            ->setAdapter($this->getAdapter())
            ->setInput($this->getInput())
            ->setOutput($this->getOutput())
            ->run();
    }

    public function down()
    {
        $this->table("awards_recommendation_event")
            ->dropForeignKey("recommendation_id")
            ->dropForeignKey("event_id")
            ->save();

        $this->table("awards_awards")
            ->dropForeignKey("domain_id")
            ->dropForeignKey("level_id")
            ->dropForeignKey("branch_id")
            ->save();

        $this->table("awards_events")
            ->dropForeignKey("branch_id")
            ->save();

        $this->table("awards_recommendations")
            ->dropForeignKey("branch_id")
            ->dropForeignKey("requester_id")
            ->dropForeignKey("member_id")
            ->dropForeignKey("award_id")
            ->save();

        $this->table("awards_recommendation_event")->drop()->save();
        $this->table("awards_events")->drop()->save();
        $this->table("awards_awards")->drop()->save();
        $this->table("awards_domains")->drop()->save();
        $this->table("awards_levels")->drop()->save();

        $permissionsTbl = TableRegistry::getTableLocator()->get("Permissions");
        $permissionsTbl->deleteAll(["name" => "Can Manage Awards"]);
        $permissionsTbl->deleteAll(["name" => "Can View Recommendations"]);
        $permissionsTbl->deleteAll(["name" => "Can View Recommendations Reports"]);
    }
}