<?php
declare(strict_types=1);

namespace Awards\Test\TestCase\Config;

use AddCrownPresencePermission;
use App\Model\Entity\Gathering;
use App\Model\Entity\GatheringActivity;
use App\Model\Entity\Member;
use App\Model\Entity\Permission;
use App\Policy\GatheringPolicy;
use App\Services\GatheringActivityService;
use App\Services\GatheringScheduleService;
use App\Test\TestCase\BaseTestCase;
use Cake\Cache\Cache;
use Cake\Core\Configure;
use Cake\Http\Exception\ForbiddenException;
use Cake\I18n\DateTime;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;

require_once dirname(__DIR__, 3) . '/config/Migrations/20261110220000_AddCrownPresencePermission.php';

class AddCrownPresencePermissionTest extends BaseTestCase
{
    private bool $cacheWasEnabled;

    private mixed $warrantSetting;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cacheWasEnabled = Cache::enabled();
        Cache::disable();
        $this->warrantSetting = Configure::read('KMP.RequireActiveWarrantForSecurity');
        Configure::write('KMP.RequireActiveWarrantForSecurity', 'yes');
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

    public function testMigrationGrantsOnlyCrownSchedulingRolesAndPreservesCustomActivityRules(): void
    {
        $activity = $this->presence();
        $crown = $this->role('Crown fixture', 'Crown Award Schedule Management');
        $scheduler = $this->role('Crown scheduler fixture', 'Crown Award Schedule Management');
        $baronial = $this->role('Baronial fixture', 'Baronial Award Schedule Management');
        $court = $this->role('Court scheduler fixture', 'Can Manage Court Schedule');
        $rules = $this->getTableLocator()->get('GatheringActivityAssignmentPermissions');
        $rules->findOrCreate([
            'gathering_activity_id' => $activity->id, 'permission_id' => self::SUPER_USER_PERMISSION_ID,
        ]);
        $before = $this->getTableLocator()->get('PermissionPolicies')->find()->count();

        $this->migrate();
        $this->migrate();

        $permission = $this->permission();
        $this->assertSame(Permission::SCOPE_BRANCH_AND_CHILDREN, $permission->scoping_rule);
        $this->assertTrue($permission->require_active_membership);
        $this->assertTrue($permission->requires_warrant);
        $this->assertFalse($permission->is_super_user);
        $this->assertSame($before, $this->getTableLocator()->get('PermissionPolicies')->find()->count());
        foreach ([$crown, $scheduler] as $roleId) {
            $this->assertSame(1, $this->getTableLocator()->get('RolesPermissions')->find()->where([
                'role_id' => $roleId, 'permission_id' => $permission->id,
            ])->count());
        }
        foreach ([$baronial, $court] as $roleId) {
            $this->assertFalse($this->getTableLocator()->get('RolesPermissions')->exists([
                'role_id' => $roleId, 'permission_id' => $permission->id,
            ]));
        }
        foreach ([$permission->id, self::SUPER_USER_PERMISSION_ID] as $permissionId) {
            $this->assertSame(1, $rules->find()->where([
                'gathering_activity_id' => $activity->id, 'permission_id' => $permissionId,
            ])->count());
        }
    }

    public static function crownRoles(): array
    {
        return [['Crown fixture'], ['Crown scheduler fixture']];
    }

    #[DataProvider('crownRoles')]
    public function testCrownRolesCanAttachAndSchedulePresenceAtAChildBranchWithoutGeneralEdit(string $role): void
    {
        $activity = $this->presence();
        $roleId = $this->role($role, 'Crown Award Schedule Management');
        $actor = $this->actor($roleId, self::KINGDOM_BRANCH_ID);
        $gathering = $this->gathering(self::TEST_BRANCH_STARGATE_ID);
        $policy = new GatheringPolicy();
        $this->assertFalse($policy->canManageActivity($actor, $gathering, $activity));
        $this->migrate();
        $actor = $this->getTableLocator()->get('Members')->get($actor->id);
        $this->assertFalse($policy->canEdit($actor, $gathering));
        $this->assertFalse($policy->canViewManagement($actor, $gathering));
        $this->assertTrue($policy->canManageActivity($actor, $gathering, $activity));

        $result = (new GatheringActivityService())->addActivity(
            (int)$gathering->id,
            (int)$activity->id,
            [],
            actorId: (int)$actor->id,
        );
        $this->assertTrue($result['success'], $result['message']);
        $gathering = $this->getTableLocator()->get('Gatherings')->get($gathering->id);
        $this->assertTrue($policy->canCreateScheduledActivity($actor, $gathering));
        $result = (new GatheringScheduleService())->add([
            'gathering_activity_id' => $activity->id,
            'start_datetime' => DateTime::now()->addDays(14)->format('Y-m-d') . 'T12:00',
            'duration_minutes' => 60, 'is_other' => false, 'display_title' => 'Crown Presence',
        ], $gathering, $actor);
        $this->assertTrue($result['success'], json_encode($result));
        $this->assertSame((int)$activity->id, (int)$result['data']->gathering_activity_id);
        $this->assertTrue($policy->canEditScheduledActivity($actor, $gathering, $result['data']));
        $this->assertFalse($policy->canEdit($actor, $gathering));
        $unrelated = $this->getTableLocator()->get('GatheringActivities')->saveOrFail(
            $this->getTableLocator()->get('GatheringActivities')->newEntity([
                'name' => 'Unrelated royal presence fixture', 'activity_type' => 'Royal Progress',
            ]),
        );
        $this->assertFalse($policy->canManageActivity($actor, $gathering, $unrelated));
        $this->expectException(ForbiddenException::class);
        (new GatheringScheduleService())->add([
            'is_other' => true, 'display_title' => 'Unrelated schedule entry',
        ], $gathering, $actor);
    }

    public static function rejectedGrants(): array
    {
        return [['wrong branch'], ['revoked role'], ['expired role'], ['expired warrant'], ['expired membership']];
    }

    #[DataProvider('rejectedGrants')]
    public function testInvalidOrOutOfScopeGrantsCannotAttachPresence(string $reason): void
    {
        $activity = $this->presence();
        $role = $this->role('Restricted Crown fixture', 'Crown Award Schedule Management');
        $actor = $this->actor($role, $reason === 'wrong branch' ? self::TEST_BRANCH_LOCAL_ID : self::KINGDOM_BRANCH_ID);
        $this->migrate();
        $connection = $this->getTableLocator()->get('Members')->getConnection();
        if ($reason === 'revoked role') {
            $connection->update('member_roles', ['revoker_id' => self::ADMIN_MEMBER_ID], ['member_id' => $actor->id]);
        } elseif ($reason === 'expired role') {
            $connection->update('member_roles', ['expires_on' => '2000-01-01'], ['member_id' => $actor->id]);
        } elseif ($reason === 'expired warrant') {
            $connection->update('warrants', ['expires_on' => '2000-01-01'], ['member_id' => $actor->id]);
        } elseif ($reason === 'expired membership') {
            $connection->update('members', ['membership_expires_on' => '2000-01-01'], ['id' => $actor->id]);
        }
        $gathering = $this->gathering(self::TEST_BRANCH_STARGATE_ID);
        $this->expectException(ForbiddenException::class);
        (new GatheringActivityService())->addActivity(
            (int)$gathering->id,
            (int)$activity->id,
            [],
            actorId: (int)$actor->id,
        );
    }

    public function testMigrationPreservesExistingPermissionSettingsAndOtherRoyalActivities(): void
    {
        $this->presence();
        $this->migrate();
        $permissions = $this->getTableLocator()->get('Permissions');
        $permission = $this->permission();
        $permission->require_active_background_check = true;
        $permissions->saveOrFail($permission);
        $this->migrate();
        $this->assertTrue($permissions->get($permission->id)->require_active_background_check);
        $rules = $this->getTableLocator()->get('GatheringActivityAssignmentPermissions');
        $this->assertSame(0, $rules->find()->innerJoinWith('GatheringActivities')->where([
            'permission_id' => $permission->id, 'GatheringActivities.name !=' => 'Crown Presence',
        ])->count());
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('irreversible');
        (new AddCrownPresencePermission(20261110220000))->down();
    }

    public function testDeletedPermissionRequiresOperatorResolution(): void
    {
        $this->presence();
        $this->migrate();
        $permission = $this->permission();
        $permissions = $this->getTableLocator()->get('Permissions');
        $permissions->getConnection()->update('permissions', [
            'deleted' => DateTime::now(),
        ], ['id' => $permission->id]);
        try {
            $this->migrate();
            $this->fail('An intentionally deleted permission must not be silently reactivated.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('deleted', $exception->getMessage());
        }
        $this->assertFalse($permissions->exists(['id' => $permission->id]));
        $this->assertSame(1, $permissions->find('withTrashed')->where([
            'name' => 'Can Manage Crown Presence',
        ])->count());
    }

    public function testCustomRoyalCatalogueIsNotRenamedOrRecreated(): void
    {
        $activity = $this->presence();
        $activities = $this->getTableLocator()->get('GatheringActivities');
        $activity->name = 'Tenant royal visit fixture';
        $activities->saveOrFail($activity);
        $this->migrate();
        $this->assertFalse($activities->exists(['name' => 'Crown Presence']));
        $this->assertSame('Tenant royal visit fixture', $activities->get($activity->id)->name);
        $this->assertFalse($this->getTableLocator()->get('GatheringActivityAssignmentPermissions')->exists([
            'gathering_activity_id' => $activity->id, 'permission_id' => $this->permission()->id,
        ]));
    }

    private function migrate(): void
    {
        (new AddCrownPresencePermission(20261110220000))->up();
    }

    private function permission(): Permission
    {
        return $this->getTableLocator()->get('Permissions')->find()
            ->where(['name' => 'Can Manage Crown Presence'])->firstOrFail();
    }

    private function presence(): GatheringActivity
    {
        $activities = $this->getTableLocator()->get('GatheringActivities');
        $activity = $activities->findOrCreate(['name' => 'Crown Presence'], function ($activity): void {
            $activity->activity_type = 'Royal Progress';
        });
        $this->getTableLocator()->get('GatheringActivityAssignmentPermissions')
            ->deleteAll(['gathering_activity_id' => $activity->id]);

        return $activity;
    }

    private function role(string $name, string $permissionName): int
    {
        $permissions = $this->getTableLocator()->get('Permissions');
        $permission = $permissions->findOrCreate(['name' => $permissionName], function ($permission): void {
            $permission->scoping_rule = Permission::SCOPE_BRANCH_ONLY;
        });
        $roles = $this->getTableLocator()->get('Roles');
        $role = $roles->saveOrFail($roles->newEntity(['name' => $name]));
        $grants = $this->getTableLocator()->get('RolesPermissions');
        $grants->saveOrFail($grants->newEntity([
            'role_id' => $role->id, 'permission_id' => $permission->id, 'created_by' => self::ADMIN_MEMBER_ID,
        ]));

        return (int)$role->id;
    }

    private function actor(int $roleId, int $branchId): Member
    {
        $tables = $this->getTableLocator();
        $members = $tables->get('Members');
        $actor = $members->get(self::TEST_MEMBER_AGATHA_ID);
        $actor->set('status', Member::STATUS_VERIFIED_MEMBERSHIP);
        $actor->set('membership_expires_on', DateTime::now()->addYears(2));
        $members->saveOrFail($actor);
        $tables->get('MemberRoles')->deleteAll(['member_id' => $actor->id]);
        $assignments = $tables->get('MemberRoles');
        $assignment = $assignments->newEmptyEntity();
        $assignment->patch([
            'member_id' => $actor->id, 'role_id' => $roleId, 'branch_id' => $branchId,
            'start_on' => DateTime::now()->subDays(1), 'expires_on' => null,
            'created_by' => self::ADMIN_MEMBER_ID, 'approver_id' => self::ADMIN_MEMBER_ID,
        ], ['guard' => false]);
        $assignments->saveOrFail($assignment);
        $warrants = $tables->get('Warrants');
        $warrant = $warrants->newEmptyEntity();
        $warrant->patch([
            'name' => 'Crown Presence regression warrant', 'member_id' => $actor->id,
            'member_role_id' => $assignment->id,
            'warrant_roster_id' => $tables->get('WarrantRosters')->find()->firstOrFail()->id,
            'entity_type' => 'Direct Grant', 'entity_id' => -1,
            'start_on' => DateTime::now()->subDays(1), 'expires_on' => DateTime::now()->addYears(1),
            'approved_date' => DateTime::now(), 'status' => 'Current', 'created_by' => self::ADMIN_MEMBER_ID,
        ], ['guard' => false]);
        $warrants->saveOrFail($warrant);

        return $members->get($actor->id);
    }

    private function gathering(int $branchId): Gathering
    {
        $gatherings = $this->getTableLocator()->get('Gatherings');

        return $gatherings->saveOrFail($gatherings->newEntity([
            'name' => 'Crown Presence regression gathering', 'branch_id' => $branchId,
            'gathering_type_id' => $this->getTableLocator()->get('GatheringTypes')->find()->firstOrFail()->id,
            'start_date' => DateTime::now()->addDays(14)->setTime(0, 0),
            'end_date' => DateTime::now()->addDays(15)->setTime(23, 59),
            'timezone' => 'America/Chicago', 'created_by' => self::ADMIN_MEMBER_ID,
        ]));
    }
}
