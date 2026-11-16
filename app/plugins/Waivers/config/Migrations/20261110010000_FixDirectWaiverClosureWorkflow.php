<?php
declare(strict_types=1);

use App\Migrations\CrossEngineMigrationTrait;
use Migrations\BaseMigration;

class FixDirectWaiverClosureWorkflow extends BaseMigration
{
    use CrossEngineMigrationTrait;

    private const MARKER = '20261110010000_FixDirectWaiverClosureWorkflow';

    private const LEGACY_DEFINITION = <<<'JSON'
{
    "$schema": "./schema.json",
    "schemaVersion": "1.0",
    "nodes": {
        "trigger-ready-to-close": {
            "type": "trigger",
            "label": "Waiver Collection Close Requested",
            "config": {
                "event": "Waivers.CollectionClosed",
                "entityIdField": "gatheringId"
            },
            "position": {
                "x": 50,
                "y": 200
            },
            "outputs": [
                {
                    "port": "next",
                    "target": "check-ready-to-close"
                }
            ]
        },
        "check-ready-to-close": {
            "type": "condition",
            "label": "Is Ready to Close?",
            "config": {
                "condition": "Waivers.IsReadyToClose",
                "gatheringId": "$.trigger.gatheringId"
            },
            "position": {
                "x": 350,
                "y": 200
            },
            "outputs": [
                {
                    "port": "true",
                    "target": "close-collection",
                    "label": "Ready"
                },
                {
                    "port": "false",
                    "target": "end-not-ready",
                    "label": "Not Ready"
                }
            ]
        },
        "close-collection": {
            "type": "action",
            "label": "Close Waiver Collection",
            "config": {
                "action": "Waivers.CloseWaiverCollection",
                "params": {
                    "gatheringId": "$.trigger.gatheringId",
                    "closedBy": "$.trigger.closedBy"
                }
            },
            "position": {
                "x": 650,
                "y": 100
            },
            "outputs": [
                {
                    "port": "next",
                    "target": "end-closed"
                }
            ]
        },
        "end-closed": {
            "type": "end",
            "label": "Collection Closed",
            "config": {},
            "position": {
                "x": 950,
                "y": 100
            },
            "outputs": []
        },
        "end-not-ready": {
            "type": "end",
            "label": "Not Ready to Close",
            "config": {},
            "position": {
                "x": 650,
                "y": 300
            },
            "outputs": []
        }
    },
    "startNode": "trigger-ready-to-close"
}
JSON;

    /** Upgrade the untouched built-in workflow without replacing tenant customizations or history. */
    public function up(): void
    {
        $current = $this->fetchRow(
            "SELECT wd.id, wd.current_version_id, wv.definition, wv.canvas_layout
             FROM workflow_definitions wd
             INNER JOIN workflow_versions wv ON wv.id = wd.current_version_id
             WHERE wd.slug = 'waiver-closure'",
        );
        if (!$current) {
            return;
        }
        $graph = json_decode((string)$current['definition'], true, 512, JSON_THROW_ON_ERROR);
        if ($graph != json_decode(self::LEGACY_DEFINITION, true, 512, JSON_THROW_ON_ERROR)) {
            return;
        }

        $graph['nodes']['trigger-ready-to-close']['outputs'][0]['target'] = 'check-closed';
        $condition = $graph['nodes']['check-ready-to-close'];
        $condition['label'] = 'Already Closed?';
        $condition['config']['condition'] = 'Waivers.IsClosed';
        $condition['outputs'] = [
            ['port' => 'true', 'target' => 'end-already-closed', 'label' => 'Already Closed'],
            ['port' => 'false', 'target' => 'close-collection', 'label' => 'Open'],
        ];
        $graph['nodes']['check-closed'] = $condition;
        $graph['nodes']['end-already-closed'] = $graph['nodes']['end-not-ready'];
        $graph['nodes']['end-already-closed']['label'] = 'Collection Already Closed';
        unset($graph['nodes']['check-ready-to-close'], $graph['nodes']['end-not-ready']);

        $definitionId = (int)$current['id'];
        $previousVersion = (int)$current['current_version_id'];
        $definition = $this->sqlEscape(json_encode($graph, JSON_THROW_ON_ERROR));
        $layout = json_decode((string)$current['canvas_layout'], true) ?: [];
        $layout['_migration'] = self::MARKER;
        $layout['_previous_version'] = $previousVersion;
        $layoutJson = $this->sqlEscape(json_encode($layout, JSON_THROW_ON_ERROR));
        $this->execute(
            "INSERT INTO workflow_versions
                (workflow_definition_id, version_number, definition, canvas_layout, status,
                 published_at, created, modified)
             SELECT {$definitionId}, COALESCE(MAX(version_number), 0) + 1,
                    '{$definition}', '{$layoutJson}', 'published', NOW(), NOW(), NOW()
             FROM workflow_versions WHERE workflow_definition_id = {$definitionId}",
        );
        $this->execute(
            "UPDATE workflow_definitions
             SET current_version_id = (
                 SELECT id FROM workflow_versions WHERE workflow_definition_id = {$definitionId}
                 ORDER BY version_number DESC LIMIT 1
             ), modified = NOW()
             WHERE id = {$definitionId} AND current_version_id = {$previousVersion}",
        );
    }

    /** Restore the previous version only while our replacement is still current. */
    public function down(): void
    {
        $marker = self::MARKER;
        $this->execute(
            "UPDATE workflow_definitions wd
             SET current_version_id = (wv.canvas_layout->>'_previous_version')::integer, modified = NOW()
             FROM workflow_versions wv
             WHERE wd.current_version_id = wv.id AND wd.slug = 'waiver-closure'
               AND wv.canvas_layout->>'_migration' = '{$marker}'",
        );
    }
}
