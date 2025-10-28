<?php

declare(strict_types=1);

use Migrations\BaseMigration;
use Cake\ORM\TableRegistry;

/**
 * Migration to execute Award Events to Gatherings data migration
 */
class RunMigrateAwardEvents extends BaseMigration
{
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

    public function down(): void
    {
        echo "Rollback not supported\n";
    }
}
