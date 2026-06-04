<?php
declare(strict_types=1);

use App\Migrations\CrossEngineMigrationTrait;
use Migrations\BaseMigration;

/**
 * Publishes the corrected Award Recommendation State Changed workflow.
 *
 * The workflow reacts to recommendation state-change triggers and performs the
 * bestowal handoff only when the new recommendation state reaches the handoff
 * boundary. Recommendation mutation services must not call bestowal workflow
 * side effects directly.
 */
class PublishAwardRecommendationStateChangedWorkflow extends BaseMigration
{
    use CrossEngineMigrationTrait;

    private const SLUG = 'awards-recommendation-state-changed';

    /**
     * @return void
     */
    public function up(): void
    {
        $jsonDir = dirname(__DIR__) . '/Seeds/WorkflowDefinitions/';
        $jsonPath = $jsonDir . self::SLUG . '.json';
        if (!file_exists($jsonPath)) {
            throw new RuntimeException("Workflow definition file not found: {$jsonPath}");
        }

        $definition = json_decode((string)file_get_contents($jsonPath), true, 512, JSON_THROW_ON_ERROR);
        $definitionJson = $this->sqlEscape(json_encode($definition, JSON_THROW_ON_ERROR));
        $triggerConfig = $this->sqlEscape(json_encode(['event' => 'Awards.RecommendationStateChanged'], JSON_THROW_ON_ERROR));
        $now = date('Y-m-d H:i:s');

        $definitionRow = $this->fetchRow(
            "SELECT id
             FROM workflow_definitions
             WHERE slug = '" . self::SLUG . "'
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
                    'Award Recommendation State Changed',
                    '" . self::SLUG . "',
                    'Runs state-change side effects after a recommendation transitions.',
                    'event',
                    '{$triggerConfig}',
                    'Awards',
                    TRUE,
                    'ephemeral',
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
                 WHERE slug = '" . self::SLUG . "'
                 ORDER BY id DESC
                 LIMIT 1",
            );
        }

        if (!$definitionRow) {
            throw new RuntimeException('Failed to create workflow definition for slug ' . self::SLUG);
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
            throw new RuntimeException('Failed to publish workflow version for slug ' . self::SLUG);
        }

        $versionId = (int)$publishedVersion['id'];

        $this->execute(
            "UPDATE workflow_definitions
             SET name = 'Award Recommendation State Changed',
                 description = 'Runs state-change side effects after a recommendation transitions.',
                 trigger_type = 'event',
                 trigger_config = '{$triggerConfig}',
                 entity_type = 'Awards',
                 is_active = TRUE,
                 execution_mode = 'ephemeral',
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
    }
}
