<?php

declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * Remove member_id from gathering_waivers table
 *
 * The member_id field is being removed as it is not needed for the waiver system.
 * Waivers are associated with gatherings and activities, not individual members.
 */
class RemoveMemberIdFromGatheringWaivers extends BaseMigration
{
    /**
     * Change Method.
     *
     * Removes the member_id column, its index, and foreign key constraint
     * from the waivers_gathering_waivers table.
     *
     * @return void
     */
    public function change(): void
    {
        $table = $this->table('waivers_gathering_waivers');

        // Remove foreign key constraint first
        $table->dropForeignKey('member_id');

        // Remove index
        $table->removeIndex(['member_id']);

        // Remove column
        $table->removeColumn('member_id');

        $table->update();
    }
}
