<?php
declare(strict_types=1);

namespace App\Test\TestCase\Core\Feature\Members;

use App\Test\TestCase\Support\HttpIntegrationTestCase;

final class TrustedDeviceLoginTest extends HttpIntegrationTestCase
{
    public function testSetupRequiresTheCurrentMembersPasswordAndNeverEchoesIt(): void
    {
        $password = 'SyntheticDevicePassword123!';
        $members = $this->getTableLocator()->get('Members');
        $member = $members->get(self::TEST_MEMBER_AGATHA_ID);
        $member->password = $password;
        $members->saveOrFail($member);
        $this->authenticateAsMember(self::TEST_MEMBER_AGATHA_ID);
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->configRequest(['headers' => ['Accept' => 'application/json']]);
        $this->post('/offline/verify-login', ['password' => 'Wrong password']);
        $this->assertResponseCode(403);
        $this->assertResponseNotContains($member->email_address);
        $this->post('/offline/verify-login', ['password' => $password]);
        $this->assertResponseOk();
        $this->assertHeader('Cache-Control', 'no-store');
        $body = json_decode((string)$this->_response->getBody(), true);
        $this->assertSame(['success' => true, 'email' => $member->email_address], $body);
        $this->assertResponseNotContains($password);
    }

    public function testUnauthenticatedSetupCannotValidateOrSaveADeviceLogin(): void
    {
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->post('/offline/verify-login', ['password' => 'UnusedPassword']);
        $this->assertRedirectContains('/members/login');
    }
}
