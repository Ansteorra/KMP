<?php
declare(strict_types=1);

namespace App\Test\TestCase\Config;

use App\Test\TestCase\BaseTestCase;
use Migrations\Migration\Environment;
use ReplaceGatheringActivityCircleWithType;

class GatheringActivityTypeMigrationTest extends BaseTestCase
{
    public function testMigrationPreservesCircleFlagsAndDefaultsOtherActivitiesWithoutGuessingNames(): void
    {
        require_once ROOT . '/config/Migrations/20261104000000_ReplaceGatheringActivityCircleWithType.php';
        $table = $this->getTableLocator()->get('GatheringActivities');
        $circle = $table->saveOrFail($table->newEntity([
            'name' => 'Order of the Laurel migration fixture', 'activity_type' => 'Order Circle',
        ]));
        $other = $table->saveOrFail($table->newEntity([
            'name' => 'Drum Circle migration fixture', 'activity_type' => 'General',
        ]));
        $environment = new Environment('gathering-activity-type-test', ['connection' => 'test']);
        $migration = (new ReplaceGatheringActivityCircleWithType(20261104000000))
            ->setAdapter($environment->getAdapter());
        $connection = $table->getConnection();
        $migration->down();
        $flags = $connection->execute(
            'SELECT id, is_circle FROM gathering_activities WHERE id IN (?, ?) ORDER BY id',
            [$circle->id, $other->id],
        )->fetchAll('assoc');
        $this->assertTrue((bool)$flags[0]['is_circle']);
        $this->assertFalse((bool)$flags[1]['is_circle']);
        $migration->up();
        $types = $connection->execute(
            'SELECT activity_type FROM gathering_activities WHERE id IN (?, ?) ORDER BY id',
            [$circle->id, $other->id],
        )->fetchAll('assoc');
        $this->assertSame(['Order Circle', 'General'], array_column($types, 'activity_type'));
        $columns = $connection->getSchemaCollection()->describe('gathering_activities');
        $this->assertNotContains('is_circle', $columns->columns());
        $this->assertFalse($columns->getColumn('activity_type')['null']);
        $this->assertSame('General', $columns->getColumn('activity_type')['default']);
    }
}
