<?php

declare(strict_types=1);

use Migrations\BaseMigration;
use Cake\ORM\TableRegistry;

/**
 * Migration to execute Award Events to Gatherings data migration
 */
class RunMigrateAwardEvents extends BaseMigration
{
    /**
     * Orchestrates the migration that converts Award Events into Gatherings and updates related references.
     *
     * Ensures a "Kingdom Court" gathering activity and a "Kingdom Calendar Event" gathering type exist, associates all awards with the activity, creates Gatherings for each Award Event (linking them to the activity and gathering type), and updates Award Recommendations to reference the newly created Gatherings.
     */
    public function up(): void
    {
        echo "\nStarting Award Events to Gatherings migration...\n";

        // Step 1: Create Kingdom Court activity
        $kingdomCourtActivity = $this->createKingdomCourtActivity();

        // Step 2: Add all awards to Kingdom Court activity
        $this->associateAwardsWithActivity($kingdomCourtActivity->id);

        // Step 3: Create Kingdom Calendar Event gathering type
        $gatheringType = $this->createKingdomCalendarEventType();

        // Step 4-5: Create gatherings for each award event
        $eventGatheringMap = $this->createGatheringsFromAwardEvents($gatheringType->id, $kingdomCourtActivity->id);

        // Step 6-7: Update award recommendations
        $this->updateAwardRecommendations($eventGatheringMap);

        echo "Migration completed successfully!\n";
    }

    /**
     * Ensure a "Kingdom Court" gathering activity exists, creating it if missing.
     *
     * @return \Cake\Datasource\EntityInterface The GatheringActivity entity representing "Kingdom Court".
     */
    protected function createKingdomCourtActivity()
    {
        echo "Step 1: Creating Kingdom Court activity...\n";
        $table = TableRegistry::getTableLocator()->get('GatheringActivities');
        $existing = $table->find()->where(['name' => 'Kingdom Court'])->first();
        if ($existing) {
            echo "  - Already exists\n";
            return $existing;
        }
        $activity = $table->newEntity(['name' => 'Kingdom Court', 'description' => 'Official Kingdom Court sessions where awards and recognitions are presented']);
        $table->save($activity);
        echo "  ✓ Created\n";
        return $activity;
    }

    /**
     * Ensure every Award is associated with the given gathering activity.
     *
     * Creates missing AwardGatheringActivities junction records that link each Award to the specified gathering activity.
     *
     * @param int $activityId ID of the gathering activity to associate with each award.
     */
    protected function associateAwardsWithActivity(int $activityId): void
    {
        echo "Step 2: Associating awards...\n";
        $awardsTable = TableRegistry::getTableLocator()->get('Awards.Awards');
        $junctionTable = TableRegistry::getTableLocator()->get('Awards.AwardGatheringActivities');
        $awards = $awardsTable->find()->all();
        $count = 0;
        foreach ($awards as $award) {
            $existing = $junctionTable->find()->where(['award_id' => $award->id, 'gathering_activity_id' => $activityId])->first();
            if ($existing) continue;
            $assoc = $junctionTable->newEntity(['award_id' => $award->id, 'gathering_activity_id' => $activityId]);
            if ($junctionTable->save($assoc)) $count++;
        }
        echo "  ✓ Associated {$count} awards\n";
    }

    /**
     * Ensures a GatheringTypes record named "Kingdom Calendar Event" exists and returns it.
     *
     * If the record does not exist, creates one with name "Kingdom Calendar Event" and description
     * "Official Kingdom calendar events" and returns the newly created entity.
     *
     * @return \Cake\Datasource\EntityInterface The existing or newly created gathering type entity.
     */
    protected function createKingdomCalendarEventType()
    {
        echo "Step 3: Creating gathering type...\n";
        $table = TableRegistry::getTableLocator()->get('GatheringTypes');
        $existing = $table->find()->where(['name' => 'Kingdom Calendar Event'])->first();
        if ($existing) {
            echo "  - Already exists\n";
            return $existing;
        }
        $type = $table->newEntity(['name' => 'Kingdom Calendar Event', 'description' => 'Official Kingdom calendar events']);
        $table->save($type);
        echo "  ✓ Created\n";
        return $type;
    }

    /**
     * Create Gatherings from every award Event and associate them with a gathering activity.
     *
     * Creates a Gathering for each record in Awards.Events using the provided gathering type,
     * links each created Gathering to the specified activity, and returns a mapping from
     * award event IDs to the newly created gathering IDs.
     *
     * @param int $gatheringTypeId The gathering type ID to assign to created Gatherings.
     * @param int $activityId The gathering activity ID to associate each created Gathering with.
     * @return array<int,int> Map where keys are award event IDs and values are the corresponding created gathering IDs.
     */
    protected function createGatheringsFromAwardEvents(int $gatheringTypeId, int $activityId): array
    {
        echo "Steps 4-5: Creating gatherings...\n";
        $eventsTable = TableRegistry::getTableLocator()->get('Awards.Events');
        $gatheringsTable = TableRegistry::getTableLocator()->get('Gatherings');
        $junctionTable = TableRegistry::getTableLocator()->get('GatheringsGatheringActivities');

        $events = $eventsTable->find()->all();
        $map = [];
        $count = 0;

        foreach ($events as $event) {
            $gathering = $gatheringsTable->newEntity([
                'name' => $event->name,
                'start_date' => $event->start_date,
                'end_date' => $event->end_date,
                'gathering_type_id' => $gatheringTypeId,
                'branch_id' => $event->branch_id,
                'description' => $event->description,
                'created_by' => 1
            ]);
            if ($gatheringsTable->save($gathering)) {
                $map[$event->id] = $gathering->id;
                $assoc = $junctionTable->newEntity(['gathering_id' => $gathering->id, 'gathering_activity_id' => $activityId]);
                $junctionTable->save($assoc);
                $count++;
            }
        }
        echo "  ✓ Created {$count} gatherings\n";
        return $map;
    }

    /**
     * Update award recommendations to reference newly created gatherings for mapped events.
     *
     * For each recommendation that has an `event_id`, sets its `gathering_id` to the corresponding
     * gathering ID from the provided map when a mapping exists.
     *
     * @param int[] $eventGatheringMap Map of event IDs to gathering IDs (keys are event IDs, values are gathering IDs).
     */
    protected function updateAwardRecommendations(array $eventGatheringMap): void
    {
        echo "Steps 6-7: Updating recommendations...\n";
        $recsTable = TableRegistry::getTableLocator()->get('Awards.Recommendations');

        // Get fresh connection and disable behaviors that might interfere
        $connection = $recsTable->getConnection();

        $recommendations = $recsTable->find()->where(['event_id IS NOT' => null])->all();
        echo "  Found " . count($recommendations) . " recommendations with event_id\n";
        echo "  Event-Gathering Map: " . json_encode($eventGatheringMap) . "\n";

        $count = 0;
        foreach ($recommendations as $rec) {
            if (isset($eventGatheringMap[$rec->event_id])) {
                $gatheringId = $eventGatheringMap[$rec->event_id];
                echo "  Processing recommendation {$rec->id}: event {$rec->event_id} -> gathering {$gatheringId}\n";

                // Try direct SQL update to bypass ORM
                $stmt = $connection->execute(
                    'UPDATE awards_recommendations SET gathering_id = :gathering_id WHERE id = :id',
                    ['gathering_id' => $gatheringId, 'id' => $rec->id],
                    ['gathering_id' => 'integer', 'id' => 'integer']
                );

                if ($stmt->rowCount() > 0) {
                    $count++;
                    echo "  ✓ Updated via SQL\n";
                } else {
                    echo "  ! SQL update affected 0 rows\n";
                }
            }
        }
        echo "  ✓ Updated {$count} recommendations\n";
    }

    /**
     * Indicates that this migration does not support rollback.
     *
     * Prints a message stating that rollback is not supported.
     */
    public function down(): void
    {
        echo "Rollback not supported\n";
    }
}