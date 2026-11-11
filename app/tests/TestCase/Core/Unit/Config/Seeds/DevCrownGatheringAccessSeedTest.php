<?php
declare(strict_types=1);

namespace App\Test\TestCase\Core\Unit\Config\Seeds;

use App\Model\Entity\Gathering;
use App\Model\Entity\GatheringScheduledActivity;
use App\Model\Entity\Member;
use App\Model\Entity\Permission;
use App\Policy\GatheringPolicy;
use App\Services\GatheringActivityService;
use App\Services\GatheringScheduleService;
use App\Test\TestCase\BaseTestCase;
use Cake\Cache\Cache;
use Cake\Console\TestSuite\ConsoleIntegrationTestTrait;
use Cake\Core\Configure;
use Cake\Http\Exception\ForbiddenException;
use Cake\I18n\DateTime;
use DevLoadBestowalTodoUsersSeed;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;

class DevCrownGatheringAccessSeedTest extends BaseTestCase
{
    use ConsoleIntegrationTestTrait;

    private bool $cacheWasEnabled;

    private mixed $warrantSetting;

    protected function setUp(): void
    {
        parent::setUp();
        require_once ROOT . '/config/Seeds/DevLoadBestowalTodoUsersSeed.php';
        $this->cacheWasEnabled = Cache::enabled();
        Cache::disable();
        $this->warrantSetting = Configure::read('KMP.RequireActiveWarrantForSecurity');
        Configure::write('KMP.RequireActiveWarrantForSecurity', 'yes');
        // The reset advances the snapshot before rebuilding personas; Forest's original term has expired.
        $this->exec('advance_dev_seed_dates --as-of ' . DateTime::now()->format('Y-m-d'));
        $this->assertExitSuccess();
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

    public function testResetSeedRecreatesPresenceAndMissingRoleGrants(): void
    {
        $tables = $this->getTableLocator();
        foreach (['GatheringActivities', 'Permissions'] as $alias) {
            $table = $tables->get($alias);
            $name = $alias === 'Permissions' ? 'Can Manage Crown Presence' : 'Crown Presence';
            $existing = $table->find()->where(['name' => $name])->first();
            if ($existing !== null) {
                $existing->name = 'Previous reset fixture ' . $name;
                $table->saveOrFail($existing);
            }
        }
        $court = $tables->get('Permissions')->find()->where(['name' => 'Can Manage Court Schedule'])->first();
        $roles = $tables->get('Roles')->find()->where(['name IN' => [
            'Ansteorran Crown', 'Crown Award Schedule Bestowal Todo', 'Golden Staff',
        ]])->all()->extract('id')->toList();
        if ($court !== null) {
            $tables->get('RolesPermissions')->deleteAll(['role_id IN' => $roles, 'permission_id' => $court->id]);
        }
        $policyCount = $tables->get('PermissionPolicies')->find()->count();

        (new DevLoadBestowalTodoUsersSeed())->run();

        $presence = $tables->get('GatheringActivities')->find()->where(['name' => 'Crown Presence'])->firstOrFail();
        $royalVisit = $tables->get('GatheringActivities')->find()->where(['name' => 'Royal Visit'])->firstOrFail();
        $this->assertNotSame($royalVisit->id, $presence->id);
        $this->assertSame('Royal Progress', $presence->activity_type);
        $permission = $tables->get('Permissions')->find()->where(['name' => 'Can Manage Crown Presence'])->firstOrFail();
        $this->assertSame(Permission::SCOPE_BRANCH_AND_CHILDREN, $permission->scoping_rule);
        $this->assertTrue($permission->require_active_membership);
        $this->assertTrue($permission->requires_warrant);
        $this->assertFalse($permission->is_super_user);
        $this->assertSame($policyCount, $tables->get('PermissionPolicies')->find()->count());
        $this->assertRoleGrants();
    }

    public function testPersonaRerunsPreserveAccessAndExistingCatalogueRules(): void
    {
        $seed = new DevLoadBestowalTodoUsersSeed();
        $seed->run();
        $tables = $this->getTableLocator();
        $catalogue = $tables->get('GatheringActivities');
        $presence = $catalogue->find()->where(['name' => 'Crown Presence'])->firstOrFail();
        $presence->description = 'Configured local description';
        $presence->display_order = 777;
        $presence->icon_name = 'archery';
        $presence->foreground_color = '#155E75';
        $presence->background_color = '#CFFAFE';
        $catalogue->saveOrFail($presence);
        $rules = $tables->get('GatheringActivityAssignmentPermissions');
        $custom = ['gathering_activity_id' => $presence->id, 'permission_id' => self::SUPER_USER_PERMISSION_ID];
        $rules->findOrCreate($custom);
        $counts = [$catalogue->find()->count(), $rules->find()->count()];
        $permissions = $tables->get('Permissions');
        $permission = $permissions->find()->where(['name' => 'Can Manage Crown Presence'])->firstOrFail();
        $permission->scoping_rule = Permission::SCOPE_BRANCH_ONLY;
        $permission->require_active_background_check = true;
        $permissions->saveOrFail($permission);

        $seed->run();
        $seed->run();

        $saved = $catalogue->find()->where(['name' => 'Crown Presence'])->firstOrFail();
        $this->assertSame($presence->id, $saved->id);
        foreach (['description', 'display_order', 'icon_name', 'foreground_color', 'background_color'] as $field) {
            $this->assertSame($presence->get($field), $saved->get($field));
        }
        $this->assertSame($counts, [$catalogue->find()->count(), $rules->find()->count()]);
        $this->assertTrue($rules->exists($custom));
        $permission = $permissions->get($permission->id);
        $this->assertSame(Permission::SCOPE_BRANCH_ONLY, $permission->scoping_rule);
        $this->assertTrue($permission->require_active_background_check);
        $this->assertRoleGrants();
        $scrollRole = $tables->get('Roles')->find()->where(['name' => 'Crown Scroll Bestowal Todo'])->firstOrFail();
        $permissionIds = $tables->get('Permissions')->find()->where(['name IN' => [
            'Can Manage Crown Presence', 'Can Manage Court Schedule',
        ]])->all()->extract('id')->toList();
        $this->assertFalse($tables->get('RolesPermissions')->exists([
            'role_id' => $scrollRole->id, 'permission_id IN' => $permissionIds,
        ]));
    }

    public static function schedulingCases(): array
    {
        $cases = [];
        foreach (['forest@ampdemo.com', 'avery.crown.scheduler@ampdemo.com'] as $email) {
            foreach (['Crown Presence', 'Kingdom Court', 'Baronial Court'] as $activity) {
                $cases[$email . ': ' . $activity] = [$email, $activity];
            }
        }

        return $cases;
    }

    #[DataProvider('schedulingCases')]
    public function testSeededUsersCanAttachAndScheduleFromAnEmptyChildEvent(string $email, string $name): void
    {
        (new DevLoadBestowalTodoUsersSeed())->run();
        $actor = $this->actor($email);
        $gathering = $this->gathering();
        $tables = $this->getTableLocator();
        $activity = $tables->get('GatheringActivities')->find()->where(['name' => $name])->firstOrFail();
        $this->assertFalse($tables->get('GatheringsGatheringActivities')->exists(['gathering_id' => $gathering->id]));
        $policy = new GatheringPolicy();
        $this->assertFalse($policy->canEdit($actor, $gathering));
        $this->assertFalse($policy->canViewManagement($actor, $gathering));
        $this->assertTrue($policy->canManageActivity($actor, $gathering, $activity));
        $attached = (new GatheringActivityService())->addActivity(
            (int)$gathering->id,
            (int)$activity->id,
            [],
            actorId: (int)$actor->id,
        );
        $this->assertTrue($attached['success'], $attached['message']);
        $gathering = $tables->get('Gatherings')->get($gathering->id);
        $this->assertTrue($policy->canCreateScheduledActivity($actor, $gathering));
        $result = (new GatheringScheduleService())->add([
            'gathering_activity_id' => $activity->id, 'is_other' => false, 'display_title' => $name,
            'start_datetime' => DateTime::now()->addDays(7)->format('Y-m-d') . 'T12:00',
            'duration_minutes' => 60,
        ], $gathering, $actor);
        $this->assertTrue($result['success'], json_encode($result));
        $saved = $tables->get('GatheringScheduledActivities')->get($result['data']->id);
        $this->assertSame((int)$activity->id, (int)$saved->gathering_activity_id);
        $this->assertSame((int)$actor->id, (int)$saved->created_by);
        $this->assertFalse($saved->is_other);
        $this->assertTrue($policy->canEditScheduledActivity($actor, $gathering, $saved));
        $this->assertFalse($policy->canManageActivity(
            $actor,
            $gathering,
            $tables->get('GatheringActivities')->find()->where(['name' => 'Royal Visit'])->firstOrFail(),
        ));
        $this->assertFalse($policy->canEditScheduledActivity($actor, $gathering, new GatheringScheduledActivity([
            'gathering_id' => $gathering->id, 'is_other' => true,
        ])));
        $this->expectException(ForbiddenException::class);
        (new GatheringScheduleService())->add([
            'is_other' => true, 'display_title' => 'Unrelated Other activity',
            'start_datetime' => DateTime::now()->addDays(7)->format('Y-m-d') . 'T14:00',
            'duration_minutes' => 60,
        ], $gathering, $actor);
    }

    public static function missingRequirements(): array
    {
        return [['membership'], ['warrant']];
    }

    public function testSeededAccessDoesNotExtendOutsideTheGrantedKingdom(): void
    {
        (new DevLoadBestowalTodoUsersSeed())->run();
        $tables = $this->getTableLocator();
        $actor = $this->actor('avery.crown.scheduler@ampdemo.com');
        $branches = $tables->get('Branches');
        $otherKingdom = $branches->saveOrFail($branches->newEntity([
            'name' => 'Independent Crown seed kingdom', 'type' => 'Kingdom',
            'parent_id' => null, 'can_have_members' => true, 'location' => 'Regression fixture',
        ]));
        $outside = $this->gathering((int)$otherKingdom->id);
        $policy = new GatheringPolicy();
        foreach (['Crown Presence', 'Kingdom Court', 'Baronial Court'] as $name) {
            $activity = $tables->get('GatheringActivities')->find()->where(['name' => $name])->firstOrFail();
            $this->assertFalse($policy->canManageActivity($actor, $outside, $activity), $name);
        }
    }

    public static function unsafePermissions(): array
    {
        return [['deleted'], ['superuser']];
    }

    #[DataProvider('unsafePermissions')]
    public function testSeedDoesNotGrantADeletedOrSuperuserPermission(string $state): void
    {
        $tables = $this->getTableLocator();
        $permissions = $tables->get('Permissions');
        $existing = $permissions->find()->where(['name' => 'Can Manage Crown Presence'])->first();
        if ($existing !== null) {
            $existing->name = 'Previous configured Crown Presence fixture';
            $permissions->saveOrFail($existing);
        }
        $permission = $permissions->saveOrFail($permissions->newEntity([
            'name' => 'Can Manage Crown Presence', 'scoping_rule' => Permission::SCOPE_BRANCH_AND_CHILDREN,
            'is_super_user' => $state === 'superuser',
        ]));
        if ($state === 'deleted') {
            $permissions->deleteOrFail($permission);
        }
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Resolve the deleted or superuser Can Manage Crown Presence permission');
        try {
            (new DevLoadBestowalTodoUsersSeed())->run();
        } finally {
            $this->assertFalse($tables->get('RolesPermissions')->exists(['permission_id' => $permission->id]));
        }
    }

    #[DataProvider('missingRequirements')]
    public function testSeededActivityAccessStillRequiresMembershipAndWarrants(string $missing): void
    {
        (new DevLoadBestowalTodoUsersSeed())->run();
        $tables = $this->getTableLocator();
        $actor = $this->actor('avery.crown.scheduler@ampdemo.com');
        if ($missing === 'membership') {
            $actor->membership_expires_on = DateTime::now()->subDays(1);
            $tables->get('Members')->saveOrFail($actor);
        } else {
            $tables->get('Warrants')->deleteAll(['member_id' => $actor->id]);
        }
        $actor = $tables->get('Members')->get($actor->id);
        $gathering = $this->gathering();
        $policy = new GatheringPolicy();
        foreach (['Crown Presence', 'Kingdom Court', 'Baronial Court'] as $name) {
            $activity = $tables->get('GatheringActivities')->find()->where(['name' => $name])->firstOrFail();
            $this->assertFalse($policy->canManageActivity($actor, $gathering, $activity), $name);
        }
    }

    private function assertRoleGrants(): void
    {
        $tables = $this->getTableLocator();
        foreach (['Ansteorran Crown', 'Crown Award Schedule Bestowal Todo', 'Golden Staff'] as $name) {
            $role = $tables->get('Roles')->find()->where(['name' => $name])->firstOrFail();
            foreach (['Can Manage Crown Presence', 'Can Manage Court Schedule'] as $permissionName) {
                $permission = $tables->get('Permissions')->find()->where(['name' => $permissionName])->firstOrFail();
                $this->assertSame(1, $tables->get('RolesPermissions')->find()->where([
                    'role_id' => $role->id, 'permission_id' => $permission->id,
                ])->count(), $name . ': ' . $permissionName);
            }
        }
    }

    private function actor(string $email): Member
    {
        $members = $this->getTableLocator()->get('Members');
        $actor = $members->find()->where(['email_address' => $email])->firstOrFail();
        $actor->status = Member::STATUS_VERIFIED_MEMBERSHIP;
        $actor->membership_expires_on = DateTime::now()->addYears(2);
        $members->saveOrFail($actor);

        return $members->get($actor->id);
    }

    private function gathering(int $branchId = self::TEST_BRANCH_STARGATE_ID): Gathering
    {
        $tables = $this->getTableLocator();

        return $tables->get('Gatherings')->saveOrFail($tables->get('Gatherings')->newEntity([
            'name' => 'Reset Crown access regression', 'branch_id' => $branchId,
            'gathering_type_id' => $tables->get('GatheringTypes')->find()->firstOrFail()->id,
            'start_date' => DateTime::now()->addDays(7)->setTime(0, 0),
            'end_date' => DateTime::now()->addDays(8)->setTime(23, 59),
            'timezone' => 'UTC', 'created_by' => self::ADMIN_MEMBER_ID,
        ]), ['skipTemplateSync' => true]);
    }
}
