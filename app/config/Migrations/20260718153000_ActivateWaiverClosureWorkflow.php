<?php
declare(strict_types=1);

use Cake\ORM\TableRegistry;
use Migrations\AbstractMigration;

class ActivateWaiverClosureWorkflow extends AbstractMigration
{
    /**
     * Activate the seeded waiver closure workflow for existing installations.
     *
     * @return void
     */
    public function up(): void
    {
        $definitionsTable = TableRegistry::getTableLocator()->get('WorkflowDefinitions');
        $definitions = $definitionsTable->find()
            ->where(['slug' => 'waiver-closure'])
            ->all();

        foreach ($definitions as $definition) {
            if (!$definition->current_version_id) {
                continue;
            }

            $definition->is_active = true;
            $definitionsTable->saveOrFail($definition);
        }
    }

    /**
     * Return the waiver closure workflow to its pre-migration inactive state.
     *
     * @return void
     */
    public function down(): void
    {
        $definitionsTable = TableRegistry::getTableLocator()->get('WorkflowDefinitions');
        $definitions = $definitionsTable->find()
            ->where(['slug' => 'waiver-closure'])
            ->all();

        foreach ($definitions as $definition) {
            $definition->is_active = false;
            $definitionsTable->saveOrFail($definition);
        }
    }
}
