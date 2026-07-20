<?php
declare(strict_types=1);

use Cake\ORM\TableRegistry;
use Migrations\AbstractMigration;

class ReorderOfficerHireWarrantValidation extends AbstractMigration
{
    public function up(): void
    {
        $jsonPath = ROOT . '/config/Seeds/WorkflowDefinitions/officers-hire.json';
        if (!file_exists($jsonPath)) {
            return;
        }

        $definitionData = json_decode((string)file_get_contents($jsonPath), true);
        if (!is_array($definitionData)) {
            return;
        }

        $definitionsTable = TableRegistry::getTableLocator()->get('WorkflowDefinitions');
        $versionsTable = TableRegistry::getTableLocator()->get('WorkflowVersions');

        $definitions = $definitionsTable->find()
            ->where(['slug' => 'officer-hire'])
            ->all();

        foreach ($definitions as $definition) {
            $definition->description = 'Full officer hire process: warrant validation before conflict resolution, '
                . 'officer creation, warrant roster creation when required, and notification.';
            $definitionsTable->saveOrFail($definition);

            $versions = $versionsTable->find()
                ->where(['workflow_definition_id' => $definition->id])
                ->all();

            foreach ($versions as $version) {
                $version->definition = $definitionData;
                $versionsTable->saveOrFail($version);
            }
        }
    }

    public function down(): void
    {
        $definitionsTable = TableRegistry::getTableLocator()->get('WorkflowDefinitions');
        $definitions = $definitionsTable->find()
            ->where(['slug' => 'officer-hire'])
            ->all();

        foreach ($definitions as $definition) {
            $definition->description = 'Full officer hire process: date-aware conflict resolution, warrant '
                . 'validation, officer creation, warrant roster creation when required, and notification.';
            $definitionsTable->saveOrFail($definition);
        }
    }
}
