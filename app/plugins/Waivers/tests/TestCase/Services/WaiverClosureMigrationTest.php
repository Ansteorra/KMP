<?php
declare(strict_types=1);

namespace Waivers\Test\TestCase\Services;

use App\Test\TestCase\BaseTestCase;
use FixDirectWaiverClosureWorkflow;
use Migrations\Db\Adapter\PostgresAdapter;
use ReflectionClass;

class WaiverClosureMigrationTest extends BaseTestCase
{
    private FixDirectWaiverClosureWorkflow $migration;

    protected function setUp(): void
    {
        parent::setUp();
        require_once ROOT . '/plugins/Waivers/config/Migrations/20261110010000_FixDirectWaiverClosureWorkflow.php';
        $this->migration = new FixDirectWaiverClosureWorkflow();
        $this->migration->setAdapter(new PostgresAdapter(['connection' => $this->connection]));
    }

    public function testUpgradePublishesNewVersionAndPreservesHistory(): void
    {
        $definitions = $this->getTableLocator()->get('WorkflowDefinitions');
        $versions = $this->getTableLocator()->get('WorkflowVersions');
        $definition = $definitions->find()->where(['slug' => 'waiver-closure'])->firstOrFail();
        $original = $versions->get($definition->current_version_id);
        $legacy = (new ReflectionClass(FixDirectWaiverClosureWorkflow::class))->getConstant('LEGACY_DEFINITION');
        $original->definition = json_decode($legacy, true, 512, JSON_THROW_ON_ERROR);
        $versions->saveOrFail($original);
        $before = $versions->find()->where(['workflow_definition_id' => $definition->id])->count();

        $this->migration->up();
        $updated = $definitions->get($definition->id);
        $this->assertNotSame($original->id, $updated->current_version_id);
        $this->assertEquals($original->definition, $versions->get($original->id)->definition);
        $expected = json_decode((string)file_get_contents(
            ROOT . '/config/Seeds/WorkflowDefinitions/waiver-closure.json',
        ), true, 512, JSON_THROW_ON_ERROR);
        $this->assertEquals($expected, $versions->get($updated->current_version_id)->definition);
        $this->migration->up();
        $this->assertSame($before + 1, $versions->find()->where(['workflow_definition_id' => $definition->id])->count());

        $this->migration->down();
        $this->assertSame($original->id, $definitions->get($definition->id)->current_version_id);
        $this->assertNotNull($versions->get($updated->current_version_id));
    }

    public function testCustomizedWorkflowIsNotChanged(): void
    {
        $definitions = $this->getTableLocator()->get('WorkflowDefinitions');
        $versions = $this->getTableLocator()->get('WorkflowVersions');
        $definition = $definitions->find()->where(['slug' => 'waiver-closure'])->firstOrFail();
        $original = $versions->get($definition->current_version_id);
        $legacy = (new ReflectionClass(FixDirectWaiverClosureWorkflow::class))->getConstant('LEGACY_DEFINITION');
        $graph = json_decode($legacy, true, 512, JSON_THROW_ON_ERROR);
        $graph['nodes']['check-ready-to-close']['config']['gatheringId'] = '$.custom.gatheringId';
        $original->definition = $graph;
        $versions->saveOrFail($original);
        $before = $versions->find()->where(['workflow_definition_id' => $definition->id])->count();

        $this->migration->up();
        $this->migration->down();
        $this->assertSame($original->id, $definitions->get($definition->id)->current_version_id);
        $this->assertEquals($graph, $versions->get($original->id)->definition);
        $this->assertSame($before, $versions->find()->where(['workflow_definition_id' => $definition->id])->count());
    }
}
