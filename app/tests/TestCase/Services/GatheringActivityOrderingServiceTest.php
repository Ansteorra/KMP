<?php
declare(strict_types=1);

namespace App\Test\TestCase\Services;

use App\KMP\GridColumns\GatheringsGridColumns;
use App\Model\Entity\Gathering;
use App\Model\Entity\GatheringType;
use App\Services\GatheringActivityOrderingService;
use App\Test\TestCase\BaseTestCase;
use Awards\Model\Entity\Award;

class GatheringActivityOrderingServiceTest extends BaseTestCase
{
    /** @return array<array<string, mixed>> Deliberately conflicting group, display and assignment orders */
    private function activities(): array
    {
        return [
            ['name' => 'Ordering Z Royal', 'activity_type' => 'Royal Progress', 'display_order' => 999],
            ['name' => 'Ordering Z Circle', 'activity_type' => 'Order Circle', 'display_order' => 0],
            ['name' => 'Ordering A Court', 'activity_type' => 'Court', 'display_order' => 0],
            ['name' => 'Ordering A Circle', 'activity_type' => 'Order Circle', 'display_order' => 999],
            ['name' => 'Ordering B Arts', 'activity_type' => 'Arts & Sciences', 'display_order' => 0],
            ['name' => 'Ordering a Royal', 'activity_type' => 'Royal Progress', 'display_order' => 999],
            ['name' => 'Ordering First General', 'activity_type' => 'General', 'display_order' => 1],
        ];
    }

    public function testEntitiesSerializationAndQueriesUseTheSameGroupedOrder(): void
    {
        $table = $this->getTableLocator()->get('GatheringActivities');
        $activities = [];
        foreach ($this->activities() as $index => $data) {
            $activity = $table->saveOrFail($table->newEntity($data));
            $activity->set('_joinData', ['sort_order' => $index]);
            $activities[] = $activity;
        }
        $expected = ['Ordering a Royal', 'Ordering Z Royal', 'Ordering Z Circle', 'Ordering A Circle',
            'Ordering A Court', 'Ordering B Arts', 'Ordering First General'];
        $this->assertSame($expected, array_column(GatheringActivityOrderingService::sort($activities), 'name'));
        $this->assertSame($expected, array_column(GatheringActivityOrderingService::sort($this->activities()), 'name'));
        $query = $table->find('forDisplay')->where(['name LIKE' => 'Ordering %']);
        $this->assertSame($expected, $query->all()->extract('name')->toList());
        $list = $table->find('list')->find('forDisplay')->where(['name LIKE' => 'Ordering %'])->toArray();
        $this->assertSame($expected, array_values($list));
        $options = array_filter(GatheringsGridColumns::getActivityFilterOptions(), static fn($option) => str_starts_with($option['label'], 'Ordering '));
        $this->assertSame($expected, array_column($options, 'label'));
        foreach ([Gathering::class, GatheringType::class, Award::class] as $class) {
            $entity = new $class();
            $this->assertNull($entity->gathering_activities);
            $this->assertFalse(isset($entity->gathering_activities));
            $entity->set('gathering_activities', $activities);
            $entity->clean();
            $this->assertSame($expected, array_column($entity->gathering_activities, 'name'));
            $this->assertSame($expected, array_column($entity->toArray()['gathering_activities'], 'name'));
            $this->assertFalse($entity->isDirty('gathering_activities'));
        }
        $this->assertSame(range(0, count($activities) - 1), array_map(static fn($a) => $a->_joinData['sort_order'], $activities));
    }
}
