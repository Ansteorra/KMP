<?php

declare(strict_types=1);

use Migrations\BaseMigration;

class MakeTermMonthsNotYears extends BaseMigration
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
        //update all Office Terms to be multiplied by 12
        $this->execute("UPDATE activities_activities SET term_length = term_length * 12");
    }
}