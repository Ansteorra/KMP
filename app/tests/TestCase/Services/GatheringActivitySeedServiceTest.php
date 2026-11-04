<?php
declare(strict_types=1);

namespace App\Test\TestCase\Services;

use App\Services\GatheringActivityPresentationService;
use App\Services\GatheringActivitySeedService;
use App\Services\IconCatalog;
use App\Services\PublicCalendarDemoService;
use App\Test\TestCase\BaseTestCase;
use Cake\I18n\DateTime;
use DevLoadGatheringActivityWaiversSeed;
use Migrations\Migration\Environment;

class GatheringActivitySeedServiceTest extends BaseTestCase
{
    public function testSeedColorsAreReadableAndExplicitRefreshPreservesOtherSettings(): void
    {
        $tables = $this->getTableLocator();
        $catalogue = $tables->get('GatheringActivities');
        $service = new GatheringActivitySeedService($tables);
        $definitions = GatheringActivitySeedService::definitions();
        $ids = $service->seed();
        foreach ($definitions as $name => $defaults) {
            $this->assertGreaterThanOrEqual(4.5, GatheringActivityPresentationService::contrastRatio(
                $defaults['foreground_color'],
                $defaults['background_color'],
            ), $name);
        }
        $laurel = $catalogue->get($ids['Laurel Circle']);
        $laurel->icon_name = 'archery';
        $laurel->display_order = 777;
        $laurel->foreground_color = '#155E75';
        $laurel->background_color = '#CFFAFE';
        $catalogue->saveOrFail($laurel);
        $upload = $catalogue->get($ids['Feast']);
        $upload->icon_source = 'upload';
        $upload->icon_asset_key = 'images/test/existing.png';
        $upload->foreground_color = '#155E75';
        $upload->background_color = '#CFFAFE';
        $catalogue->saveOrFail($upload);

        // Normal date refreshes and explicit icon refreshes retain editor color choices.
        $service->seed(refreshIcons: true);
        $this->assertSame('#155E75', $catalogue->get($laurel->id)->foreground_color);
        $laurel = $catalogue->get($laurel->id);
        $laurel->icon_name = 'archery';
        $catalogue->saveOrFail($laurel);
        $calendar = new PublicCalendarDemoService($tables);
        $calendar->refresh();
        $this->assertSame('#155E75', $catalogue->get($laurel->id)->foreground_color);
        $calendar->refresh(refreshActivityColors: true);
        $this->assertSame($ids, $service->seed());
        foreach ($definitions as $name => $defaults) {
            $activity = $catalogue->get($ids[$name]);
            $this->assertSame($name === 'Feast' ? '#155E75' : $defaults['foreground_color'], $activity->foreground_color);
            $this->assertSame($name === 'Feast' ? '#CFFAFE' : $defaults['background_color'], $activity->background_color);
        }
        $this->assertSame('archery', $catalogue->get($laurel->id)->icon_name);
        $this->assertSame(777, $catalogue->get($laurel->id)->display_order);
        $this->assertSame('upload', $catalogue->get($upload->id)->icon_source);
        $this->assertSame('images/test/existing.png', $catalogue->get($upload->id)->icon_asset_key);
    }

    public function testAdditionalOrderCirclesUseMatchingRegisteredIcons(): void
    {
        $expected = [
            'Defense Circle' => 'master-of-defense',
            'Order of the Mark Circle' => 'order-of-the-mark',
            'Golden Lance Circle' => 'lance',
            'Iris of Merit Circle' => 'rainbow',
            'Star of Merit Circle' => 'ansteorran-star',
            'Blades of Merit Circle' => 'single-rapier',
            'Centurion Circle' => 'phoenix',
            "Arc d'Or Circle" => 'archery',
        ];
        $service = new GatheringActivitySeedService($this->getTableLocator());
        $ids = $service->seed();
        foreach ($expected as $name => $icon) {
            $activity = $this->getTableLocator()->get('GatheringActivities')->get($ids[$name]);
            $this->assertSame('Order Circle', $activity->activity_type);
            $this->assertSame($icon, $activity->icon_name);
            $this->assertNotNull(IconCatalog::canonical($icon));
        }
        $this->assertSame($ids, $service->seed());
        $this->assertSame('Blades of Merit Circle', GatheringActivitySeedService::canonicalName('Blade of Merit Circle'));
        $this->assertSame("Arc d'Or Circle", GatheringActivitySeedService::canonicalName('Arc De Or Circle'));
    }

    public function testExplicitIconRefreshUsesOrderInsigniaAndPreservesOtherActivitySettings(): void
    {
        $tables = $this->getTableLocator();
        $catalogue = $tables->get('GatheringActivities');
        $service = new GatheringActivitySeedService($tables);
        $ids = $service->seed();
        $laurel = $catalogue->get($ids['Laurel Circle']);
        $laurel->icon_name = 'activity';
        $laurel->display_order = 777;
        $catalogue->saveOrFail($laurel);
        $upload = $catalogue->get($ids['Feast']);
        $upload->icon_source = 'upload';
        $upload->icon_asset_key = 'images/test/existing.png';
        $catalogue->saveOrFail($upload);
        $this->assertSame($ids, $service->seed(refreshIcons: true));
        $this->assertSame('laurel-wreath', $catalogue->get($ids['Laurel Circle'])->icon_name);
        $this->assertSame('pelican-vulning', $catalogue->get($ids['Pelican Circle'])->icon_name);
        $this->assertSame('chain-necklace', $catalogue->get($ids['Chivalric Circle'])->icon_name);
        $this->assertSame(777, $catalogue->get($ids['Laurel Circle'])->display_order);
        $this->assertSame('upload', $catalogue->get($upload->id)->icon_source);
        $this->assertSame('images/test/existing.png', $catalogue->get($upload->id)->icon_asset_key);
        foreach (GatheringActivitySeedService::definitions() as $name => $defaults) {
            $this->assertNotNull(IconCatalog::canonical($defaults['icon_name']), $name);
            if ($name !== 'Feast') {
                $this->assertSame($defaults['icon_name'], $catalogue->get($ids[$name])->icon_name);
            }
        }
        $laurel = $catalogue->get($laurel->id);
        $laurel->icon_name = 'archery';
        $catalogue->saveOrFail($laurel);
        $service->seed();
        $this->assertSame('archery', $catalogue->get($laurel->id)->icon_name);
    }

    public function testWaiverSeedSupportsNormalNamingVariantsAndCanBeRunRepeatedly(): void
    {
        require_once ROOT . '/plugins/Waivers/config/Seeds/DevLoadGatheringActivityWaiversSeed.php';
        $tables = $this->getTableLocator();
        $ids = (new GatheringActivitySeedService($tables))->seed();
        $types = $tables->get('Waivers.WaiverTypes');
        $waiver = $types->find()->where(['name' => 'General Liability Waiver'])->first() ?? $types->find()->firstOrFail();
        $waiver->name = 'General Liability Waiver';
        $types->saveOrFail($waiver);
        $links = $tables->get('Waivers.GatheringActivityWaivers');
        $conditions = ['gathering_activity_id' => $ids['Steel Combat'], 'waiver_type_id' => $waiver->id];
        if (!$links->exists($conditions)) {
            $links->saveOrFail($links->newEntity($conditions));
        }
        $environment = new Environment('normal-activity-waiver-seed-test', ['connection' => 'test']);
        $seed = (new DevLoadGatheringActivityWaiversSeed())->setAdapter($environment->getAdapter());
        $seed->run();
        $count = $links->find()->count();
        $seed->run();
        $this->assertSame($count, $links->find()->count());
        $this->assertSame(1, $links->find()->where($conditions)->count());
    }

    public function testNormalCatalogueRetainsConfigurationAndConsolidatesLegacyReferences(): void
    {
        $tables = $this->getTableLocator();
        $catalogue = $tables->get('GatheringActivities');
        $normal = $catalogue->find()->where(['name' => 'Armored Combat'])->firstOrFail();
        $normal->icon_name = 'archery';
        $normal->display_order = 777;
        $normal->description = 'Retain configured description';
        $catalogue->saveOrFail($normal);
        $old = $catalogue->saveOrFail($catalogue->newEntity(['name' => '[Calendar Demo] Armored Combat']));
        $iconTest = $catalogue->saveOrFail($catalogue->newEntity(['name' => '[Local icon test] Armored Combat']));
        $rename = $catalogue->saveOrFail($catalogue->newEntity(['name' => '[Calendar Demo] Other Gathering']));
        $eventType = $tables->get('GatheringTypes')->find()->firstOrFail();
        $event = $tables->get('Gatherings')->saveOrFail($tables->get('Gatherings')->newEntity([
            'name' => 'Catalogue consolidation fixture', 'branch_id' => self::KINGDOM_BRANCH_ID,
            'gathering_type_id' => $eventType->id, 'created_by' => self::ADMIN_MEMBER_ID,
            'start_date' => new DateTime('2027-01-08'), 'end_date' => new DateTime('2027-01-10'),
        ]));
        $links = $tables->get('GatheringsGatheringActivities');
        foreach ([$normal->id => 'Normal detail', $old->id => 'Old detail', $iconTest->id => 'Icon-test detail'] as $id => $detail) {
            $link = $links->find()->where(['gathering_id' => $event->id, 'gathering_activity_id' => $id])->first()
                ?? $links->newEntity(['gathering_id' => $event->id, 'gathering_activity_id' => $id, 'sort_order' => 0]);
            $link->custom_description = $detail;
            $link->not_removable = $id === $old->id;
            $links->saveOrFail($link);
        }
        $scheduled = $tables->get('GatheringScheduledActivities');
        $session = $scheduled->saveOrFail($scheduled->newEntity([
            'gathering_id' => $event->id, 'gathering_activity_id' => $old->id,
            'display_title' => 'Preserve scheduled session', 'start_datetime' => new DateTime('2027-01-09'),
            'has_end_time' => false,
        ]));
        $permission = $tables->get('Permissions')->find()->firstOrFail();
        $award = $tables->get('Awards.Awards')->find()->firstOrFail();
        $waiver = $tables->get('Waivers.WaiverTypes')->find()->firstOrFail();
        $relations = [
            ['GatheringActivityAssignmentPermissions', 'permission_id', $permission->id],
            ['Awards.AwardGatheringActivities', 'award_id', $award->id],
            ['Waivers.GatheringActivityWaivers', 'waiver_type_id', $waiver->id],
            ['GatheringTypeGatheringActivities', 'gathering_type_id', $eventType->id],
        ];
        foreach ($relations as [$alias, $key, $id]) {
            $table = $tables->get($alias);
            foreach ([$normal->id, $old->id] as $activityId) {
                $conditions = [$key => $id, 'gathering_activity_id' => $activityId];
                $entity = $table->find()->where($conditions)->first() ?? $table->newEntity(
                    $conditions + ($alias === 'GatheringTypeGatheringActivities' ? ['not_removable' => false, 'sort_order' => 0] : []),
                );
                $table->saveOrFail($entity);
            }
        }
        $otherPermission = $tables->get('Permissions')->find()->where(['id !=' => $permission->id])->firstOrFail();
        $assignments = $tables->get('GatheringActivityAssignmentPermissions');
        $uniqueLink = $assignments->saveOrFail($assignments->newEntity([
            'gathering_activity_id' => $old->id, 'permission_id' => $otherPermission->id,
        ]));
        $service = new GatheringActivitySeedService($tables);
        $ids = $service->seed();
        $this->assertSame($normal->id, $ids['Armored Combat']);
        $this->assertSame($rename->id, $ids['Other Gathering']);
        $this->assertSame('Other Gathering', $catalogue->get($rename->id)->name);
        $this->assertSame('archery', $catalogue->get($normal->id)->icon_name);
        $this->assertSame(777, $catalogue->get($normal->id)->display_order);
        $this->assertSame('Retain configured description', $catalogue->get($normal->id)->description);
        $this->assertFalse($catalogue->exists(['id IN' => [$old->id, $iconTest->id]]));
        $this->assertSame($normal->id, $scheduled->get($session->id)->gathering_activity_id);
        $this->assertSame($normal->id, $assignments->get($uniqueLink->id)->gathering_activity_id);
        $this->assertSame('Steel Combat', GatheringActivitySeedService::canonicalName('Rapier Combat'));
        $this->assertSame('Target Archery', GatheringActivitySeedService::canonicalName('Archery'));
        $link = $links->find()->where(['gathering_id' => $event->id, 'gathering_activity_id' => $normal->id])->firstOrFail();
        $this->assertTrue($link->not_removable);
        $this->assertStringContainsString('Normal detail', $link->custom_description);
        $this->assertStringContainsString('Old detail', $link->custom_description);
        $this->assertStringContainsString('Icon-test detail', $link->custom_description);
        foreach ($relations as [$alias, $key, $id]) {
            $this->assertSame(1, $tables->get($alias)->find()->where([$key => $id, 'gathering_activity_id' => $normal->id])->count());
        }
        $beforeCount = $catalogue->find()->count();
        $this->assertSame($ids, $service->seed());
        $this->assertSame($beforeCount, $catalogue->find()->count());
    }
}
