<?php
declare(strict_types=1);

use Cake\ORM\TableRegistry;
use Migrations\AbstractMigration;

class UpdateAndActivateMemberRegistrationWorkflow extends AbstractMigration
{
    public function up(): void
    {
        $jsonPath = ROOT . '/config/Seeds/WorkflowDefinitions/member-registration.json';
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
            ->where(['slug' => 'member-registration'])
            ->all();

        foreach ($definitions as $definition) {
            $definition->description = 'Member registration notifications for saved members: send the '
                . 'adult self-registration welcome/secretary emails or minor secretary notice.';
            if ($definition->current_version_id) {
                $definition->is_active = true;
            }
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
            ->where(['slug' => 'member-registration'])
            ->all();

        foreach ($definitions as $definition) {
            $definition->is_active = false;
            $definitionsTable->saveOrFail($definition);
        }
    }
}
