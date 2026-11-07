<?php
declare(strict_types=1);

namespace App\Test\TestCase\Services;

use App\Services\TurboConfig;
use App\Test\TestCase\BaseTestCase;
use Cake\Core\Configure;
use Cake\Http\ServerRequest;
use RuntimeException;

class TurboConfigTest extends BaseTestCase
{
    public function testNativeIdentityEditorsAndFileBoundariesMatchTheBrowserContract(): void
    {
        $identity = $this->getTableLocator()->get('Members')->get(self::ADMIN_MEMBER_ID);
        $request = (new ServerRequest())->withAttribute('identity', $identity)
            ->withParam('controller', 'Members')->withParam('action', 'view');
        $this->assertFalse(TurboConfig::boundary($request));
        foreach (['logout', 'impersonate', 'setupMfa', 'upload', 'download', 'export'] as $action) {
            $this->assertTrue(TurboConfig::boundary($request->withParam('action', $action)));
        }
        $this->assertTrue(TurboConfig::boundary($request->withParam('_ext', 'pdf')));
        $this->assertTrue(TurboConfig::boundary($request->withParam('controller', 'WorkflowDefinitions')));
        $this->assertTrue(TurboConfig::boundary($request->withParam('controller', 'TableAdmin')));
        $this->assertTrue(TurboConfig::boundary($request->withParam('prefix', 'PlatformAdmin')));
    }

    public function testGatewayOriginIsExactAndCannotInjectPolicyDirectives(): void
    {
        $debug = Configure::read('debug');
        Configure::write('debug', false);
        try {
            Configure::write('Turbo.gatewayUrl', 'https://live.example.test:8443/events');
            $this->assertSame('https://live.example.test:8443', TurboConfig::gatewayOrigin());
            foreach (
                [
                'http://live.example.test/events', 'https://live.example.test/events?token=unsafe',
                'https://user:password@live.example.test/events',
                'https://live.example.test/events; connect-src *', '//live.example.test/events',
                ] as $url
            ) {
                Configure::write('Turbo.gatewayUrl', $url);
                try {
                    TurboConfig::gatewayUrl();
                    $this->fail('Invalid gateway configuration was accepted.');
                } catch (RuntimeException $exception) {
                    $this->assertSame(
                        'Live gateway URL must be an explicit HTTPS events endpoint.',
                        $exception->getMessage(),
                    );
                }
            }
        } finally {
            Configure::write('debug', $debug);
            Configure::delete('Turbo');
        }
    }
}
