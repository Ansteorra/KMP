<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

class AddTitlePronounsPronunciationToMembers extends AbstractMigration
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
        $table = $this->table('members')
            ->addColumn("title", "string", [
                "default" => null,
                "limit" => 255,
                "null" => true,
            ])
            ->addColumn("pronouns", "string", [
                "default" => null,
                "limit" => 50,
                "null" => true,
            ])
            ->addColumn("pronunciation", "string", [
                "default" => null,
                "limit" => 255,
                "null" => true,
            ]);
        $table->update();
    }
}