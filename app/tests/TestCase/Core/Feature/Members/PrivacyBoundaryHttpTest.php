<?php
declare(strict_types=1);

namespace App\Test\TestCase\Core\Feature\Members;

use App\Model\Entity\ServicePrincipal;
use App\Model\Entity\ServicePrincipalToken;
use App\Policy\GatheringAttendancePolicy;
use App\Policy\MemberPolicy;
use App\Policy\MembersTablePolicy;
use App\Test\TestCase\Support\HttpIntegrationTestCase;
use Cake\Cache\Cache;

/** Exercise privacy decisions through routing, real credentials, policies and JSON views. */
class PrivacyBoundaryHttpTest extends HttpIntegrationTestCase
{
    public function testBasicApiCredentialCannotReadOrSearchPrivateMemberFields(): void
    {
        $target = $this->getTableLocator()->get('Members')->get(self::TEST_MEMBER_BRYCE_ID);
        $token = $this->apiCredential(false);
        $this->apiGet('/api/v1/members/' . $target->id, $token);
        $this->assertResponseOk();
        $data = $this->json()['data'];
        $this->assertSame((int)$target->id, $data['id']);
        foreach (['first_name', 'last_name', 'email_address', 'membership_number', 'created'] as $field) {
            $this->assertArrayNotHasKey($field, $data);
        }
        $this->apiGet('/api/v1/members?search=' . rawurlencode($target->email_address), $token);
        $this->assertResponseOk();
        $this->assertSame([], $this->json()['data']);
        $this->apiGet('/api/v1/members?search=' . rawurlencode($target->sca_name), $token);
        $this->assertResponseOk();
        $this->assertContains((int)$target->id, array_column($this->json()['data'], 'id'));
    }

    public function testPiiApiCredentialRetainsAuthorizedPrivateDetailAndSearch(): void
    {
        $target = $this->getTableLocator()->get('Members')->get(self::TEST_MEMBER_BRYCE_ID);
        $token = $this->apiCredential(true);
        $this->apiGet('/api/v1/members/' . $target->id, $token);
        $this->assertResponseOk();
        $this->assertSame($target->email_address, $this->json()['data']['email_address']);
        $this->apiGet('/api/v1/members?search=' . rawurlencode($target->email_address), $token);
        $this->assertResponseOk();
        $this->assertContains((int)$target->id, array_column($this->json()['data'], 'id'));
    }

    public function testGatheringStewardCannotUseContactLookupForAnUnrelatedMember(): void
    {
        $gathering = $this->gathering();
        $staff = $this->getTableLocator()->get('GatheringStaff');
        $staff->saveOrFail($staff->newEntity([
            'gathering_id' => $gathering->id,
            'member_id' => self::TEST_MEMBER_AGATHA_ID,
            'role' => 'Steward',
            'email' => 'synthetic-steward@example.test',
            'is_steward' => true,
            'show_on_public_page' => false,
            'sort_order' => 0,
        ]));
        $members = $this->getTableLocator()->get('Members');
        $target = $members->get(self::TEST_MEMBER_BRYCE_ID);
        $actor = $members->get(self::TEST_MEMBER_AGATHA_ID);
        $this->authenticateAsMember((int)$actor->id);
        $this->get('/gathering-staff/get-member-contact-info?' . http_build_query([
            'member_public_id' => $target->public_id,
            'gathering_public_id' => $gathering->public_id,
        ]));
        $this->assertResponseCode(403);
        $this->assertResponseNotContains($target->email_address);
        $this->assertArrayNotHasKey('email', $this->json());

        $this->get('/gathering-staff/get-member-contact-info?' . http_build_query([
            'member_public_id' => $actor->public_id,
            'gathering_public_id' => $gathering->public_id,
        ]));
        $this->assertResponseOk();
        $this->assertSame($actor->email_address, $this->json()['email']);
    }

    public function testAttendanceEnrichmentRequiresConsentAndAnAuthorizedRecipient(): void
    {
        $activities = $this->getTableLocator()->get('GatheringActivities');
        $activity = $activities->saveOrFail($activities->newEntity(['name' => 'Privacy HTTP Test Activity']));
        $gathering = $this->gathering([$activity->id]);
        $award = $this->getTableLocator()->get('Awards.Awards')->find()->firstOrFail();
        $links = $this->getTableLocator()->get('Awards.AwardGatheringActivities');
        $links->saveOrFail($links->newEntity(['award_id' => $award->id, 'gathering_activity_id' => $activity->id]));
        $target = $this->getTableLocator()->get('Members')->get(self::TEST_MEMBER_BRYCE_ID);
        $attendanceTable = $this->getTableLocator()->get('GatheringAttendances');
        $attendance = $attendanceTable->saveOrFail($attendanceTable->newEntity([
            'gathering_id' => $gathering->id,
            'member_id' => $target->id,
            'share_with_crown' => true,
            'share_with_kingdom' => false,
            'share_with_hosting_group' => false,
        ]));
        $url = '/awards/recommendations/gatherings-for-award/' . $award->id
            . '?member_id=' . rawurlencode($target->public_id);
        $this->get($url);
        $this->assertResponseOk();
        $this->assertFalse($this->gatheringResult((int)$gathering->id)['has_attendance']);

        $this->authenticateAsMember(self::TEST_MEMBER_AGATHA_ID);
        $this->get($url);
        $this->assertResponseOk();
        $this->assertFalse($this->gatheringResult((int)$gathering->id)['has_attendance']);

        $attendance->share_with_kingdom = true;
        $attendanceTable->saveOrFail($attendance);
        $this->get($url);
        $this->assertResponseOk();
        $this->assertTrue($this->gatheringResult((int)$gathering->id)['has_attendance']);

        $attendance->share_with_kingdom = false;
        $attendanceTable->saveOrFail($attendance);
        $roleId = $this->roleWithPolicies([[GatheringAttendancePolicy::class, 'canViewCrown']]);
        $roles = $this->getTableLocator()->get('MemberRoles');
        $roles->saveOrFail($roles->newEntity([
            'member_id' => self::TEST_MEMBER_AGATHA_ID,
            'role_id' => $roleId,
            'branch_id' => self::KINGDOM_BRANCH_ID,
            'start_on' => '2000-01-01',
            'approver_id' => self::ADMIN_MEMBER_ID,
            'entity_type' => 'Direct Grant',
        ]));
        Cache::clearGroup('security');
        $this->authenticateAsMember(self::TEST_MEMBER_AGATHA_ID);
        $this->get($url);
        $this->assertResponseOk();
        $this->assertTrue($this->gatheringResult((int)$gathering->id)['has_attendance']);
    }

    private function apiCredential(bool $pii): string
    {
        $policies = [[MemberPolicy::class, 'canView'], [MemberPolicy::class, 'canIndex'], [MembersTablePolicy::class, 'canIndex']];
        if ($pii) {
            $policies[] = [MemberPolicy::class, 'canViewPii'];
        }
        $roleId = $this->roleWithPolicies($policies);
        $principals = $this->getTableLocator()->get('ServicePrincipals');
        $principal = $principals->newEmptyEntity();
        $principal->name = 'Privacy HTTP Principal ' . bin2hex(random_bytes(4));
        $principal->client_id = ServicePrincipal::generateClientId();
        $principal->client_secret_hash = password_hash('synthetic-client-secret', PASSWORD_DEFAULT);
        $principal->is_active = true;
        $principals->saveOrFail($principal);
        $roles = $this->getTableLocator()->get('ServicePrincipalRoles');
        $roles->saveOrFail($roles->newEntity([
            'service_principal_id' => $principal->id,
            'role_id' => $roleId,
            'start_on' => '2000-01-01',
            'approver_id' => self::ADMIN_MEMBER_ID,
            'entity_type' => 'Direct Grant',
        ]));
        $token = ServicePrincipalToken::generateToken();
        $tokens = $this->getTableLocator()->get('ServicePrincipalTokens');
        $entity = $tokens->newEmptyEntity();
        $entity->service_principal_id = $principal->id;
        $entity->token_hash = ServicePrincipalToken::hashToken($token);
        $entity->name = 'Synthetic privacy regression';
        $tokens->saveOrFail($entity);
        Cache::clearGroup('security');

        return $token;
    }

    private function roleWithPolicies(array $policies): int
    {
        $roles = $this->getTableLocator()->get('Roles');
        $role = $roles->saveOrFail($roles->newEntity(['name' => 'Privacy test role ' . bin2hex(random_bytes(4))]));
        $permissions = $this->getTableLocator()->get('Permissions');
        $permission = $permissions->saveOrFail($permissions->newEntity([
            'name' => 'Privacy test permission ' . bin2hex(random_bytes(4)),
            'require_active_membership' => false,
            'require_active_background_check' => false,
            'require_min_age' => 0,
            'is_system' => false,
            'is_super_user' => false,
            'requires_warrant' => false,
            'scoping_rule' => 'Global',
        ]));
        $mappings = $this->getTableLocator()->get('PermissionPolicies');
        foreach ($policies as [$policyClass, $method]) {
            $mappings->saveOrFail($mappings->newEntity([
                'permission_id' => $permission->id,
                'policy_class' => $policyClass,
                'policy_method' => $method,
            ]));
        }
        $grants = $this->getTableLocator()->get('RolesPermissions');
        $grants->saveOrFail($grants->newEntity([
            'role_id' => $role->id,
            'permission_id' => $permission->id,
            'created_by' => self::ADMIN_MEMBER_ID,
        ]));

        return (int)$role->id;
    }

    private function gathering(array $activityIds = []): object
    {
        $gatherings = $this->getTableLocator()->get('Gatherings');

        return $gatherings->saveOrFail($gatherings->newEntity([
            'branch_id' => self::KINGDOM_BRANCH_ID,
            'gathering_type_id' => 1,
            'name' => 'Privacy HTTP Gathering',
            'start_date' => '2099-08-10 10:00:00',
            'end_date' => '2099-08-10 16:00:00',
            'timezone' => 'America/Chicago',
            'created_by' => self::ADMIN_MEMBER_ID,
            'gathering_activities' => ['_ids' => $activityIds],
        ]));
    }

    private function apiGet(string $url, string $token): void
    {
        $this->configRequest(['headers' => ['Authorization' => 'Bearer ' . $token, 'Accept' => 'application/json']]);
        $this->get($url);
    }

    private function json(): array
    {
        return json_decode((string)$this->_response->getBody(), true, 512, JSON_THROW_ON_ERROR);
    }

    private function gatheringResult(int $id): array
    {
        foreach ($this->json()['gatherings'] as $gathering) {
            if ((int)$gathering['id'] === $id) {
                return $gathering;
            }
        }
        $this->fail('Expected the public gathering to remain in the response.');
    }
}
