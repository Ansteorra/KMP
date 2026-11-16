<?php
declare(strict_types=1);

namespace App\Test\TestCase\Services\Cache;

use App\Authenticator\MemberSessionAuthenticator;
use App\KMP\PermissionsLoader;
use App\KMP\Telemetry\RequestQueryCounter;
use App\KMP\TenantContext;
use App\KMP\TenantMetadata;
use App\Model\Entity\Member;
use App\Model\Entity\Permission;
use App\Policy\GatheringPolicy;
use App\Services\Cache\ReadModelCache;
use App\Services\Security\MemberSecurityGraph;
use App\Services\Security\MemberSessionState;
use App\Test\TestCase\BaseTestCase;
use Awards\Services\BestowalDelegationAccessService;
use Cake\Core\Configure;
use Cake\Datasource\ConnectionManager;
use Cake\Http\ServerRequest;
use Cake\Http\Session;
use Cake\I18n\DateTime;
use Cake\ORM\TableRegistry;
use Cake\Routing\Router;
use Closure;
use Psr\Log\NullLogger;

/** Exercise committed, warm GET caches rather than the transaction bypass used by ordinary policy tests. */
class SecurityReadCacheBoundaryTest extends BaseTestCase
{
    private array $created = [];
    private mixed $warrantOverride;
    private int $permissionId;
    private int $roleId;
    private int $assignmentId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rollbackTransaction();
        $this->warrantOverride = Configure::read('KMP.RequireActiveWarrantForSecurity');
        Configure::write('KMP.RequireActiveWarrantForSecurity', 'no');
        $this->request();
        $this->permissionId = $this->create('Permissions', [
            'name' => 'Cache boundary ' . bin2hex(random_bytes(8)),
            'scoping_rule' => Permission::SCOPE_BRANCH_ONLY,
        ]);
        $this->roleId = $this->create('Roles', ['name' => 'Cache boundary ' . bin2hex(random_bytes(8))]);
        $this->create('RolesPermissions', ['role_id' => $this->roleId,
            'permission_id' => $this->permissionId, 'created_by' => self::ADMIN_MEMBER_ID]);
        $this->create('PermissionPolicies', ['permission_id' => $this->permissionId,
            'policy_class' => GatheringPolicy::class, 'policy_method' => 'canEdit']);
        $this->assignmentId = $this->create('MemberRoles', [
            'member_id' => self::TEST_MEMBER_AGATHA_ID, 'role_id' => $this->roleId,
            'branch_id' => self::TEST_BRANCH_STARGATE_ID, 'approver_id' => self::ADMIN_MEMBER_ID,
            'start_on' => '2000-01-01 00:00:00', 'expires_on' => null,
        ]);
    }

    protected function tearDown(): void
    {
        DateTime::setTestNow();
        $this->request('POST');
        foreach (array_reverse($this->created) as [$table, $where]) {
            ConnectionManager::get('test')->delete($table, $where);
        }
        if ($this->warrantOverride === null) {
            Configure::delete('KMP.RequireActiveWarrantForSecurity');
        } else {
            Configure::write('KMP.RequireActiveWarrantForSecurity', $this->warrantOverride);
        }
        parent::tearDown();
    }

    private function create(string $alias, array $data): int
    {
        $table = TableRegistry::getTableLocator()->get($alias);
        $entity = $table->newEmptyEntity()->patch($data, ['guard' => false]);
        $table->saveOrFail($entity, ['checkRules' => false]);
        $where = [];
        foreach ((array)$table->getPrimaryKey() as $column) {
            $where[$column] = $entity->get($column);
        }
        $this->created[] = [$table->getTable(), $where];

        return (int)$entity->id;
    }

    private function request(string $method = 'GET', ?Session $session = null): ServerRequest
    {
        $request = new ServerRequest(['environment' => ['REQUEST_METHOD' => $method]]);
        if ($session !== null) {
            $request = $request->withAttribute('session', $session);
        }
        Router::setRequest($request);

        return $request;
    }

    /** Restore committed test mutations even after a failed assertion. */
    private function change(string $table, array $where, array $values, Closure $check): void
    {
        $db = ConnectionManager::get('test');
        $types = $db->getSchemaCollection()->describe($table)->typeMap();
        $rows = $db->selectQuery()->select('*')->from($table)->where($where)->execute()->fetchAll('assoc');
        try {
            $db->update($table, $values, $where, $types);
            $this->request();
            $check();
        } finally {
            foreach ($rows as $row) {
                $db->update($table, array_intersect_key($row, $values), ['id' => $row['id']], $types);
            }
            $this->request();
        }
    }

    private function graph(int $memberId): array
    {
        return [PermissionsLoader::getPermissions($memberId), PermissionsLoader::getRoles($memberId),
            PermissionsLoader::getPolicies($memberId)];
    }

    private function assertFixtureGrant(bool $allowed): void
    {
        $this->request();
        $cached = PermissionsLoader::getPermissions(self::TEST_MEMBER_AGATHA_ID);
        $this->assertSame($allowed, isset($cached[$this->permissionId]));
        $this->request('POST');
        $this->assertEquals($cached, PermissionsLoader::getPermissions(self::TEST_MEMBER_AGATHA_ID));
        $this->request();
    }

    public function testWarmGraphsMatchFreshGraphsAndCannotBleedBetweenMembers(): void
    {
        $driver = ConnectionManager::get('test')->getDriver();
        $logger = $driver->getLogger();
        $counter = new RequestQueryCounter($logger);
        $driver->setLogger($counter);
        $driver->enableQueryLogging();
        try {
            foreach (
                [self::ADMIN_MEMBER_ID, self::TEST_MEMBER_AGATHA_ID, self::TEST_MEMBER_BRYCE_ID,
                self::TEST_MEMBER_DEVON_ID, self::TEST_MEMBER_EIRIK_ID] as $id
            ) {
                $this->request();
                $cold = $this->graph($id);
                $this->request();
                $counter->reset();
                $this->assertEquals($cold, $this->graph($id));
                $this->assertSame(1, $counter->count(), 'A real cache hit may query only its committed revision.');
                $this->request('POST');
                $this->assertEquals($cold, $this->graph($id));
            }
            $this->request();
            $this->assertArrayNotHasKey(self::SUPER_USER_PERMISSION_ID, $this->graph(self::TEST_MEMBER_AGATHA_ID)[0]);
            $this->assertArrayNotHasKey($this->permissionId, $this->graph(self::ADMIN_MEMBER_ID)[0]);
        } finally {
            $driver->setLogger($logger ?? new NullLogger());
            if ($logger === null) {
                $driver->disableQueryLogging();
            }
        }
    }

    public function testFilteredOrMutatedResultsCannotPoisonTheWarmGraph(): void
    {
        $before = $this->graph(self::TEST_MEMBER_AGATHA_ID);
        $permissions = PermissionsLoader::getPermissions(self::TEST_MEMBER_AGATHA_ID);
        $permissions[$this->permissionId]->branch_ids = null;
        $permissions[$this->permissionId]->is_super_user = true;
        $filtered = PermissionsLoader::getPolicies(self::TEST_MEMBER_AGATHA_ID, [self::TEST_BRANCH_LOCAL_ID]);
        $this->assertArrayNotHasKey('canEdit', $filtered[GatheringPolicy::class] ?? []);
        $this->request();
        $this->assertEquals($before, $this->graph(self::TEST_MEMBER_AGATHA_ID));
    }

    public function testRoleScopePolicyAndPermissionEditsInvalidateWarmGrants(): void
    {
        $this->assertFixtureGrant(true);
        $this->change('member_roles', ['id' => $this->assignmentId], ['branch_id' => self::TEST_BRANCH_LOCAL_ID], function (): void {
            $this->assertSame([self::TEST_BRANCH_LOCAL_ID], PermissionsLoader::getPermissions(self::TEST_MEMBER_AGATHA_ID)[$this->permissionId]->branch_ids);
            $policies = PermissionsLoader::getPolicies(self::TEST_MEMBER_AGATHA_ID, [self::TEST_BRANCH_STARGATE_ID]);
            $this->assertArrayNotHasKey('canEdit', $policies[GatheringPolicy::class] ?? []);
        });
        $this->change('permissions', ['id' => $this->permissionId], ['deleted' => DateTime::now()], function (): void {
            $this->assertFixtureGrant(false);
        });
        $this->change('member_roles', ['id' => $this->assignmentId], ['revoker_id' => self::ADMIN_MEMBER_ID], function (): void {
            $this->assertFixtureGrant(false);
        });
        $this->change('permission_policies', ['permission_id' => $this->permissionId], ['policy_method' => 'canCancel'], function (): void {
            $policies = PermissionsLoader::getPolicies(self::TEST_MEMBER_AGATHA_ID);
            $this->assertArrayNotHasKey('canEdit', $policies[GatheringPolicy::class] ?? []);
            $this->assertArrayHasKey('canCancel', $policies[GatheringPolicy::class]);
        });
    }

    public function testWarmGraphIsBypassedInsideARolledBackRevocation(): void
    {
        $this->assertFixtureGrant(true);
        $db = ConnectionManager::get('test');
        $db->begin();
        try {
            $db->update('member_roles', ['revoker_id' => self::ADMIN_MEMBER_ID], ['id' => $this->assignmentId]);
            $this->assertFixtureGrant(false);
        } finally {
            $db->rollback();
        }
        $this->assertFixtureGrant(true);
        foreach (['POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'] as $method) {
            $key = 'mutation-method-' . $method;
            $this->request();
            ReadModelCache::remember($key, static fn() => 'cached');
            $this->request($method);
            $this->assertSame('fresh', ReadModelCache::remember($key, static fn() => 'fresh'));
        }
    }

    public function testMembershipAndBackgroundExpiryRemoveWarmPermissionsAtTheBoundary(): void
    {
        $now = new DateTime('2030-01-15 23:59:58', 'UTC');
        foreach (
            ['require_active_membership' => 'membership_expires_on',
            'require_active_background_check' => 'background_check_expires_on'] as $requirement => $field
        ) {
            DateTime::setTestNow($now);
            $this->change('permissions', ['id' => $this->permissionId], [$requirement => true], function () use ($field, $now): void {
                $this->change(
                    'members',
                    ['id' => self::TEST_MEMBER_AGATHA_ID],
                    ['status' => Member::STATUS_VERIFIED_MEMBERSHIP, $field => $now->addSeconds(2)],
                    function () use ($now): void {
                        $this->assertFixtureGrant(true);
                        DateTime::setTestNow($now->addSeconds(2));
                        $this->assertFixtureGrant(false);
                        DateTime::setTestNow($now->addSeconds(3));
                        $this->assertFixtureGrant(false);
                    },
                );
            });
        }
    }

    public function testFutureRoleStartsAndMonthBasedAgeTransitionsRefreshNegativeCaches(): void
    {
        $now = new DateTime('2030-01-31 23:59:58', 'UTC');
        DateTime::setTestNow($now);
        $this->change('member_roles', ['id' => $this->assignmentId], ['start_on' => $now->addSeconds(1)], function () use ($now): void {
            $this->assertFixtureGrant(false);
            DateTime::setTestNow($now->addSeconds(1));
            $this->assertFixtureGrant(false);
            DateTime::setTestNow($now->addSeconds(2));
            $this->assertFixtureGrant(true);
        });
        DateTime::setTestNow($now);
        $this->change('permissions', ['id' => $this->permissionId], ['require_min_age' => 18], function () use ($now): void {
            $this->change('members', ['id' => self::TEST_MEMBER_AGATHA_ID], ['birth_year' => 2012, 'birth_month' => 2], function () use ($now): void {
                $this->assertFixtureGrant(false);
                DateTime::setTestNow($now->addSeconds(2));
                $this->assertFixtureGrant(true);
            });
        });
    }

    public function testWarmWarrantGrantExpiresAndCannotBeSatisfiedByAnotherRole(): void
    {
        $now = new DateTime('2030-01-15 12:00:00', 'UTC');
        DateTime::setTestNow($now);
        Configure::write('KMP.RequireActiveWarrantForSecurity', 'yes');
        $roster = TableRegistry::getTableLocator()->get('WarrantRosters')->find()->firstOrFail();
        $warrantId = $this->create('Warrants', ['name' => 'Cache boundary warrant',
            'member_id' => self::TEST_MEMBER_AGATHA_ID, 'member_role_id' => $this->assignmentId,
            'warrant_roster_id' => $roster->id, 'entity_id' => $this->assignmentId, 'status' => 'Current',
            'start_on' => $now->subDays(1), 'expires_on' => $now->addSeconds(2)]);
        $this->change('permissions', ['id' => $this->permissionId], ['requires_warrant' => true], function () use ($now, $warrantId): void {
            $this->change('members', ['id' => self::TEST_MEMBER_AGATHA_ID], ['warrantable' => true], function () use ($now, $warrantId): void {
                $this->assertFixtureGrant(true);
                $this->change('warrants', ['id' => $warrantId], ['member_role_id' => null], function (): void {
                    $this->assertFixtureGrant(false);
                });
                $this->assertFixtureGrant(true);
                DateTime::setTestNow($now->addSeconds(2));
                $this->assertFixtureGrant(false);
            });
        });
    }

    private function impersonationSession(): Session
    {
        $members = TableRegistry::getTableLocator()->get('Members');
        $admin = $members->get(self::ADMIN_MEMBER_ID);
        $target = $members->get(self::TEST_MEMBER_AGATHA_ID);
        $session = new Session();
        $session->write('Auth', MemberSessionState::fromMember($target));
        $session->write('Impersonation', ['active' => true, 'tenant_id' => MemberSessionState::tenantId(),
            'auth_version' => (string)$admin->auth_version, 'impersonator_id' => self::ADMIN_MEMBER_ID,
            'impersonated_member_id' => self::TEST_MEMBER_AGATHA_ID]);

        return $session;
    }

    public function testImpersonationUsesTargetGraphAndRevokedAdministratorCannotContinue(): void
    {
        $this->graph(self::ADMIN_MEMBER_ID);
        $this->graph(self::TEST_MEMBER_AGATHA_ID);
        $session = $this->impersonationSession();
        $authenticator = new MemberSessionAuthenticator();
        $result = $authenticator->authenticate($this->request(session: $session));
        $this->assertTrue($result->isValid());
        $this->assertSame(self::TEST_MEMBER_AGATHA_ID, (int)$result->getData()->id);
        $this->assertFalse($result->getData()->isSuperUser());
        $this->assertArrayHasKey($this->permissionId, $result->getData()->getPermissions());
        $this->change('member_roles', ['member_id' => self::ADMIN_MEMBER_ID], ['revoker_id' => self::ADMIN_MEMBER_ID], function () use ($session, $authenticator): void {
            $this->assertFalse($authenticator->authenticate($this->request(session: $session))->isValid());
            $this->assertNull($session->read('Auth'));
            $this->assertNull($session->read('Impersonation'));
        });
    }

    public function testWarmImpersonationRejectsBothAccountAndCredentialRevocation(): void
    {
        foreach ([self::ADMIN_MEMBER_ID, self::TEST_MEMBER_AGATHA_ID] as $id) {
            foreach (
                [['status' => Member::STATUS_DEACTIVATED], ['auth_version' => 'revoked-cache-test'],
                ['deleted' => DateTime::now()]] as $change
            ) {
                $this->graph(self::ADMIN_MEMBER_ID);
                $this->graph(self::TEST_MEMBER_AGATHA_ID);
                $session = $this->impersonationSession();
                $authenticator = new MemberSessionAuthenticator();
                $this->assertTrue($authenticator->authenticate($this->request(session: $session))->isValid());
                $this->change('members', ['id' => $id], $change, function () use ($session, $authenticator): void {
                    $this->assertFalse($authenticator->authenticate($this->request(session: $session))->isValid());
                    $this->assertNull($session->read('Auth'));
                });
            }
        }
    }

    public function testSameMemberIdsInAnotherTenantCannotReuseGraphOrImpersonation(): void
    {
        $a = new TenantMetadata('cache-round2-a', 'cache-round2-a', 'A', 'active', 'db', 'db', 'role');
        $b = new TenantMetadata('cache-round2-b', 'cache-round2-b', 'B', 'active', 'db', 'db', 'role');
        $session = TenantContext::with($a, function (): Session {
            $this->graph(self::ADMIN_MEMBER_ID);

            return $this->impersonationSession();
        });
        TenantContext::with($b, function () use ($session): void {
            $authenticator = new MemberSessionAuthenticator();
            $this->assertFalse($authenticator->authenticate($this->request(session: $session))->isValid());
            $this->assertNull($session->read('Auth'));
        });
        $key = 'same-member-' . self::ADMIN_MEMBER_ID;
        TenantContext::with($a, fn() => ReadModelCache::remember($key, static fn() => 'administrator'));
        $this->request();
        $this->assertSame('unprivileged', TenantContext::with($b, fn() => ReadModelCache::remember($key, static fn() => 'unprivileged')));
        $this->assertSame('administrator', TenantContext::with($a, fn() => ReadModelCache::remember($key, static fn() => 'wrong')));
    }

    public function testStewardRevocationChangesGatheringPolicyWithoutGlobalPermission(): void
    {
        $gathering = TableRegistry::getTableLocator()->get('Gatherings')->find()
            ->where(['branch_id !=' => self::TEST_BRANCH_STARGATE_ID])->firstOrFail();
        $member = TableRegistry::getTableLocator()->get('Members')->get(self::TEST_MEMBER_AGATHA_ID);
        $policy = new GatheringPolicy();
        $this->assertFalse($policy->canEdit($member, $gathering));
        $staffId = $this->create('GatheringStaff', ['gathering_id' => $gathering->id,
            'member_id' => $member->id, 'role' => 'Cache boundary steward', 'is_steward' => true]);
        $this->request();
        $this->assertTrue($policy->canEdit($member, $gathering));
        $this->assertContains((int)$gathering->id, MemberSecurityGraph::stewardGatheringIds((int)$member->id));
        $this->change('gathering_staff', ['id' => $staffId], ['is_steward' => false], function () use ($policy, $member, $gathering): void {
            $this->assertFalse($policy->canEdit($member, $gathering));
            $this->assertNotContains((int)$gathering->id, MemberSecurityGraph::stewardGatheringIds((int)$member->id));
        });
    }

    public function testCachedDelegationNeverRetainsGrantorAuthorityAfterRevocation(): void
    {
        $gathering = TableRegistry::getTableLocator()->get('Gatherings')->find()->firstOrFail();
        $this->create('Awards.BestowalDelegations', ['gathering_id' => $gathering->id,
            'branch_id' => self::KINGDOM_BRANCH_ID, 'grantor_id' => self::ADMIN_MEMBER_ID,
            'delegate_id' => self::TEST_MEMBER_AGATHA_ID, 'created_by' => self::ADMIN_MEMBER_ID]);
        $access = new BestowalDelegationAccessService();
        $member = TableRegistry::getTableLocator()->get('Members')->get(self::TEST_MEMBER_AGATHA_ID);
        $this->request();
        $this->assertNotEmpty($access->scopesFor($member));
        $grant = $access->grantsFor((int)$member->id)[0];
        $this->assertTrue($grant->grantor->isSuperUser());
        $this->assertNull($grant->grantor->password);
        $this->assertNull($grant->grantor->auth_version);
        $this->assertFalse($member->isSuperUser());
        $this->change('member_roles', ['member_id' => self::ADMIN_MEMBER_ID], ['revoker_id' => self::ADMIN_MEMBER_ID], function () use ($access, $member): void {
            $this->assertSame([], $access->scopesFor($member));
            $this->assertFalse($access->grantsFor((int)$member->id)[0]->grantor->isSuperUser());
        });
    }

    public function testBranchMovesRefreshDescendantScopesWithoutWideningOtherPolicies(): void
    {
        $this->change(
            'permissions',
            ['id' => $this->permissionId],
            ['scoping_rule' => Permission::SCOPE_BRANCH_AND_CHILDREN],
            function (): void {
                $before = PermissionsLoader::getPermissions(self::TEST_MEMBER_AGATHA_ID)[$this->permissionId];
                $this->assertNotContains(self::TEST_BRANCH_LOCAL_ID, $before->branch_ids);
                $this->change(
                    'branches',
                    ['id' => self::TEST_BRANCH_LOCAL_ID],
                    ['parent_id' => self::TEST_BRANCH_STARGATE_ID],
                    function (): void {
                        $after = PermissionsLoader::getPermissions(self::TEST_MEMBER_AGATHA_ID)[$this->permissionId];
                        $this->assertContains(self::TEST_BRANCH_LOCAL_ID, $after->branch_ids);
                        $this->assertNotContains(self::KINGDOM_BRANCH_ID, $after->branch_ids);
                    },
                );
                $this->assertNotContains(
                    self::TEST_BRANCH_LOCAL_ID,
                    PermissionsLoader::getPermissions(self::TEST_MEMBER_AGATHA_ID)[$this->permissionId]->branch_ids,
                );
            },
        );
    }

    public function testCachedDelegationRechecksGrantorExpiryWithoutAnyDatabaseWrite(): void
    {
        $now = new DateTime('2030-01-15 12:00:00', 'UTC');
        DateTime::setTestNow($now);
        $gathering = TableRegistry::getTableLocator()->get('Gatherings')->find()->firstOrFail();
        $this->create('Awards.BestowalDelegations', ['gathering_id' => $gathering->id,
            'branch_id' => self::KINGDOM_BRANCH_ID, 'grantor_id' => self::ADMIN_MEMBER_ID,
            'delegate_id' => self::TEST_MEMBER_AGATHA_ID, 'created_by' => self::ADMIN_MEMBER_ID]);
        $access = new BestowalDelegationAccessService();
        $member = TableRegistry::getTableLocator()->get('Members')->get(self::TEST_MEMBER_AGATHA_ID);
        $this->change(
            'member_roles',
            ['member_id' => self::ADMIN_MEMBER_ID],
            ['start_on' => $now->subDays(1), 'expires_on' => $now->addSeconds(2)],
            function () use ($access, $member, $now): void {
                $this->assertNotEmpty($access->scopesFor($member));
                $this->assertTrue($access->grantsFor((int)$member->id)[0]->grantor->isSuperUser());
                DateTime::setTestNow($now->addSeconds(2));
                $this->request();
                $this->assertSame([], $access->scopesFor($member));
                $this->assertFalse($access->grantsFor((int)$member->id)[0]->grantor->isSuperUser());
            },
        );
    }
}
