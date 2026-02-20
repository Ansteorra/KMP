<?php

declare(strict_types=1);

use Migrations\BaseMigration;


class RecommendationStates extends BaseMigration
{
    public bool $autoId = false;
    /**
     * Change Method.
     *
     * More information on this method is available here:
     * https://book.cakephp.org/phinx/0/en/migrations.html#the-change-method
     * @return void
     */
    public function change(): void
    {
        $this->table("awards_recommendations_states_logs", ['id' => false])
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
            ->addColumn("from_state", "string", [
                "default" => null,
                "limit" => 255,
                "null" => false,
            ])
            ->addColumn("to_state", "string", [
                "default" => null,
                "limit" => 255,
                "null" => false,
            ])
            ->addColumn("from_status", "string", [
                "default" => null,
                "limit" => 255,
                "null" => false,
            ])
            ->addColumn("to_status", "string", [
                "default" => null,
                "limit" => 255,
                "null" => false,
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
            ->addPrimaryKey(["id"])
            ->create();

        $this->table('awards_recommendations')
            ->addColumn('close_reason',  "text", [
                "default" => null,
                "limit" => null,
                "null" => true,
            ])
            ->addColumn("state", "string", [
                "default" => null,
                "limit" => 255,
                "null" => false,
            ])
            ->renameColumn('status_date', 'state_date')
            ->update();

        $this->table("awards_recommendations_states_logs")
            ->addForeignKey("recommendation_id", "awards_recommendations", "id", [])
            ->update();
    }
}