<?php

declare(strict_types=1);

use Migrations\BaseMigration;

class AddPreregisterClosesOnToGatherings extends BaseMigration
{
    /**
     * Add the date pre-registration closes. When set, the pre-register link is
     * only offered up to and including this date; when null, pre-registration
     * stays open until the event itself.
     *
     * @return void
     */
    public function change(): void
    {
        $this->table('gatherings')
            ->addColumn('preregister_closes_on', 'date', [
                'default' => null,
                'null' => true,
                'comment' => 'Date pre-registration closes; null means open until the event',
            ])
            ->update();
    }
}
