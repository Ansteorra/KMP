<?php
declare(strict_types=1);

namespace App\Test\TestCase\Middleware;

use App\Middleware\TrustedProxyMiddleware;
use App\Services\Security\RequestRateLimiter;
use App\Services\Security\TrustedProxy;
use App\Test\TestCase\BaseTestCase;
use Cake\Datasource\ConnectionManager;
use Cake\Http\Response;
use Cake\Http\ServerRequest;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class TrustedProxyMiddlewareTest extends BaseTestCase
{
    public function testUntrustedPeerCannotSpoofClientAddressOrTenantHost(): void
    {
        $request = $this->process('203.0.113.40', '198.51.100.10', new TrustedProxy(true, ['10.0.0.0/24']));
        $this->assertSame('203.0.113.40', $request->clientIp());
        $this->assertSame('alpha.example.test', $request->getUri()->getHost());
        $this->assertSame('http', $request->getUri()->getScheme());
    }

    public function testTrustedProxySkipsOnlyConfiguredHops(): void
    {
        $request = $this->process('10.0.0.5', '192.0.2.99, 198.51.100.10, 10.0.0.6', new TrustedProxy(true, ['10.0.0.0/24']));
        $this->assertSame('198.51.100.10', $request->clientIp());
        $this->assertSame('alpha.example.test', $request->getUri()->getHost());
        $this->assertSame('https', $request->getUri()->getScheme());
    }

    public function testAzureUsesOnlyTheRightmostManagedIngressValue(): void
    {
        $policy = new TrustedProxy(true, [], true);
        $this->assertSame('198.51.100.10', $this->process('10.0.0.5', '192.0.2.99, 198.51.100.10', $policy)->clientIp());
        $this->assertSame('198.51.100.11', $this->process('10.0.0.5', '192.0.2.99, 198.51.100.11', $policy)->clientIp());
    }

    public function testMalformedForwardingCannotFallBackToSpoofedAlternateHeaders(): void
    {
        foreach (['', 'not-an-ip', '198.51.100.10, unknown', ',198.51.100.10', '198.51.100.10:999999'] as $forwarded) {
            $request = $this->process('10.0.0.5', $forwarded, new TrustedProxy(true, ['10.0.0.5']));
            $this->assertSame('10.0.0.5', $request->clientIp());
        }
    }

    public function testDisabledOrUnconfiguredTrustIgnoresAllForwardedAddresses(): void
    {
        $this->assertSame('10.0.0.5', $this->process('10.0.0.5', '198.51.100.10', new TrustedProxy())->clientIp());
        $this->assertSame('10.0.0.5', $this->process('10.0.0.5', '198.51.100.10', new TrustedProxy(true))->clientIp());
    }

    public function testIpv6ProxyRangesHaveExactBoundaries(): void
    {
        $policy = new TrustedProxy(true, ['fd00:1234::/32']);
        $this->assertSame('2001:db8::5', $this->process('fd00:1234::a', '2001:db8::5', $policy)->clientIp());
        $this->assertSame('fd00:1235::a', $this->process('fd00:1235::a', '2001:db8::5', $policy)->clientIp());
    }

    public function testProxyClientsHaveSeparateRateLimitsAndForgedPrefixesCannotEvadeThem(): void
    {
        $policy = new TrustedProxy(true, ['10.0.0.5']);
        $limiter = new RequestRateLimiter(ConnectionManager::get('default'), static fn(): int => 1800);
        for ($index = 0; $index < 20; $index++) {
            $ip = $this->process('10.0.0.5', '192.0.2.' . $index . ', 198.51.100.10', $policy)->clientIp();
            $this->assertTrue($limiter->attempt(RequestRateLimiter::BUCKET_PLATFORM_LOGIN_IP, $ip)->allowed);
        }
        $sameClient = $this->process('10.0.0.5', '192.0.2.200, 198.51.100.10', $policy)->clientIp();
        $otherClient = $this->process('10.0.0.5', '192.0.2.200, 198.51.100.11', $policy)->clientIp();
        $this->assertFalse($limiter->attempt(RequestRateLimiter::BUCKET_PLATFORM_LOGIN_IP, $sameClient)->allowed);
        $this->assertTrue($limiter->attempt(RequestRateLimiter::BUCKET_PLATFORM_LOGIN_IP, $otherClient)->allowed);
    }

    public function testManagedIngressModeRequiresServerRuntimeConfiguration(): void
    {
        $names = ['TRUST_PROXY', 'TRUSTED_PROXY_IPS', 'KMP_PROXY_MODE', 'CONTAINER_APP_NAME'];
        $saved = [];
        foreach ($names as $name) {
            $saved[$name] = [getenv($name), $_ENV[$name] ?? null];
            unset($_ENV[$name]);
            putenv($name . '=');
        }
        try {
            putenv('TRUST_PROXY=true');
            putenv('KMP_PROXY_MODE=azure-container-apps');
            $this->assertFalse(TrustedProxy::fromEnvironment()->isTrustedPeer('10.0.0.5'));
            putenv('CONTAINER_APP_NAME=synthetic-app');
            $this->assertTrue(TrustedProxy::fromEnvironment()->isTrustedPeer('10.0.0.5'));
            $this->assertFalse(TrustedProxy::fromEnvironment()->isTrustedPeer('203.0.113.40'));
            putenv('TRUST_PROXY=false');
            $this->assertFalse(TrustedProxy::fromEnvironment()->isTrustedPeer('10.0.0.5'));
        } finally {
            foreach ($saved as $name => [$value, $env]) {
                putenv($value === false ? $name : $name . '=' . $value);
                if ($env !== null) {
                    $_ENV[$name] = $env;
                }
            }
        }
    }

    public function testMalformedAndOverbroadProxyRangesNeverGrantTrust(): void
    {
        $policy = new TrustedProxy(true, ['0.0.0.0/0', '::/0', '10.0.0.0/no', '10.0.0.0/33']);
        $this->assertFalse($policy->isTrustedPeer('10.0.0.5'));
        $this->assertFalse($policy->isTrustedPeer('::1'));
        $this->assertFalse($policy->isTrustedPeer('not-an-ip'));
        $this->assertTrue((new TrustedProxy(true, ['10.0.0.0/24']))->isTrustedPeer('10.0.0.255'));
        $this->assertFalse((new TrustedProxy(true, ['10.0.0.0/24']))->isTrustedPeer('10.0.1.0'));
    }

    private function process(string $peer, string $forwarded, TrustedProxy $policy): ServerRequest
    {
        $handler = new class implements RequestHandlerInterface {
            public ?ServerRequest $request = null;

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $this->request = $request;

                return new Response();
            }
        };
        $request = new ServerRequest([
            'url' => 'http://alpha.example.test/members/login',
            'environment' => [
                'REMOTE_ADDR' => $peer,
                'HTTP_X_FORWARDED_FOR' => $forwarded,
                'HTTP_X_REAL_IP' => '192.0.2.200',
                'HTTP_CLIENT_IP' => '192.0.2.201',
            ],
        ]);
        $request = $request->withUri($request->getUri()->withHost('alpha.example.test')->withScheme('http'))
            ->withHeader('X-Forwarded-For', $forwarded)
            ->withHeader('X-Forwarded-Proto', 'https')
            ->withHeader('X-Forwarded-Host', 'beta.example.test');
        (new TrustedProxyMiddleware($policy))->process($request, $handler);

        $this->assertNotNull($handler->request);

        return $handler->request;
    }
}
