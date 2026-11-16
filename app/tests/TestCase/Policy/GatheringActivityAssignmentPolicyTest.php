<?php
declare(strict_types=1);

namespace App\Test\TestCase\Policy;

use App\KMP\KmpIdentityInterface;
use App\KMP\Telemetry\RequestQueryCounter;
use App\Model\Entity\Permission;
use App\Policy\GatheringActivityPolicy;
use App\Policy\GatheringPolicy;
use App\Test\TestCase\BaseTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionProperty;

class GatheringActivityAssignmentPolicyTest extends BaseTestCase
{
    public function testCrownGrantCannotMaskLocalBaronialActivityGrant(): void
    {
        $activities = $this->getTableLocator()->get('GatheringActivities');
        $activity = $activities->saveOrFail($activities->newEntity(['name' => 'Mixed-grant court']));
        $permissions = $this->getTableLocator()->get('Permissions');
        $rules = $this->getTableLocator()->get('GatheringActivityAssignmentPermissions');
        $grants = [];
        $cases = [
            ['Crown fixture grant', Permission::SCOPE_BRANCH_ONLY, self::KINGDOM_BRANCH_ID],
            ['Baronial fixture grant', Permission::SCOPE_BRANCH_AND_CHILDREN, self::TEST_BRANCH_STARGATE_ID],
        ];
        foreach ($cases as [$name, $scope, $branchId]) {
            $permission = $permissions->saveOrFail($permissions->newEntity([
                'name' => $name, 'scoping_rule' => $scope,
            ]));
            $rules->saveOrFail($rules->newEntity([
                'gathering_activity_id' => $activity->id, 'permission_id' => $permission->id,
            ]));
            $grants[] = (object)['id' => $permission->id, 'scoping_rule' => $scope, 'branch_ids' => [$branchId]];
        }
        $gathering = $this->getTableLocator()->get('Gatherings')->newEmptyEntity();
        $gathering->branch_id = self::TEST_BRANCH_STARGATE_ID;
        foreach ([$grants, array_reverse($grants)] as $orderedGrants) {
            $user = $this->createMock(KmpIdentityInterface::class);
            $user->method('checkCan')->willReturn(false);
            $user->method('getPermissions')->willReturn($orderedGrants);
            $policy = new GatheringActivityPolicy();
            $this->assertTrue($policy->canAssign($user, $activity, $gathering));
            $outside = clone $gathering;
            $outside->branch_id = self::TEST_BRANCH_LOCAL_ID;
            $this->assertFalse($policy->canAssign($user, $activity, $outside));
        }
    }

    public function testDeniedCatalogueCheckHasBoundedQueries(): void
    {
        $activities = $this->getTableLocator()->get('GatheringActivities');
        $candidates = [];
        for ($i = 0; $i < 30; $i++) {
            $candidates[] = $activities->saveOrFail($activities->newEntity(['name' => 'Catalogue fixture ' . $i]));
        }
        $gathering = $this->getTableLocator()->get('Gatherings')->find()->firstOrFail();
        $user = $this->createMock(KmpIdentityInterface::class);
        $user->method('getIdentifier')->willReturn(self::TEST_MEMBER_AGATHA_ID);
        $user->method('getPolicies')->willReturn([]);
        $user->method('getPermissions')->willReturn([]);
        $driver = $activities->getConnection()->getDriver();
        $originalLogger = $driver->getLogger();
        $wasLogging = (new ReflectionProperty($driver, 'logQueries'))->getValue($driver);
        $counter = new RequestQueryCounter();
        $driver->setLogger($counter);
        $driver->enableQueryLogging();
        try {
            // Warm ORM metadata before comparing a single activity with the whole catalogue.
            (new GatheringActivityPolicy())->canAssignAny($user, [$candidates[0]], $gathering);
            $counter->reset();
            $this->assertFalse((new GatheringActivityPolicy())->canAssignAny($user, [$candidates[0]], $gathering));
            $singleActivityQueries = $counter->count();
            $counter->reset();
            $this->assertFalse((new GatheringActivityPolicy())->canAssignAny($user, $candidates, $gathering));
            $this->assertGreaterThan(0, $singleActivityQueries, 'The query counter must observe database reads.');
            $this->assertSame($singleActivityQueries, $counter->count(), 'Catalogue size must not multiply queries.');
        } finally {
            (new ReflectionProperty($driver, 'logger'))->setValue($driver, $originalLogger);
            if (!$wasLogging) {
                $driver->disableQueryLogging();
            }
        }
    }

    public static function assignmentCases(): array
    {
        return [
            'unrestricted editor' => [false, true, false, false, true],
            'unrestricted non-editor' => [false, false, false, false, false],
            'scoped activity delegate' => [true, false, true, false, true],
            'editor without activity grant' => [true, true, false, false, false],
            'delegate outside branch' => [true, false, true, true, false],
            'editor outside activity scope' => [true, true, true, true, false],
        ];
    }

    #[DataProvider('assignmentCases')]
    public function testActivityRulesAreScopedAndOverrideGeneralEditRights(
        bool $restricted,
        bool $canEdit,
        bool $hasPermission,
        bool $wrongBranch,
        bool $expected,
    ): void {
        $activities = $this->getTableLocator()->get('GatheringActivities');
        $activity = $activities->saveOrFail($activities->newEntity(['name' => 'Assignment policy fixture']));
        $permissions = $this->getTableLocator()->get('Permissions');
        $permission = $permissions->saveOrFail($permissions->newEntity([
            'name' => 'Activity policy delegate', 'scoping_rule' => Permission::SCOPE_BRANCH_ONLY,
        ]));
        if ($restricted) {
            $rules = $this->getTableLocator()->get('GatheringActivityAssignmentPermissions');
            $rules->saveOrFail($rules->newEntity([
                'gathering_activity_id' => $activity->id, 'permission_id' => $permission->id,
            ]));
        }
        $gathering = $this->getTableLocator()->get('Gatherings')->newEmptyEntity();
        $gathering->branch_id = self::KINGDOM_BRANCH_ID;
        $user = $this->createMock(KmpIdentityInterface::class);
        $user->method('getPolicies')->willReturn($canEdit ? [GatheringPolicy::class => [
            'canEdit' => (object)['scoping_rule' => Permission::SCOPE_GLOBAL],
        ]] : []);
        $user->method('getPermissions')->willReturn($hasPermission ? [(object)[
            'id' => $permission->id, 'scoping_rule' => Permission::SCOPE_BRANCH_ONLY,
            'branch_ids' => [$wrongBranch ? self::TEST_BRANCH_LOCAL_ID : self::KINGDOM_BRANCH_ID],
        ]] : []);

        $this->assertSame($expected, (new GatheringActivityPolicy())->canAssign($user, $activity, $gathering));
        $this->assertSame($expected, (new GatheringActivityPolicy())->canAssignAny($user, [$activity], $gathering));
        $this->assertFalse((new GatheringActivityPolicy())->canAssign($user, $activity));
    }
}
