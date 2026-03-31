<?php
declare(strict_types=1);

namespace App\Test\TestCase\Model\Table;

use App\Model\Table\GatheringsTable;
use App\Test\TestCase\BaseTestCase;

/**
 * Tests Gathering template activity sync behavior.
 */
class GatheringsTableTest extends BaseTestCase
{
    /**
     * @var \App\Model\Table\GatheringsTable
     */
    protected $Gatherings;

    /**
     * Initialize Gatherings table fixture state.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $config = $this->getTableLocator()->exists('Gatherings') ? [] : ['className' => GatheringsTable::class];
        $this->Gatherings = $this->getTableLocator()->get('Gatherings', $config);
    }

    /**
     * Clean up Gatherings table fixture state.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        unset($this->Gatherings);
        parent::tearDown();
    }

    /**
     * Sync template activities and not_removable flags on type change.
     *
     * @return void
     */
    public function testTypeChangeSyncsTemplateActivitiesAndNotRemovableFlags(): void
    {
        $gatheringTypes = $this->getTableLocator()->get('GatheringTypes');
        $gatheringActivities = $this->getTableLocator()->get('GatheringActivities');
        $typeActivities = $this->getTableLocator()->get('GatheringTypeGatheringActivities');
        $gatheringLinks = $this->getTableLocator()->get('GatheringsGatheringActivities');
        $branches = $this->getTableLocator()->get('Branches');

        $unique = uniqid('sync', true);
        $typeA = $gatheringTypes->saveOrFail($gatheringTypes->newEntity([
            'name' => "Type A {$unique}",
            'description' => 'Type A template',
            'clonable' => true,
            'color' => '#112233',
        ]));
        $typeB = $gatheringTypes->saveOrFail($gatheringTypes->newEntity([
            'name' => "Type B {$unique}",
            'description' => 'Type B template',
            'clonable' => true,
            'color' => '#223344',
        ]));

        $activityA = $gatheringActivities->saveOrFail($gatheringActivities->newEntity(['name' => "A {$unique}"]));
        $activityB = $gatheringActivities->saveOrFail($gatheringActivities->newEntity(['name' => "B {$unique}"]));
        $activityC = $gatheringActivities->saveOrFail($gatheringActivities->newEntity(['name' => "C {$unique}"]));
        $branch = $branches->saveOrFail($branches->newEntity([
            'name' => "Branch {$unique}",
            'location' => "Location {$unique}",
        ]));

        $typeActivities->saveOrFail($typeActivities->newEntity([
            'gathering_type_id' => $typeA->id,
            'gathering_activity_id' => $activityA->id,
            'not_removable' => true,
        ]));
        $typeActivities->saveOrFail($typeActivities->newEntity([
            'gathering_type_id' => $typeB->id,
            'gathering_activity_id' => $activityA->id,
            'not_removable' => false,
        ]));
        $typeActivities->saveOrFail($typeActivities->newEntity([
            'gathering_type_id' => $typeB->id,
            'gathering_activity_id' => $activityB->id,
            'not_removable' => true,
        ]));

        $gathering = $this->Gatherings->saveOrFail($this->Gatherings->newEntity([
            'branch_id' => (int)$branch->id,
            'gathering_type_id' => $typeA->id,
            'name' => "Gathering {$unique}",
            'start_date' => '2030-01-10 10:00:00',
            'end_date' => '2030-01-12 10:00:00',
            'created_by' => self::ADMIN_MEMBER_ID,
        ]));

        $linkA = $gatheringLinks->find()
            ->where(['gathering_id' => $gathering->id, 'gathering_activity_id' => $activityA->id])
            ->firstOrFail();
        $this->assertTrue((bool)$linkA->not_removable);

        $gatheringLinks->saveOrFail($gatheringLinks->newEntity([
            'gathering_id' => $gathering->id,
            'gathering_activity_id' => $activityC->id,
            'sort_order' => 999,
            'not_removable' => true,
        ]));

        $gathering->gathering_type_id = $typeB->id;
        $this->Gatherings->saveOrFail($gathering);

        $updatedLinkA = $gatheringLinks->find()
            ->where(['gathering_id' => $gathering->id, 'gathering_activity_id' => $activityA->id])
            ->firstOrFail();
        $this->assertFalse((bool)$updatedLinkA->not_removable);

        $addedLinkB = $gatheringLinks->find()
            ->where(['gathering_id' => $gathering->id, 'gathering_activity_id' => $activityB->id])
            ->firstOrFail();
        $this->assertTrue((bool)$addedLinkB->not_removable);

        $updatedLinkC = $gatheringLinks->find()
            ->where(['gathering_id' => $gathering->id, 'gathering_activity_id' => $activityC->id])
            ->firstOrFail();
        $this->assertFalse((bool)$updatedLinkC->not_removable);
    }
}
