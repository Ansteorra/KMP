<?php

declare(strict_types=1);

use Migrations\BaseMigration;

class ChangeAppSettingValueToText extends BaseMigration
{
    /**
     * Change Method.
     *
     * More information on this method is available here:
     * https://book.cakephp.org/phinx/0/en/migrations.html#the-change-method
     * @return void
     */
    public function change(): void
    {
        //change the value column from a string to a text
        $this->table('app_settings')
            ->changeColumn('value', 'text')
            ->update();
        $this->table('app_settings')
            ->addColumn('type', 'string', [
                'default' => null,
                'limit' => 255,
                'null' => true,
            ])
            ->update();
    }
}
