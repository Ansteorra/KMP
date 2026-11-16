<?php
declare(strict_types=1);

namespace App\Test\TestCase\Services\Cache;

use App\KMP\PermissionsLoader;
use App\KMP\TenantContext;
use App\KMP\TenantMetadata;
use App\Services\Cache\ReadModelCache;
use App\Services\ReferenceCatalogue;
use App\Services\Security\MemberSecurityGraph;
use App\Test\TestCase\BaseTestCase;
use Awards\Services\BestowalDelegationAccessService;
use Cake\Database\Connection;
use Cake\Datasource\ConnectionManager;
use Cake\Http\ServerRequest;
use Cake\I18n\DateTime;
use Cake\ORM\TableRegistry;
use Cake\Routing\Router;
use RuntimeException;

/** Real commits and independent connections exercise invalidation outside ORM callbacks. */
class ReadModelCacheTest extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->rollbackTransaction();
        $this->request();
    }

    protected function tearDown(): void
    {
        DateTime::setTestNow();
        Router::setRequest(new ServerRequest(['environment' => ['REQUEST_METHOD' => 'POST']]));
        parent::tearDown();
    }

    private function request(string $method = 'GET'): void
    {
        Router::setRequest(new ServerRequest(['environment' => ['REQUEST_METHOD' => $method]]));
    }

    public function testEmptyResultsAreCachedAndTenantScopesCannotCollide(): void
    {
        $calls = 0;
        $load = function () use (&$calls): array {
            $calls++;

            return [];
        };
        $a = new TenantMetadata('cache-a', 'cache-a', 'A', 'active', 'db', 'db', 'role');
        $b = new TenantMetadata('cache-b', 'cache-b', 'B', 'active', 'db', 'db', 'role');
        $key = 'empty-' . bin2hex(random_bytes(8));
        $this->assertSame([], TenantContext::with($a, fn() => ReadModelCache::remember($key, $load)));
        $this->request();
        $this->assertSame([], TenantContext::with($a, fn() => ReadModelCache::remember($key, $load)));
        $this->assertSame(1, $calls);
        TenantContext::with($b, fn() => ReadModelCache::remember($key, $load));
        $this->assertSame(2, $calls);
    }

    public function testTransactionsAndMutationRequestsNeverReadOrPublishCachedState(): void
    {
        $db = ConnectionManager::get('test');
        $before = ReferenceCatalogue::branches();
        $db->begin();
        try {
            $db->update('branches', ['name' => 'Uncommitted cache test'], ['id' => self::TEST_BRANCH_STARGATE_ID]);
            $this->assertSame('Uncommitted cache test', ReferenceCatalogue::branches()[self::TEST_BRANCH_STARGATE_ID]);
        } finally {
            $db->rollback();
        }
        $this->request();
        $this->assertSame($before, ReferenceCatalogue::branches());
        $key = 'mutation-' . bin2hex(random_bytes(8));
        $this->assertSame('committed', ReadModelCache::remember($key, static fn() => 'committed'));
        $this->request('POST');
        $this->assertSame('fresh', ReadModelCache::remember($key, static fn() => 'fresh'));
        $this->request();
        $this->assertSame('committed', ReadModelCache::remember($key, static fn() => 'unexpected'));
    }

    public function testIndependentWritersCanCommitOutOfOrderAndBothInvalidate(): void
    {
        $db = ConnectionManager::get('test');
        $before = ReferenceCatalogue::branches();
        $a = new Connection($db->config());
        $b = new Connection($db->config());
        try {
            $a->begin();
            $a->execute("SET LOCAL lock_timeout = '1s'");
            $a->update('branches', ['name' => 'Writer A'], ['id' => self::TEST_BRANCH_STARGATE_ID]);
            $b->begin();
            $b->execute("SET LOCAL lock_timeout = '1s'");
            $b->update('branches', ['name' => 'Writer B'], ['id' => self::TEST_BRANCH_LOCAL_ID]);
            $this->request();
            $this->assertSame($before, ReferenceCatalogue::branches());
            $b->commit();
            $this->request();
            $afterB = ReferenceCatalogue::branches();
            $this->assertSame('Writer B', $afterB[self::TEST_BRANCH_LOCAL_ID]);
            $this->assertSame($before[self::TEST_BRANCH_STARGATE_ID], $afterB[self::TEST_BRANCH_STARGATE_ID]);
            $a->commit();
            $this->request();
            $this->assertSame('Writer A', ReferenceCatalogue::branches()[self::TEST_BRANCH_STARGATE_ID]);
            $this->assertSame('Writer B', ReferenceCatalogue::branches()[self::TEST_BRANCH_LOCAL_ID]);
        } finally {
            if ($a->inTransaction()) {
                $a->rollback();
            }
            if ($b->inTransaction()) {
                $b->rollback();
            }
            foreach ([self::TEST_BRANCH_STARGATE_ID, self::TEST_BRANCH_LOCAL_ID] as $id) {
                $db->update('branches', ['name' => $before[$id]], ['id' => $id]);
            }
            $a->getDriver()->disconnect();
            $b->getDriver()->disconnect();
        }
    }

    public function testCommittedRawRoleRevocationInvalidatesNativeGraph(): void
    {
        $db = ConnectionManager::get('test');
        $rows = $db->execute('SELECT id, revoker_id FROM member_roles WHERE member_id = :member', [
            'member' => self::TEST_MEMBER_BRYCE_ID,
        ])->fetchAll('assoc');
        $before = PermissionsLoader::getPermissions(self::TEST_MEMBER_BRYCE_ID);
        $this->assertNotEmpty($before);
        try {
            $db->update('member_roles', ['revoker_id' => self::ADMIN_MEMBER_ID], ['member_id' => self::TEST_MEMBER_BRYCE_ID]);
            $this->request();
            $this->assertSame([], PermissionsLoader::getPermissions(self::TEST_MEMBER_BRYCE_ID));
            $this->assertSame([], PermissionsLoader::getRoles(self::TEST_MEMBER_BRYCE_ID));
            $this->assertSame([], PermissionsLoader::getPolicies(self::TEST_MEMBER_BRYCE_ID));
        } finally {
            foreach ($rows as $row) {
                $db->update('member_roles', ['revoker_id' => $row['revoker_id']], ['id' => $row['id']]);
            }
        }
        $this->request();
        $this->assertEquals($before, PermissionsLoader::getPermissions(self::TEST_MEMBER_BRYCE_ID));
    }

    public function testRoleCacheExpiresAtTheExactBoundaryWithoutAnyWrite(): void
    {
        $db = ConnectionManager::get('test');
        $role = $db->execute('SELECT id,start_on,expires_on,revoker_id FROM member_roles WHERE member_id = :member LIMIT 1', [
            'member' => self::TEST_MEMBER_BRYCE_ID,
        ])->fetch('assoc');
        $now = new DateTime('2030-01-15 12:00:00', 'UTC');
        try {
            $db->update('member_roles', ['start_on' => '2030-01-15 11:00:00',
                'expires_on' => '2030-01-15 12:00:02', 'revoker_id' => null], ['id' => $role['id']]);
            DateTime::setTestNow($now);
            $this->request();
            $before = PermissionsLoader::getRoles(self::TEST_MEMBER_BRYCE_ID);
            DateTime::setTestNow($now->addSeconds(3));
            $this->request();
            $after = PermissionsLoader::getRoles(self::TEST_MEMBER_BRYCE_ID);
            $this->assertNotEquals($before, $after);
        } finally {
            $db->update('member_roles', array_diff_key($role, ['id' => true]), ['id' => $role['id']]);
        }
    }

    public function testOfficeFactsReevaluateFutureStartsAndExpiration(): void
    {
        $db = ConnectionManager::get('test');
        // Run real-time daily maintenance before advancing the clock for this isolated assignment.
        TableRegistry::getTableLocator()->get('Officers.Officers');
        $row = $db->execute('SELECT id,member_id,office_id,branch_id,status,start_on,expires_on FROM officers_officers LIMIT 1')
            ->fetch('assoc');
        $now = new DateTime('2030-01-15 12:00:00', 'UTC');
        try {
            $db->update('officers_officers', ['status' => 'Current', 'start_on' => '2030-01-15 12:00:02',
                'expires_on' => '2030-01-15 12:00:04'], ['id' => $row['id']]);
            DateTime::setTestNow($now);
            $this->request();
            $before = MemberSecurityGraph::currentOfficeScopes((int)$row['member_id']);
            DateTime::setTestNow($now->addSeconds(3));
            $this->request();
            $active = MemberSecurityGraph::currentOfficeScopes((int)$row['member_id']);
            $this->assertCount(count($before) + 1, $active);
            DateTime::setTestNow($now->addSeconds(5));
            $this->request();
            $this->assertSame($before, MemberSecurityGraph::currentOfficeScopes((int)$row['member_id']));
        } finally {
            $db->update('officers_officers', ['status' => $row['status'], 'start_on' => $row['start_on'],
                'expires_on' => $row['expires_on']], ['id' => $row['id']]);
        }
    }

    public function testEveryDependencyInvalidatesEvenForRawBulkStatements(): void
    {
        $db = ConnectionManager::get('test');
        $tables = ['branches', 'gathering_types', 'gathering_activities',
            'gathering_type_gathering_activities', 'gathering_activity_assignment_permissions',
            'gathering_staff', 'members', 'gatherings', 'roles', 'permissions', 'roles_permissions',
            'permission_policies', 'member_roles', 'warrants', 'app_settings',
            'officers_officers', 'awards_bestowal_delegations'];
        $calls = 0;
        $key = 'dependencies-' . bin2hex(random_bytes(8));
        $load = static function () use (&$calls): int {
            return ++$calls;
        };
        $expected = ReadModelCache::remember($key, $load);
        foreach ($tables as $table) {
            $db->execute('DELETE FROM ' . $db->getDriver()->quoteIdentifier($table) . ' WHERE FALSE');
            $this->request();
            $this->assertSame(++$expected, ReadModelCache::remember($key, $load), $table);
        }
    }

    public function testCommitRefreshesTheRevisionWithinTheSameRequest(): void
    {
        $db = ConnectionManager::get('test');
        $before = ReferenceCatalogue::branches();
        try {
            $db->transactional(function () use ($db): void {
                $db->update('branches', ['name' => 'Committed within GET'], ['id' => self::TEST_BRANCH_STARGATE_ID]);
            });
            $this->assertSame('Committed within GET', ReferenceCatalogue::branches()[self::TEST_BRANCH_STARGATE_ID]);
        } finally {
            $db->update('branches', ['name' => $before[self::TEST_BRANCH_STARGATE_ID]], ['id' => self::TEST_BRANCH_STARGATE_ID]);
        }
    }

    public function testWarrantSettingDoesNotReuseTheSeparateAppSettingsCache(): void
    {
        $db = ConnectionManager::get('test');
        $name = 'KMP.RequireActiveWarrantForSecurity';
        $before = MemberSecurityGraph::warrantRequirement();
        try {
            $db->update('app_settings', ['value' => 'no'], ['name' => $name]);
            $this->request();
            $this->assertSame('no', MemberSecurityGraph::warrantRequirement());
            $db->update('app_settings', ['value' => 'yes'], ['name' => $name]);
            $this->request();
            $this->assertSame('yes', MemberSecurityGraph::warrantRequirement());
        } finally {
            $db->update('app_settings', ['value' => $before], ['name' => $name]);
        }
    }

    public function testDelegationsKeepEventAndBranchBoundariesAndObserveRevocation(): void
    {
        $tables = TableRegistry::getTableLocator();
        $db = ConnectionManager::get('test');
        $gathering = $tables->get('Gatherings')->find()->firstOrFail();
        $grants = $tables->get('Awards.BestowalDelegations');
        $grant = $grants->saveOrFail($grants->newEntity([
            'gathering_id' => $gathering->id, 'branch_id' => self::KINGDOM_BRANCH_ID,
            'grantor_id' => self::ADMIN_MEMBER_ID, 'delegate_id' => self::TEST_MEMBER_BRYCE_ID,
            'created_by' => self::ADMIN_MEMBER_ID,
        ], ['accessibleFields' => ['grantor_id' => true, 'created_by' => true]]));
        $access = new BestowalDelegationAccessService();
        try {
            $this->request();
            $active = $access->grantsFor(self::TEST_MEMBER_BRYCE_ID, (int)$gathering->id, self::KINGDOM_BRANCH_ID);
            $this->assertCount(1, $active);
            $this->assertNull($active[0]->grantor->password);
            $this->assertSame([], $access->grantsFor(self::TEST_MEMBER_BRYCE_ID, (int)$gathering->id, self::TEST_BRANCH_LOCAL_ID));
            $this->assertSame([], $access->grantsFor(self::TEST_MEMBER_BRYCE_ID, (int)$gathering->id + 1000000));
            $db->update('awards_bestowal_delegations', ['revoked_at' => DateTime::now()], ['id' => $grant->id]);
            $this->request();
            $this->assertSame([], $access->grantsFor(self::TEST_MEMBER_BRYCE_ID));
        } finally {
            $db->delete('awards_bestowal_delegations', ['id' => $grant->id]);
        }
    }

    public function testCompactionRotatesCheckpointWithoutRevivingOldEntries(): void
    {
        $db = ConnectionManager::get('test');
        $oldRevision = $db->execute('SELECT revision FROM application_read_cache_revision')->fetchColumn(0);
        $key = 'compaction-' . bin2hex(random_bytes(8));
        $this->assertSame('before', ReadModelCache::remember($key, static fn() => 'before'));
        $db->execute("UPDATE application_read_cache_changes SET created_at = clock_timestamp() - interval '1 day'");
        $db->execute('DELETE FROM branches WHERE FALSE');
        $this->request();
        $this->assertNotSame($oldRevision, $db->execute('SELECT revision FROM application_read_cache_revision')->fetchColumn(0));
        $this->assertSame('after', ReadModelCache::remember($key, static fn() => 'after'));
    }

    public function testMissingRevisionFailsClosedEvenWhenAnEntryIsWarm(): void
    {
        $db = ConnectionManager::get('test');
        $key = 'missing-revision-' . bin2hex(random_bytes(8));
        ReadModelCache::remember($key, static fn() => 'cached');
        try {
            $db->execute('DELETE FROM application_read_cache_revision');
            $this->request();
            try {
                ReadModelCache::remember($key, static fn() => 'unchecked');
                $this->fail('Missing invalidation metadata must not fall back to a cache entry.');
            } catch (RuntimeException $e) {
                $this->assertSame('Read cache revision is unavailable.', $e->getMessage());
            }
        } finally {
            $db->execute('INSERT INTO application_read_cache_revision (id,revision) VALUES (1,gen_random_uuid())');
        }
    }
}
