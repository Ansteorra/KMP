<?php
declare(strict_types=1);

namespace Awards\Command;

use App\Model\Entity\WorkflowInstance;
use Awards\Model\Entity\RecommendationApprovalRun;
use Awards\Model\Entity\RecommendationMigrationResult;
use Awards\Services\RecommendationMigrationService;
use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\ORM\TableRegistry;

class ReconcileRecommendationStateCommand extends Command
{
    /**
     * @param \Awards\Services\RecommendationMigrationService|null $migrationService Migration classifier
     */
    public function __construct(private ?RecommendationMigrationService $migrationService = null)
    {
        parent::__construct();
        $this->migrationService ??= new RecommendationMigrationService();
    }

    /**
     * @inheritDoc
     */
    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        $parser = parent::buildOptionParser($parser);
        $parser
            ->setDescription('Compare restored legacy recommendation status/state against hydrated workflow ownership.')
            ->addOption('recommendation-id', [
                'help' => 'Limit reconciliation to one recommendation ID.',
            ])
            ->addOption('state', [
                'help' => 'Limit reconciliation to one legacy recommendation state.',
            ]);

        return $parser;
    }

    /**
     * @inheritDoc
     */
    public function execute(Arguments $args, ConsoleIo $io): int
    {
        $recommendationsTable = TableRegistry::getTableLocator()->get('Awards.Recommendations');
        $runsTable = TableRegistry::getTableLocator()->get('Awards.RecommendationApprovalRuns');
        $query = $recommendationsTable->find()->contain(['Awards.ApprovalProcesses']);
        if ($args->getOption('recommendation-id')) {
            $query->where(['Recommendations.id' => (int)$args->getOption('recommendation-id')]);
        }
        if ($args->getOption('state')) {
            $query->where(['Recommendations.state' => (string)$args->getOption('state')]);
        }

        $checked = 0;
        $divergences = 0;
        foreach ($query->all() as $recommendation) {
            $checked++;
            $classification = $this->migrationService->classify($recommendation);
            $target = $classification['target'];
            $activeRun = $runsTable->find()
                ->contain(['WorkflowInstances'])
                ->where([
                    'recommendation_id' => $recommendation->id,
                    'RecommendationApprovalRuns.status IN' => [
                        RecommendationApprovalRun::STATUS_IN_PROGRESS,
                        RecommendationApprovalRun::STATUS_CHANGES_REQUESTED,
                    ],
                ])
                ->order(['RecommendationApprovalRuns.id' => 'DESC'])
                ->first();

            $reason = $this->findDivergence($recommendation, $target, $activeRun);
            if ($reason === null) {
                continue;
            }

            $divergences++;
            $io->out(sprintf(
                'Recommendation #%d (%s / %s): expected %s, %s',
                $recommendation->id,
                (string)$recommendation->status,
                (string)$recommendation->state,
                $target,
                $reason,
            ));
        }

        $io->out(sprintf(
            'Recommendation state reconciliation complete: checked=%d divergences=%d',
            $checked,
            $divergences,
        ));

        return $divergences === 0 ? Command::CODE_SUCCESS : Command::CODE_ERROR;
    }

    /**
     * Find the first reconciliation issue for a recommendation.
     *
     * @param mixed $recommendation Recommendation entity
     * @param string $target Expected migration target
     * @param mixed $activeRun Active approval run, if any
     * @return string|null
     */
    private function findDivergence($recommendation, string $target, $activeRun): ?string
    {
        if ($target === RecommendationMigrationResult::TARGET_APPROVAL_WORKFLOW) {
            if (!$activeRun) {
                return 'no active approval workflow run exists.';
            }
            $instanceStatus = $activeRun->workflow_instance->status ?? null;
            $activeStatuses = [WorkflowInstance::STATUS_RUNNING, WorkflowInstance::STATUS_WAITING];
            if (!in_array($instanceStatus, $activeStatuses, true)) {
                return "workflow instance is {$instanceStatus}.";
            }

            return null;
        }

        if ($target === RecommendationMigrationResult::TARGET_BESTOWAL) {
            return $recommendation->bestowal_id === null ? 'no bestowal is linked.' : null;
        }

        if ($target === RecommendationMigrationResult::TARGET_CLOSED && $activeRun) {
            return "unexpected active approval workflow run #{$activeRun->id} exists.";
        }

        return null;
    }
}
