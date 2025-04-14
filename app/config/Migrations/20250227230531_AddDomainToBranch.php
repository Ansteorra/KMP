<?php

declare(strict_types=1);

use Migrations\BaseMigration;

class AddDomainToBranch extends BaseMigration
{
    public function useTransactions(): bool
    {
        return false;
    }
    /**
     * Change Method.
     *
     * More information on this method is available here:
     * https://book.cakephp.org/migrations/4/en/migrations.html#the-change-method
     * @return void
     */
    public function change(): void
    {
        $table = $this->table('branches');
        $table->addColumn('domain', 'string', ['default' => '', 'limit' => 255, 'null' => false]);
        $table->update();
    }
}
