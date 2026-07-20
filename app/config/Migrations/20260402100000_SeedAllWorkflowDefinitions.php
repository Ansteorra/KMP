<?php

declare(strict_types=1);

use Migrations\AbstractMigration;
use App\Migrations\CrossEngineMigrationTrait;

/**
 * Seed all 8 workflow definitions from JSON files.
 *
 * This ensures workflow definitions survive database resets by running
 * as part of the migration chain. Idempotent — skips existing slugs.
 */
class SeedAllWorkflowDefinitions extends AbstractMigration
{
    use CrossEngineMigrationTrait;

    /**
     * Seed workflow definitions from the current metadata/JSON source.
     *
     * @return void
     */
    public function up(): void
    {
        // Clean up legacy seed entries from 20260209170000_SeedWorkflowDefinitions
        // Those used different slugs and were active by default; this migration supersedes them.
        $legacySlugs = ['warrant-roster', 'activities-authorization', 'officer-hire'];
        // Also clean up over-engineered workflows that were too simple to justify being workflows
        $removedSlugs = ['member-password-reset', 'member-age-up', 'active-window-sync'];
        foreach (array_merge($legacySlugs, $removedSlugs) as $slug) {
            $row = $this->fetchRow("SELECT id FROM workflow_definitions WHERE slug = '{$slug}'");
            if ($row) {
                $id = $row['id'];
                $this->execute("UPDATE workflow_definitions SET current_version_id = NULL WHERE id = {$id}");
                $this->execute("DELETE FROM workflow_instances WHERE workflow_definition_id = {$id}");
                $this->execute("DELETE FROM workflow_versions WHERE workflow_definition_id = {$id}");
                $this->execute("DELETE FROM workflow_definitions WHERE id = {$id}");
            }
        }

        $jsonDir = dirname(__DIR__) . '/Seeds/WorkflowDefinitions/';
        if (!is_dir($jsonDir)) {
            return;
        }

        require_once dirname(__DIR__) . '/Seeds/InitWorkflowDefinitionsSeed.php';
        $seed = new InitWorkflowDefinitionsSeed();

        $now = date('Y-m-d H:i:s');

        foreach ($seed->getWorkflowMeta() as $meta) {
            // Skip if already exists
            $exists = $this->fetchRow(
                "SELECT id FROM workflow_definitions WHERE slug = '{$meta['slug']}'",
            );
            if ($exists) {
                continue;
            }

            $jsonPath = $jsonDir . $meta['json_file'];
            if (!file_exists($jsonPath)) {
                continue;
            }

            $definitionJson = file_get_contents($jsonPath);
            $decoded = json_decode($definitionJson, true);
            if ($decoded === null) {
                continue;
            }

            $name = $this->sqlEscape($meta['name']);
            $slug = $this->sqlEscape($meta['slug']);
            $desc = $this->sqlEscape($meta['description']);
            $triggerConfig = $this->sqlEscape(json_encode($meta['trigger_config']));
            $entityType = $this->sqlEscape($meta['entity_type']);
            $defJson = $this->sqlEscape(json_encode($decoded));

            $executionMode = $this->sqlEscape($meta['execution_mode'] ?? 'durable');

            $isActive = $this->sqlBool(!empty($meta['is_active']));

            $this->execute(
                'INSERT INTO workflow_definitions (' .
                'name, slug, description, trigger_type, trigger_config, entity_type, ' .
                'is_active, execution_mode, current_version_id, created_by, modified_by, created, modified' .
                ') ' .
                "VALUES ('{$name}', '{$slug}', '{$desc}', '{$meta['trigger_type']}', " .
                "'{$triggerConfig}', '{$entityType}', {$isActive}, '{$executionMode}', NULL, 1, 1, " .
                "'{$now}', '{$now}')",
            );

            $this->execute(
                'INSERT INTO workflow_versions (' .
                'workflow_definition_id, version_number, definition, canvas_layout, status, ' .
                'published_at, published_by, created_by, created, modified' .
                ') ' .
                "VALUES ((SELECT id FROM workflow_definitions WHERE slug = '{$slug}'), 1, " .
                "'{$defJson}', '{}', 'published', '{$now}', 1, 1, '{$now}', '{$now}')",
            );

            $this->execute(
                'UPDATE workflow_definitions SET current_version_id = ' .
                '(SELECT wv.id FROM workflow_versions wv ' .
                'JOIN workflow_definitions wd ON wv.workflow_definition_id = wd.id ' .
                "WHERE wd.slug = '{$slug}' AND wv.version_number = 1) " .
                "WHERE slug = '{$slug}'",
            );
        }
    }

    /**
     * Leave seeded workflow definitions in place on rollback.
     *
     * @return void
     */
    public function down(): void
    {
        // Workflow definitions are configuration data — leave them on rollback.
    }
}
