<?php
declare(strict_types=1);

namespace App\Test\TestCase\Command;

use App\Command\AgeUpMembersCommand;
use App\Command\SyncActiveWindowStatusesCommand;
use App\Model\Entity\WorkflowDefinition;
use App\Services\ServiceResult;
use App\Services\WorkflowEngine\TriggerDispatcher;
use App\Services\WorkflowEngine\WorkflowEngineInterface;
use App\Test\TestCase\BaseTestCase;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\TestSuite\ConsoleIntegrationTestTrait;
use Cake\Console\TestSuite\StubConsoleOutput;
use Cake\ORM\TableRegistry;

/**
 * Tests for workflow engine dual-path dispatch in cron commands.
 *
 * Validates that AgeUpMembersCommand, SyncMemberWarrantableStatusesCommand,
 * and SyncActiveWindowStatusesCommand correctly check for active workflows
 * and dispatch or fall back to legacy logic.
 */
class WorkflowCronDispatchTest extends BaseTestCase
{
    use ConsoleIntegrationTestTrait;

    private $defTable;
    private $versionsTable;

    protected function setUp(): void
    {
        parent::setUp();

        $this->defTable = TableRegistry::getTableLocator()->get('WorkflowDefinitions');
        $this->versionsTable = TableRegistry::getTableLocator()->get('WorkflowVersions');
    }

    /**
     * Create a workflow definition with a published current version.
     *
     * @param string $slug Unique slug
     * @param string $triggerType Trigger type
     * @return \App\Model\Entity\WorkflowDefinition
     */
    private function createActiveWorkflow(
        string $slug,
        string $triggerType = 'event',
    ): WorkflowDefinition {
        // Remove any existing definition with this slug (e.g. from seed migrations)
        $this->defTable->deleteAll(['slug' => $slug]);

        $def = $this->defTable->newEntity([
            'name' => 'Test: ' . $slug,
            'slug' => $slug,
            'trigger_type' => $triggerType,
            'trigger_config' => ['description' => 'Test workflow for ' . $slug],
            'entity_type' => 'Members',
            'is_active' => true,
        ]);
        $this->defTable->saveOrFail($def);

        $version = $this->versionsTable->newEntity([
            'workflow_definition_id' => $def->id,
            'version_number' => 1,
            'definition' => [
                'nodes' => [
                    'trigger1' => [
                        'type' => 'trigger',
                        'config' => ['event' => 'Members.AgeUpTriggered'],
                        'outputs' => [['port' => 'default', 'target' => 'end1']],
                    ],
                    'end1' => ['type' => 'end', 'config' => [], 'outputs' => []],
                ],
            ],
            'status' => 'published',
        ]);
        $this->versionsTable->saveOrFail($version);

        $def->current_version_id = $version->id;
        $this->defTable->saveOrFail($def);

        return $def;
    }

    /**
     * Create a mock TriggerDispatcher that returns successful results.
     *
     * @return \App\Services\WorkflowEngine\TriggerDispatcher
     */
    private function createMockDispatcher(): TriggerDispatcher
    {
        $mockEngine = $this->createMock(WorkflowEngineInterface::class);
        $mockEngine->method('dispatchTrigger')
            ->willReturn([new ServiceResult(true, 'Workflow started')]);

        return new TriggerDispatcher($mockEngine);
    }

    /**
     * Create ConsoleIo with stub outputs for direct command testing.
     *
     * @return array{io: ConsoleIo, out: StubConsoleOutput, err: StubConsoleOutput}
     */
    private function createStubIo(): array
    {
        $out = new StubConsoleOutput();
        $err = new StubConsoleOutput();
        $io = new ConsoleIo($out, $err);

        return ['io' => $io, 'out' => $out, 'err' => $err];
    }

    // =====================================================
    // AgeUpMembersCommand Tests
    // =====================================================

    /**
     * Test: AgeUp runs legacy logic when no 'member-age-up' workflow exists.
     */
    public function testAgeUpRunsLegacyWhenNoWorkflow(): void
    {
        $this->defTable->deleteAll(['slug' => 'member-age-up']);

        $this->exec('age_up_members');

        $this->assertExitSuccess();
        $this->assertOutputContains('Running legacy age-up logic');
    }

    /**
     * Test: AgeUp dispatches via workflow when 'member-age-up' workflow is active.
     */
    public function testAgeUpDispatchesWhenWorkflowActive(): void
    {
        $this->createActiveWorkflow('member-age-up');

        $stub = $this->createStubIo();
        $command = new AgeUpMembersCommand();
        $command->setTriggerDispatcher($this->createMockDispatcher());

        $args = new Arguments([], ['dry-run' => false], ['dry-run']);
        $result = $command->execute($args, $stub['io']);

        $output = implode("\n", $stub['out']->messages());
        $this->assertSame(0, $result);
        $this->assertStringContainsString('Dispatching', $output);
        $this->assertStringContainsString('Workflow dispatched', $output);
        $this->assertStringNotContainsString('Running legacy age-up logic', $output);
    }

    /**
     * Test: AgeUp runs legacy in dry-run mode even with active workflow.
     */
    public function testAgeUpDryRunSkipsWorkflowDispatch(): void
    {
        $this->createActiveWorkflow('member-age-up');

        $this->exec('age_up_members --dry-run');

        $this->assertExitSuccess();
        $this->assertOutputContains('Running legacy age-up logic');
    }

    /**
     * Test: AgeUp falls back to legacy when workflow exists but is inactive.
     */
    public function testAgeUpFallsBackWhenWorkflowInactive(): void
    {
        $def = $this->createActiveWorkflow('member-age-up');

        $def->is_active = false;
        $this->defTable->saveOrFail($def);

        $this->exec('age_up_members');

        $this->assertExitSuccess();
        $this->assertOutputContains('Running legacy age-up logic');
    }

    /**
     * Test: AgeUp falls back to legacy when workflow has no current version.
     */
    public function testAgeUpFallsBackWhenNoCurrentVersion(): void
    {
        // Ensure no matching workflow with the slug
        $this->defTable->deleteAll(['slug' => 'member-age-up']);

        $def = $this->defTable->newEntity([
            'name' => 'Test: member-age-up',
            'slug' => 'member-age-up',
            'trigger_type' => 'event',
            'trigger_config' => [],
            'entity_type' => 'Members',
            'is_active' => true,
        ]);
        $this->defTable->saveOrFail($def);
        // No version attached — current_version_id is null

        $this->exec('age_up_members');

        $this->assertExitSuccess();
        $this->assertOutputContains('Running legacy age-up logic');
    }

    // =====================================================
    // SyncMemberWarrantableStatusesCommand Tests
    // =====================================================

    /**
     * Test: SyncWarrantable fires WarrantableSyncTriggered event after sync.
     */
    public function testSyncWarrantableFiresEventAfterSync(): void
    {
        $this->exec('sync_member_warrantable_statuses');

        $this->assertExitSuccess();
        $this->assertOutputContains('Dispatched Members.WarrantableSyncTriggered');
    }

    /**
     * Test: SyncWarrantable does NOT fire event in dry-run mode.
     */
    public function testSyncWarrantableNoEventOnDryRun(): void
    {
        $this->exec('sync_member_warrantable_statuses --dry-run');

        $this->assertExitSuccess();
        $this->assertOutputNotContains('Dispatched Members.WarrantableSyncTriggered');
    }

    // =====================================================
    // SyncActiveWindowStatusesCommand Tests
    // =====================================================

    /**
     * Test: ActiveWindowSync runs legacy when no workflow exists.
     */
    public function testActiveWindowSyncRunsLegacyWhenNoWorkflow(): void
    {
        $this->defTable->deleteAll(['slug' => 'active-window-sync']);

        $this->exec('sync_active_window_statuses');

        $this->assertExitSuccess();
        $this->assertOutputContains('Running legacy active-window sync');
    }

    /**
     * Test: ActiveWindowSync dispatches via workflow when active.
     */
    public function testActiveWindowSyncDispatchesWhenWorkflowActive(): void
    {
        $this->createActiveWorkflow('active-window-sync');

        $stub = $this->createStubIo();
        $command = new SyncActiveWindowStatusesCommand();
        $command->setTriggerDispatcher($this->createMockDispatcher());

        $args = new Arguments([], ['dry-run' => false], ['dry-run']);
        $result = $command->execute($args, $stub['io']);

        $output = implode("\n", $stub['out']->messages());
        $this->assertSame(0, $result);
        $this->assertStringContainsString('Dispatching', $output);
        $this->assertStringContainsString('Workflow dispatched', $output);
        $this->assertStringNotContainsString('Running legacy active-window sync', $output);
    }

    /**
     * Test: ActiveWindowSync dry-run skips workflow dispatch.
     */
    public function testActiveWindowSyncDryRunSkipsWorkflow(): void
    {
        $this->createActiveWorkflow('active-window-sync');

        $this->exec('sync_active_window_statuses --dry-run');

        $this->assertExitSuccess();
        $this->assertOutputContains('Running legacy active-window sync');
    }
}
