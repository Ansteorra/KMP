<?php
declare(strict_types=1);

namespace App\Test\TestCase\View\Element;

use App\Model\Entity\Member;
use Cake\Http\ServerRequest;
use Cake\Routing\Route\DashedRoute;
use Cake\Routing\Router;
use Cake\TestSuite\TestCase;
use Cake\View\View;

/** Pure element rendering; no database fixtures. */
class RevokeSessionsTest extends TestCase
{
    public function testControlUsesPostAndMemberPermission(): void
    {
        Router::createRouteBuilder('/')->fallbacks(DashedRoute::class);
        $member = new Member(['id' => 101]);
        foreach ([true, false] as $allowed) {
            $identity = new class ($allowed) {
                public function __construct(private bool $allowed)
                {
                }

                public function can(string $action, mixed $resource): bool
                {
                    return $action === 'changePassword' && $resource->id === 101 && $this->allowed;
                }
            };
            $request = (new ServerRequest())->withAttribute('identity', $identity);
            $view = new View($request);
            $html = $view->element('members/revokeSessions', ['member' => $member]);
            if ($allowed) {
                $this->assertStringContainsString('Sign out all devices', $html);
                $this->assertStringContainsString('/members/revoke-sessions/101', $html);
                $this->assertStringContainsString('method="post"', $html);
                $this->assertStringContainsString('Disconnected offline cards expire within seven days', $html);
            } else {
                $this->assertStringNotContainsString('revoke-sessions', $html);
            }
        }
    }
}
