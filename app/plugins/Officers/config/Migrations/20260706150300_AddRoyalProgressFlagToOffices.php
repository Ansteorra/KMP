<?php

declare(strict_types=1);

use Migrations\BaseMigration;

class AddRoyalProgressFlagToOffices extends BaseMigration
{
    /**
     * Flag offices (Crown, Coronet, etc.) whose holders' gathering RSVPs
     * count as royal progress on the public kingdom calendar (issue #61).
     *
     * @return void
     */
    public function change(): void
    {
        $this->table('officers_offices')
            ->addColumn('is_royal_progress', 'boolean', [
                'default' => false,
                'limit' => null,
                'null' => false,
                'comment' => "Holders' event RSVPs are shown as royal progress on the public calendar",
            ])
            ->update();
    }
}
