<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class AddTerminalActionItems extends BaseMigration
{
    /** Apply reversible schema changes. */
    public function change(): void
    {
        $this->table('action_items')->addColumn('is_terminal', 'boolean', ['default' => false])->update();
    }
}
