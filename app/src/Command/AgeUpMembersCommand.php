<?php
declare(strict_types=1);

namespace App\Command;

use App\Model\Entity\Member;
use App\Services\WorkflowEngine\DefaultWorkflowEngine;
use App\Services\WorkflowEngine\TriggerDispatcher;
use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\Core\Container;
use Cake\I18n\FrozenDate;
use Cake\Log\Log;
use Cake\ORM\TableRegistry;
use Throwable;

/**
 * CLI command to age up youth members who have turned 18.
 *
 * Supports dual-path dispatch: when a 'member-age-up' workflow is active,
 * delegates to the workflow engine; otherwise runs legacy age-up logic.
 * Dispatches per kingdom when kingdoms are configured so workflow actions can
 * still use kingdom context without persisted tenant-scoped definitions.
 */
class AgeUpMembersCommand extends Command
{
    /**
     * Injected dispatcher — null means createTriggerDispatcher() will be called.
     *
     * @var \App\Services\WorkflowEngine\TriggerDispatcher|null
     */
    private ?TriggerDispatcher $triggerDispatcher = null;

    /**
     * Set a custom TriggerDispatcher (for testing).
     *
     * @param \App\Services\WorkflowEngine\TriggerDispatcher $dispatcher Dispatcher instance
     * @return void
     */
    public function setTriggerDispatcher(TriggerDispatcher $dispatcher): void
    {
        $this->triggerDispatcher = $dispatcher;
    }

    /**
     * Create a TriggerDispatcher instance.
     *
     * @return \App\Services\WorkflowEngine\TriggerDispatcher
     */
    protected function createTriggerDispatcher(): TriggerDispatcher
    {
        $container = Container::create();
        $engine = new DefaultWorkflowEngine($container);

        return new TriggerDispatcher($engine);
    }

    /**
     * @inheritDoc
     */
    public function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        $parser = parent::buildOptionParser($parser);

        $parser->addOption('dry-run', [
            'short' => 'd',
            'boolean' => true,
            'default' => false,
            'help' => 'Preview affected members without saving changes.',
        ]);

        return $parser;
    }

    /**
     * Execute command with dual-path dispatch.
     *
     * @param \Cake\Console\Arguments $args Console arguments instance.
     * @param \Cake\Console\ConsoleIo $io Console IO instance.
     * @return int
     */
    public function execute(Arguments $args, ConsoleIo $io): int
    {
        $dryRun = (bool)$args->getOption('dry-run');

        // Check for active 'member-age-up' workflow
        if (!$dryRun && $this->dispatchViaWorkflow($io)) {
            return Command::CODE_SUCCESS;
        }

        // Legacy logic
        $io->info('Running legacy age-up logic...');

        return $this->executeLegacy($args, $io);
    }

    /**
     * Attempt to dispatch via the workflow engine.
     *
     * Iterates over all kingdoms when a global workflow definition is active.
     *
     * @param \Cake\Console\ConsoleIo $io Console IO instance.
     * @return bool True if workflow was dispatched, false to fall back to legacy.
     */
    protected function dispatchViaWorkflow(ConsoleIo $io): bool
    {
        try {
            $branchesTable = TableRegistry::getTableLocator()->get('Branches');
            $kingdoms = $branchesTable->find()
                ->select(['id', 'name'])
                ->where(['type' => 'Kingdom'])
                ->all()
                ->toArray();

            $dispatched = false;
            $dispatcher = null;
            $defTable = TableRegistry::getTableLocator()->get('WorkflowDefinitions');
            $fullDef = $defTable->find()
                ->where([
                    'slug' => 'member-age-up',
                    'is_active' => true,
                    'current_version_id IS NOT' => null,
                ])
                ->contain(['CurrentVersion'])
                ->first();

            if (!$fullDef || !$fullDef->current_version) {
                return false;
            }

            foreach ($kingdoms as $kingdom) {
                $dispatcher = $dispatcher ?? ($this->triggerDispatcher ?? $this->createTriggerDispatcher());
                $io->info(sprintf('Active "member-age-up" workflow found for kingdom: %s. Dispatching...', $kingdom->name));

                $results = $dispatcher->dispatch('Members.AgeUpTriggered', [
                    'triggered_at' => date('c'),
                    'trigger' => 'cron',
                    'kingdom_id' => $kingdom->id,
                ]);

                $successCount = 0;
                foreach ($results as $result) {
                    if ($result->isSuccess()) {
                        $successCount++;
                    }
                }

                $io->success(sprintf('Workflow dispatched for %s (started %d workflow(s)).', $kingdom->name, $successCount));
                $dispatched = true;
            }

            // If no kingdoms found, try global definition
            if (empty($kingdoms)) {
                $dispatcher = $this->triggerDispatcher ?? $this->createTriggerDispatcher();
                $io->info('Active "member-age-up" workflow found. Dispatching...');

                $results = $dispatcher->dispatch('Members.AgeUpTriggered', [
                    'triggered_at' => date('c'),
                    'trigger' => 'cron',
                    'kingdom_id' => null,
                ]);

                $successCount = 0;
                foreach ($results as $result) {
                    if ($result->isSuccess()) {
                        $successCount++;
                    }
                }

                $io->success(sprintf('Workflow dispatched (started %d workflow(s)).', $successCount));
                $dispatched = true;
            }

            return $dispatched;
        } catch (Throwable $e) {
            Log::error('AgeUpMembersCommand: Workflow dispatch failed: ' . $e->getMessage());
            $io->warning('Workflow dispatch failed, falling back to legacy logic.');

            return false;
        }
    }

    /**
     * Run the legacy age-up logic.
     *
     * @param \Cake\Console\Arguments $args Console arguments instance.
     * @param \Cake\Console\ConsoleIo $io Console IO instance.
     * @return int
     */
    protected function executeLegacy(Arguments $args, ConsoleIo $io): int
    {
        $dryRun = (bool)$args->getOption('dry-run');
        $now = FrozenDate::now();
        $membersTable = TableRegistry::getTableLocator()->get('Members');

        $minorStatuses = [
            Member::STATUS_UNVERIFIED_MINOR,
            Member::STATUS_MINOR_PARENT_VERIFIED,
            Member::STATUS_MINOR_MEMBERSHIP_VERIFIED,
            Member::STATUS_VERIFIED_MINOR,
        ];

        $targetYear = (int)$now->format('Y') - 18;
        $targetMonth = (int)$now->format('n');

        $query = $membersTable->find()
            ->where([
                'Members.status IN' => $minorStatuses,
                'Members.birth_year IS NOT' => null,
                'Members.birth_month IS NOT' => null,
            ])
            ->andWhere([
                'OR' => [
                    'Members.birth_year <' => $targetYear,
                    [
                        'Members.birth_year' => $targetYear,
                        'Members.birth_month <=' => $targetMonth,
                    ],
                ],
            ])
            ->contain([]);

        $candidates = $query->all();
        $totalCandidates = $candidates->count();

        if ($totalCandidates === 0) {
            $io->success('No youth members are eligible for age-up processing.');

            return Command::CODE_SUCCESS;
        }

        $io->out(sprintf(
            'Evaluating %d youth member%s as of %s%s',
            $totalCandidates,
            $totalCandidates === 1 ? '' : 's',
            $now->toDateString(),
            $dryRun ? ' (dry-run)' : '',
        ));

        $updated = 0;
        $toActive = 0;
        $toVerifiedMembership = 0;
        $errors = 0;

        $membersTable->getConnection()->transactional(function () use (
            $candidates,
            $membersTable,
            $dryRun,
            &$updated,
            &$toActive,
            &$toVerifiedMembership,
            &$errors,
        ) {
            foreach ($candidates as $member) {
                $age = $member->age;
                if ($age === null || $age < 18) {
                    continue;
                }

                $originalStatus = $member->status;
                $originalParent = $member->parent_id;

                $member->ageUpReview();

                $statusChanged = $member->status !== $originalStatus;
                $parentChanged = $member->parent_id !== $originalParent;

                if (!$statusChanged && !$parentChanged) {
                    continue;
                }

                $updated++;
                if ($member->status === Member::STATUS_ACTIVE) {
                    $toActive++;
                } elseif ($member->status === Member::STATUS_VERIFIED_MEMBERSHIP) {
                    $toVerifiedMembership++;
                }

                if ($dryRun) {
                    continue;
                }

                $member->set('modified_by', 1);

                if (!$membersTable->save($member, ['atomic' => false, 'checkRules' => false, 'validate' => false])) {
                    $errors++;
                }
            }

            return true;
        });

        if ($updated === 0) {
            $io->success('No members required updates.');
        } else {
            $io->out(sprintf(
                'Updated %d member%s (%d → Active, %d → Verified Membership)%s',
                $updated,
                $updated === 1 ? '' : 's',
                $toActive,
                $toVerifiedMembership,
                $dryRun ? ' [dry-run only]' : '',
            ));
        }

        if ($errors > 0) {
            $io->error(sprintf('Failed to save %d member%s.', $errors, $errors === 1 ? '' : 's'));

            return Command::CODE_ERROR;
        }

        return Command::CODE_SUCCESS;
    }
}
