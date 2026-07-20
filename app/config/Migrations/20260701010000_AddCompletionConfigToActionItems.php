<?php

declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * Add completion-time metadata to core action items.
 *
 * This keeps assignee resolution (`assignee_config`) separate from requirements
 * that must be satisfied before an action item can be completed.
 */
class AddCompletionConfigToActionItems extends BaseMigration
{
    /**
     * @return void
     */
    public function up(): void
    {
        $table = $this->table('action_items');
        if (!$table->hasColumn('completion_config')) {
            $table
                ->addColumn('completion_config', 'json', [
                    'after' => 'source_ref',
                    'default' => null,
                    'null' => true,
                    'comment' => 'Completion requirements and plugin-provided form metadata',
                ])
                ->update();
        }
    }

    /**
     * @return void
     */
    public function down(): void
    {
        $table = $this->table('action_items');
        if ($table->hasColumn('completion_config')) {
            $table
                ->removeColumn('completion_config')
                ->update();
        }
    }
}
