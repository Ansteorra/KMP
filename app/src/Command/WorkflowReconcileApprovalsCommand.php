<?php
declare(strict_types=1);

namespace App\Command;

use App\Application;
use App\Model\Entity\WorkflowApproval;
use App\Model\Entity\WorkflowInstance;
use App\Services\WorkflowEngine\WorkflowEngineInterface;
use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\CommandFactoryInterface;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\Core\Container;
use Cake\ORM\TableRegistry;
use LogicException;

class WorkflowReconcileApprovalsCommand extends Command
{
    /**
     * @param \Cake\Console\CommandFactoryInterface|null $factory Command factory
     * @param \App\Services\WorkflowEngine\WorkflowEngineInterface|null $workflowEngine Workflow engine
     */
    public function __construct(
        ?CommandFactoryInterface $factory = null,
        private ?WorkflowEngineInterface $workflowEngine = null,
    ) {
        parent::__construct($factory);
    }

    /**
     * @inheritDoc
     */
    public static function defaultName(): string
    {
        return 'workflow reconcile_approvals';
    }

    /**
     * @inheritDoc
     */
    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        $parser = parent::buildOptionParser($parser);
        $parser->setDescription(
            'Find resolved approvals whose workflow instances are still waiting and resume them.',
        );
        $parser->addOption('dry-run', [
            'boolean' => true,
            'default' => false,
            'help' => 'Report stuck approvals without resuming workflows.',
        ]);

        return $parser;
    }

    /**
     * @inheritDoc
     */
    public function execute(Arguments $args, ConsoleIo $io): int
    {
        $dryRun = (bool)$args->getOption('dry-run');
        $approvalsTable = TableRegistry::getTableLocator()->get('WorkflowApprovals');
        $responsesTable = TableRegistry::getTableLocator()->get('WorkflowApprovalResponses');
        $candidates = $approvalsTable->find()
            ->contain(['WorkflowInstances'])
            ->where([
                'WorkflowApprovals.status IN' => [
                    WorkflowApproval::STATUS_APPROVED,
                    WorkflowApproval::STATUS_REJECTED,
                ],
                'WorkflowInstances.status' => WorkflowInstance::STATUS_WAITING,
            ])
            ->all();

        $stuck = 0;
        $resumed = 0;
        $failed = 0;

        foreach ($candidates as $approval) {
            $instance = $approval->workflow_instance;
            if (!$instance || !in_array($approval->node_id, $instance->active_nodes ?? [], true)) {
                continue;
            }

            $stuck++;
            $io->out(sprintf(
                'Approval #%d resolved as %s but instance #%d is still waiting on node %s.',
                $approval->id,
                $approval->status,
                $approval->workflow_instance_id,
                $approval->node_id,
            ));

            if ($dryRun) {
                continue;
            }

            $latestResponse = $responsesTable->find()
                ->where(['workflow_approval_id' => $approval->id])
                ->order(['responded_at' => 'DESC', 'id' => 'DESC'])
                ->first();
            $outputPort = $approval->status === WorkflowApproval::STATUS_APPROVED ? 'approved' : 'rejected';
            $resumeData = [
                'approval' => [
                    'approvalStatus' => $approval->status,
                    'instanceId' => $approval->workflow_instance_id,
                    'nodeId' => $approval->node_id,
                ],
                'approverId' => $latestResponse?->member_id,
                'decision' => $latestResponse?->decision ?? $outputPort,
                'comment' => $latestResponse?->comment,
            ];

            $result = $this->getWorkflowEngine()->resumeWorkflow(
                (int)$approval->workflow_instance_id,
                (string)$approval->node_id,
                $outputPort,
                $resumeData,
            );
            if ($result->isSuccess()) {
                $resumed++;
                continue;
            }

            $failed++;
            $io->err(sprintf(
                'Failed to resume instance #%d from approval #%d: %s',
                $approval->workflow_instance_id,
                $approval->id,
                $result->getError() ?? 'unknown error',
            ));
        }

        $io->out(sprintf(
            'Approval reconciliation complete: stuck=%d resumed=%d failed=%d%s',
            $stuck,
            $resumed,
            $failed,
            $dryRun ? ' (dry-run)' : '',
        ));

        return $failed === 0 ? Command::CODE_SUCCESS : Command::CODE_ERROR;
    }

    /**
     * Get the workflow engine.
     *
     * @return \App\Services\WorkflowEngine\WorkflowEngineInterface
     */
    private function getWorkflowEngine(): WorkflowEngineInterface
    {
        if ($this->workflowEngine === null) {
            $container = new Container();
            (new Application(CONFIG))->services($container);
            if (!$container->has(WorkflowEngineInterface::class)) {
                throw new LogicException('WorkflowEngine service is not available.');
            }
            $this->workflowEngine = $container->get(WorkflowEngineInterface::class);
        }

        return $this->workflowEngine;
    }
}
