<?php

declare(strict_types=1);

namespace App\Test\TestCase\Services\WorkflowEngine;

use App\Model\Entity\WorkflowInstance;
use App\Services\WorkflowEngine\DefaultWorkflowEngine;
use App\Services\WorkflowRegistry\WorkflowActionRegistry;
use App\Test\TestCase\BaseTestCase;
use Cake\Core\ContainerInterface;
use Cake\ORM\TableRegistry;

class WorkflowEntityMetadataHydrationAction
{
    public function createAuthorization(array $context, array $config): array
    {
        return [
            'authorizationId' => 987,
            'memberId' => $context['trigger']['memberId'] ?? null,
        ];
    }
}

class WorkflowEntityMetadataHydrationTest extends BaseTestCase
{
    private DefaultWorkflowEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();

        $actionService = new WorkflowEntityMetadataHydrationAction();
        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturnCallback(
            fn(string $id): bool => $id === WorkflowEntityMetadataHydrationAction::class,
        );
        $container->method('get')->willReturnCallback(function (string $id) use ($actionService) {
            if ($id === WorkflowEntityMetadataHydrationAction::class) {
                return $actionService;
            }

            throw new \RuntimeException("Service '{$id}' not registered in test container.");
        });

        $this->engine = new DefaultWorkflowEngine($container);

        WorkflowActionRegistry::register('TestEntityMetadata', [[
            'action' => 'TestEntityMetadata.CreateAuthorization',
            'label' => 'Create Authorization',
            'description' => 'Returns a created authorization ID for metadata hydration tests.',
            'inputSchema' => [],
            'outputSchema' => [],
            'serviceClass' => WorkflowEntityMetadataHydrationAction::class,
            'serviceMethod' => 'createAuthorization',
        ]]);
    }

    protected function tearDown(): void
    {
        WorkflowActionRegistry::unregister('TestEntityMetadata');
        parent::tearDown();
    }

    public function testStartWorkflowHydratesEntityIdFromActionResultBeforeWaiting(): void
    {
        $definitionsTable = TableRegistry::getTableLocator()->get('WorkflowDefinitions');
        $versionsTable = TableRegistry::getTableLocator()->get('WorkflowVersions');
        $instancesTable = TableRegistry::getTableLocator()->get('WorkflowInstances');

        $slug = 'entity-metadata-hydration-' . uniqid();

        $definition = $definitionsTable->newEntity([
            'name' => 'Entity Metadata Hydration',
            'slug' => $slug,
            'trigger_type' => 'event',
            'entity_type' => 'Activities.Authorizations',
            'is_active' => true,
        ]);
        $definitionsTable->saveOrFail($definition);

        $version = $versionsTable->newEntity([
            'workflow_definition_id' => $definition->id,
            'version_number' => 1,
            'status' => 'published',
            'definition' => [
                'nodes' => [
                    'trigger1' => [
                        'type' => 'trigger',
                        'config' => [
                            'event' => 'Test.AuthorizationRequested',
                            'entityIdField' => 'authorizationId',
                        ],
                        'outputs' => [['port' => 'default', 'target' => 'create-auth']],
                    ],
                    'create-auth' => [
                        'type' => 'action',
                        'config' => ['action' => 'TestEntityMetadata.CreateAuthorization'],
                        'outputs' => [['port' => 'default', 'target' => 'approval-gate']],
                    ],
                    'approval-gate' => [
                        'type' => 'approval',
                        'config' => [
                            'approverType' => 'member',
                            'approverConfig' => ['member_id' => self::ADMIN_MEMBER_ID],
                            'requiredCount' => 1,
                        ],
                        'outputs' => [
                            ['port' => 'approved', 'target' => 'end1'],
                            ['port' => 'rejected', 'target' => 'end1'],
                        ],
                    ],
                    'end1' => [
                        'type' => 'end',
                        'config' => [],
                        'outputs' => [],
                    ],
                ],
            ],
        ]);
        $versionsTable->saveOrFail($version);

        $definition->current_version_id = $version->id;
        $definitionsTable->saveOrFail($definition);

        $result = $this->engine->startWorkflow($slug, ['memberId' => self::ADMIN_MEMBER_ID], self::ADMIN_MEMBER_ID);

        $this->assertTrue($result->isSuccess());

        $instance = $instancesTable->get($result->data['instanceId']);
        $this->assertSame(WorkflowInstance::STATUS_WAITING, $instance->status);
        $this->assertSame('Activities.Authorizations', $instance->entity_type);
        $this->assertSame(987, $instance->entity_id);
        $this->assertSame(987, $instance->context['trigger']['authorizationId']);
    }
}
