<?php
declare(strict_types=1);

use Cake\ORM\TableRegistry;
use Migrations\AbstractMigration;

class ActivateOfficersReleaseWorkflow extends AbstractMigration
{
    public function up(): void
    {
        $definitionsTable = TableRegistry::getTableLocator()->get('WorkflowDefinitions');
        $definitions = $definitionsTable->find()
            ->where(['slug' => 'officers-release'])
            ->all();

        foreach ($definitions as $definition) {
            if (!$definition->current_version_id) {
                continue;
            }

            $definition->is_active = true;
            $definitionsTable->saveOrFail($definition);
        }
    }

    public function down(): void
    {
        $definitionsTable = TableRegistry::getTableLocator()->get('WorkflowDefinitions');
        $definitions = $definitionsTable->find()
            ->where(['slug' => 'officers-release'])
            ->all();

        foreach ($definitions as $definition) {
            $definition->is_active = false;
            $definitionsTable->saveOrFail($definition);
        }
    }
}
