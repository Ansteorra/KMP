<?php

declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * Support bestowals scheduled for roaming court (no fixed Event Schedule slot).
 */
class AddRoamingCourtToBestowals extends BaseMigration
{
    /**
     * @return void
     */
    public function up(): void
    {
        $this->table('awards_bestowals')
            ->addColumn('roaming_court', 'boolean', [
                'default' => false,
                'null' => false,
                'comment' => 'Award will be given during the event without a fixed court session',
            ])
            ->update();
    }

    /**
     * @return void
     */
    public function down(): void
    {
        $this->table('awards_bestowals')
            ->removeColumn('roaming_court')
            ->update();
    }
}
