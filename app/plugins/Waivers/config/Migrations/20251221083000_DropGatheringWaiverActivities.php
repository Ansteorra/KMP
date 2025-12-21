<?php

declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * DropGatheringWaiverActivities Migration
 *
 * Removes the waivers_gathering_waiver_activities join table now that activity
 * associations are no longer maintained.
 */
class DropGatheringWaiverActivities extends BaseMigration
{
    public function change(): void
    {
        $table = $this->table('waivers_gathering_waiver_activities');
        if ($table->exists()) {
            $table->drop()->save();
        }
    }
}
