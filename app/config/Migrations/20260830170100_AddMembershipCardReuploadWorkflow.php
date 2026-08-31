<?php
declare(strict_types=1);

use App\Migrations\CrossEngineMigrationTrait;
use Migrations\BaseMigration;

class AddMembershipCardReuploadWorkflow extends BaseMigration
{
    use CrossEngineMigrationTrait;

    private const SLUG = 'membership-card-reupload-request';

    /**
     * Add and activate the replacement-card notification workflow for existing tenants.
     *
     * @return void
     */
    public function up(): void
    {
        $jsonPath = ROOT . '/config/Seeds/WorkflowDefinitions/membership-card-reupload-request.json';
        if (!file_exists($jsonPath)) {
            throw new RuntimeException("Workflow definition file not found: {$jsonPath}");
        }

        $definitionData = json_decode((string)file_get_contents($jsonPath), true);
        if (!is_array($definitionData)) {
            throw new RuntimeException('The membership-card re-upload workflow JSON is invalid.');
        }

        $existing = $this->fetchRow(
            "SELECT id, current_version_id FROM workflow_definitions WHERE slug = '" . self::SLUG . "'",
        );
        if ($existing) {
            $definitionId = (int)$existing['id'];
            if (empty($existing['current_version_id'])) {
                $this->createVersion($definitionId, $definitionData);
            }
            $this->execute(
                "UPDATE workflow_definitions
                    SET is_active = TRUE,
                        execution_mode = 'ephemeral',
                        modified = '" . date('Y-m-d H:i:s') . "'
                  WHERE id = {$definitionId}",
            );

            return;
        }

        $now = date('Y-m-d H:i:s');
        $name = $this->sqlEscape('Membership Card Re-upload Request');
        $description = $this->sqlEscape(
            'Notifies a member when an administrator removes an unreadable membership card '
            . 'and requests a replacement upload.',
        );
        $triggerConfig = $this->sqlEscape(json_encode([
            'event' => 'Members.MembershipCardReuploadRequested',
        ], JSON_THROW_ON_ERROR));

        $this->execute(
            'INSERT INTO workflow_definitions ('
            . 'name, slug, description, trigger_type, trigger_config, entity_type, '
            . 'is_active, execution_mode, current_version_id, created_by, modified_by, created, modified'
            . ") VALUES ('{$name}', '" . self::SLUG . "', '{$description}', 'event', "
            . "'{$triggerConfig}', 'Members.Members', TRUE, 'ephemeral', NULL, 1, 1, '{$now}', '{$now}')",
        );
        $created = $this->fetchRow(
            "SELECT id FROM workflow_definitions WHERE slug = '" . self::SLUG . "'",
        );
        $this->createVersion((int)$created['id'], $definitionData);
    }

    /**
     * Deactivate the workflow while preserving instance/version history.
     *
     * @return void
     */
    public function down(): void
    {
        $this->execute(
            "UPDATE workflow_definitions
                SET is_active = FALSE
              WHERE slug = '" . self::SLUG . "'",
        );
    }

    /**
     * @param int $definitionId Workflow definition ID
     * @param array<string, mixed> $definitionData Validated workflow graph
     * @return void
     */
    private function createVersion(int $definitionId, array $definitionData): void
    {
        $now = date('Y-m-d H:i:s');
        $definitionJson = $this->sqlEscape(json_encode($definitionData, JSON_THROW_ON_ERROR));
        $version = $this->fetchRow(
            "SELECT id FROM workflow_versions
              WHERE workflow_definition_id = {$definitionId}
              ORDER BY version_number DESC
              LIMIT 1",
        );
        if ($version) {
            $this->execute(
                "UPDATE workflow_definitions
                    SET current_version_id = " . (int)$version['id'] . "
                  WHERE id = {$definitionId}",
            );

            return;
        }

        $this->execute(
            'INSERT INTO workflow_versions ('
            . 'workflow_definition_id, version_number, definition, canvas_layout, status, '
            . 'published_at, published_by, created_by, created, modified'
            . ") VALUES ({$definitionId}, 1, '{$definitionJson}', '{}', 'published', "
            . "'{$now}', 1, 1, '{$now}', '{$now}')",
        );
        $createdVersion = $this->fetchRow(
            "SELECT id FROM workflow_versions
              WHERE workflow_definition_id = {$definitionId} AND version_number = 1",
        );
        $this->execute(
            "UPDATE workflow_definitions
                SET current_version_id = " . (int)$createdVersion['id'] . "
              WHERE id = {$definitionId}",
        );
    }
}
