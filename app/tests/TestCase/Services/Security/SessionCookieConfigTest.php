<?php
declare(strict_types=1);

namespace App\Test\TestCase\Services\Security;

use App\Services\Security\SessionCookieConfig;
use Cake\TestSuite\TestCase;

class SessionCookieConfigTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        unset($_ENV['KMP_SESSION_COOKIE_DOMAIN'], $_SERVER['KMP_SESSION_COOKIE_DOMAIN']);
        unset($_ENV['SESSION_COOKIE_DOMAIN'], $_SERVER['SESSION_COOKIE_DOMAIN']);
        putenv('KMP_SESSION_COOKIE_DOMAIN');
        putenv('SESSION_COOKIE_DOMAIN');
    }

    protected function tearDown(): void
    {
        unset($_ENV['KMP_SESSION_COOKIE_DOMAIN'], $_SERVER['KMP_SESSION_COOKIE_DOMAIN']);
        unset($_ENV['SESSION_COOKIE_DOMAIN'], $_SERVER['SESSION_COOKIE_DOMAIN']);
        putenv('KMP_SESSION_COOKIE_DOMAIN');
        putenv('SESSION_COOKIE_DOMAIN');

        parent::tearDown();
    }

    public function testEmptyDomainKeepsSessionCookieHostOnly(): void
    {
        $ini = SessionCookieConfig::withDomainOverride([
            'session.cookie_secure' => true,
            'session.cookie_domain' => '.example.test',
        ]);

        $this->assertArrayNotHasKey('session.cookie_domain', $ini);
    }

    public function testExplicitDomainOverrideIsOptIn(): void
    {
        $_ENV['KMP_SESSION_COOKIE_DOMAIN'] = 'tenant.example.test';

        $ini = SessionCookieConfig::withDomainOverride(['session.cookie_secure' => true]);

        $this->assertSame('tenant.example.test', $ini['session.cookie_domain']);
    }
}
