<?php

declare(strict_types=1);

use Migrations\BaseMigration;

class AddNoActionReason extends BaseMigration
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
        $table = $this->table('awards_recommendations');
        $table->addColumn('no_action_reason', "text", [
            "default" => null,
            "limit" => null,
            "null" => true,
        ]);
        $table->update();
    }
}
