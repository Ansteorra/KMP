<?php
declare(strict_types=1);

namespace App\Test\TestCase\Services;

use App\Model\Entity\GatheringActivity;
use App\Services\GatheringActivitySeedService;
use App\Services\PublicCalendarDemoService;
use App\Test\TestCase\BaseTestCase;
use Cake\I18n\DateTime;

class PublicCalendarDemoServiceTest extends BaseTestCase
{
    public function testExamplesUseLocalGroupsAndPreserveLaterHostChanges(): void
    {
        $tables = $this->getTableLocator();
        $branches = $tables->get('Branches');
        $hosts = [];
        foreach (['Calendar Host Alpha', 'Calendar Host Beta'] as $name) {
            $hosts[] = $branches->saveOrFail($branches->newEntity([
                'name' => $name, 'type' => 'Local Group', 'parent_id' => self::KINGDOM_BRANCH_ID,
                'location' => 'Example Town',
            ]));
        }
        $service = new PublicCalendarDemoService($tables);
        $service->refresh();
        $state = $tables->get('AppSettings')->find()->where(['name' => PublicCalendarDemoService::EVENT_IDS_SETTING])->firstOrFail();
        $ids = array_values(json_decode($state->value, true, flags: JSON_THROW_ON_ERROR));
        $events = $tables->get('Gatherings')->find()->where(['Gatherings.public_id IN' => $ids])->contain(['Branches'])->all()->toList();
        $this->assertGreaterThan(1, count(array_unique(array_column($events, 'branch_id'))));
        foreach ($events as $event) {
            $this->assertSame('Local Group', $event->branch->type);
        }
        $events[0]->branch_id = $hosts[1]->id;
        $tables->get('Gatherings')->saveOrFail($events[0]);
        $service->refresh();
        $this->assertSame($hosts[1]->id, $tables->get('Gatherings')->get($events[0]->id)->branch_id);
    }

    public function testLegacyNamesAreRenamedWithoutChangingPublicIdsAndLaterTitleEditsArePreserved(): void
    {
        $tables = $this->getTableLocator();
        $events = $tables->get('Gatherings');
        $type = $tables->get('GatheringTypes')->find()->firstOrFail();
        $data = [
            'branch_id' => self::KINGDOM_BRANCH_ID, 'gathering_type_id' => $type->id,
            'created_by' => self::ADMIN_MEMBER_ID,
            'start_date' => new DateTime('2026-01-02'), 'end_date' => new DateTime('2026-01-04'),
        ];
        $legacy = $events->saveOrFail($events->newEntity(['name' => '[Calendar Demo] Crown & Order Circles'] + $data));
        $publicId = $legacy->public_id;
        $unrelated = $events->saveOrFail($events->newEntity(['name' => 'Crown & Order Circles'] + $data));
        $service = new PublicCalendarDemoService($tables);
        $service->refresh(new DateTime('2026-11-04', 'America/Chicago'));
        $updated = $events->get($legacy->id);
        $this->assertSame('Crown & Order Circles', $updated->name);
        $this->assertSame($publicId, $updated->public_id);
        $this->assertFalse($events->get($unrelated->id)->published);
        $updated->name = 'Crown Tournament & Circles';
        $events->saveOrFail($updated);
        $service->refresh(new DateTime('2029-06-03', 'America/Chicago'));
        $this->assertSame('Crown Tournament & Circles', $events->get($legacy->id)->name);
        $this->assertSame($publicId, $events->get($legacy->id)->public_id);
        $state = $tables->get('AppSettings')->find()->where(['name' => PublicCalendarDemoService::EVENT_IDS_SETTING])->firstOrFail();
        $ids = json_decode($state->value, true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame($publicId, $ids['crown-circles']);
        $this->assertCount(6, $ids);
        $this->assertEquals($unrelated->start_date, $events->get($unrelated->id)->start_date);
    }

    public function testRefreshPublishesAllTypesAndMovesOnlyDemoDatesWithoutDuplicatingRecords(): void
    {
        $tables = $this->getTableLocator();
        $events = $tables->get('Gatherings');
        $eventType = $tables->get('GatheringTypes')->find()->firstOrFail();
        $unrelated = $events->saveOrFail($events->newEntity([
            'name' => 'Unrelated calendar fixture', 'branch_id' => self::KINGDOM_BRANCH_ID,
            'gathering_type_id' => $eventType->id, 'created_by' => self::ADMIN_MEMBER_ID,
            'start_date' => new DateTime('2026-01-02 18:00:00'),
            'end_date' => new DateTime('2026-01-04 17:00:00'), 'timezone' => 'America/Chicago',
        ]));
        $service = new PublicCalendarDemoService($tables);
        $counts = $service->refresh(new DateTime('2026-11-04', 'America/Chicago'));
        $this->assertSame(['events' => 6, 'activities' => 27], $counts);
        $state = $tables->get('AppSettings')->find()->where(['name' => PublicCalendarDemoService::EVENT_IDS_SETTING])->firstOrFail();
        $publicIds = array_values(json_decode($state->value, true, flags: JSON_THROW_ON_ERROR));
        $query = fn() => $events->find()->where(['public_id IN' => $publicIds])->orderBy(['id' => 'ASC']);
        $before = $query()->all()->toList();
        $this->assertCount(6, $before);
        foreach ($before as $event) {
            $this->assertStringNotContainsString('[Calendar Demo]', $event->name);
            $this->assertStringNotContainsString('synthetic', $event->description);
            $this->assertTrue($event->published);
            $this->assertTrue($event->public_page_enabled);
            $this->assertGreaterThan(new DateTime('2026-11-04'), $event->start_date);
        }
        $activities = $tables->get('GatheringActivities')->find()
            ->where(['id IN' => array_values((new GatheringActivitySeedService($tables))->seed())])->all()->toList();
        $catalogueCount = $tables->get('GatheringActivities')->find()->count();
        $this->assertEqualsCanonicalizing(GatheringActivity::ACTIVITY_TYPES, array_unique(array_column($activities, 'activity_type')));
        $this->assertGreaterThan(8 * 86400, $before[2]->end_date->getTimestamp() - $before[2]->start_date->getTimestamp());
        $this->assertGreaterThan(4, $tables->get('GatheringsGatheringActivities')->find()
            ->where(['gathering_id' => $before[0]->id])->count());
        $service->refresh(new DateTime('2029-06-03', 'America/Chicago'));
        $after = $query()->all()->toList();
        $this->assertSame(array_column($before, 'id'), array_column($after, 'id'));
        $this->assertSame($catalogueCount, $tables->get('GatheringActivities')->find()->count());
        $this->assertSame(0, $tables->get('GatheringActivities')->find()
            ->where(['name LIKE' => PublicCalendarDemoService::LEGACY_PREFIX . '%'])->count());
        $this->assertCount(0, $tables->get('GatheringAttendances')->find()
            ->where(['gathering_id IN' => array_column($after, 'id')])->all());
        foreach ($after as $event) {
            $this->assertGreaterThan(new DateTime('2029-06-03'), $event->start_date);
        }
        $this->assertEquals($unrelated->start_date, $events->get($unrelated->id)->start_date);
    }
}
