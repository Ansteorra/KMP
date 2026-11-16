<?php
declare(strict_types=1);

namespace App\Services;

use App\Services\Cache\ReadModelCache;
use Cake\ORM\TableRegistry;

/** Tenant reference data only; callers still apply the current identity's policies. */
final class ReferenceCatalogue
{
    /** @return array<int, string> */
    public static function branches(): array
    {
        return ReadModelCache::remember('branches', static fn(): array => TableRegistry::getTableLocator()
            ->get('Branches')->find('list')->orderBy(['name' => 'ASC'])->toArray());
    }

    /** @return list<\App\Model\Entity\GatheringType> */
    public static function gatheringTypes(): array
    {
        return ReadModelCache::remember('gathering_types', static fn(): array => TableRegistry::getTableLocator()
            ->get('GatheringTypes')->find()->select(['id', 'name', 'description'])
            ->contain(['GatheringActivities'])->orderBy(['name' => 'ASC'])->toArray());
    }

    /** @return array<int, list<int>> */
    public static function activityPermissionIds(): array
    {
        return ReadModelCache::remember('activity_permissions', static function (): array {
            $rules = TableRegistry::getTableLocator()->get('GatheringActivityAssignmentPermissions')->find()
                ->select(['gathering_activity_id', 'permission_id'])->all();
            $map = [];
            foreach ($rules as $rule) {
                $map[$rule->gathering_activity_id][] = (int)$rule->permission_id;
            }

            return $map;
        });
    }

    /** @return array<int, array{parent_id: int|null, type: string|null}> */
    public static function branchIndex(): array
    {
        return ReadModelCache::remember('branch_index', static function (): array {
            $rows = TableRegistry::getTableLocator()->get('Branches')->find()
                ->select(['id', 'parent_id', 'type'])->enableHydration(false)->all();
            $index = [];
            foreach ($rows as $row) {
                $index[(int)$row['id']] = [
                    'parent_id' => $row['parent_id'] !== null ? (int)$row['parent_id'] : null,
                    'type' => $row['type'] !== null ? (string)$row['type'] : null,
                ];
            }

            return $index;
        });
    }
}
