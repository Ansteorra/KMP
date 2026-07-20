<?php

declare(strict_types=1);

use Cake\ORM\TableRegistry;
use Migrations\AbstractMigration;

class UpdateOfficersReleaseWorkflowDefinition extends AbstractMigration
{
    public function up(): void
    {
        $jsonPath = ROOT . '/config/Seeds/WorkflowDefinitions/officers-release.json';
        if (!file_exists($jsonPath)) {
            return;
        }

        $definitionData = json_decode(file_get_contents($jsonPath), true);
        if (!is_array($definitionData)) {
            return;
        }

        $definitionsTable = TableRegistry::getTableLocator()->get('WorkflowDefinitions');
        $versionsTable = TableRegistry::getTableLocator()->get('WorkflowVersions');

        $definitions = $definitionsTable->find()
            ->where(['slug' => 'officers-release'])
            ->all();

        foreach ($definitions as $definition) {
            $definition->description = 'Releases an officer through the same lifecycle as the legacy manager: ' .
                'stop active window, cancel warrants when required, and send release notification.';
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
        // Not reversible.
    }
}
