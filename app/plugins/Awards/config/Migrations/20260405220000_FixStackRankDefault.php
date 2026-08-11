<?php
declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * Set a default value for stack_rank since the Sortable/kanban behavior
 * that previously populated it has been removed.
 */
class FixStackRankDefault extends BaseMigration
{
    public function change(): void
    {
        $table = $this->table('awards_recommendations');
        $table->changeColumn('stack_rank', 'integer', [
            'default' => 0,
            'null' => false,
        ]);
        $table->update();
    }
}
