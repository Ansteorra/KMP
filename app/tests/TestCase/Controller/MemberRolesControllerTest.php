<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use App\Test\TestCase\Support\HttpIntegrationTestCase;

class MemberRolesControllerTest extends HttpIntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->authenticateAsSuperUser();
        $this->configRequest(['headers' => ['Referer' => 'http://localhost/roles']]);
    }

    public function testGrantAcceptsTheMemberAutocompletePublicId(): void
    {
        $member = $this->getTableLocator()->get('Members')->get(self::TEST_MEMBER_AGATHA_ID);
        $this->assertSuccessfulGrant($member->public_id);
    }

    public function testGrantRetainsLegacyNumericMemberIds(): void
    {
        $this->assertSuccessfulGrant((string)self::TEST_MEMBER_AGATHA_ID);
    }

    public function testUnknownPublicIdDoesNotCreateAMemberRole(): void
    {
        $roles = $this->getTableLocator()->get('Roles');
        $role = $roles->saveOrFail($roles->newEntity(['name' => 'Unknown member grant test']));
        $this->post('/member-roles/add', ['role_id' => $role->id, 'member_id' => 'missing-public-member']);

        $this->assertRedirect('/roles');
        $this->assertFlashMessage('The member could not be found.');
        $this->assertFalse($this->getTableLocator()->get('MemberRoles')->exists(['role_id' => $role->id]));
    }

    private function assertSuccessfulGrant(string $identifier): void
    {
        $roles = $this->getTableLocator()->get('Roles');
        $role = $roles->saveOrFail($roles->newEntity(['name' => 'Member identifier grant test']));
        $this->post('/member-roles/add', ['role_id' => $role->id, 'member_id' => $identifier]);

        $this->assertRedirect('/roles');
        $this->assertFlashMessage('The Member role has been saved.');
        $this->assertTrue($this->getTableLocator()->get('MemberRoles')->exists([
            'role_id' => $role->id,
            'member_id' => self::TEST_MEMBER_AGATHA_ID,
        ]));
    }
}
