<?php
declare(strict_types=1);

namespace App\Services\Security;

use App\Services\Cache\ReadModelCache;
use Cake\Core\Configure;
use Cake\Datasource\ConnectionManager;
use Cake\I18n\DateTime;
use Cake\ORM\TableRegistry;
use RuntimeException;

/** Supplement RBAC with resource-scoped facts; never merge delegated grants into global permissions. */
final class MemberSecurityGraph
{
    /** Warrant enforcement must use the same committed generation as the native graph. */
    public static function warrantRequirement(): string
    {
        $override = Configure::read('KMP.RequireActiveWarrantForSecurity');
        if ($override !== null) {
            return (string)$override;
        }

        return ReadModelCache::remember('warrant_requirement', static function (): string {
            $setting = TableRegistry::getTableLocator()->get('AppSettings')->find()
                ->where(['name' => 'KMP.RequireActiveWarrantForSecurity'])->first();
            if ($setting === null) {
                throw new RuntimeException('Warrant security setting is unavailable.');
            }

            return (string)$setting->value;
        });
    }

    /** @return list<int> Gathering IDs, not branch/global permissions. */
    public static function stewardGatheringIds(int $memberId): array
    {
        return ReadModelCache::remember('steward_gatherings:' . $memberId, static fn(): array => array_map(
            'intval',
            TableRegistry::getTableLocator()->get('GatheringStaff')->find()
                ->where(['GatheringStaff.member_id' => $memberId, 'GatheringStaff.is_steward' => true])
                ->select(['gathering_id'])->all()->extract('gathering_id')->toArray(),
        ));
    }

    /** Earliest role/warrant/membership/background or month-based age transition, including exact boundaries. */
    public static function validUntil(int $memberId): int
    {
        $dates = ReadModelCache::remember(
            'security_dates:' . $memberId,
            static fn(): array => ConnectionManager::get('default')->execute(
                'SELECT start_on AS boundary FROM member_roles WHERE member_id = :member'
                . ' UNION ALL SELECT expires_on FROM member_roles WHERE member_id = :member'
                . ' UNION ALL SELECT start_on FROM warrants WHERE member_id = :member'
                . ' UNION ALL SELECT expires_on FROM warrants WHERE member_id = :member'
                . ' UNION ALL SELECT membership_expires_on FROM members WHERE id = :member'
                . ' UNION ALL SELECT background_check_expires_on FROM members WHERE id = :member',
                ['member' => $memberId],
            )->fetchAll('assoc'),
        );
        $now = DateTime::now();
        $until = $now->startOfMonth()->addMonths(1)->getTimestamp();
        foreach ($dates as $row) {
            if ($row['boundary'] === null) {
                continue;
            }
            $boundary = new DateTime($row['boundary'], 'UTC');
            if ($boundary->getTimestamp() >= $now->getTimestamp()) {
                $until = min($until, $boundary->getTimestamp());
            }
        }

        return $until;
    }

    /** @return list<array{office_id: int, branch_id: int}> */
    public static function currentOfficeScopes(int $memberId): array
    {
        $locator = TableRegistry::getTableLocator();
        $initialized = $locator->exists('Officers.Officers');
        // Preserve the table's daily status maintenance even on a cache hit.
        $officers = $locator->get('Officers.Officers');
        if (!$initialized) {
            ReadModelCache::forgetRequestRevision();
        }
        $rows = ReadModelCache::remember(
            'officer_assignments:' . $memberId,
            static fn(): array => $officers->find()
                ->select(['office_id', 'branch_id', 'start_on', 'expires_on'])
                ->where(['Officers.member_id' => $memberId, 'Officers.status' => 'Current'])
                ->enableHydration(false)->all()->toList(),
        );
        $now = DateTime::now();
        $active = [];
        foreach ($rows as $row) {
            if (
                $row['start_on'] !== null && $row['start_on'] <= $now
                && ($row['expires_on'] === null || $row['expires_on'] >= $now)
            ) {
                $active[] = ['office_id' => (int)$row['office_id'], 'branch_id' => (int)$row['branch_id']];
            }
        }

        return $active;
    }
}
