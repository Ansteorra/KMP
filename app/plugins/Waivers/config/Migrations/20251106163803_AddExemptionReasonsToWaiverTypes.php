<?php

declare(strict_types=1);

use Migrations\BaseMigration;

class AddExemptionReasonsToWaiverTypes extends BaseMigration
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
        $table = $this->table('waivers_waiver_types');
        $table->addColumn('exemption_reasons', 'text', [
            'default' => null,
            'null' => true,
            'comment' => 'JSON array of valid reasons for why a waiver might not be required (e.g., ["No minors present", "Activity cancelled", "Virtual event"])'
        ]);
        $table->update();
    }
}
