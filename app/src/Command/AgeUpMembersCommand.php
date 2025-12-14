<?php

declare(strict_types=1);

namespace App\Command;

use App\Model\Entity\Member;
use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\I18n\FrozenDate;
use Cake\ORM\TableRegistry;

/**
 * CLI command to age up youth members who have turned 18.
 *
 * Converts qualifying minor statuses to their adult equivalents and unlinks the
 * parent relationship. Intended for scheduled execution.
 */
class AgeUpMembersCommand extends Command
{
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
     * Execute command.
     *
     * @param \Cake\Console\Arguments $args Console arguments instance.
     * @param \Cake\Console\ConsoleIo $io Console IO instance.
     * @return int
     */
    public function execute(Arguments $args, ConsoleIo $io): int
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
            $dryRun ? ' (dry-run)' : ''
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
            &$errors
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
                $dryRun ? ' [dry-run only]' : ''
            ));
        }

        if ($errors > 0) {
            $io->error(sprintf('Failed to save %d member%s.', $errors, $errors === 1 ? '' : 's'));

            return Command::CODE_ERROR;
        }

        return Command::CODE_SUCCESS;
    }
}