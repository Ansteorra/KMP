<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class AddGatheringActivityDisplayOrder extends BaseMigration
{
    /** @return void */
    public function change(): void
    {
        $this->table('gathering_activities')
            ->addColumn('display_order', 'integer', ['default' => 100, 'null' => false])
            ->addIndex(['display_order', 'name'])
            ->update();
    }
}
