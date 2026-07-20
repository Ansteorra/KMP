<?php

declare(strict_types=1);

use Migrations\AbstractMigration;

/**
 * Add on_reassigned output port to the authorization approval-gate node
 * and update the workflow version definition in the database.
 */
class AddOnReassignedPortToAuthWorkflow extends AbstractMigration
{
    public function up(): void
    {
        $jsonPath = ROOT . '/config/Seeds/WorkflowDefinitions/activities-authorization-request.json';
        if (!file_exists($jsonPath)) {
            return;
        }

        $definitionData = json_decode(file_get_contents($jsonPath), true);

        $versionsTable = \Cake\ORM\TableRegistry::getTableLocator()->get('WorkflowVersions');
        $versions = $versionsTable->find()
            ->where(['workflow_definition_id' => 4])
            ->all();

        foreach ($versions as $version) {
            $version->definition = $definitionData;
            $versionsTable->save($version);
        }
    }

    public function down(): void
    {
        // Not reversible
    }
}
