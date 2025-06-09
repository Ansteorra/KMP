<?php

declare(strict_types=1);

use Migrations\BaseMigration;

class AddTypeToBranches extends BaseMigration
{
    public function useTransactions(): bool
    {
        return false;
    }
    /**
     * Change Method.
     *
     * More information on this method is available here:
     * https://book.cakephp.org/phinx/0/en/migrations.html#the-change-method
     * @return void
     */
    public function change(): void
    {
        $table = $this->table('branches')
            ->addColumn('type', 'string', [
                'default' => null,
                'limit' => 255,
                'null' => true,
            ]);
        $table->update();
        //update the existing branch records
        $this->execute("UPDATE branches SET type = 'Kingdom' WHERE name = 'Kingdom'");
    }
}