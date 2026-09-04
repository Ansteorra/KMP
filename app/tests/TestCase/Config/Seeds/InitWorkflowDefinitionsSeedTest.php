<?php

declare(strict_types=1);

namespace App\Test\TestCase\Config\Seeds;

use Activities\Services\ActivitiesWorkflowProvider;
use AddMembershipCardReuploadEmailTemplate;
use App\Services\WorkflowEngine\Providers\MembersWorkflowProvider;
use App\Services\WorkflowEngine\Providers\WarrantWorkflowProvider;
use App\Services\WorkflowRegistry\WorkflowActionRegistry;
use App\Services\WorkflowRegistry\WorkflowConditionRegistry;
use App\Services\WorkflowRegistry\WorkflowEntityRegistry;
use App\Services\WorkflowRegistry\WorkflowTriggerRegistry;
use App\Test\TestCase\BaseTestCase;
use Awards\Services\AwardsWorkflowProvider;
use InitWorkflowDefinitionsSeed;
use Migrations\Migration\Environment;
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
            $this->assertTrue(
                $workflowMetaBySlug[$slug]['is_active'] ?? false,
                sprintf('Dual-path dispatch slug "%s" must be active after seeding.', $slug),
            );
        }
    }

    public function testMembershipCardReuploadWorkflowUsesMemberEmailTemplate(): void
    {
        $definition = $this->loadWorkflowDefinitionJson('membership-card-reupload-request.json');
        $sendNode = $definition['nodes']['send-reupload-request'];
        $params = $sendNode['config']['params'];

        $this->assertSame('Core.SendEmail', $sendNode['config']['action']);
        $this->assertSame('$.nodes.load-member.result.record.email_address', $params['to']);
        $this->assertSame('membership-card-reupload-requested', $params['template']);
        $this->assertSame('$.trigger.contactEmail', $params['replyTo']);
        $this->assertSame('$.nodes.load-member.result.record.sca_name', $params['vars']['memberScaName']);

        require_once ROOT . '/config/Migrations/20260830170000_AddMembershipCardReuploadEmailTemplate.php';
        $environment = new Environment('membership-card-reupload-template-test', [
            'connection' => 'test',
        ]);
        (new AddMembershipCardReuploadEmailTemplate(20260830170000))
            ->setAdapter($environment->getAdapter())
            ->up();

        $template = $this->getTableLocator()->get('EmailTemplates')
            ->findForSlug('membership-card-reupload-requested');
        $this->assertNotNull($template);
        $this->assertTrue($template->is_active);
        $this->assertStringContainsString('{{contactEmail}}', $template->text_template);
        $this->assertContains('memberScaName', array_column($template->variables_schema, 'name'));
        $this->assertContains('contactEmail', array_column($template->variables_schema, 'name'));
    }

    public function testOfficerAssignmentUpdateWorkflowUsesEphemeralActionContract(): void
    {
        require_once ROOT . '/config/Seeds/InitWorkflowDefinitionsSeed.php';

        $seed = new InitWorkflowDefinitionsSeed();
        $workflowMetaBySlug = [];
        foreach ($seed->getWorkflowMeta() as $workflowMeta) {
            $workflowMetaBySlug[$workflowMeta['slug']] = $workflowMeta;
        }

        $meta = $workflowMetaBySlug['officer-assignment-update'];
        $this->assertSame(
            ['event' => 'Officers.AssignmentUpdateRequested'],
            $meta['trigger_config'],
        );
        $this->assertSame('Officers.Officers', $meta['entity_type']);
        $this->assertSame('ephemeral', $meta['execution_mode']);
        $this->assertTrue($meta['is_active']);

        $definition = $this->loadWorkflowDefinitionJson('officer-assignment-update.json');
        $nodes = $definition['nodes'];
        $this->assertSame('trigger-assignment-update', $definition['startNode']);
        $this->assertSame(
            'Officers.AssignmentUpdateRequested',
            $nodes['trigger-assignment-update']['config']['event'],
        );

        $update = $nodes['update-officer-assignment']['config'];
        $this->assertSame('Officers.UpdateOfficerAssignment', $update['action']);
        $this->assertSame([
            'officerId' => '$.trigger.officerId',
            'actorId' => '$.trigger.actorId',
            'startOn' => '$.trigger.startOn',
            'expiresOn' => '$.trigger.expiresOn',
            'emailAddress' => '$.trigger.emailAddress',
            'deputyDescription' => '$.trigger.deputyDescription',
            'termNote' => '$.trigger.termNote',
        ], $update['params']);

        $extension = $nodes['request-warrant-extension']['config'];
        $this->assertSame('Officers.RequestWarrantExtension', $extension['action']);
        $this->assertSame('$.trigger.actorId', $extension['params']['actorId']);
        $this->assertSame(
            '$.nodes.update-officer-assignment.result.data.warrantMessage',
            $extension['params']['existingWarrantMessage'],
        );
        $this->assertSame(
            [
                'next' => 'prepare-assignment-update-notification-vars',
                'error' => 'prepare-assignment-update-notification-vars',
            ],
            array_column($nodes['request-warrant-extension']['outputs'], 'target', 'port'),
        );

        $prepare = $nodes['prepare-assignment-update-notification-vars']['config'];
        $this->assertSame('Officers.PrepareAssignmentUpdateNotificationVars', $prepare['action']);
        $this->assertSame(
            '$.nodes.update-officer-assignment.result.data.changeSummary',
            $prepare['params']['changeSummary'],
        );
        $this->assertSame(
            '$.nodes.update-officer-assignment.result.data.termChangeNote',
            $prepare['params']['termChangeNote'],
        );
        $this->assertSame(
            '$.nodes.request-warrant-extension.result.data.warrantMessage',
            $prepare['params']['warrantMessage'],
        );
        $this->assertSame(
            'end-saved-with-preparation-warning',
            array_column(
                $nodes['prepare-assignment-update-notification-vars']['outputs'],
                'target',
                'port',
            )['error'],
        );

        $send = $nodes['send-assignment-update-notification']['config'];
        $this->assertSame('Core.SendEmail', $send['action']);
        $this->assertSame('officer-assignment-updated-notification', $send['params']['template']);
        $this->assertSame([
            'memberScaName',
            'officeName',
            'branchName',
            'startDate',
            'endDate',
            'changeSummary',
            'termChangeNote',
            'warrantMessage',
            'siteAdminSignature',
        ], array_keys($send['params']['vars']));
        $this->assertSame(
            'end-saved-with-email-warning',
            array_column($nodes['send-assignment-update-notification']['outputs'], 'target', 'port')['error'],
        );
        $this->assertTrue($nodes['end-complete']['config']['result']['success']);
        $this->assertTrue($nodes['end-complete']['config']['result']['updated']);
        $this->assertSame(
            ['$.nodes.request-warrant-extension.result.data.warning'],
            $nodes['end-complete']['config']['result']['warnings'],
        );
        $prepareWarning = $nodes['end-saved-with-preparation-warning']['config']['result'];
        $this->assertTrue($prepareWarning['success']);
        $this->assertTrue($prepareWarning['updated']);
        $this->assertSame([
            '$.nodes.request-warrant-extension.result.data.warning',
            'Officer notification details could not be prepared. Notify the officer separately.',
        ], $prepareWarning['warnings']);
        $this->assertNotContains(
            '$.nodes.send-assignment-update-notification.result.error',
            $prepareWarning['warnings'],
        );
        $this->assertNotContains(
            '$.nodes.prepare-assignment-update-notification-vars.result.error',
            $prepareWarning['warnings'],
        );

        $emailWarning = $nodes['end-saved-with-email-warning']['config']['result'];
        $this->assertTrue($emailWarning['success']);
        $this->assertTrue($emailWarning['updated']);
        $this->assertSame([
            '$.nodes.request-warrant-extension.result.data.warning',
            'The officer notification could not be sent. Notify the officer separately.',
        ], $emailWarning['warnings']);
        $this->assertNotContains(
            '$.nodes.send-assignment-update-notification.result.error',
            $emailWarning['warnings'],
        );

        $this->registerWorkflowProviders();
        $assignmentActions = [
            'Officers.UpdateOfficerAssignment',
            'Officers.RequestWarrantExtension',
            'Officers.PrepareAssignmentUpdateNotificationVars',
        ];
        foreach ($assignmentActions as $action) {
            $this->assertNotNull(
                WorkflowActionRegistry::getAction($action),
                sprintf('The workflow action "%s" must be registered.', $action),
            );
        }
    }

    public function testObsoleteRecommendationStateWorkflowsAreNotSeeded(): void
    {
        require_once ROOT . '/config/Seeds/InitWorkflowDefinitionsSeed.php';

        $seed = new InitWorkflowDefinitionsSeed();
        $workflowMetaBySlug = [];
        foreach ($seed->getWorkflowMeta() as $workflowMeta) {
            $workflowMetaBySlug[$workflowMeta['slug']] = $workflowMeta;
        }

        $this->assertArrayNotHasKey('awards-recommendation-state-changed', $workflowMetaBySlug);
        $this->assertArrayNotHasKey('awards-recommendation-bulk-transition', $workflowMetaBySlug);
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
