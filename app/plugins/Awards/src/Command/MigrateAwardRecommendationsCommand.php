<?php
declare(strict_types=1);

namespace Awards\Command;

use App\Services\WorkflowEngine\TriggerDispatcher;
use Awards\Model\Entity\RecommendationMigrationRun;
use Awards\Services\RecommendationMigrationService;
use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;

class MigrateAwardRecommendationsCommand extends Command
{
    private ?TriggerDispatcher $triggerDispatcher;

    /**
     * @param \App\Services\WorkflowEngine\TriggerDispatcher|null $triggerDispatcher Workflow trigger dispatcher
     */
    public function __construct(?TriggerDispatcher $triggerDispatcher = null)
    {
        parent::__construct();
        $this->triggerDispatcher = $triggerDispatcher;
    }

    /**
     * Build migration command options.
     *
     * @param \Cake\Console\ConsoleOptionParser $parser Option parser
     * @return \Cake\Console\ConsoleOptionParser
     */
    public function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        $parser = parent::buildOptionParser($parser);
        $parser
            ->setDescription(
                'Audit and migrate existing award recommendations into closed, bestowal, or approval ownership.',
            )
            ->addOption('dry-run', [
                'boolean' => true,
                'default' => true,
                'help' => 'Preview migration results without mutating recommendations.',
            ])
            ->addOption('apply', [
                'boolean' => true,
                'default' => false,
                'help' => 'Apply safe migration actions and persist audit results.',
            ])
            ->addOption('resume', [
                'boolean' => true,
                'default' => false,
                'help' => 'Run in reconciliation mode for previously-started migration work.',
            ])
            ->addOption('actor-id', [
                'default' => '1',
                'help' => 'Member ID to record as the migration actor.',
            ])
            ->addOption('recommendation-id', [
                'help' => 'Limit migration to one recommendation ID.',
            ])
            ->addOption('award-id', [
                'help' => 'Limit migration to one award ID.',
            ])
            ->addOption('branch-id', [
                'help' => 'Limit migration to one branch ID.',
            ])
            ->addOption('state', [
                'help' => 'Limit migration to one current recommendation state.',
            ]);

        return $parser;
    }

    /**
     * Execute the migration command.
     *
     * @param \Cake\Console\Arguments $args Command arguments
     * @param \Cake\Console\ConsoleIo $io Console IO
     * @return int
     */
    public function execute(Arguments $args, ConsoleIo $io): int
    {
        $mode = RecommendationMigrationRun::MODE_DRY_RUN;
        if ((bool)$args->getOption('apply')) {
            $mode = RecommendationMigrationRun::MODE_APPLY;
        } elseif ((bool)$args->getOption('resume')) {
            $mode = RecommendationMigrationRun::MODE_RESUME;
        }

        $filters = array_filter([
            'recommendation_id' => $args->getOption('recommendation-id'),
            'award_id' => $args->getOption('award-id'),
            'branch_id' => $args->getOption('branch-id'),
            'state' => $args->getOption('state'),
        ], fn($value) => $value !== null && $value !== '');

        $service = new RecommendationMigrationService($this->triggerDispatcher);
        $result = $service->run($mode, $filters, (int)$args->getOption('actor-id'));
        if (!$result->isSuccess()) {
            $io->err((string)$result->getError());

            return Command::CODE_ERROR;
        }

        $data = $result->getData();
        $io->success(sprintf('Recommendation migration %s run #%d completed.', $mode, $data['runId']));
        foreach (($data['summary'] ?? []) as $key => $count) {
            $io->out(sprintf(' - %s: %d', $key, $count));
        }
        if (
            $mode !== RecommendationMigrationRun::MODE_DRY_RUN
            && (int)($data['summary']['error'] ?? 0) > 0
        ) {
            $io->err('Recommendation migration completed with record-level errors.');

            return Command::CODE_ERROR;
        }

        return Command::CODE_SUCCESS;
    }
}
