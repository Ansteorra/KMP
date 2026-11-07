<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use App\Test\TestCase\Support\HttpIntegrationTestCase;
use Cake\Cache\Cache;
use Cake\Core\Configure;
use Cake\ORM\TableRegistry;

class NavigationControllerTest extends HttpIntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->authenticateAsSuperUser();
        $this->configRequest(['headers' => ['Accept' => 'application/json', 'X-Requested-With' => 'XMLHttpRequest']]);
    }

    public function testContextIsOpaquePrivateAndDoesNotEnterPageHistory(): void
    {
        $this->get('/navigation/context');
        $this->assertResponseOk();
        $this->assertHeader('Cache-Control', 'no-store, private');
        $body = json_decode((string)$this->_response->getBody(), true, 32, JSON_THROW_ON_ERROR);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $body['context']);
        $this->assertSame(['context'], array_keys($body));
        $this->assertSession(null, 'pageStack');
    }

    public function testContextTracksRevocationEvenAfterPermissionCacheEviction(): void
    {
        $this->get('/navigation/context');
        $first = (string)$this->_response->getBody();
        $roles = TableRegistry::getTableLocator()->get('MemberRoles');
        $assignments = $roles->find()->where(['member_id' => self::ADMIN_MEMBER_ID])->all()->toList();
        $this->assertNotEmpty($assignments);
        foreach ($assignments as $assignment) {
            $assignment->set('revoker_id', self::ADMIN_MEMBER_ID);
            $roles->saveOrFail($assignment, ['checkRules' => false]);
        }
        $this->get('/navigation/context');
        $this->assertResponseOk();
        $this->assertNotSame($first, (string)$this->_response->getBody());
        $second = (string)$this->_response->getBody();
        Cache::clearGroup('security');
        $this->get('/navigation/context');
        $this->assertResponseOk();
        $this->assertSame($second, (string)$this->_response->getBody());
    }

    public function testUnrelatedRoleChangesDoNotDiscardTheCurrentUsersDrafts(): void
    {
        $this->get('/navigation/context');
        $first = (string)$this->_response->getBody();
        $roles = TableRegistry::getTableLocator()->get('MemberRoles');
        $assignment = $roles->find()->where([
            'member_id !=' => self::ADMIN_MEMBER_ID,
            'revoker_id IS' => null,
        ])->firstOrFail();
        $assignment->set('revoker_id', self::ADMIN_MEMBER_ID);
        $roles->saveOrFail($assignment, ['checkRules' => false]);
        $this->get('/navigation/context');
        $this->assertResponseOk();
        $this->assertSame($first, (string)$this->_response->getBody());
    }

    public function testContextChangesWithRuntimeSwitches(): void
    {
        $previous = Configure::read('Turbo');
        $this->get('/navigation/context');
        $first = (string)$this->_response->getBody();
        Configure::write('Turbo.enabled', true);
        try {
            $this->get('/navigation/context');
            $this->assertNotSame($first, (string)$this->_response->getBody());
        } finally {
            if ($previous === null) {
                Configure::delete('Turbo');
            } else {
                Configure::write('Turbo', $previous);
            }
        }
    }

    public function testContextRejectsWritesAndAnonymousRequests(): void
    {
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->post('/navigation/context');
        $this->assertResponseCode(405);
    }

    public function testDifferentCandidateRevisionsCannotShareAuthenticatedSnapshots(): void
    {
        $previous = Configure::read('App.releaseRevision');
        try {
            Configure::write('App.releaseRevision', 'candidate-a');
            $this->get('/navigation/context');
            $first = (string)$this->_response->getBody();
            Configure::write('App.releaseRevision', 'candidate-b');
            $this->get('/navigation/context');
            $this->assertResponseOk();
            $this->assertNotSame($first, (string)$this->_response->getBody());
        } finally {
            if ($previous === null) {
                Configure::delete('App.releaseRevision');
            } else {
                Configure::write('App.releaseRevision', $previous);
            }
        }
    }
}
