<?php
declare(strict_types=1);

namespace App\Test\TestCase\Core\Feature\Members;

use App\Test\TestCase\Support\HttpIntegrationTestCase;

/** Old enrollment URLs cannot re-enable application PIN authentication. */
final class MembersQuickLoginSetupTest extends HttpIntegrationTestCase
{
    public function testRetiredPinEnrollmentCannotCreateCredentials(): void
    {
        $this->authenticateAsSuperUser();
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $table = $this->getTableLocator()->get('MemberQuickLoginDevices');
        $before = $table->find()->count();
        $this->post('/members/setup-quick-login-pin', [
            'quick_login_pin' => '123456', 'quick_login_pin_confirm' => '123456',
        ]);
        $this->assertResponseCode(404);
        $this->assertSame($before, $table->find()->count());
    }
}
