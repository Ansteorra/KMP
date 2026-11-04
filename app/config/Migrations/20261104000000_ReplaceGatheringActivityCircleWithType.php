<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class ReplaceGatheringActivityCircleWithType extends BaseMigration
{
    /** @return void */
    public function up(): void
    {
        $this->table('gathering_activities')
            ->addColumn('activity_type', 'string', ['limit' => 32, 'default' => 'General', 'null' => false])
            ->update();
        $this->execute("UPDATE gathering_activities SET activity_type = 'Order Circle' WHERE is_circle = TRUE");
        $this->table('gathering_activities')->removeColumn('is_circle')->update();
    }

    /** @return void */
    public function down(): void
    {
        $this->table('gathering_activities')
            ->addColumn('is_circle', 'boolean', ['default' => false, 'null' => false])
            ->update();
        $this->execute("UPDATE gathering_activities SET is_circle = TRUE WHERE activity_type = 'Order Circle'");
        $this->table('gathering_activities')->removeColumn('activity_type')->update();
    }
}
