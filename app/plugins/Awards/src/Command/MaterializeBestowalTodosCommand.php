<?php
declare(strict_types=1);

namespace Awards\Command;

use Awards\Model\Entity\Bestowal;
use Awards\Services\BestowalTodoMaterializationService;
use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\ORM\Locator\LocatorAwareTrait;

/**
 * Backfill the parallel to-do checklist for bestowals that predate (or were
 * created without) their award's assigned bestowal to-do template.
 *
 * New bestowals materialize their checklist automatically via
 * {@see \Awards\Services\BestowalCreationService} / AdHocBestowalService. This
 * command exists for onboarding: bestowals created by the recommendation
 * backfill migration are written before templates are assigned to awards, so
 * they need a one-time materialization pass. Materialization is idempotent, so
 * re-running is safe and never duplicates checklist items.
 */
class MaterializeBestowalTodosCommand extends Command
{
    use LocatorAwareTrait;

    private BestowalTodoMaterializationService $materializationService;

    /**
     * @param \Awards\Services\BestowalTodoMaterializationService|null $materializationService Optional injected service.
     */
    public function __construct(?BestowalTodoMaterializationService $materializationService = null)
    {
        parent::__construct();
        $this->materializationService = $materializationService ?? new BestowalTodoMaterializationService();
    }

    /**
     * Build command options.
     *
     * @param \Cake\Console\ConsoleOptionParser $parser Option parser
     * @return \Cake\Console\ConsoleOptionParser
     */
    public function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        $parser = parent::buildOptionParser($parser);
        $parser
            ->setDescription(
                'Materialize bestowal to-do checklists for existing open bestowals (idempotent).',
            )
            ->addOption('bestowal-id', [
                'help' => 'Limit materialization to a single bestowal ID.',
            ]);

        return $parser;
    }

    /**
     * Execute the command.
     *
     * @param \Cake\Console\Arguments $args Command arguments
     * @param \Cake\Console\ConsoleIo $io Console IO
     * @return int
     */
    public function execute(Arguments $args, ConsoleIo $io): int
    {
        $query = $this->fetchTable('Awards.Bestowals')->find()
            ->where([
                'Bestowals.deleted IS' => null,
                'OR' => [
                    'Bestowals.lifecycle_status IS' => null,
                    'Bestowals.lifecycle_status !=' => Bestowal::LIFECYCLE_CANCELLED,
                ],
            ])
            ->orderBy(['Bestowals.id' => 'ASC']);

        $bestowalId = $args->getOption('bestowal-id');
        if ($bestowalId !== null && $bestowalId !== '') {
            $query->where(['Bestowals.id' => (int)$bestowalId]);
        }

        $processed = 0;
        $created = 0;
        $failed = 0;
        foreach ($query->all() as $bestowal) {
            $processed++;
            $result = $this->materializationService->materializeForBestowal($bestowal);
            if (!$result->isSuccess()) {
                $failed++;
                $io->err(sprintf(
                    ' - bestowal #%d: %s',
                    (int)$bestowal->id,
                    (string)$result->getError(),
                ));
                continue;
            }

            $data = $result->getData();
            if (is_array($data)) {
                $created += count($data);
            }
        }

        $io->success(sprintf(
            'Processed %d bestowal(s); materialized %d new to-do item(s).',
            $processed,
            $created,
        ));

        if ($failed > 0) {
            $io->err(sprintf('%d bestowal(s) failed to materialize.', $failed));

            return Command::CODE_ERROR;
        }

        return Command::CODE_SUCCESS;
    }
}
