<?php

declare(strict_types=1);

use Migrations\BaseMigration;

class AddPublishFlagToGathering extends BaseMigration
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
        $table = $this->table('gatherings')

            ->addColumn("published", "boolean", [
                "default" => false,
                "limit" => null,
                "null" => false,
                'comment' => 'Flag to indicate if the gathering is published or not',
            ])

            ->addColumn("published_by", "integer", [
                "default" => null,
                "limit" => 11,
                "null" => true,
                'comment' => 'The user who published the gathering',
            ])

            ->addColumn("published_on", "datetime", [
                "default" => null,
                "limit" => null,
                "null" => true,
                'comment' => 'The date and time when the gathering was published',
            ]);

        $table->addForeignKey('published_by', 'members', 'id', [
            'delete' => 'SET_NULL',
            'update' => 'NO_ACTION',
            'constraint' => 'fk_gatherings_published_by'
        ]);

        $table->addIndex(['published'], ['name' => 'idx_gatherings_published']);
        $table->addIndex(['published_on'], ['name' => 'idx_gatherings_published_on']);

        $table->update();
    }
}
