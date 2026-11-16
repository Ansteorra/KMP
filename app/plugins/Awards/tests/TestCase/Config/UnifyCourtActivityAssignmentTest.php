<?php
declare(strict_types=1);

namespace Awards\Test\TestCase\Config;

use App\Test\TestCase\BaseTestCase;
use UnifyCourtActivityAssignment;

require_once dirname(__DIR__, 3) . '/config/Migrations/20261027180100_UnifyCourtActivityAssignment.php';

class UnifyCourtActivityAssignmentTest extends BaseTestCase
{
    public function testMigrationCopiesCourtGrantsIdempotentlyAndRemovesOnlyLegacyGatheringMappings(): void
    {
        $activities = $this->getTableLocator()->get('GatheringActivities');
        $activity = $activities->saveOrFail($activities->newEntity(['name' => 'Court assignment migration fixture']));
        $eligibility = $this->getTableLocator()->get('Awards.AwardGatheringActivities');
        $award = $this->getTableLocator()->get('Awards.Awards')->find()->firstOrFail();
        $eligibility->saveOrFail($eligibility->newEntity([
            'award_id' => $award->id, 'gathering_activity_id' => $activity->id,
        ]));
        $permissions = $this->getTableLocator()->get('Permissions');
        $mappings = $this->getTableLocator()->get('PermissionPolicies');
        $permissionIds = [];
        foreach (
            ['Crown Court Management', 'Principality Court Management',
            'Baronial Court Management', 'Can Manage Court Schedule'] as $name
        ) {
            $permission = $permissions->findOrCreate(['name' => $name], function ($permission): void {
                $permission->scoping_rule = 'Branch Only';
            });
            $permissionIds[] = $permission->id;
            $mappings->findOrCreate([
                'permission_id' => $permission->id, 'policy_class' => 'App\\Policy\\GatheringPolicy',
                'policy_method' => 'canAddCourtActivity',
            ]);
            $mappings->findOrCreate([
                'permission_id' => $permission->id, 'policy_class' => 'Awards\\Policy\\BestowalPolicy',
                'policy_method' => 'canManageCourtSchedule',
            ]);
        }
        $connection = $activities->getConnection();
        $migration = $this->getMockBuilder(UnifyCourtActivityAssignment::class)
            ->setConstructorArgs([20261027180100])->onlyMethods(['execute'])->getMock();
        $migration->method('execute')->willReturnCallback(
            fn(string $sql, array $params = []): int => $connection->execute($sql, $params)->rowCount(),
        );
        $migration->up();
        $migration->up();
        $rules = $this->getTableLocator()->get('GatheringActivityAssignmentPermissions');
        $this->assertSame(4, $rules->find()->where(['gathering_activity_id' => $activity->id])->count());
        $this->assertSame(0, $mappings->find()->where([
            'permission_id IN' => $permissionIds, 'policy_class' => 'App\\Policy\\GatheringPolicy',
            'policy_method IN' => ['canAddCourtActivity', 'canCreateScheduledActivity', 'canEditScheduledActivity'],
        ])->count());
        $this->assertSame(4, $mappings->find()->where([
            'permission_id IN' => $permissionIds, 'policy_class' => 'Awards\\Policy\\BestowalPolicy',
            'policy_method' => 'canManageCourtSchedule',
        ])->count());

        $migration->down();
        $this->assertSame(12, $mappings->find()->where([
            'permission_id IN' => $permissionIds, 'policy_class' => 'App\\Policy\\GatheringPolicy',
            'policy_method IN' => ['canAddCourtActivity', 'canCreateScheduledActivity', 'canEditScheduledActivity'],
        ])->count());
        $this->assertSame(4, $rules->find()->where(['gathering_activity_id' => $activity->id])->count());
    }
}
