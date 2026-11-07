<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use App\Services\UiUpdateRegistry;
use App\Test\TestCase\Support\HttpIntegrationTestCase;
use Cake\Core\Configure;
use Cake\Routing\Router;

class UiUpdatesControllerTest extends HttpIntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->authenticateAsSuperUser();
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        Configure::write('Turbo.enabled', true);
        Configure::write('Turbo.liveEnabled', true);
        Configure::write('Turbo.gatewayUrl', 'https://live.example.test/events');
    }

    protected function tearDown(): void
    {
        Configure::delete('Turbo');
        parent::tearDown();
    }

    public function testRefreshUsesAnAuthorizedRegisteredScreenAndNoSuppliedTemplate(): void
    {
        $this->post('/ui/refresh', ['context_url' => '/members', 'template' => '/etc/passwd', 'target' => 'arbitrary']);
        $this->assertResponseOk();
        $this->assertContentType('text/vnd.turbo-stream.html');
        $this->assertResponseContains('action="kmp-invalidate"');
        $this->assertResponseNotContains('arbitrary');
        $this->assertResponseNotContains('/etc/passwd');
    }

    public function testRefreshRejectsForeignOriginsAndMutationRoutes(): void
    {
        foreach (['https://attacker.example/members', '//attacker.example/members', '/members/delete/1', '/unknown-screen'] as $url) {
            $this->post('/ui/refresh', ['context_url' => $url]);
            $this->assertResponseCode(400);
        }
    }

    public function testRefreshIsUnavailableWhenLiveDeliveryIsDisabled(): void
    {
        Configure::write('Turbo.liveEnabled', false);
        $this->post('/ui/refresh', ['context_url' => '/members']);
        $this->assertResponseCode(503);
    }

    public function testDetailDependenciesIncludePluginCataloguesWithoutSubscribingToOtherMembers(): void
    {
        $this->get('/members/view/' . self::ADMIN_MEMBER_ID);
        $this->assertResponseOk();
        $context = UiUpdateRegistry::resolve(Router::getRequest(), '/members/view/' . self::ADMIN_MEMBER_ID);
        $this->assertContains('Members:' . self::ADMIN_MEMBER_ID, $context['topics']);
        $this->assertContains('Officers.Offices:catalogue', $context['topics']);
        $this->assertContains('Activities.Activities:catalogue', $context['topics']);
        $this->assertContains('Awards.Awards:catalogue', $context['topics']);
        $this->assertNotContains('Members:list', $context['topics']);
        $this->assertNotContains('Members:' . self::TEST_MEMBER_AGATHA_ID, $context['topics']);
    }

    public function testNotesRefreshUsesOnlyTheRegisteredListAndRechecksPrivateVisibility(): void
    {
        Configure::write('Turbo.liveEnabled', false);
        $notes = $this->getTableLocator()->get('Notes');
        foreach ([false => 'Public live note', true => 'Private live note'] as $private => $subject) {
            $notes->saveOrFail($notes->newEntity([
                'entity_type' => 'Members', 'entity_id' => self::TEST_MEMBER_AGATHA_ID,
                'author_id' => self::ADMIN_MEMBER_ID, 'subject' => $subject, 'body' => 'Scoped note',
                'private' => (bool)$private,
            ]));
        }
        Configure::write('Turbo.liveEnabled', true);
        $this->authenticateAsMember(self::TEST_MEMBER_AGATHA_ID);
        $this->post('/ui/refresh', [
            'context_url' => '/members/view/' . self::TEST_MEMBER_AGATHA_ID,
            'changes' => ['notes'], 'target' => 'arbitrary-target',
        ]);
        $this->assertResponseOk();
        $this->assertResponseContains('Public live note');
        $this->assertResponseNotContains('Private live note');
        $this->assertResponseNotContains('arbitrary-target');
        $this->assertResponseNotContains('<html');
        $this->assertResponseContains('target="notes-list-'
            . md5('Members:' . self::TEST_MEMBER_AGATHA_ID) . '"');
    }
}
