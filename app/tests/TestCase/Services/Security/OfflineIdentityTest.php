<?php
declare(strict_types=1);

namespace App\Test\TestCase\Services\Security;

use App\Model\Entity\Member;
use App\Services\Security\OfflineIdentity;
use Cake\Core\Configure;
use Cake\Http\Response;
use Cake\Http\ServerRequest;
use Cake\Http\Session;
use Cake\TestSuite\TestCase;

/** Pure request/identity tests; no database or customer fixtures. */
class OfflineIdentityTest extends TestCase
{
    /** @inheritDoc */
    public function setUp(): void
    {
        parent::setUp();
        Configure::write('KMP.tenancy.enabled', false);
    }

    private function request(int $id = 101, string $epoch = 'test-epoch', bool $impersonating = false): ServerRequest
    {
        $member = new Member();
        $member->set('id', $id);
        $member->set('auth_version', $epoch);
        $session = new Session(['defaults' => 'php', 'cookie' => 'offline-unit-test']);
        if ($impersonating) {
            $session->write('Impersonation', ['active' => true]);
        }

        return (new ServerRequest(['session' => $session]))->withAttribute('identity', $member);
    }

    public function testActorAndEpochMustBothMatchOnEveryReplay(): void
    {
        $request = $this->request();
        $context = OfflineIdentity::context($request);
        $data = ['offline_owner' => $context['owner'], 'offline_epoch' => $context['epoch']];
        $this->assertTrue(OfflineIdentity::matches($request, $data));
        $this->assertFalse(OfflineIdentity::matches($this->request(102), $data));
        $this->assertFalse(OfflineIdentity::matches($this->request(101, 'rotated'), $data));
        $this->assertFalse(OfflineIdentity::matches($request, []));
        $this->assertFalse(OfflineIdentity::matches($request, ['offline_owner' => [], 'offline_epoch' => []]));
        $this->assertSame(7 * 86400 * 1000, $context['expiresAt'] - $context['serverTime']);
        $this->assertStringNotContainsString('test-epoch', json_encode($context));
    }

    public function testImpersonationAndAnonymousRequestsCannotReplay(): void
    {
        $request = $this->request(101, 'test-epoch', true);
        $context = OfflineIdentity::context($request);
        $this->assertFalse(OfflineIdentity::matches($request, [
            'offline_owner' => $context['owner'], 'offline_epoch' => $context['epoch'],
        ]));
        $this->assertNull(OfflineIdentity::context(new ServerRequest()));
        $request->getSession()->delete('Impersonation');
    }

    public function testResponseIsBoundToItsActualActorAndCannotBeCached(): void
    {
        $request = $this->request();
        $context = OfflineIdentity::context($request);
        $response = OfflineIdentity::bind(new Response(), $request);
        $this->assertSame($context['owner'], $response->getHeaderLine('X-KMP-Offline-Owner'));
        $this->assertSame($context['epoch'], $response->getHeaderLine('X-KMP-Offline-Epoch'));
        $this->assertSame('no-store', $response->getHeaderLine('Cache-Control'));
    }
}
