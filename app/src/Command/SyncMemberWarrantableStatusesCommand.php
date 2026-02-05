<?php

declare(strict_types=1);

namespace App\Command;

use App\Model\Entity\Member;
use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\I18n\FrozenTime;
use Cake\ORM\TableRegistry;

/**
 * CLI command to synchronize stored member warrantable flags.
 *
 * Recomputes warrant eligibility for a targeted candidate set and persists only
 * rows where the stored boolean value is stale.
 *
 * Candidate members are those:
 * - modified in the last 24 hours, OR
 * - currently marked warrantable=true while status is verified and membership is expired.
 */
class SyncMemberWarrantableStatusesCommand extends Command
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
            'help' => 'Preview warrantable status changes without saving to the database.',
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
        $now = FrozenTime::now();
        $recentThreshold = $now->subHours(24);
        $membersTable = TableRegistry::getTableLocator()->get('Members');
        $supportsModifiedBy = $membersTable->getSchema()->hasColumn('modified_by');

        $io->out(sprintf(
            'Member warrantable sync started at %s%s',
            $now->toDateTimeString(),
            $dryRun ? ' (dry-run)' : ''
        ));

        $summary = [
            'scanned' => 0,
            'changed' => 0,
            'became_warrantable' => 0,
            'became_not_warrantable' => 0,
            'errors' => 0,
        ];

        $membersTable->getConnection()->transactional(function () use (
            $membersTable,
            $supportsModifiedBy,
            $now,
            $recentThreshold,
            $dryRun,
            &$summary
        ) {
            $members = $membersTable->find()
                ->where([
                    'OR' => [
                        'Members.modified >=' => $recentThreshold,
                        [
                            'Members.status' => Member::STATUS_VERIFIED_MEMBERSHIP,
                            'Members.membership_expires_on <' => $now,
                            'Members.warrantable' => true,
                        ],
                    ],
                ])
                ->all();

            foreach ($members as $member) {
                $summary['scanned']++;

                $originalWarrantable = (bool)$member->warrantable;
                $member->warrantableReview();
                $currentWarrantable = (bool)$member->warrantable;

                if ($currentWarrantable === $originalWarrantable) {
                    continue;
                }

                $summary['changed']++;
                if ($currentWarrantable) {
                    $summary['became_warrantable']++;
                } else {
                    $summary['became_not_warrantable']++;
                }

                if ($dryRun) {
                    continue;
                }

                if ($supportsModifiedBy) {
                    $member->set('modified_by', 1);
                }

                if (!$membersTable->save($member, [
                    'atomic' => false,
                    'checkRules' => false,
                    'validate' => false,
                    'callbacks' => false,
                ])) {
                    $summary['errors']++;
                }
            }

            return true;
        });

        $io->out(sprintf(
            'Scanned %d candidate member%s.',
            $summary['scanned'],
            $summary['scanned'] === 1 ? '' : 's'
        ));

        if ($summary['changed'] === 0) {
            $io->success('No warrantable status changes were required.');
        } else {
            $io->out(sprintf(
                'Updated %d member%s (%d became warrantable, %d became not warrantable)%s',
                $summary['changed'],
                $summary['changed'] === 1 ? '' : 's',
                $summary['became_warrantable'],
                $summary['became_not_warrantable'],
                $dryRun ? ' [dry-run only]' : ''
            ));
        }

        if ($summary['errors'] > 0) {
            $io->error(sprintf(
                'Failed to save %d member%s.',
                $summary['errors'],
                $summary['errors'] === 1 ? '' : 's'
            ));

            return Command::CODE_ERROR;
        }

        return Command::CODE_SUCCESS;
    }
}
