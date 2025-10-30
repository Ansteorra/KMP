<?php

declare(strict_types=1);

namespace App\Command;

use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\ORM\TableRegistry;
use Cake\I18n\FrozenTime;

/**
 * Migrate Award Events Command
 * 
 * Migrates legacy Award Events to the new Gatherings system with the following steps:
 * 1. Create a "Kingdom Court" gathering activity
 * 2. Associate all awards with the "Kingdom Court" activity
 * 3. Create a "Kingdom Calendar Event" gathering type
 * 4. Create gatherings for each award event
 * 5. Add "Kingdom Court" activity to each new gathering
 * 6. Update award recommendations to reference gatherings instead of events
 * 
 * Usage:
 *   bin/cake migrate_award_events
 *   bin/cake migrate_award_events --dry-run
 */
class MigrateAwardEventsCommand extends Command
{
    /**
     * @var \Cake\Console\ConsoleIo
     */
    protected $io;

    /**
     * @var bool
     */
    protected $dryRun = false;

    /**
     * Hook method for defining this command's option parser.
     *
     * @param \Cake\Console\ConsoleOptionParser $parser The parser to be defined
     * @return \Cake\Console\ConsoleOptionParser
     */
    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        $parser
            ->setDescription('Migrate Award Events to Gatherings system')
            ->addOption('dry-run', [
                'help' => 'Run without making any database changes',
                'boolean' => true,
                'default' => false,
            ]);

        return $parser;
    }

    /**
     * Execute the command
     *
     * @param \Cake\Console\Arguments $args The command arguments.
     * @param \Cake\Console\ConsoleIo $io The console io
     * @return int|null The exit code or null for success
     */
    public function execute(Arguments $args, ConsoleIo $io): ?int
    {
        $this->io = $io;
        $this->dryRun = $args->getOption('dry-run');

        if ($this->dryRun) {
            $io->warning('DRY RUN MODE - No changes will be made to the database');
            $io->out('');
        }

        $io->out('<info>Starting Award Events to Gatherings migration...</info>');
        $io->hr();

        try {
            // Step 1: Create Kingdom Court activity
            $kingdomCourtActivity = $this->createKingdomCourtActivity();
            if (!$kingdomCourtActivity) {
                $io->error('Failed to create Kingdom Court activity');
                return Command::CODE_ERROR;
            }

            // Step 2: Add all awards to Kingdom Court activity
            $this->associateAwardsWithActivity($kingdomCourtActivity->id);

            // Step 3: Create Kingdom Calendar Event gathering type
            $gatheringType = $this->createKingdomCalendarEventType();
            if (!$gatheringType) {
                $io->error('Failed to create Kingdom Calendar Event gathering type');
                return Command::CODE_ERROR;
            }

            // Step 4-5: Create gatherings for each award event with Kingdom Court activity
            $eventGatheringMap = $this->createGatheringsFromAwardEvents($gatheringType->id, $kingdomCourtActivity->id);

            // Step 6-7: Update award recommendations
            $this->updateAwardRecommendations($eventGatheringMap);

            $io->hr();
            $io->success('Migration completed successfully!');

            if ($this->dryRun) {
                $io->warning('DRY RUN MODE - No changes were made to the database');
            }

            return Command::CODE_SUCCESS;
        } catch (\Exception $e) {
            $io->error('Migration failed: ' . $e->getMessage());
            $io->out('Stack trace:');
            $io->out($e->getTraceAsString());
            return Command::CODE_ERROR;
        }
    }

    /**
     * Step 1: Create Kingdom Court gathering activity
     *
     * @return \App\Model\Entity\GatheringActivity|null
     */
    protected function createKingdomCourtActivity()
    {
        $this->io->out('<info>Step 1: Creating Kingdom Court activity...</info>');

        $gatheringActivitiesTable = TableRegistry::getTableLocator()->get('GatheringActivities');

        // Check if it already exists
        $existing = $gatheringActivitiesTable->find()
            ->where(['name' => 'Kingdom Court'])
            ->first();

        if ($existing) {
            $this->io->out('  - Kingdom Court activity already exists (ID: ' . $existing->id . ')');
            return $existing;
        }

        if ($this->dryRun) {
            $this->io->out('  - [DRY RUN] Would create Kingdom Court activity');
            // Return a mock entity for dry run
            $mock = $gatheringActivitiesTable->newEmptyEntity();
            $mock->id = 999999; // Fake ID for dry run
            $mock->name = 'Kingdom Court';
            return $mock;
        }

        $activity = $gatheringActivitiesTable->newEntity([
            'name' => 'Kingdom Court',
            'description' => 'Official Kingdom Court sessions where awards and recognitions are presented',
        ]);

        if ($gatheringActivitiesTable->save($activity)) {
            $this->io->success('  ✓ Created Kingdom Court activity (ID: ' . $activity->id . ')');
            return $activity;
        } else {
            $this->io->error('  ✗ Failed to create Kingdom Court activity');
            $this->io->out('    Errors: ' . json_encode($activity->getErrors()));
            return null;
        }
    }

    /**
     * Step 2: Associate all awards with Kingdom Court activity
     *
     * @param int $activityId The Kingdom Court activity ID
     * @return void
     */
    protected function associateAwardsWithActivity(int $activityId): void
    {
        $this->io->out('<info>Step 2: Associating all awards with Kingdom Court activity...</info>');

        $awardsTable = TableRegistry::getTableLocator()->get('Awards.Awards');
        $awardGatheringActivitiesTable = TableRegistry::getTableLocator()->get('Awards.AwardGatheringActivities');

        $awards = $awardsTable->find()->all();
        $count = $awards->count();

        $this->io->out("  - Found {$count} awards to associate");

        if ($this->dryRun) {
            $this->io->out("  - [DRY RUN] Would associate {$count} awards with activity ID {$activityId}");
            return;
        }

        $associated = 0;
        $skipped = 0;

        foreach ($awards as $award) {
            // Check if association already exists
            $existing = $awardGatheringActivitiesTable->find()
                ->where([
                    'award_id' => $award->id,
                    'gathering_activity_id' => $activityId,
                ])
                ->first();

            if ($existing) {
                $skipped++;
                continue;
            }

            $association = $awardGatheringActivitiesTable->newEntity([
                'award_id' => $award->id,
                'gathering_activity_id' => $activityId,
            ]);

            if ($awardGatheringActivitiesTable->save($association)) {
                $associated++;
            } else {
                $this->io->warning("  - Failed to associate award {$award->id} ({$award->name})");
            }
        }

        $this->io->success("  ✓ Associated {$associated} awards, skipped {$skipped} existing associations");
    }

    /**
     * Step 3: Create Kingdom Calendar Event gathering type
     *
     * @return \App\Model\Entity\GatheringType|null
     */
    protected function createKingdomCalendarEventType()
    {
        $this->io->out('<info>Step 3: Creating Kingdom Calendar Event gathering type...</info>');

        $gatheringTypesTable = TableRegistry::getTableLocator()->get('GatheringTypes');

        // Check if it already exists
        $existing = $gatheringTypesTable->find()
            ->where(['name' => 'Kingdom Calendar Event'])
            ->first();

        if ($existing) {
            $this->io->out('  - Kingdom Calendar Event type already exists (ID: ' . $existing->id . ')');
            return $existing;
        }

        if ($this->dryRun) {
            $this->io->out('  - [DRY RUN] Would create Kingdom Calendar Event gathering type');
            // Return a mock entity for dry run
            $mock = $gatheringTypesTable->newEmptyEntity();
            $mock->id = 999999; // Fake ID for dry run
            $mock->name = 'Kingdom Calendar Event';
            return $mock;
        }

        $type = $gatheringTypesTable->newEntity([
            'name' => 'Kingdom Calendar Event',
            'description' => 'Official Kingdom calendar events migrated from the legacy award events system',
        ]);

        if ($gatheringTypesTable->save($type)) {
            $this->io->success('  ✓ Created Kingdom Calendar Event gathering type (ID: ' . $type->id . ')');
            return $type;
        } else {
            $this->io->error('  ✗ Failed to create Kingdom Calendar Event gathering type');
            $this->io->out('    Errors: ' . json_encode($type->getErrors()));
            return null;
        }
    }

    /**
     * Steps 4-5: Create gatherings from award events and add Kingdom Court activity
     *
     * @param int $gatheringTypeId The gathering type ID
     * @param int $activityId The Kingdom Court activity ID
     * @return array Map of event_id => gathering_id
     */
    protected function createGatheringsFromAwardEvents(int $gatheringTypeId, int $activityId): array
    {
        $this->io->out('<info>Steps 4-5: Creating gatherings from award events...</info>');

        $eventsTable = TableRegistry::getTableLocator()->get('Awards.Events');
        $gatheringsTable = TableRegistry::getTableLocator()->get('Gatherings');
        $gatheringActivitiesGatheringsTable = TableRegistry::getTableLocator()->get('GatheringsGatheringActivities');

        $events = $eventsTable->find()->all();
        $count = $events->count();

        $this->io->out("  - Found {$count} award events to convert");

        $eventGatheringMap = [];
        $created = 0;
        $skipped = 0;

        foreach ($events as $event) {
            // Check if gathering already exists with this name and dates
            $existing = $gatheringsTable->find()
                ->where([
                    'name' => $event->name,
                    'start_date' => $event->start_date,
                    'end_date' => $event->end_date,
                    'gathering_type_id' => $gatheringTypeId,
                ])
                ->first();

            if ($existing) {
                $this->io->out("  - Gathering already exists for event '{$event->name}' (Gathering ID: {$existing->id})");
                $eventGatheringMap[$event->id] = $existing->id;
                $skipped++;
                continue;
            }

            if ($this->dryRun) {
                $this->io->out("  - [DRY RUN] Would create gathering for event '{$event->name}'");
                $eventGatheringMap[$event->id] = 999999; // Fake ID for dry run
                continue;
            }

            // Create the gathering
            $gathering = $gatheringsTable->newEntity([
                'name' => $event->name,
                'start_date' => $event->start_date,
                'end_date' => $event->end_date,
                'gathering_type_id' => $gatheringTypeId,
                'branch_id' => $event->branch_id,
                'description' => $event->description . ' (Migrated from legacy award event system)',
                'created_by' => 1
            ]);

            if ($gatheringsTable->save($gathering)) {
                $this->io->out("  ✓ Created gathering '{$gathering->name}' (ID: {$gathering->id})");
                $eventGatheringMap[$event->id] = $gathering->id;
                $created++;

                // Add Kingdom Court activity to this gathering
                $activityAssoc = $gatheringActivitiesGatheringsTable->newEntity([
                    'gathering_id' => $gathering->id,
                    'gathering_activity_id' => $activityId,
                ]);

                if ($gatheringActivitiesGatheringsTable->save($activityAssoc)) {
                    $this->io->out("    ✓ Added Kingdom Court activity to gathering");
                } else {
                    $this->io->warning("    - Failed to add Kingdom Court activity to gathering");
                }
            } else {
                $this->io->error("  ✗ Failed to create gathering for event '{$event->name}'");
                $this->io->out('    Errors: ' . json_encode($gathering->getErrors()));
            }
        }

        $this->io->success("  ✓ Created {$created} gatherings, skipped {$skipped} existing");

        return $eventGatheringMap;
    }

    /**
     * Steps 6-7: Update award recommendations to reference gatherings
     *
     * @param array $eventGatheringMap Map of event_id => gathering_id
     * @return void
     */
    protected function updateAwardRecommendations(array $eventGatheringMap): void
    {
        $this->io->out('<info>Steps 6-7: Updating award recommendations...</info>');

        $recommendationsTable = TableRegistry::getTableLocator()->get('Awards.Recommendations');
        $connection = $recommendationsTable->getConnection();

        // Step 6: Update award_recommendations.event_id to gathering_id
        $this->io->out('  - Updating Recommendations table...');

        $recommendations = $recommendationsTable->find()
            ->where(['event_id IS NOT' => null])
            ->all();

        $updatedRecommendations = 0;
        $skippedRecommendations = 0;

        foreach ($recommendations as $recommendation) {
            if (!isset($eventGatheringMap[$recommendation->event_id])) {
                $this->io->warning("    - No gathering found for event ID {$recommendation->event_id}, skipping recommendation {$recommendation->id}");
                $skippedRecommendations++;
                continue;
            }

            $gatheringId = $eventGatheringMap[$recommendation->event_id];

            if ($this->dryRun) {
                $this->io->out("    - [DRY RUN] Would update recommendation {$recommendation->id}: event_id {$recommendation->event_id} -> gathering_id {$gatheringId}");
                $updatedRecommendations++;
                continue;
            }

            // Set gathering_id and clear event_id
            $recommendation->gathering_id = $gatheringId;

            if ($recommendationsTable->save($recommendation)) {
                $updatedRecommendations++;
            } else {
                $this->io->warning("    - Failed to update recommendation {$recommendation->id}");
            }
        }

        $this->io->success("    ✓ Updated {$updatedRecommendations} recommendations, skipped {$skippedRecommendations}");

        // Step 7: Update awards_recommendations_events.event_id to gathering_id
        // This is a junction table without a model, so we use direct queries
        $this->io->out('  - Updating awards_recommendations_events junction table...');

        // Get all records with event_id
        $query = $connection->execute(
            'SELECT id, event_id FROM awards_recommendations_events WHERE event_id IS NOT NULL'
        );
        $recommendationsEvents = $query->fetchAll('assoc');

        $updatedRecEvents = 0;
        $skippedRecEvents = 0;

        foreach ($recommendationsEvents as $recEvent) {
            $eventId = $recEvent['event_id'];
            $recordId = $recEvent['id'];

            if (!isset($eventGatheringMap[$eventId])) {
                $this->io->warning("    - No gathering found for event ID {$eventId}, skipping record {$recordId}");
                $skippedRecEvents++;
                continue;
            }

            $gatheringId = $eventGatheringMap[$eventId];

            if ($this->dryRun) {
                $this->io->out("    - [DRY RUN] Would update awards_recommendations_events {$recordId}: event_id {$eventId} -> gathering_id {$gatheringId}");
                $updatedRecEvents++;
                continue;
            }

            // Update gathering_id and clear event_id
            $connection->execute(
                'UPDATE awards_recommendations_events SET gathering_id = ?, event_id = NULL WHERE id = ?',
                [$gatheringId, $recordId],
                ['integer', 'integer']
            );
            $updatedRecEvents++;
        }

        $this->io->success("    ✓ Updated {$updatedRecEvents} awards_recommendations_events records, skipped {$skippedRecEvents}");
    }
}