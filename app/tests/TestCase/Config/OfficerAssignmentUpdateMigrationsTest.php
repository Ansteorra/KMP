<?php
declare(strict_types=1);

namespace App\Test\TestCase\Config;

use AddOfficerAssignmentUpdatedEmailTemplate;
use AddOfficerAssignmentUpdateWorkflow;
use App\Test\TestCase\BaseTestCase;
use Cake\I18n\DateTime;
use Cake\ORM\TableRegistry;
use Migrations\Migration\Environment;

class OfficerAssignmentUpdateMigrationsTest extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        require_once ROOT
            . '/config/Migrations/20260902100000_AddOfficerAssignmentUpdatedEmailTemplate.php';
        require_once ROOT
            . '/config/Migrations/20260902100100_AddOfficerAssignmentUpdateWorkflow.php';
    }

    public function testWorkflowMigrationCreatesActiveEphemeralPublishedDefinition(): void
    {
        $definitions = TableRegistry::getTableLocator()->get('WorkflowDefinitions');
        $existing = $definitions->find()
            ->where(['slug' => 'officer-assignment-update'])
            ->first();
        if ($existing !== null) {
            $existing->slug = 'officer-assignment-update-existing-' . bin2hex(random_bytes(4));
            $definitions->saveOrFail($existing);
        }

        $migration = $this->workflowMigration();
        $migration->up();
        $migration->up();

        $definition = $definitions->find()
            ->contain(['CurrentVersion'])
            ->where(['slug' => 'officer-assignment-update'])
            ->firstOrFail();
        $this->assertSame('Officers.AssignmentUpdateRequested', $definition->trigger_config['event']);
        $this->assertSame('Officers.Officers', $definition->entity_type);
        $this->assertSame('ephemeral', $definition->execution_mode);
        $this->assertTrue($definition->is_active);
        $this->assertNotNull($definition->current_version_id);
        $this->assertSame('published', $definition->current_version->status);

        $expectedDefinition = json_decode(
            (string)file_get_contents(
                ROOT . '/config/Seeds/WorkflowDefinitions/officer-assignment-update.json',
            ),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
        $this->assertEquals($expectedDefinition, $definition->current_version->definition);
        $this->assertSame(1, $definitions->find()
            ->where(['slug' => 'officer-assignment-update'])
            ->count());
    }

    public function testWorkflowMigrationPreservesTenantCurrentVersion(): void
    {
        $definitions = TableRegistry::getTableLocator()->get('WorkflowDefinitions');
        $definition = $definitions->find()
            ->where(['slug' => 'officer-assignment-update'])
            ->first();
        if ($definition === null) {
            $this->workflowMigration()->up();
            $definition = $definitions->find()
                ->where(['slug' => 'officer-assignment-update'])
                ->firstOrFail();
        }

        $versions = TableRegistry::getTableLocator()->get('WorkflowVersions');
        $latest = $versions->find()
            ->where(['workflow_definition_id' => $definition->id])
            ->orderByDesc('version_number')
            ->first();
        $customGraph = [
            '$schema' => './schema.json',
            'schemaVersion' => '1.0',
            'nodes' => [
                'trigger' => [
                    'type' => 'trigger',
                    'config' => ['event' => 'Officers.AssignmentUpdateRequested'],
                    'outputs' => [['port' => 'next', 'target' => 'end']],
                ],
                'end' => [
                    'type' => 'end',
                    'config' => ['result' => ['success' => true, 'customized' => true]],
                    'outputs' => [],
                ],
            ],
            'startNode' => 'trigger',
        ];
        $customVersion = $versions->saveOrFail($versions->newEntity([
            'workflow_definition_id' => $definition->id,
            'version_number' => (int)($latest?->version_number ?? 0) + 1,
            'definition' => $customGraph,
            'canvas_layout' => [],
            'status' => 'published',
            'published_at' => DateTime::now(),
            'published_by' => self::ADMIN_MEMBER_ID,
            'created_by' => self::ADMIN_MEMBER_ID,
        ]));
        $definition->name = 'Kingdom Customized Officer Assignment Update';
        $definition->current_version_id = $customVersion->id;
        $definition->execution_mode = 'durable';
        $definition->is_active = false;
        $definitions->saveOrFail($definition);
        $versionCount = $versions->find()
            ->where(['workflow_definition_id' => $definition->id])
            ->count();

        $this->workflowMigration()->up();

        $preserved = $definitions->get($definition->id);
        $this->assertSame('Kingdom Customized Officer Assignment Update', $preserved->name);
        $this->assertSame((int)$customVersion->id, (int)$preserved->current_version_id);
        $this->assertSame('durable', $preserved->execution_mode);
        $this->assertFalse($preserved->is_active);
        $this->assertEquals($customGraph, $versions->get($customVersion->id)->definition);
        $this->assertSame($versionCount, $versions->find()
            ->where(['workflow_definition_id' => $definition->id])
            ->count());
    }

    public function testEmailTemplateMigrationCreatesExpectedVariablesAndPreservesCustomization(): void
    {
        $templates = TableRegistry::getTableLocator()->get('EmailTemplates');
        $existing = $templates->find()
            ->where(['slug' => 'officer-assignment-updated-notification'])
            ->first();
        if ($existing !== null) {
            $existing->slug = 'officer-assignment-updated-existing-' . bin2hex(random_bytes(4));
            $templates->saveOrFail($existing);
        }

        $migration = $this->emailTemplateMigration();
        $migration->up();

        $template = $templates->find()
            ->where(['slug' => 'officer-assignment-updated-notification'])
            ->firstOrFail();
        $expectedVars = [
            'memberScaName',
            'officeName',
            'branchName',
            'startDate',
            'endDate',
            'changeSummary',
            'termChangeNote',
            'warrantMessage',
            'siteAdminSignature',
        ];
        $this->assertTrue($template->is_active);
        $this->assertSame($expectedVars, array_column($template->available_vars, 'name'));
        $this->assertEqualsCanonicalizing(
            $expectedVars,
            array_column($template->variables_schema, 'name'),
        );
        foreach ($expectedVars as $variable) {
            $this->assertStringContainsString('{{' . $variable . '}}', $template->text_template);
        }
        $this->assertStringContainsString('{{#if termChangeNote}}', $template->text_template);
        $this->assertStringContainsString('{{#if warrantMessage}}', $template->text_template);

        $template->subject_template = 'Kingdom-customized assignment notice';
        $template->is_active = false;
        $templates->saveOrFail($template);

        $migration->up();

        $preserved = $templates->get($template->id);
        $this->assertSame('Kingdom-customized assignment notice', $preserved->subject_template);
        $this->assertFalse($preserved->is_active);
    }

    private function workflowMigration(): AddOfficerAssignmentUpdateWorkflow
    {
        $environment = new Environment('officer-assignment-update-workflow-test', [
            'connection' => 'test',
        ]);

        return (new AddOfficerAssignmentUpdateWorkflow(20260902100100))
            ->setAdapter($environment->getAdapter());
    }

    private function emailTemplateMigration(): AddOfficerAssignmentUpdatedEmailTemplate
    {
        $environment = new Environment('officer-assignment-update-template-test', [
            'connection' => 'test',
        ]);

        return (new AddOfficerAssignmentUpdatedEmailTemplate(20260902100000))
            ->setAdapter($environment->getAdapter());
    }
}
