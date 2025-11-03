<?php

declare(strict_types=1);

use Migrations\BaseMigration;

class AddPublicPageEnabledToGatherings extends BaseMigration
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
        $table = $this->table('gatherings');
        $table->addColumn('public_page_enabled', 'boolean', [
            'default' => true,
            'null' => false,
            'comment' => 'Whether the public landing page is enabled for this gathering',
        ]);
        $table->update();
    }
}
