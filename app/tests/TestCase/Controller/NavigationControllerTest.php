<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use App\Services\Cache\TenantAwareCache;
use App\Test\TestCase\Support\HttpIntegrationTestCase;
use Cake\Cache\Cache;
use Cake\Core\Configure;

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

    public function testContextChangesWithPermissionsAndRuntimeSwitches(): void
    {
        $this->get('/navigation/context');
        $first = (string)$this->_response->getBody();
        Cache::write(TenantAwareCache::tenantScopedKey('security_generation'), 'changed-for-navigation-test');
        $this->get('/navigation/context');
        $this->assertNotSame($first, (string)$this->_response->getBody());
        $second = (string)$this->_response->getBody();
        Configure::write('Turbo.enabled', true);
        try {
            $this->get('/navigation/context');
            $this->assertNotSame($second, (string)$this->_response->getBody());
        } finally {
            Configure::delete('Turbo');
            Cache::delete(TenantAwareCache::tenantScopedKey('security_generation'));
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
