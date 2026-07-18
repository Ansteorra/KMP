<?php
declare(strict_types=1);

namespace Awards\Test\TestCase\Controller;

use App\Model\Entity\Permission;
use App\Test\TestCase\Support\HttpIntegrationTestCase;
use Awards\Model\Entity\Bestowal;
use Awards\Model\Entity\Recommendation;
use Cake\Cache\Cache;
use Cake\ORM\TableRegistry;

/**
 * End-to-end rendering and mutation checks for protected bestowal fields.
 */
class BestowalFieldSecurityControllerTest extends HttpIntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->enableCsrfToken();
        $this->enableSecurityToken();
    }

    public function testGeneralBestowalViewerCannotSeeOrSubmitProtectedFields(): void
    {
        $bestowal = $this->createProtectedBestowal();
        $this->grantBestowalAccess(self::TEST_MEMBER_AGATHA_ID);
        $this->authenticateAsMember(self::TEST_MEMBER_AGATHA_ID);

        $this->get('/awards/bestowals/view/' . $bestowal->id);
        $this->assertResponseOk();
        $this->assertResponseNotContains('Herald secret');
        $this->assertResponseNotContains('Noble secret');
        $this->assertResponseNotContains('Summary secret');
        $this->assertResponseNotContains('Recommendation secret');
        $this->assertResponseNotContains('Linked Recommendations');

        $this->configRequest(['headers' => ['Turbo-Frame' => 'editBestowalQuick']]);
        $this->get('/awards/bestowals/turbo-edit-form/' . $bestowal->id);
        $this->assertResponseOk();
        $this->assertResponseNotContains('name="herald_notes"');
        $this->assertResponseNotContains('name="noble_notes"');
        $this->assertResponseNotContains('name="reason_summary"');
        $this->assertResponseNotContains('name="link_recommendation_ids[]"');

        $this->post('/awards/bestowals/edit/' . $bestowal->id, [
            'noble_notes' => 'Tampered secret',
        ]);
        $this->assertResponseCode(403);
        $this->assertSame(
            'Noble secret',
            TableRegistry::getTableLocator()->get('Awards.Bestowals')->get($bestowal->id)->noble_notes,
        );
    }

    public function testCourtManagerCanSeeHeraldNotesOnly(): void
    {
        $bestowal = $this->createProtectedBestowal();
        $this->grantBestowalAccess(self::TEST_MEMBER_BRYCE_ID, ['canAccessHeraldNotes']);
        $this->authenticateAsMember(self::TEST_MEMBER_BRYCE_ID);

        $this->get('/awards/bestowals/view/' . $bestowal->id);

        $this->assertResponseOk();
        $this->assertResponseContains('Herald secret');
        $this->assertResponseNotContains('Noble secret');
        $this->assertResponseNotContains('Summary secret');
        $this->assertResponseNotContains('Recommendation secret');
        $this->assertResponseNotContains('Linked Recommendations');
    }

    public function testCrownCanSeeAllProtectedFields(): void
    {
        $bestowal = $this->createProtectedBestowal();
        $this->grantBestowalAccess(self::TEST_MEMBER_DEVON_ID, [
            'canAccessHeraldNotes',
            'canAccessCrownFields',
        ]);
        $this->authenticateAsMember(self::TEST_MEMBER_DEVON_ID);

        $this->get('/awards/bestowals/view/' . $bestowal->id);

        $this->assertResponseOk();
        $this->assertResponseContains('Herald secret');
        $this->assertResponseContains('Noble secret');
        $this->assertResponseContains('Summary secret');
        $this->assertResponseContains('Recommendation secret');
        $this->assertResponseContains('Linked Recommendations');
    }

    private function createProtectedBestowal(): Bestowal
    {
        $award = TableRegistry::getTableLocator()->get('Awards.Awards')
            ->find()
            ->where(['branch_id' => self::KINGDOM_BRANCH_ID])
            ->firstOrFail();
        $bestowals = TableRegistry::getTableLocator()->get('Awards.Bestowals');
        $bestowal = $bestowals->saveOrFail($bestowals->newEntity([
            'member_id' => self::ADMIN_MEMBER_ID,
            'award_id' => $award->id,
            'source' => Bestowal::SOURCE_RECOMMENDATION,
            'stack_rank' => 0,
            'herald_notes' => 'Herald secret',
            'noble_notes' => 'Noble secret',
            'reason_summary' => 'Summary secret',
        ]));

        $recommendations = TableRegistry::getTableLocator()->get('Awards.Recommendations');
        $recommendation = $recommendations->saveOrFail($recommendations->newEntity([
            'stack_rank' => 0,
            'requester_id' => self::ADMIN_MEMBER_ID,
            'member_id' => self::ADMIN_MEMBER_ID,
            'branch_id' => self::KINGDOM_BRANCH_ID,
            'award_id' => $award->id,
            'state' => Recommendation::getStates()[0],
            'requester_sca_name' => 'Security Test Requester',
            'member_sca_name' => 'Security Test Recipient',
            'contact_email' => 'security-test@example.test',
            'contact_number' => '555-555-0100',
            'reason' => 'Recommendation secret',
            'call_into_court' => 'Never',
            'court_availability' => 'Any',
            'created_by' => self::ADMIN_MEMBER_ID,
            'modified_by' => self::ADMIN_MEMBER_ID,
        ]));
        $links = TableRegistry::getTableLocator()->get('Awards.BestowalRecommendations');
        $links->saveOrFail($links->newEntity([
            'bestowal_id' => $bestowal->id,
            'recommendation_id' => $recommendation->id,
        ]));

        return $bestowal;
    }

    /**
     * @param list<string> $fieldPolicyMethods
     */
    private function grantBestowalAccess(int $memberId, array $fieldPolicyMethods = []): void
    {
        $permissions = TableRegistry::getTableLocator()->get('Permissions');
        $permission = $permissions->saveOrFail($permissions->newEntity([
            'name' => 'Bestowal Field Security Test ' . uniqid('', true),
            'require_active_membership' => false,
            'require_active_background_check' => false,
            'require_min_age' => 0,
            'is_system' => false,
            'is_super_user' => false,
            'requires_warrant' => false,
            'scoping_rule' => Permission::SCOPE_BRANCH_ONLY,
        ]));

        $permissionPolicies = TableRegistry::getTableLocator()->get('PermissionPolicies');
        foreach (array_merge(['canView', 'canEdit'], $fieldPolicyMethods) as $policyMethod) {
            $permissionPolicies->saveOrFail($permissionPolicies->newEntity([
                'permission_id' => (int)$permission->id,
                'policy_class' => 'Awards\\Policy\\BestowalPolicy',
                'policy_method' => $policyMethod,
            ]));
        }

        $roles = TableRegistry::getTableLocator()->get('Roles');
        $role = $roles->saveOrFail($roles->newEntity([
            'name' => 'Bestowal Field Security Test Role ' . uniqid('', true),
        ]));
        $connection = $roles->getConnection();
        $connection->execute(
            'INSERT INTO roles_permissions (role_id, permission_id, created, created_by)
             VALUES (?, ?, NOW(), ?)',
            [(int)$role->id, (int)$permission->id, self::ADMIN_MEMBER_ID],
        );
        $connection->execute(
            'INSERT INTO member_roles
             (member_id, role_id, branch_id, start_on, expires_on, approver_id, entity_type,
              created, modified, created_by, modified_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), ?, ?)',
            [
                $memberId,
                (int)$role->id,
                self::KINGDOM_BRANCH_ID,
                '2020-01-01 00:00:00',
                '2100-01-01',
                self::ADMIN_MEMBER_ID,
                'Direct Grant',
                self::ADMIN_MEMBER_ID,
                self::ADMIN_MEMBER_ID,
            ],
        );
        Cache::clearGroup('security');
    }
}
