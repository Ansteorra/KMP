<?php
declare(strict_types=1);

namespace Officers\Test\TestCase\Controller;

use App\Model\Entity\Gathering;
use App\Model\Entity\Member;
use App\Policy\GatheringPolicy;
use App\Test\TestCase\Support\HttpIntegrationTestCase;
use Cake\Cache\Cache;
use Cake\Console\TestSuite\ConsoleIntegrationTestTrait;
use Cake\Core\Configure;
use Cake\I18n\DateTime;
use InitCalendarDeputySeed;
use PHPUnit\Framework\Attributes\DataProvider;

require_once dirname(__DIR__, 3) . '/config/Seeds/InitCalendarDeputySeed.php';

class CalendarDeputyGatheringsTest extends HttpIntegrationTestCase
{
    use ConsoleIntegrationTestTrait;

    private bool $cacheWasEnabled;

    private mixed $warrantSetting;

    private int $typeId;

    private int $deputyId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cacheWasEnabled = Cache::enabled();
        Cache::disable();
        $this->warrantSetting = Configure::read('KMP.RequireActiveWarrantForSecurity');
        Configure::write('KMP.RequireActiveWarrantForSecurity', 'yes');
        $this->exec('advance_dev_seed_dates --as-of ' . DateTime::now()->format('Y-m-d'));
        $this->assertExitSuccess();
        (new InitCalendarDeputySeed())->run();
        $tables = $this->getTableLocator();
        $type = $tables->get('GatheringTypes')->saveOrFail($tables->get('GatheringTypes')->newEntity([
            'name' => 'Calendar Deputy empty event type', 'description' => 'No template activities', 'clonable' => false,
        ]));
        $this->typeId = (int)$type->id;
        $role = $tables->get('Roles')->find()->where(['name' => InitCalendarDeputySeed::ROLE])->firstOrFail();
        $this->deputyId = self::TEST_MEMBER_AGATHA_ID;
        $this->assignRole($this->deputyId, (int)$role->id, self::KINGDOM_BRANCH_ID);
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->authenticateAsMember($this->deputyId);
    }

    protected function tearDown(): void
    {
        try {
            if ($this->warrantSetting === null) {
                Configure::delete('KMP.RequireActiveWarrantForSecurity');
            } else {
                Configure::write('KMP.RequireActiveWarrantForSecurity', $this->warrantSetting);
            }
            if ($this->cacheWasEnabled) {
                Cache::enable();
            }
        } finally {
            parent::tearDown();
        }
    }

    public static function hostBranches(): array
    {
        return [
            [self::KINGDOM_BRANCH_ID], [self::TEST_BRANCH_STARGATE_ID],
            [self::TEST_BRANCH_LOCAL_ID], [self::TEST_BRANCH_CENTRAL_REGION_ID],
        ];
    }

    #[DataProvider('hostBranches')]
    public function testDeputyCanCreateAndEditEventsForAnyGroup(int $branchId): void
    {
        $actor = $this->getTableLocator()->get('Members')->get($this->deputyId);
        $this->assertTrue((new GatheringPolicy())->canAdd($actor, $this->getTableLocator()->get('Gatherings')));
        $this->assertNull($actor->getBranchIdsForAction('add', 'Gatherings'));
        $this->get('/gatherings/add');
        $this->assertResponseOk((string)$this->_response->getHeaderLine('Location'));
        $this->assertResponseContains('name="branch_id"');
        $name = 'Calendar Deputy event for branch ' . $branchId;
        $this->post('/gatherings/add', $this->eventData($name, $branchId));
        $this->assertRedirect();
        $gatherings = $this->getTableLocator()->get('Gatherings');
        $event = $gatherings->find()->where(['name' => $name])->firstOrFail();
        $this->assertSame($branchId, $event->branch_id);
        $this->assertSame($this->deputyId, $event->created_by);
        $this->assertFalse($event->published);
        $this->post('/gatherings/edit/' . $event->id, $this->eventData($name . ' edited', $branchId));
        $this->assertRedirect();
        $this->assertSame($name . ' edited', $gatherings->get($event->id)->name);
        $this->assertFalse((new GatheringPolicy())->canDelete($actor, $event));
    }

    public function testDeputyCanPublishAndUnpublishAnEventAtAnotherGroup(): void
    {
        $event = $this->event();
        $this->post('/gatherings/publish/' . $event->id . '?publish=true');
        $this->assertRedirect();
        $gatherings = $this->getTableLocator()->get('Gatherings');
        $published = $gatherings->get($event->id);
        $this->assertTrue($published->published);
        $this->assertSame($this->deputyId, $published->published_by);
        $this->assertNotNull($published->published_on);
        $this->post('/gatherings/publish/' . $event->id . '?publish=false');
        $this->assertRedirect();
        $unpublished = $gatherings->get($event->id);
        $this->assertFalse($unpublished->published);
        $this->assertNull($unpublished->published_by);
        $this->assertNull($unpublished->published_on);
    }

    public function testHostingSeneschalCanEditDeputyCreatedEventWithinTheirGroup(): void
    {
        $event = $this->event();
        $tables = $this->getTableLocator();
        $office = $tables->get('Officers.Offices')->find()->where(['name' => 'Local Seneschal'])->firstOrFail();
        $this->assignRole(self::TEST_MEMBER_BRYCE_ID, (int)$office->grants_role_id, self::TEST_BRANCH_STARGATE_ID);
        $this->authenticateAsMember(self::TEST_MEMBER_BRYCE_ID);
        $this->post('/gatherings/edit/' . $event->id, $this->eventData('Updated by hosting seneschal', $event->branch_id));
        $this->assertRedirect();
        $saved = $tables->get('Gatherings')->get($event->id);
        $this->assertSame('Updated by hosting seneschal', $saved->name);
        $this->assertSame($this->deputyId, $saved->created_by);
        $this->post('/gatherings/publish/' . $event->id . '?publish=true');
        $this->assertFalse($tables->get('Gatherings')->get($event->id)->published);

        $other = $this->event(self::TEST_BRANCH_LOCAL_ID);
        $actor = $tables->get('Members')->get(self::TEST_MEMBER_BRYCE_ID);
        $this->assertFalse((new GatheringPolicy())->canEdit($actor, $other));
        $this->post('/gatherings/edit/' . $other->id, $this->eventData('Unauthorized edit', $other->branch_id));
        $this->assertSame($other->name, $tables->get('Gatherings')->get($other->id)->name);
    }

    public function testRevokingDeputyRoleRemovesCreateEditAndPublishAuthority(): void
    {
        $event = $this->event();
        $tables = $this->getTableLocator();
        $tables->get('MemberRoles')->updateAll([
            'revoker_id' => self::ADMIN_MEMBER_ID, 'expires_on' => DateTime::now()->subDays(1),
        ], ['member_id' => $this->deputyId]);
        $actor = $tables->get('Members')->get($this->deputyId);
        $policy = new GatheringPolicy();
        $this->assertFalse($policy->canAdd($actor, $tables->get('Gatherings')));
        $this->assertFalse($policy->canEdit($actor, $event));
        $this->assertFalse($policy->canPublish($actor, $event));
        $this->authenticateAsMember($this->deputyId);
        $this->post('/gatherings/publish/' . $event->id . '?publish=true');
        $this->assertFalse($tables->get('Gatherings')->get($event->id)->published);
    }

    public static function eligibilityRequirements(): array
    {
        return [['membership'], ['warrant']];
    }

    #[DataProvider('eligibilityRequirements')]
    public function testManagementRequiresCurrentMembershipAndWarrant(string $requirement): void
    {
        $tables = $this->getTableLocator();
        $event = $this->event();
        if ($requirement === 'membership') {
            $tables->get('Members')->updateAll([
                'membership_expires_on' => DateTime::now()->subDays(1),
            ], ['id' => $this->deputyId]);
        } else {
            $tables->get('Warrants')->updateAll([
                'expires_on' => DateTime::now()->subDays(1),
            ], ['member_id' => $this->deputyId]);
        }
        $actor = $tables->get('Members')->get($this->deputyId);
        $this->assertFalse((new GatheringPolicy())->canAdd($actor, $tables->get('Gatherings')));
        $this->assertFalse((new GatheringPolicy())->canEdit($actor, $event));
        $this->authenticateAsMember($this->deputyId);
        $this->post('/gatherings/edit/' . $event->id, $this->eventData('Unauthorized edit', $event->branch_id));
        $this->assertSame($event->name, $tables->get('Gatherings')->get($event->id)->name);
    }

    private function assignRole(int $memberId, int $roleId, int $branchId): void
    {
        $tables = $this->getTableLocator();
        $member = $tables->get('Members')->get($memberId);
        $member->status = Member::STATUS_VERIFIED_MEMBERSHIP;
        $member->membership_expires_on = DateTime::now()->addYears(2);
        $member->birth_year = 1980;
        $member->birth_month = 1;
        $member->first_name = 'Calendar';
        $member->last_name = 'Regression';
        $member->street_address = '123 Test Street';
        $member->city = 'Test City';
        $member->state = 'TX';
        $member->zip = '75001';
        $member->phone_number = '555-0100';
        $tables->get('Members')->saveOrFail($member);
        $this->assertTrue($member->warrantable);
        $roles = $tables->get('MemberRoles');
        $roles->updateAll([
            'revoker_id' => self::ADMIN_MEMBER_ID, 'expires_on' => DateTime::now()->subDays(1),
        ], ['member_id' => $memberId]);
        $assignment = $roles->newEmptyEntity();
        $assignment->patch([
            'member_id' => $memberId, 'role_id' => $roleId, 'branch_id' => $branchId,
            'start_on' => DateTime::now()->subDays(1), 'expires_on' => null,
            'created_by' => self::ADMIN_MEMBER_ID, 'approver_id' => self::ADMIN_MEMBER_ID,
        ], ['guard' => false]);
        $roles->saveOrFail($assignment);
        $warrants = $tables->get('Warrants');
        $warrant = $warrants->newEmptyEntity();
        $warrant->patch([
            'name' => 'Calendar Deputy regression warrant', 'member_id' => $memberId,
            'member_role_id' => $assignment->id,
            'warrant_roster_id' => $tables->get('WarrantRosters')->find()->firstOrFail()->id,
            'entity_type' => 'Direct Grant', 'entity_id' => -1,
            'start_on' => DateTime::now()->subDays(1), 'expires_on' => DateTime::now()->addYears(1),
            'approved_date' => DateTime::now(), 'status' => 'Current', 'created_by' => self::ADMIN_MEMBER_ID,
        ], ['guard' => false]);
        $warrants->saveOrFail($warrant);
    }

    private function eventData(string $name, int $branchId): array
    {
        return [
            'name' => $name, 'branch_id' => $branchId, 'gathering_type_id' => $this->typeId,
            'start_date' => DateTime::now()->addDays(7)->format('Y-m-d') . 'T10:00',
            'end_date' => DateTime::now()->addDays(7)->format('Y-m-d') . 'T18:00',
            'location' => 'Calendar regression fixture', 'timezone' => 'UTC', 'public_page_enabled' => true,
        ];
    }

    private function event(int $branchId = self::TEST_BRANCH_STARGATE_ID): Gathering
    {
        $table = $this->getTableLocator()->get('Gatherings');

        return $table->saveOrFail($table->newEntity($this->eventData(
            'Deputy-created event at branch ' . $branchId,
            $branchId,
        ) + ['created_by' => $this->deputyId]));
    }
}
