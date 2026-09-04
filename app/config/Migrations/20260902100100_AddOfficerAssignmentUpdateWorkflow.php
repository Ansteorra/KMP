<?php
declare(strict_types=1);

use App\Migrations\CrossEngineMigrationTrait;
use Migrations\BaseMigration;

class AddOfficerAssignmentUpdateWorkflow extends BaseMigration
{
    use CrossEngineMigrationTrait;

    private const SLUG = 'officer-assignment-update';
    private const OWNERSHIP_MARKER = '20260902100100_AddOfficerAssignmentUpdateWorkflow';

    /**
     * Add and activate the officer assignment update workflow for existing tenants.
     *
     * An existing current version is tenant-owned and is never replaced.
     *
     * @return void
     */
    public function up(): void
    {
        $jsonPath = ROOT . '/config/Seeds/WorkflowDefinitions/officer-assignment-update.json';
        if (!file_exists($jsonPath)) {
            throw new RuntimeException("Workflow definition file not found: {$jsonPath}");
        }

        $definitionData = json_decode((string)file_get_contents($jsonPath), true);
        if (!is_array($definitionData)) {
            throw new RuntimeException('The officer assignment update workflow JSON is invalid.');
        }

        $existing = $this->fetchRow(
            "SELECT id, current_version_id FROM workflow_definitions WHERE slug = '" . self::SLUG . "'",
        );
        if ($existing && !empty($existing['current_version_id'])) {
            return;
        }

        $now = gmdate('Y-m-d H:i:s');
        $definitionId = $existing ? (int)$existing['id'] : $this->createDefinition($now);
        if ($existing) {
            $name = $this->sqlEscape('Officer Assignment Update');
            $description = $this->sqlEscape(
                'Updates an officer assignment, records required term-change notes, requests a warrant '
                . 'extension when needed, and notifies the officer.',
            );
            $triggerConfig = $this->sqlEscape(json_encode([
                'event' => 'Officers.AssignmentUpdateRequested',
            ], JSON_THROW_ON_ERROR));
            $this->execute(
                "UPDATE workflow_definitions
                    SET name = '{$name}',
                        description = '{$description}',
                        trigger_type = 'event',
                        trigger_config = '{$triggerConfig}',
                        entity_type = 'Officers.Officers',
                        is_active = TRUE,
                        execution_mode = 'ephemeral',
                        modified = '{$now}',
                        modified_by = 1
                  WHERE id = {$definitionId}",
            );
        }

        $this->createVersion($definitionId, $definitionData, $now);
    }

    /**
     * Deactivate the workflow while preserving tenant versions and execution history.
     *
     * @return void
     */
    public function down(): void
    {
        $definition = $this->fetchRow(
            "SELECT wd.id, wv.canvas_layout
               FROM workflow_definitions wd
               LEFT JOIN workflow_versions wv ON wv.id = wd.current_version_id
              WHERE wd.slug = '" . self::SLUG . "'",
        );
        if (!$definition) {
            return;
        }

        $canvasLayout = is_array($definition['canvas_layout'])
            ? $definition['canvas_layout']
            : json_decode((string)$definition['canvas_layout'], true);
        if (!is_array($canvasLayout) || ($canvasLayout['_migration'] ?? null) !== self::OWNERSHIP_MARKER) {
            return;
        }

        $this->execute(
            'UPDATE workflow_definitions SET is_active = FALSE WHERE id = ' . (int)$definition['id'],
        );
    }

    /**
     * @param string $now UTC SQL timestamp
     * @return int
     */
    private function createDefinition(string $now): int
    {
        $name = $this->sqlEscape('Officer Assignment Update');
        $description = $this->sqlEscape(
            'Updates an officer assignment, records required term-change notes, requests a warrant '
            . 'extension when needed, and notifies the officer.',
        );
        $triggerConfig = $this->sqlEscape(json_encode([
            'event' => 'Officers.AssignmentUpdateRequested',
        ], JSON_THROW_ON_ERROR));

        $this->execute(
            'INSERT INTO workflow_definitions ('
            . 'name, slug, description, trigger_type, trigger_config, entity_type, '
            . 'is_active, execution_mode, current_version_id, created_by, modified_by, created, modified'
            . ") VALUES ('{$name}', '" . self::SLUG . "', '{$description}', 'event', "
            . "'{$triggerConfig}', 'Officers.Officers', TRUE, 'ephemeral', NULL, 1, 1, '{$now}', '{$now}')",
        );
        $created = $this->fetchRow(
            "SELECT id FROM workflow_definitions WHERE slug = '" . self::SLUG . "'",
        );

        return (int)$created['id'];
    }

    /**
     * @param int $definitionId Workflow definition ID
     * @param array<string, mixed> $definitionData Validated workflow graph
     * @param string $now UTC SQL timestamp
     * @return void
     */
    private function createVersion(int $definitionId, array $definitionData, string $now): void
    {
        $definitionJson = $this->sqlEscape(json_encode($definitionData, JSON_THROW_ON_ERROR));
        $canvasLayout = $this->sqlEscape(json_encode([
            '_migration' => self::OWNERSHIP_MARKER,
        ], JSON_THROW_ON_ERROR));
        $latestVersion = $this->fetchRow(
            "SELECT MAX(version_number) AS version_number
               FROM workflow_versions
              WHERE workflow_definition_id = {$definitionId}",
        );
        $versionNumber = (int)($latestVersion['version_number'] ?? 0) + 1;

        $this->execute(
            'INSERT INTO workflow_versions ('
            . 'workflow_definition_id, version_number, definition, canvas_layout, status, '
            . 'published_at, published_by, created_by, created, modified'
            . ") VALUES ({$definitionId}, {$versionNumber}, '{$definitionJson}', '{$canvasLayout}', 'published', "
            . "'{$now}', 1, 1, '{$now}', '{$now}')",
        );
        $createdVersion = $this->fetchRow(
            "SELECT id FROM workflow_versions
              WHERE workflow_definition_id = {$definitionId} AND version_number = {$versionNumber}",
        );
        $this->execute(
            "UPDATE workflow_definitions
                SET current_version_id = " . (int)$createdVersion['id'] . ",
                    is_active = TRUE,
                    execution_mode = 'ephemeral',
                    modified = '{$now}',
                    modified_by = 1
              WHERE id = {$definitionId}",
        );
    }
}
