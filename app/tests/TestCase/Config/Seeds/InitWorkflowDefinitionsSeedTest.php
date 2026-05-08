<?php

declare(strict_types=1);

namespace App\Test\TestCase\Config\Seeds;

use Activities\Services\ActivitiesWorkflowProvider;
use App\Services\WorkflowEngine\Providers\MembersWorkflowProvider;
use App\Services\WorkflowEngine\Providers\WarrantWorkflowProvider;
use App\Services\WorkflowRegistry\WorkflowActionRegistry;
use App\Services\WorkflowRegistry\WorkflowConditionRegistry;
use App\Services\WorkflowRegistry\WorkflowEntityRegistry;
use App\Services\WorkflowRegistry\WorkflowTriggerRegistry;
use App\Test\TestCase\BaseTestCase;
use Awards\Services\AwardsWorkflowProvider;
use InitWorkflowDefinitionsSeed;
use Officers\Services\OfficersWorkflowProvider;
use Waivers\Services\WaiversWorkflowProvider;

class InitWorkflowDefinitionsSeedTest extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->clearWorkflowRegistries();
    }

    protected function tearDown(): void
    {
        $this->clearWorkflowRegistries();
        parent::tearDown();
    }

    public function testAuthorizationWorkflowUsesAuthorizationsEntityType(): void
    {
        require_once ROOT . '/config/Seeds/InitWorkflowDefinitionsSeed.php';

        $seed = new InitWorkflowDefinitionsSeed();
        $authorizationWorkflow = null;

        foreach ($seed->getWorkflowMeta() as $workflowMeta) {
            if (($workflowMeta['slug'] ?? null) === 'activities-authorization-request') {
                $authorizationWorkflow = $workflowMeta;
                break;
            }
        }

        $this->assertNotNull($authorizationWorkflow);
        $this->assertSame('Activities.Authorizations', $authorizationWorkflow['entity_type']);
    }

    public function testSeedWorkflowTriggerConfigMatchesJsonTriggerNodes(): void
    {
        require_once ROOT . '/config/Seeds/InitWorkflowDefinitionsSeed.php';

        $seed = new InitWorkflowDefinitionsSeed();

        foreach ($seed->getWorkflowMeta() as $workflowMeta) {
            $definition = $this->loadWorkflowDefinitionJson($workflowMeta['json_file']);
            $triggerEvents = $this->extractTriggerEvents($definition);

            $this->assertContains(
                $workflowMeta['trigger_config']['event'],
                $triggerEvents,
                sprintf(
                    'Workflow "%s" metadata event must match a trigger node in %s.',
                    $workflowMeta['slug'],
                    $workflowMeta['json_file'],
                ),
            );
        }
    }

    public function testSeedWorkflowTriggerEventsAreRegistered(): void
    {
        require_once ROOT . '/config/Seeds/InitWorkflowDefinitionsSeed.php';
        $this->registerWorkflowProviders();

        $seed = new InitWorkflowDefinitionsSeed();

        foreach ($seed->getWorkflowMeta() as $workflowMeta) {
            $event = $workflowMeta['trigger_config']['event'];

            $this->assertNotNull(
                WorkflowTriggerRegistry::getTrigger($event),
                sprintf(
                    'Workflow "%s" uses trigger event "%s", but no provider registers that trigger.',
                    $workflowMeta['slug'],
                    $event,
                ),
            );
        }
    }

    public function testKnownDualPathDispatchesHaveMatchingSeededDefinitions(): void
    {
        require_once ROOT . '/config/Seeds/InitWorkflowDefinitionsSeed.php';

        $seed = new InitWorkflowDefinitionsSeed();
        $workflowMetaBySlug = [];
        foreach ($seed->getWorkflowMeta() as $workflowMeta) {
            $workflowMetaBySlug[$workflowMeta['slug']] = $workflowMeta;
        }

        $dualPathDispatches = [
            'warrants-roster-approval' => 'Warrants.RosterCreated',
            'waiver-closure' => 'Waivers.CollectionClosed',
        ];

        foreach ($dualPathDispatches as $slug => $event) {
            $this->assertArrayHasKey(
                $slug,
                $workflowMetaBySlug,
                sprintf('Dual-path dispatch slug "%s" should have a seeded workflow definition.', $slug),
            );
            $this->assertSame(
                $event,
                $workflowMetaBySlug[$slug]['trigger_config']['event'],
                sprintf('Dual-path dispatch slug "%s" should seed the event its controller dispatches.', $slug),
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function loadWorkflowDefinitionJson(string $jsonFile): array
    {
        $path = ROOT . '/config/Seeds/WorkflowDefinitions/' . $jsonFile;
        $contents = file_get_contents($path);

        $this->assertIsString($contents, sprintf('Workflow definition file should be readable: %s', $jsonFile));

        $definition = json_decode($contents, true);

        $this->assertIsArray($definition, sprintf('Workflow definition file should contain valid JSON: %s', $jsonFile));

        return $definition;
    }

    /**
     * @param array<string, mixed> $definition Workflow definition graph.
     * @return array<int, string>
     */
    private function extractTriggerEvents(array $definition): array
    {
        $events = [];

        foreach (($definition['nodes'] ?? []) as $node) {
            if (($node['type'] ?? null) !== 'trigger') {
                continue;
            }

            $event = $node['config']['event'] ?? $node['config']['eventName'] ?? null;
            if (is_string($event) && $event !== '') {
                $events[] = $event;
            }
        }

        return $events;
    }

    private function registerWorkflowProviders(): void
    {
        ActivitiesWorkflowProvider::register();
        AwardsWorkflowProvider::register();
        MembersWorkflowProvider::register();
        OfficersWorkflowProvider::register();
        WarrantWorkflowProvider::register();
        WaiversWorkflowProvider::register();
    }

    private function clearWorkflowRegistries(): void
    {
        WorkflowActionRegistry::clear();
        WorkflowConditionRegistry::clear();
        WorkflowEntityRegistry::clear();
        WorkflowTriggerRegistry::clear();
    }
}
