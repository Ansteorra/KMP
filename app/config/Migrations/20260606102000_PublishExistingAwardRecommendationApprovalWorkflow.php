<?php

declare(strict_types=1);

use App\Migrations\CrossEngineMigrationTrait;
use Migrations\BaseMigration;

class PublishExistingAwardRecommendationApprovalWorkflow extends BaseMigration
{
    use CrossEngineMigrationTrait;

    /**
     * Publish the durable approval workflow for existing award recommendations.
     *
     * @return void
     */
    public function up(): void
    {
        $jsonPath = dirname(__DIR__) . '/Seeds/WorkflowDefinitions/awards-existing-recommendation-approval.json';
        if (!file_exists($jsonPath)) {
            throw new RuntimeException("Workflow definition file not found: {$jsonPath}");
        }

        $definition = json_decode((string)file_get_contents($jsonPath), true, 512, JSON_THROW_ON_ERROR);
        $definitionJson = $this->sqlEscape(json_encode($definition, JSON_THROW_ON_ERROR));
        $triggerConfig = $this->sqlEscape(json_encode([
            'event' => 'Awards.ExistingRecommendationApprovalRequested',
        ], JSON_THROW_ON_ERROR));
        $now = date('Y-m-d H:i:s');
        $slug = 'awards-existing-recommendation-approval';

        $definitionRow = $this->fetchRow(
            "SELECT id
             FROM workflow_definitions
             WHERE slug = '{$slug}'
             ORDER BY id DESC
             LIMIT 1",
        );

        if (!$definitionRow) {
            $this->execute(
                "INSERT INTO workflow_definitions (
                    name,
                    slug,
                    description,
                    trigger_type,
                    trigger_config,
                    entity_type,
                    is_active,
                    execution_mode,
                    current_version_id,
                    created_by,
                    modified_by,
                    created,
                    modified
                ) VALUES (
                    'Existing Award Recommendation Approval',
                    '{$slug}',
                    'Starts the configured award approval process for an existing recommendation.',
                    'event',
                    '{$triggerConfig}',
                    'Awards.Recommendations',
                    {$this->sqlBool(true)},
                    'durable',
                    NULL,
                    1,
                    1,
                    '{$now}',
                    '{$now}'
                )",
            );
            $definitionRow = $this->fetchRow(
                "SELECT id
                 FROM workflow_definitions
                 WHERE slug = '{$slug}'
                 ORDER BY id DESC
                 LIMIT 1",
            );
        }

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
             SET name = 'Existing Award Recommendation Approval',
                 description = 'Starts the configured award approval process for an existing recommendation.',
                 trigger_type = 'event',
                 trigger_config = '{$triggerConfig}',
                 entity_type = 'Awards.Recommendations',
                 is_active = {$this->sqlBool(true)},
                 execution_mode = 'durable',
                 current_version_id = {$versionId},
                 modified_by = 1,
                 modified = '{$now}'
             WHERE id = {$definitionId}",
        );
    }

    /**
     * Leave published workflow versions in place on rollback.
     *
     * @return void
     */
    public function down(): void
    {
        // Leave workflow versions in place on rollback.
    }
}
