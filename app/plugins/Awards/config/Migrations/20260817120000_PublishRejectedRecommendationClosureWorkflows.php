<?php
declare(strict_types=1);

use App\Model\Entity\WorkflowInstance;
use App\Model\Entity\WorkflowVersion;
use App\Services\WorkflowEngine\DefaultWorkflowVersionManager;
use Cake\I18n\DateTime;
use Cake\ORM\TableRegistry;
use Migrations\BaseMigration;

/**
 * Publish award approval workflow versions that close rejected recommendations.
 */
class PublishRejectedRecommendationClosureWorkflows extends BaseMigration
{
    /**
     * @return void
     */
    public function up(): void
    {
        $definitionsTable = TableRegistry::getTableLocator()->get('WorkflowDefinitions');
        $versionsTable = TableRegistry::getTableLocator()->get('WorkflowVersions');
        $instancesTable = TableRegistry::getTableLocator()->get('WorkflowInstances');
        $versionManager = new DefaultWorkflowVersionManager();

        foreach ($this->workflowFiles() as $slug => $jsonFile) {
            $jsonPath = ROOT . '/config/Seeds/WorkflowDefinitions/' . $jsonFile;
            if (!file_exists($jsonPath)) {
                continue;
            }

            $canonicalDefinition = json_decode((string)file_get_contents($jsonPath), true);
            if (!is_array($canonicalDefinition)) {
                throw new RuntimeException(sprintf('Invalid workflow definition JSON: %s', $jsonFile));
            }

            $workflow = $definitionsTable->find()
                ->where(['slug' => $slug])
                ->first();
            if (!$workflow || !$workflow->current_version_id) {
                continue;
            }

            $currentVersion = $versionsTable->get((int)$workflow->current_version_id);
            $currentVersionId = (int)$currentVersion->id;
            $definition = is_array($currentVersion->definition) ? $currentVersion->definition : [];
            $updatedDefinition = $this->addRejectedClosurePath($definition, $canonicalDefinition);
            if ($updatedDefinition === null) {
                continue;
            }

            $maxVersion = $versionsTable->find()
                ->where(['workflow_definition_id' => (int)$workflow->id])
                ->select(['max_version' => $versionsTable->find()->func()->max('version_number')])
                ->first();
            $nextVersionNumber = $maxVersion && $maxVersion->max_version
                ? (int)$maxVersion->max_version + 1
                : 1;

            $currentVersion->status = WorkflowVersion::STATUS_ARCHIVED;
            $versionsTable->saveOrFail($currentVersion);

            // Publish the patched graph directly: these seeded approval workflows contain an
            // intentional reusable-gate cycle that the generic UI graph validator rejects.
            $newVersion = $versionsTable->newEntity([
                'workflow_definition_id' => (int)$workflow->id,
                'version_number' => $nextVersionNumber,
                'definition' => $updatedDefinition,
                'canvas_layout' => $currentVersion->canvas_layout,
                'status' => WorkflowVersion::STATUS_PUBLISHED,
                'change_notes' => 'Close rejected award recommendations as No Action / Closed.',
                'published_at' => DateTime::now(),
            ]);
            $newVersion = $versionsTable->saveOrFail($newVersion);
            $targetVersionId = (int)$newVersion->id;

            $workflow->current_version_id = $targetVersionId;
            $workflow->is_active = true;
            $definitionsTable->saveOrFail($workflow);

            $activeInstances = $instancesTable->find()
                ->where([
                    'workflow_definition_id' => (int)$workflow->id,
                    'workflow_version_id' => $currentVersionId,
                    'status IN' => [
                        WorkflowInstance::STATUS_PENDING,
                        WorkflowInstance::STATUS_RUNNING,
                        WorkflowInstance::STATUS_WAITING,
                    ],
                ])
                ->all();
            foreach ($activeInstances as $instance) {
                $migration = $versionManager->migrateInstance((int)$instance->id, $targetVersionId, null);
                if (!$migration->isSuccess()) {
                    throw new RuntimeException((string)$migration->getError());
                }
            }
        }
    }

    /**
     * Published workflow versions are immutable and are not removed on rollback.
     *
     * @return void
     */
    public function down(): void
    {
    }

    /**
     * @return array<string, string>
     */
    private function workflowFiles(): array
    {
        return [
            'awards-recommendation-submitted' => 'awards-recommendation-submitted.json',
            'awards-existing-recommendation-approval' => 'awards-existing-recommendation-approval.json',
        ];
    }

    /**
     * Add only the rejection closure nodes while preserving other UI-authored workflow changes.
     *
     * @param array<string, mixed> $definition Current published definition.
     * @param array<string, mixed> $canonicalDefinition Repository definition containing the closure path.
     * @return array<string, mixed>|null Updated definition, or null when already current.
     */
    private function addRejectedClosurePath(array $definition, array $canonicalDefinition): ?array
    {
        $nodes = $definition['nodes'] ?? null;
        $canonicalNodes = $canonicalDefinition['nodes'] ?? null;
        if (!is_array($nodes) || !is_array($canonicalNodes)) {
            throw new RuntimeException('Award approval workflow nodes are missing.');
        }

        $closedNode = $nodes['approval-process-closed'] ?? null;
        if (!is_array($closedNode)) {
            throw new RuntimeException('Award approval workflow is missing the closed-process condition.');
        }

        $updated = false;
        $closedOutputFound = false;
        foreach (($closedNode['outputs'] ?? []) as $index => $output) {
            if (($output['port'] ?? null) !== 'true') {
                continue;
            }
            $closedOutputFound = true;
            if (($output['target'] ?? null) !== 'transition-rejected-recommendation') {
                $closedNode['outputs'][$index]['target'] = 'transition-rejected-recommendation';
                $updated = true;
            }
        }
        if (!$closedOutputFound) {
            throw new RuntimeException('Award approval workflow closed-process condition has no true output.');
        }
        $nodes['approval-process-closed'] = $closedNode;

        $transitionNodeKey = 'transition-rejected-recommendation';
        $canonicalTransitionNode = $canonicalNodes[$transitionNodeKey] ?? null;
        if (!is_array($canonicalTransitionNode)) {
            throw new RuntimeException(sprintf('Canonical workflow is missing node %s.', $transitionNodeKey));
        }
        if (($nodes[$transitionNodeKey] ?? null) !== $canonicalTransitionNode) {
            $nodes[$transitionNodeKey] = $canonicalTransitionNode;
            $updated = true;
        }

        $canonicalRejectedEnd = $canonicalNodes['end-rejected'] ?? null;
        if (!is_array($canonicalRejectedEnd)) {
            throw new RuntimeException('Canonical workflow is missing the rejected end node.');
        }
        if (!isset($nodes['end-rejected']) || !is_array($nodes['end-rejected'])) {
            throw new RuntimeException('Award approval workflow is missing the rejected end node.');
        }
        if (($nodes['end-rejected']['config'] ?? null) !== $canonicalRejectedEnd['config']) {
            $nodes['end-rejected']['config'] = $canonicalRejectedEnd['config'];
            $updated = true;
        }

        if (!$updated) {
            return null;
        }

        $definition['nodes'] = $nodes;

        return $definition;
    }
}
