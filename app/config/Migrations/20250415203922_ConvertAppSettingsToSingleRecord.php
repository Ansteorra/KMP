<?php

declare(strict_types=1);

use Migrations\BaseMigration;
use Cake\ORM\TableRegistry;

class ConvertAppSettingsToSingleRecord extends BaseMigration
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
        return;
        // removed this logic because we aren't doing this optimization yet but the migrations has already deployed to UAT.
    }
}