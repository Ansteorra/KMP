<?php

declare(strict_types=1);

use Migrations\BaseMigration;

class AddWebsiteUrlToGatherings extends BaseMigration
{
    /**
     * Add an event website URL so the public kingdom calendar can show the
     * event's web link inline (issue #59).
     *
     * @return void
     */
    public function change(): void
    {
        $this->table('gatherings')
            ->addColumn('website_url', 'string', [
                'default' => null,
                'limit' => 512,
                'null' => true,
                'comment' => 'Public website / announcement URL for the event',
            ])
            ->update();
    }
}
