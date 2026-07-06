<?php

declare(strict_types=1);

use Migrations\BaseMigration;

class AddPreregisterUrlToGatherings extends BaseMigration
{
    /**
     * Add a pre-registration URL so public event pages can link attendees to
     * the (currently external) pre-registration and payment process.
     *
     * @return void
     */
    public function change(): void
    {
        $this->table('gatherings')
            ->addColumn('preregister_url', 'string', [
                'default' => null,
                'limit' => 512,
                'null' => true,
                'comment' => 'External pre-registration / payment URL shown on the public event page',
            ])
            ->update();
    }
}
