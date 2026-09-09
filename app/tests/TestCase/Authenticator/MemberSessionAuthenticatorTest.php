<?php
declare(strict_types=1);

namespace App\Test\TestCase\Authenticator;

use App\Authenticator\MemberSessionAuthenticator;
use App\KMP\TenantContext;
use App\KMP\TenantMetadata;
use App\Model\Entity\Member;
use App\Services\Security\MemberSessionState;
use Cake\Core\Configure;
use Cake\Http\ServerRequest;
use Cake\Http\Session;
use Cake\TestSuite\TestCase;

class MemberSessionAuthenticatorTest extends TestCase
{
    public function testSessionContainsNoPiiOrPermissionsAndRejectsAnotherTenant(): void
    {
        $member = new Member(['id' => 7, 'email_address' => 'synthetic@example.test', 'status' => 'active']);
        $member->deleted = false;
        $member->auth_version = 'epoch-a';
        $a = new TenantMetadata('tenant-a', 'a', 'A', 'active', 'localhost', 'a', 'a');
        $b = new TenantMetadata('tenant-b', 'b', 'B', 'active', 'localhost', 'b', 'b');
        $state = TenantContext::with($a, fn() => MemberSessionState::fromMember($member));
        $this->assertSame(['version', 'tenant_id', 'member_id', 'auth_version', 'issued_at'], array_keys($state));
        TenantContext::with($b, function () use ($state): void {
            $session = new Session();
            $session->write('Auth', $state);
            $session->write('QuickLoginSetup', ['private' => true]);
            $request = (new ServerRequest())->withAttribute('session', $session);
            $authenticator = new MemberSessionAuthenticator();
            $this->assertFalse($authenticator->authenticate($request)->isValid());
            $this->assertNull($session->read('Auth'));
            $this->assertNull($session->read('QuickLoginSetup'));
        });
    }

    public function testTenantlessHealthProbeLeavesTheMemberSessionUntouched(): void
    {
        $original = Configure::read('KMP.tenancy.enabled');
        Configure::write('KMP.tenancy.enabled', true);
        try {
            $state = ['version' => 1, 'tenant_id' => 'tenant-a', 'member_id' => 7, 'auth_version' => 'epoch-a'];
            $session = new Session();
            $session->write('Auth', $state);
            $request = (new ServerRequest(['environment' => ['REQUEST_METHOD' => 'HEAD']]))
                ->withAttribute('session', $session)
                ->withAttribute('params', ['controller' => 'Health', 'action' => 'index']);
            $authenticator = new MemberSessionAuthenticator();
            $this->assertFalse($authenticator->authenticate($request)->isValid());
            $this->assertSame($state, $session->read('Auth'));

            $privateRequest = $request->withAttribute('params', ['controller' => 'Members', 'action' => 'viewMobileCardJson']);
            $this->assertFalse($authenticator->authenticate($privateRequest)->isValid());
            $this->assertNull($session->read('Auth'), 'Private routes still reject a missing tenant binding.');
        } finally {
            Configure::write('KMP.tenancy.enabled', $original);
        }
    }

    public function testLegacySessionIsRejectedBeforeMemberLookup(): void
    {
        $session = new Session();
        $session->write('Auth', new Member(['id' => 7]));
        $authenticator = new MemberSessionAuthenticator();
        $request = (new ServerRequest())->withAttribute('session', $session);
        $this->assertFalse($authenticator->authenticate($request)->isValid());
        $this->assertNull($session->read('Auth'));
    }

    public function testEpochAndAccountStatusAreRequired(): void
    {
        $member = new Member(['id' => 7, 'status' => 'active']);
        $member->deleted = false;
        $member->auth_version = 'epoch-a';
        $state = MemberSessionState::fromMember($member);
        $this->assertTrue(MemberSessionState::matches($state, $member));
        $member->auth_version = 'epoch-b';
        $this->assertFalse(MemberSessionState::matches($state, $member));
        $member->deleted = false;
        $member->auth_version = 'epoch-a';
        $member->status = Member::STATUS_DEACTIVATED;
        $this->assertFalse(MemberSessionState::matches($state, $member));
        $this->assertArrayNotHasKey('auth_version', $member->toArray());
    }

    public function testTenancyWithoutResolvedContextFailsClosed(): void
    {
        Configure::write('KMP.tenancy.enabled', true);
        try {
            $this->assertNull(MemberSessionState::tenantId());
        } finally {
            Configure::delete('KMP.tenancy.enabled');
        }
    }
}
