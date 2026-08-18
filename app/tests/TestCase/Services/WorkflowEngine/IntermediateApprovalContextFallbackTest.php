<?php
declare(strict_types=1);

namespace App\Test\TestCase\Services\WorkflowEngine;

use App\Model\Entity\WorkflowApproval;
use App\Model\Entity\WorkflowDefinition;
use App\Model\Entity\WorkflowVersion;
use App\Services\WorkflowEngine\DefaultWorkflowEngine;
use App\Test\TestCase\BaseTestCase;
use Cake\Core\ContainerInterface;
use Cake\ORM\TableRegistry;

class IntermediateApprovalContextFallbackTest extends BaseTestCase
{
    public function testNullApprovalDataUsesPersistedFallbacksWithoutDroppingFalseyValues(): void
    {
        $engine = new DefaultWorkflowEngine($this->createMock(ContainerInterface::class));
        $definitions = TableRegistry::getTableLocator()->get('WorkflowDefinitions');
        $definition = $definitions->saveOrFail($definitions->newEntity([
            'name' => 'Intermediate approval fallback test',
            'slug' => 'intermediate-approval-fallback-' . uniqid(),
            'trigger_type' => WorkflowDefinition::TRIGGER_MANUAL,
            'is_active' => true,
        ]));
        $versions = TableRegistry::getTableLocator()->get('WorkflowVersions');
        $version = $versions->saveOrFail($versions->newEntity([
            'workflow_definition_id' => (int)$definition->id,
            'version_number' => 1,
            'definition' => [
                'nodes' => [
                    'trigger' => [
                        'type' => 'trigger',
                        'config' => [],
                        'outputs' => [['port' => 'default', 'target' => 'approval']],
                    ],
                    'approval' => [
                        'type' => 'approval',
                        'config' => [
                            'approverType' => 'permission',
                            'requiredCount' => 3,
                        ],
                        'outputs' => [
                            ['port' => 'approved', 'target' => 'end'],
                            ['port' => 'rejected', 'target' => 'end'],
                        ],
                    ],
                    'end' => ['type' => 'end', 'config' => [], 'outputs' => []],
                ],
            ],
            'status' => WorkflowVersion::STATUS_PUBLISHED,
        ]));
        $definition->current_version_id = (int)$version->id;
        $definitions->saveOrFail($definition);

        $startResult = $engine->startWorkflow((string)$definition->slug);
        $this->assertTrue($startResult->isSuccess(), (string)$startResult->reason);
        $instanceId = (int)$startResult->data['instanceId'];
        $approvals = TableRegistry::getTableLocator()->get('WorkflowApprovals');
        $approval = $approvals->find()
            ->where([
                'workflow_instance_id' => $instanceId,
                'node_id' => 'approval',
                'status' => WorkflowApproval::STATUS_PENDING,
            ])
            ->firstOrFail();
        $approval->approved_count = 2;
        $approval->required_count = 3;
        $approverConfig = $approval->approver_config ?? [];
        $approverConfig['approval_chain'] = [
            ['approver_id' => self::ADMIN_MEMBER_ID],
            ['approver_id' => self::TEST_MEMBER_AGATHA_ID],
        ];
        $approval->approver_config = $approverConfig;
        $approvals->saveOrFail($approval);

        $result = $engine->fireIntermediateApprovalActions($instanceId, 'approval', [
            'approvedCount' => null,
            'requiredCount' => null,
            'approverId' => null,
            'nextApproverId' => null,
            'approvalChain' => null,
            'decision' => null,
            'comment' => null,
            'falseValue' => false,
            'zeroValue' => 0,
        ]);

        $this->assertTrue($result->isSuccess(), (string)$result->reason);
        $instance = TableRegistry::getTableLocator()->get('WorkflowInstances')->get($instanceId);
        $nodeContext = $instance->context['nodes']['approval'] ?? [];
        $this->assertSame(2, $nodeContext['approvedCount'] ?? null);
        $this->assertSame(3, $nodeContext['requiredCount'] ?? null);
        $this->assertSame($approverConfig['approval_chain'], $nodeContext['approvalChain'] ?? null);
        $this->assertSame('approve', $nodeContext['decision'] ?? null);
        $this->assertArrayHasKey('approverId', $nodeContext);
        $this->assertNull($nodeContext['approverId']);
        $this->assertFalse($nodeContext['falseValue'] ?? true);
        $this->assertSame(0, $nodeContext['zeroValue'] ?? null);
    }
}
