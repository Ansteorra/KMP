<?php

declare(strict_types=1);

use Migrations\BaseMigration;

class AddDeletedToGatheringActivityWaiversUniqueIndex extends BaseMigration
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
        $table = $this->table('waivers_gathering_activity_waivers');

        // Remove the old unique index that doesn't account for soft deletes
        $table->removeIndexByName('idx_gathering_activity_waivers_unique');

        // Add new unique index that includes deleted field
        // This allows the same gathering_activity_id + waiver_type_id combination
        // if one is soft-deleted (deleted IS NOT NULL)
        $table->addIndex(['gathering_activity_id', 'waiver_type_id', 'deleted'], [
            'name' => 'idx_gathering_activity_waivers_unique',
            'unique' => true
        ]);

        $table->update();
    }
}
