<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use App\Test\TestCase\Support\HttpIntegrationTestCase;
use Cake\Core\Configure;

class TurboFormContractTest extends HttpIntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->authenticateAsSuperUser();
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        Configure::write('Turbo.enabled', true);
    }

    protected function tearDown(): void
    {
        Configure::delete('Turbo');
        parent::tearDown();
    }

    public function testSuccessfulHtmlMutationUsesSeeOther(): void
    {
        $this->post('/roles/add', ['name' => 'Turbo contract role']);
        $this->assertResponseCode(303);
        $this->assertHeaderContains('Location', '/roles/view/');
    }

    public function testValidationRendersSubmittedValuesAndErrorsWithUnprocessableContent(): void
    {
        $name = str_repeat('x', 256);
        $this->post('/roles/add', ['name' => $name]);
        $this->assertResponseCode(422);
        $this->assertResponseContains($name);
        $this->assertResponseContains('invalid-feedback');
        $this->assertResponseContains('aria-invalid="true"');
    }

    public function testFallbackRetainsExistingRedirectContract(): void
    {
        Configure::write('Turbo.enabled', false);
        $this->post('/roles/add', ['name' => 'Native contract role']);
        $this->assertResponseCode(302);
    }

    public function testRestoredMemberGatheringSearchUsesTheContainedBranchAlias(): void
    {
        $this->configRequest(['headers' => ['Turbo-Frame' => 'member-gatherings-grid-' . self::ADMIN_MEMBER_ID]]);
        $this->get('/members/gatherings-grid-data/' . self::ADMIN_MEMBER_ID . '?search=retained');
        $this->assertResponseOk();
        $this->assertResponseContains('member-gatherings-grid-' . self::ADMIN_MEMBER_ID);
    }

    public function testRedirectDestinationWithStreamAndHtmlAcceptRetainsPluginTabs(): void
    {
        $this->configRequest(['headers' => [
            'Accept' => 'text/vnd.turbo-stream.html, text/html, application/xhtml+xml',
        ]]);
        $this->get('/members/view/' . self::ADMIN_MEMBER_ID);
        $this->assertResponseOk();
        $this->assertResponseContains('id="nav-member-authorizations"');
        $this->assertResponseContains('id="nav-member-authorizations-tab"');
    }

    public function testAuthorizedBackgroundScreenReadRetainsPluginTabsAndModalBoundaries(): void
    {
        $this->configRequest(['headers' => [
            'Accept' => 'text/html', 'X-Requested-With' => 'XMLHttpRequest', 'X-KMP-UI-Refresh' => 'screen',
        ]]);
        $this->get('/members/view/' . self::ADMIN_MEMBER_ID);
        $this->assertResponseOk();
        $this->assertResponseContains('id="nav-member-authorizations-tab"');
        $this->assertResponseContains('id="kmp-modal-host"');
        $this->assertResponseContains('id="main-content"');
    }
}
