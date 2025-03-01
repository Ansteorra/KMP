<?php

declare(strict_types=1);

use Migrations\BaseMigration;

class AddDomainToDepartment extends BaseMigration
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
        $table = $this->table('officers_departments');
        $table->addColumn('domain', 'string', ['default' => '', 'limit' => 255, 'null' => false]);
        $table->update();

        $table = $this->table('officers_offices');
        $table->addColumn('default_contact_address', 'string', ['default' => '', 'limit' => 255, 'null' => false]);
        $table->update();

        $table = $this->table('officers_officers');
        $table->addColumn('email_address', 'string', ['default' => '', 'limit' => 255, 'null' => false]);
        $table->update();
    }
}