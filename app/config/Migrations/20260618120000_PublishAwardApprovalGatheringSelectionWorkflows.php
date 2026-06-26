<?php

declare(strict_types=1);

use App\Migrations\CrossEngineMigrationTrait;
use Migrations\BaseMigration;

class PublishAwardApprovalGatheringSelectionWorkflows extends BaseMigration
{
    use CrossEngineMigrationTrait;

    /**
     * @var array<int, string>
     */
    private const TARGET_SLUGS = [
        'awards-recommendation-submitted',
        'awards-existing-recommendation-approval',
    ];

    /**
     * Publish workflow versions that require gathering selection during award approvals.
     *
     * @return void
     */
    public function up(): void
    {
        $jsonDir = dirname(__DIR__) . '/Seeds/WorkflowDefinitions/';
        require_once dirname(__DIR__) . '/Seeds/InitWorkflowDefinitionsSeed.php';

        $seed = new InitWorkflowDefinitionsSeed();
        $now = date('Y-m-d H:i:s');

        foreach ($seed->getWorkflowMeta() as $meta) {
            if (!in_array($meta['slug'], self::TARGET_SLUGS, true)) {
                continue;
            }

            $this->publishWorkflowVersion($meta, $jsonDir, $now);
        }
    }

    /**
     * Leave published workflow versions in place on rollback.
     *
     * @return void
     */
    public function down(): void
    {
        // Leave published workflow versions in place on rollback.
    }

    /**
     * @param array<string, mixed> $meta Workflow metadata from the seed source.
     */
    private function publishWorkflowVersion(array $meta, string $jsonDir, string $now): void
    {
        $jsonPath = $jsonDir . $meta['json_file'];
        if (!file_exists($jsonPath)) {
            throw new RuntimeException("Workflow definition file not found: {$jsonPath}");
        }

        $definition = json_decode((string)file_get_contents($jsonPath), true, 512, JSON_THROW_ON_ERROR);
        $definitionJson = $this->sqlEscape(json_encode($definition, JSON_THROW_ON_ERROR));
        $triggerConfig = $this->sqlEscape(json_encode($meta['trigger_config'], JSON_THROW_ON_ERROR));
        $slug = $this->sqlEscape((string)$meta['slug']);
        $name = $this->sqlEscape((string)$meta['name']);
        $description = $this->sqlEscape((string)$meta['description']);
        $triggerType = $this->sqlEscape((string)$meta['trigger_type']);
        $entityType = $this->sqlEscape((string)$meta['entity_type']);
        $executionMode = $this->sqlEscape((string)($meta['execution_mode'] ?? 'durable'));
        $isActive = $this->sqlBool(!empty($meta['is_active']));

        $definitionRow = $this->fetchRow(
            "SELECT id
             FROM workflow_definitions
             WHERE slug = '{$slug}'
             ORDER BY id DESC
             LIMIT 1",
        );

        if (!$definitionRow) {
            throw new RuntimeException("Workflow definition not found for slug {$slug}");
        }

        $definitionId = (int)$definitionRow['id'];
        $versionRow = $this->fetchRow(
            "SELECT COALESCE(MAX(version_number), 0) + 1 AS next_version
             FROM workflow_versions
             WHERE workflow_definition_id = {$definitionId}",
        );
        $versionNumber = (int)($versionRow['next_version'] ?? 1);

        $this->execute(
            "INSERT INTO workflow_versions (
                workflow_definition_id,
                version_number,
                definition,
                canvas_layout,
                status,
                published_at,
                published_by,
                created_by,
                created,
                modified
            ) VALUES (
                {$definitionId},
                {$versionNumber},
                '{$definitionJson}',
                '{}',
                'published',
                '{$now}',
                1,
                1,
                '{$now}',
                '{$now}'
            )",
        );

        $publishedVersion = $this->fetchRow(
            "SELECT id
             FROM workflow_versions
             WHERE workflow_definition_id = {$definitionId}
               AND version_number = {$versionNumber}
             ORDER BY id DESC
             LIMIT 1",
        );
        if (!$publishedVersion) {
            throw new RuntimeException("Failed to publish workflow version for slug {$slug}");
        }

        $versionId = (int)$publishedVersion['id'];
        $this->execute(
            "UPDATE workflow_definitions
             SET name = '{$name}',
                 description = '{$description}',
                 trigger_type = '{$triggerType}',
                 trigger_config = '{$triggerConfig}',
                 entity_type = '{$entityType}',
                 is_active = {$isActive},
                 execution_mode = '{$executionMode}',
                 current_version_id = {$versionId},
                 modified_by = 1,
                 modified = '{$now}'
             WHERE id = {$definitionId}",
        );
    }
}
