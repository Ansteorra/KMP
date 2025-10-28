<?php

declare(strict_types=1);

use Migrations\BaseMigration;

class AddGatheringIdToRecommendations extends BaseMigration
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
        $table = $this->table('awards_recommendations');
        $table->addColumn('gathering_id', 'integer', [
            'default' => null,
            'limit' => 11,
            'null' => true,
            'after' => 'event_id',
        ]);
        $table->addIndex([
            'gathering_id',
        ], [
            'name' => 'BY_GATHERING_ID',
            'unique' => false,
        ]);
        $table->addForeignKey(
            'gathering_id',
            'gatherings',
            'id',
            [
                'delete' => 'SET_NULL',
                'update' => 'CASCADE',
                'constraint' => 'fk_recommendations_gathering_id'
            ]
        );
        $table->update();
    }
}