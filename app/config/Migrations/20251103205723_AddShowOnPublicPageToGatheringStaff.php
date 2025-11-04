<?php

declare(strict_types=1);

use Migrations\BaseMigration;

class AddShowOnPublicPageToGatheringStaff extends BaseMigration
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
        $table = $this->table('gathering_staff');
        $table->addColumn('show_on_public_page', 'boolean', [
            'default' => false,
            'null' => false,
            'after' => 'is_steward',
        ]);
        $table->update();
    }
}
